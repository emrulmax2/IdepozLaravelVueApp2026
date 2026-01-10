<?php

namespace App\Services\Sms;

use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AwsSnsSmsService
{
    private ?SnsClient $client = null;
    private bool $enabled = false;
    private ?string $senderId;
    private string $smsType;

    public function __construct()
    {
        $config = config('services.sns', []);

        $this->senderId = $config['sender_id'] ?? null;
        $this->smsType = $config['sms_type'] ?? 'Transactional';

        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;
        $region = $config['region'] ?? 'us-east-1';
        $explicitlyEnabled = filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($explicitlyEnabled && $key && $secret) {
            $this->enabled = true;
            $this->client = new SnsClient([
                'version' => '2010-03-31',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
        }
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
        if (! $this->enabled || $this->client === null) {
            Log::info('AWS SNS SMS skipped: service disabled or not configured.', [
                'phone' => $phoneNumberE164,
            ]);

            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('SMS service is not configured.');
            }

            return;
        }

        try {
            $messageAttributes = [
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => $this->smsType,
                ],
            ];

            if ($this->senderId) {
                $messageAttributes['AWS.SNS.SMS.SenderID'] = [
                    'DataType' => 'String',
                    'StringValue' => $this->senderId,
                ];
            }

            $this->client->publish([
                'PhoneNumber' => $phoneNumberE164,
                'Message' => $message,
                'MessageAttributes' => $messageAttributes,
            ]);
        } catch (\Throwable $throwable) {
            Log::error('Failed to deliver SMS via AWS SNS.', [
                'phone' => $phoneNumberE164,
                'error' => $throwable->getMessage(),
            ]);

            throw new RuntimeException('We could not deliver the SMS at this time.', 0, $throwable);
        }
    }
}
