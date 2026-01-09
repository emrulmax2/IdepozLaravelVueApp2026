<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MobileOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class MobileOtpController extends Controller
{
    private const OTP_TTL_MINUTES = 5;
    private const REQUEST_RATE_LIMIT = 3;
    private const REQUEST_RATE_LIMIT_DECAY = 300;
    private const RESEND_COOLDOWN_SECONDS = 30;
    private const VERIFY_MAX_ATTEMPTS = 5;

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
        ]);

        $phone = $validated['phone'];
        $cooldownKey = $this->otpCooldownKey($phone);
        $throttleKey = $this->otpThrottleKey($phone);

        $this->guardRateLimiter($cooldownKey, 1, self::RESEND_COOLDOWN_SECONDS, 'phone');
        $this->guardRateLimiter($throttleKey, self::REQUEST_RATE_LIMIT, self::REQUEST_RATE_LIMIT_DECAY, 'phone');

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['We could not find an account for that phone number.'],
            ]);
        }

        $otpValue = $this->generateOtp();
        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);

        MobileOtp::where('user_id', $user->id)->delete();

        MobileOtp::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'code' => Hash::make($otpValue),
            'expires_at' => $expiresAt,
        ]);

        Log::info('Mobile OTP generated', [
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
            'phone' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = User::where('phone', $validated['phone'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['We could not find an account for that phone number.'],
            ]);
        }

        $otpRecord = MobileOtp::where('user_id', $user->id)
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

        $otpRecord->forceFill([
            'used_at' => now(),
            'attempts' => $otpRecord->attempts + 1,
        ])->save();

        MobileOtp::where('user_id', $user->id)
            ->where('id', '!=', $otpRecord->id)
            ->delete();

        $user->forceFill([
            'phone_verified_at' => $user->phone_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        $token = $user->createToken('mobile-otp')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'phone_verified_at' => $user->phone_verified_at,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
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

    private function otpThrottleKey(string $phone): string
    {
        return 'otp-request:' . sha1($phone);
    }

    private function otpCooldownKey(string $phone): string
    {
        return 'otp-cooldown:' . sha1($phone);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
