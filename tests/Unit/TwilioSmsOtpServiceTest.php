<?php

namespace Tests\Unit;

use App\Services\TwilioSmsOtpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TwilioSmsOtpServiceTest extends TestCase
{
    public function test_send_posts_sms_message_to_twilio(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'token',
            'services.twilio.messaging_service_sid' => null,
            'services.twilio.from' => '+14155550123',
            'services.twilio.otp_message' => 'Your OTP code is: :otp',
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ], 201),
        ]);

        $result = app(TwilioSmsOtpService::class)->send('01012345678', '+20', '12345');

        $this->assertSame([
            'success' => true,
            'sid' => 'SM123',
            'status' => 'queued',
        ], $result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
                && $request['To'] === '+201012345678'
                && $request['From'] === '+14155550123'
                && $request['Body'] === 'Your OTP code is: 12345';
        });
    }

    public function test_send_posts_sms_message_with_messaging_service_to_twilio(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'token',
            'services.twilio.messaging_service_sid' => 'MG123',
            'services.twilio.from' => null,
            'services.twilio.otp_message' => 'Your OTP code is: :otp',
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'accepted',
            ], 201),
        ]);

        app(TwilioSmsOtpService::class)->send('01012345678', '+20', '12345');

        Http::assertSent(function (Request $request) {
            return $request['To'] === '+201012345678'
                && $request['MessagingServiceSid'] === 'MG123'
                && $request['Body'] === 'Your OTP code is: 12345'
                && ! isset($request['From']);
        });
    }

    public function test_send_requires_sms_sender_when_messaging_service_is_not_configured(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'token',
            'services.twilio.messaging_service_sid' => null,
            'services.twilio.from' => null,
        ]);

        Http::fake();

        try {
            app(TwilioSmsOtpService::class)->send('01012345678', '+20', '12345');
            $this->fail('Expected Twilio SMS sender configuration to be required.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SMS sender is not configured', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
