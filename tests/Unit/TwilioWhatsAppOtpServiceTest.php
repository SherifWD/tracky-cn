<?php

namespace Tests\Unit;

use App\Services\TwilioWhatsAppOtpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TwilioWhatsAppOtpServiceTest extends TestCase
{
    public function test_send_posts_whatsapp_message_to_twilio(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'token',
            'services.twilio.messaging_service_sid' => null,
            'services.twilio.whatsapp_from' => '+14155238886',
            'services.twilio.whatsapp_content_sid' => null,
            'services.twilio.whatsapp_require_template' => false,
            'services.twilio.otp_message' => 'Your OTP code is: :otp',
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ], 201),
        ]);

        $result = app(TwilioWhatsAppOtpService::class)->send('01012345678', '+20', '12345');

        $this->assertSame([
            'success' => true,
            'sid' => 'SM123',
            'status' => 'queued',
        ], $result);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
                && $request['To'] === 'whatsapp:+201012345678'
                && $request['From'] === 'whatsapp:+14155238886'
                && $request['Body'] === 'Your OTP code is: 12345';
        });
    }

    public function test_send_posts_content_template_to_twilio(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'token',
            'services.twilio.messaging_service_sid' => 'MG123',
            'services.twilio.whatsapp_from' => null,
            'services.twilio.whatsapp_content_sid' => 'HX123',
            'services.twilio.whatsapp_require_template' => true,
            'services.twilio.otp_variable' => '1',
        ]);

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'accepted',
            ], 201),
        ]);

        app(TwilioWhatsAppOtpService::class)->send('01012345678', '+20', '12345');

        Http::assertSent(function (Request $request) {
            return $request['To'] === 'whatsapp:+201012345678'
                && $request['MessagingServiceSid'] === 'MG123'
                && $request['ContentSid'] === 'HX123'
                && $request['ContentVariables'] === '{"1":"12345"}'
                && ! isset($request['Body']);
        });
    }

    public function test_send_requires_content_template_when_template_mode_is_enabled(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'token',
            'services.twilio.messaging_service_sid' => null,
            'services.twilio.whatsapp_from' => '+14155238886',
            'services.twilio.whatsapp_content_sid' => null,
            'services.twilio.whatsapp_require_template' => true,
        ]);

        Http::fake();

        try {
            app(TwilioWhatsAppOtpService::class)->send('01012345678', '+20', '12345');
            $this->fail('Expected Twilio content template configuration to be required.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Content SID is required', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
