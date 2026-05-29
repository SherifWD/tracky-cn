<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TwilioSmsOtpService
{
    public function send(string $phone, string $countryCode, string $otp): array
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $messagingServiceSid = config('services.twilio.messaging_service_sid');
        $from = config('services.twilio.from');

        if (blank($accountSid) || blank($authToken)) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        if (blank($messagingServiceSid) && blank($from)) {
            throw new RuntimeException('Twilio SMS sender is not configured.');
        }

        $payload = [
            'To' => $this->normalizePhoneNumber($phone, $countryCode),
            'Body' => str_replace(':otp', $otp, config('services.twilio.otp_message')),
        ];

        if (filled($messagingServiceSid)) {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = $this->formatSmsSender($from);
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

        if ($response->failed()) {
            $twilioError = $response->json() ?: [];

            Log::error('Twilio SMS OTP failed.', [
                'status' => $response->status(),
                'code' => $twilioError['code'] ?? null,
                'message' => $twilioError['message'] ?? $response->body(),
                'more_info' => $twilioError['more_info'] ?? null,
            ]);

            $code = filled($twilioError['code'] ?? null) ? " Twilio error {$twilioError['code']}." : '';
            $message = filled($twilioError['message'] ?? null) ? ' '.$twilioError['message'] : '';

            throw new RuntimeException("Failed to send OTP via SMS.{$code}{$message}");
        }

        return [
            'success' => true,
            'sid' => $response->json('sid'),
            'status' => $response->json('status'),
        ];
    }

    private function normalizePhoneNumber(string $phone, string $countryCode): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return '+'.preg_replace('/\D+/', '', $phone);
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone);
        $countryDigits = preg_replace('/\D+/', '', $countryCode);

        if ($countryDigits !== '' && str_starts_with($phoneDigits, $countryDigits)) {
            return '+'.$phoneDigits;
        }

        return '+'.$countryDigits.ltrim($phoneDigits, '0');
    }

    private function formatSmsSender(string $sender): string
    {
        $sender = trim($sender);

        if (str_starts_with($sender, '+')) {
            return '+'.preg_replace('/\D+/', '', $sender);
        }

        $digits = preg_replace('/\D+/', '', $sender);

        return $digits === '' ? $sender : '+'.$digits;
    }
}
