<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CountryPhoneCode;
use App\Models\MobileRegistrationOtp;
use App\Models\User;
use App\Services\Sms\AwsSnsSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileRegistrationController extends Controller
{
    private const OTP_TTL_MINUTES = 5;
    private const REQUEST_RATE_LIMIT = 5;
    private const REQUEST_RATE_LIMIT_DECAY = 900;
    private const RESEND_COOLDOWN_SECONDS = 45;
    private const VERIFY_MAX_ATTEMPTS = 5;

    public function __construct(private AwsSnsSmsService $smsService)
    {
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_phone_code_id' => ['required', 'integer'],
            'phone' => ['required', 'string', 'min:4', 'max:20'],
        ]);

        [$countryCode, $phone] = $this->normalizePhoneInput($validated);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is already registered. Please sign in instead.'],
            ]);
        }

        $cooldownKey = $this->otpCooldownKey($phone);
        $windowKey = $this->otpThrottleKey($phone);

        $this->guardRateLimiter($cooldownKey, 1, self::RESEND_COOLDOWN_SECONDS, 'phone');
        $this->guardRateLimiter($windowKey, self::REQUEST_RATE_LIMIT, self::REQUEST_RATE_LIMIT_DECAY, 'phone');

        $otpValue = $this->generateOtp();
        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);

        MobileRegistrationOtp::updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $validated['name'],
                'country_phone_code_id' => $countryCode->id,
                'code' => Hash::make($otpValue),
                'expires_at' => $expiresAt,
                'attempts' => 0,
                'used_at' => null,
            ]
        );

        try {
            $this->smsService->sendOtp($phone, $otpValue, self::OTP_TTL_MINUTES);
        } catch (Throwable $throwable) {
            Log::error('Failed to send registration OTP via AWS SNS', [
                'phone' => $phone,
                'error' => $throwable->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'phone' => ['We could not send the OTP right now. Please try again shortly.'],
            ]);
        }

        Log::info('Mobile registration OTP generated', [
            'phone' => $phone,
            'preview' => app()->environment(['local', 'testing']) ? $otpValue : null,
        ]);

        return response()->json([
            'message' => 'OTP sent successfully.',
            'expires_at' => $expiresAt->toIso8601String(),
            'resend_available_in' => max(0, RateLimiter::availableIn($cooldownKey)),
            'preview_code' => app()->environment(['local', 'testing']) ? $otpValue : null,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_phone_code_id' => ['required', 'integer'],
            'phone' => ['required', 'string', 'min:4', 'max:20'],
            'otp' => ['required', 'digits:6'],
        ]);

        [$countryCode, $phone] = $this->normalizePhoneInput($validated);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is already registered. Please sign in instead.'],
            ]);
        }

        $otpRecord = MobileRegistrationOtp::where('phone', $phone)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'otp' => ['No active OTP found. Please request a new code.'],
            ]);
        }

        if ($otpRecord->hasExpired()) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP has expired.'],
            ]);
        }

        if ($otpRecord->country_phone_code_id && $otpRecord->country_phone_code_id !== $countryCode->id) {
            throw ValidationException::withMessages([
                'country_phone_code_id' => ['Use the same country code you used when requesting the OTP.'],
            ]);
        }

        if ($otpRecord->attempts >= self::VERIFY_MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => ['Maximum verification attempts exceeded. Please request a new OTP.'],
            ]);
        }

        if (! Hash::check($validated['otp'], $otpRecord->code)) {
            $otpRecord->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP. Please try again.'],
            ]);
        }

        $user = User::create([
            'name' => $otpRecord->name,
            'phone' => $otpRecord->phone,
            'country_phone_code_id' => $otpRecord->country_phone_code_id ?: $countryCode->id,
            'email' => $this->buildPlaceholderEmail($otpRecord->phone),
            'password' => Str::password(),
        ]);

        $user->forceFill([
            'phone_verified_at' => now(),
            'last_login_at' => now(),
        ])->save();

        $otpRecord->forceFill([
            'used_at' => now(),
            'attempts' => $otpRecord->attempts + 1,
        ])->save();

        $token = $user->createToken('mobile-registration')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country_phone_code_id' => $user->country_phone_code_id,
                'phone_verified_at' => $user->phone_verified_at,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    private function guardRateLimiter(string $key, int $maxAttempts, int $decaySeconds, string $field): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                $field => ["Too many attempts. Try again in {$seconds} seconds."],
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * @return array{0: CountryPhoneCode, 1: string}
     */
    private function normalizePhoneInput(array $validated): array
    {
        $countryCode = CountryPhoneCode::query()
            ->active()
            ->find($validated['country_phone_code_id']);

        if (! $countryCode) {
            throw ValidationException::withMessages([
                'country_phone_code_id' => ['The selected country code is not available.'],
            ]);
        }

        $phone = $countryCode->normalizeNationalNumber($validated['phone']);

        return [$countryCode, $phone];
    }

    private function otpThrottleKey(string $phone): string
    {
        return 'register-otp-request:' . sha1($phone);
    }

    private function otpCooldownKey(string $phone): string
    {
        return 'register-otp-cooldown:' . sha1($phone);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function buildPlaceholderEmail(string $phone): string
    {
        $sanitized = preg_replace('/[^0-9]/', '', $phone) ?: Str::random(8);

        return sprintf('%s@mobile.local', $sanitized);
    }
}
