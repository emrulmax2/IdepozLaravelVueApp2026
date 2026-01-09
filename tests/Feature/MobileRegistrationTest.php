<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_name_and_phone_using_otp(): void
    {
        config()->set('app.debug', true);

        $payload = [
            'name' => 'Test User',
            'phone' => '+15551231234',
        ];

        $requestResponse = $this->postJson('/api/auth/register/request-otp', $payload);

        $requestResponse
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'expires_at',
                'resend_available_in',
                'preview_code',
            ]);

        $otp = $requestResponse->json('preview_code');
        $this->assertIsString($otp);
        $this->assertSame(6, strlen($otp));

        $verifyResponse = $this->postJson('/api/auth/register/verify-otp', [
            'phone' => $payload['phone'],
            'otp' => $otp,
        ]);

        $verifyResponse
            ->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'phone_verified_at',
                    'last_login_at',
                ],
            ]);

        $user = User::where('phone', $payload['phone'])->first();

        $this->assertNotNull($user);
        $this->assertSame($payload['name'], $user->name);
        $this->assertNotNull($user->phone_verified_at);
    }
}
