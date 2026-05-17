<?php

namespace Tests\Unit;

use App\Services\TwilioWhatsAppOtpService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
}
