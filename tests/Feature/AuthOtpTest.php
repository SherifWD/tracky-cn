<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwilioSmsOtpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('a', 64),
            'services.twilio.otp_ttl' => 10,
        ]);

        Cache::flush();

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
            $table->unique(['phone', 'country_code']);
        });
    }

    public function test_login_sends_sms_otp_and_stores_expiry(): void
    {
        $user = User::create([
            'phone' => '01012345678',
            'country_code' => '+20',
        ]);

        $sentOtp = null;

        $this->mock(TwilioSmsOtpService::class, function ($mock) use (&$sentOtp) {
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

    public function test_login_sends_sms_otp_for_unknown_phone_without_creating_user(): void
    {
        $sentOtp = null;

        $this->mock(TwilioSmsOtpService::class, function ($mock) use (&$sentOtp) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01099999999', '+20', Mockery::on(function ($otp) use (&$sentOtp) {
                    $sentOtp = $otp;

                    return is_string($otp) && preg_match('/^\d{5}$/', $otp) === 1;
                }))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $response = $this->postJson('/api/auth/login', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP sent successfully')
            ->assertJsonPath('data.user', null)
            ->assertJsonPath('data.phone', '01099999999')
            ->assertJsonPath('data.country_code', '+20');

        $this->assertNotNull($sentOtp);
        $this->assertDatabaseMissing('users', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ]);
    }

    public function test_login_uses_existing_user_when_phone_exists_with_different_country_code_format(): void
    {
        $user = User::create([
            'phone' => '01006138028',
            'country_code' => '20',
        ]);

        $sentOtp = null;

        $this->mock(TwilioSmsOtpService::class, function ($mock) use (&$sentOtp) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01006138028', '+20', Mockery::on(function ($otp) use (&$sentOtp) {
                    $sentOtp = $otp;

                    return is_string($otp) && preg_match('/^\d{5}$/', $otp) === 1;
                }))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $response = $this->postJson('/api/auth/login', [
            'phone' => '01006138028',
            'country_code' => '+20',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame($sentOtp, $user->refresh()->otp);
    }

    public function test_login_does_not_match_existing_user_when_same_phone_has_different_country_code(): void
    {
        User::create([
            'name' => 'test',
            'phone' => '01006138028',
            'country_code' => '+218',
        ]);

        $this->mock(TwilioSmsOtpService::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01006138028', '+20', Mockery::type('string'))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $response = $this->postJson('/api/auth/login', [
            'phone' => '01006138028',
            'country_code' => '+20',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP sent successfully')
            ->assertJsonPath('data.user', null)
            ->assertJsonPath('data.phone', '01006138028')
            ->assertJsonPath('data.country_code', '+20');
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

    public function test_validate_otp_accepts_trimmed_otp_and_normalized_existing_phone(): void
    {
        $user = User::create([
            'phone' => '01012345678',
            'country_code' => '+20',
            'otp' => '12345',
            'tmp_otp' => '12345',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '1012345678',
            'country_code' => '20',
            'otp' => ' 12345 ',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP validated successfully')
            ->assertJsonPath('data.user.id', $user->id);

        $user->refresh();

        $this->assertNull($user->otp);
        $this->assertNull($user->otp_expires_at);
    }

    public function test_validate_otp_accepts_normalized_phone_for_pending_otp(): void
    {
        $sentOtp = null;

        $this->mock(TwilioSmsOtpService::class, function ($mock) use (&$sentOtp) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01099999999', '+20', Mockery::on(function ($otp) use (&$sentOtp) {
                    $sentOtp = $otp;

                    return is_string($otp) && preg_match('/^\d{5}$/', $otp) === 1;
                }))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $this->postJson('/api/auth/login', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ])->assertOk();

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '1099999999',
            'country_code' => '20',
            'otp' => ' '.$sentOtp.' ',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP validated successfully')
            ->assertJsonPath('data.user.phone', '1099999999')
            ->assertJsonPath('data.user.country_code', '20');
    }

    public function test_validate_otp_uses_existing_user_if_pending_otp_exists_for_duplicate_phone(): void
    {
        $user = User::create([
            'phone' => '01006138028',
            'country_code' => '20',
        ]);

        Cache::put(
            'auth:pending-otp:'.sha1('+201006138028'),
            [
                'otp' => '55317',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
            now()->addMinutes(10)
        );

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '01006138028',
            'country_code' => '+20',
            'otp' => '55317',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP validated successfully')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(1, User::where('phone', '01006138028')->count());
    }

    public function test_validate_otp_creates_new_user_when_same_phone_has_different_country_code(): void
    {
        User::create([
            'name' => 'test',
            'phone' => '01006138028',
            'country_code' => '+218',
        ]);

        $sentOtp = null;

        $this->mock(TwilioSmsOtpService::class, function ($mock) use (&$sentOtp) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01006138028', '+20', Mockery::on(function ($otp) use (&$sentOtp) {
                    $sentOtp = $otp;

                    return is_string($otp) && preg_match('/^\d{5}$/', $otp) === 1;
                }))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $this->postJson('/api/auth/login', [
            'phone' => '01006138028',
            'country_code' => '+20',
        ])->assertOk();

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '01006138028',
            'country_code' => '+20',
            'otp' => $sentOtp,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP validated successfully')
            ->assertJsonPath('data.user.phone', '01006138028')
            ->assertJsonPath('data.user.country_code', '+20');

        $this->assertSame(2, User::where('phone', '01006138028')->count());
        $this->assertDatabaseHas('users', [
            'phone' => '01006138028',
            'country_code' => '+218',
        ]);
        $this->assertDatabaseHas('users', [
            'phone' => '01006138028',
            'country_code' => '+20',
        ]);
    }

    public function test_validate_otp_registers_unknown_phone_when_pending_otp_is_correct(): void
    {
        $sentOtp = null;

        $this->mock(TwilioSmsOtpService::class, function ($mock) use (&$sentOtp) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01099999999', '+20', Mockery::on(function ($otp) use (&$sentOtp) {
                    $sentOtp = $otp;

                    return is_string($otp) && preg_match('/^\d{5}$/', $otp) === 1;
                }))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $this->postJson('/api/auth/login', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ])->assertOk();

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '01099999999',
            'country_code' => '+20',
            'otp' => $sentOtp,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'OTP validated successfully')
            ->assertJsonPath('data.user.phone', '01099999999')
            ->assertJsonPath('data.user.country_code', '+20')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ]);
        $this->assertArrayNotHasKey('otp', $response->json('data.user'));
        $this->assertArrayNotHasKey('tmp_otp', $response->json('data.user'));
    }

    public function test_validate_otp_does_not_register_unknown_phone_when_pending_otp_is_wrong(): void
    {
        $this->mock(TwilioSmsOtpService::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->with('01099999999', '+20', Mockery::type('string'))
                ->andReturn(['success' => true, 'sid' => 'SM123', 'status' => 'queued']);
        });

        $this->postJson('/api/auth/login', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ])->assertOk();

        $response = $this->postJson('/api/auth/login/validate-otp', [
            'phone' => '01099999999',
            'country_code' => '+20',
            'otp' => '00000',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result', false)
            ->assertJsonPath('msg', 'Invalid or expired OTP');

        $this->assertDatabaseMissing('users', [
            'phone' => '01099999999',
            'country_code' => '+20',
        ]);
    }

    public function test_auto_login_returns_same_token_and_user_shape_as_otp_validation(): void
    {
        $user = User::create([
            'name' => 'Sherif',
            'phone' => '01012345678',
            'country_code' => '+20',
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/auto-login');

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'Authenticated user retrieved successfully')
            ->assertJsonPath('data.token', $token)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Sherif')
            ->assertJsonPath('data.user.phone', '01012345678')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user',
                ],
            ]);

        $this->assertArrayNotHasKey('otp', $response->json('data.user'));
        $this->assertArrayNotHasKey('tmp_otp', $response->json('data.user'));
    }
}
