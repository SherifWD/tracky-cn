<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwilioWhatsAppOtpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'services.twilio.otp_ttl' => 10,
        ]);

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('country_code')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(0);
            $table->string('otp')->nullable();
            $table->string('tmp_otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_login_sends_whatsapp_otp_and_stores_expiry(): void
    {
        $user = User::create([
            'phone' => '01012345678',
            'country_code' => '+20',
        ]);

        $sentOtp = null;

        $this->mock(TwilioWhatsAppOtpService::class, function ($mock) use (&$sentOtp) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01012345678', '+20', Mockery::on(function ($otp) use (&$sentOtp) {
                    $sentOtp = $otp;

                    return is_string($otp) && preg_match('/^\d{5}$/', $otp) === 1;
                }))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $response = $this->postJson('/api/auth/login', [
            'phone' => '01012345678',
            'country_code' => '+20',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP sent successfully');

        $this->assertArrayNotHasKey('otp', $response->json('data.user'));
        $this->assertArrayNotHasKey('tmp_otp', $response->json('data.user'));

        $user->refresh();

        $this->assertSame($sentOtp, $user->otp);
        $this->assertSame($sentOtp, $user->tmp_otp);
        $this->assertNotNull($user->otp_expires_at);
    }

    public function test_validate_otp_returns_jwt_and_clears_login_otp(): void
    {
        $user = User::create([
            'phone' => '01012345678',
            'country_code' => '+20',
            'otp' => '12345',
            'tmp_otp' => '12345',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '01012345678',
            'country_code' => '+20',
            'otp' => '12345',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP validated successfully')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                ],
            ]);

        $this->assertArrayNotHasKey('otp', $response->json('data.user'));
        $this->assertArrayNotHasKey('tmp_otp', $response->json('data.user'));

        $user->refresh();

        $this->assertNull($user->otp);
        $this->assertNull($user->otp_expires_at);
        $this->assertSame('12345', $user->tmp_otp);
    }
}
