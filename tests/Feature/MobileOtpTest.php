<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_and_verify_a_mobile_otp(): void
    {
        config()->set('app.debug', true);

        $user = User::factory()->create();

        $requestResponse = $this->postJson('/api/auth/request-otp', [
            'phone' => $user->phone,
        ]);

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

        $verifyResponse = $this->postJson('/api/auth/verify-otp', [
            'phone' => $user->phone,
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

        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
