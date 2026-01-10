<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BulkSmsBdService
{
    private bool $enabled = false;
    private ?string $apiKey = null;
    private ?string $senderId = null;
    private string $endpoint;
    private string $messageType;

    public function __construct()
    {
        $config = config('services.bulksmsbd', []);

        $this->apiKey = $config['api_key'] ?? null;
        $this->senderId = $config['sender_id'] ?? null;
        $this->endpoint = $config['base_url'] ?? 'https://bulksmsbd.net/api/smsapipush';
        $this->messageType = $config['type'] ?? 'text';
        $this->enabled = filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($this->apiKey)
            && ! empty($this->senderId);
    }

    public function canHandleCountry(?string $isoCode): bool
    {
        return strtoupper($isoCode ?? '') === 'BD';
    }

    public function sendOtp(string $phoneNumberE164, string $otpCode, int $ttlMinutes): void
    {
        $message = sprintf(
            'Your verification code is %s. It expires in %d minute%s.',
            $otpCode,
            $ttlMinutes,
            $ttlMinutes === 1 ? '' : 's'
        );

        $this->sendMessage($phoneNumberE164, $message);
    }

    public function sendMessage(string $phoneNumberE164, string $message): void
    {
        if (! $this->enabled) {
            Log::info('BulkSMS BD skipped: service disabled or missing credentials.', [
                'phone' => $phoneNumberE164,
            ]);

            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('BulkSMS BD is not configured.');
            }

            return;
        }

        $response = Http::timeout(15)->get($this->endpoint, [
            'api_key' => $this->apiKey,
            'senderid' => $this->senderId,
            'number' => ltrim($phoneNumberE164, '+'),
            'message' => $message,
            'type' => $this->messageType,
        ]);

        $body = trim($response->body());

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('BulkSMS BD HTTP error: %s - %s', $response->status(), $body)
            );
        }

        if (! $this->responseIndicatesSuccess($body)) {
            throw new RuntimeException('BulkSMS BD rejected the SMS: ' . $body);
        }
    }

    private function responseIndicatesSuccess(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        if (preg_match('/202/', $body)) {
            return true;
        }

        return str_contains(strtolower($body), 'success');
    }
}
