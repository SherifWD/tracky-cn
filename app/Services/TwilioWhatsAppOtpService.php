<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TwilioWhatsAppOtpService
{
    public function send(string $phone, string $countryCode, string $otp): array
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $messagingServiceSid = config('services.twilio.messaging_service_sid');
        $from = config('services.twilio.whatsapp_from');

        if (blank($accountSid) || blank($authToken)) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        if (blank($messagingServiceSid) && blank($from)) {
            throw new RuntimeException('Twilio WhatsApp sender is not configured.');
        }

        $payload = [
            'To' => $this->formatWhatsAppAddress($this->normalizePhoneNumber($phone, $countryCode)),
        ];

        if (filled($messagingServiceSid)) {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = $this->formatWhatsAppAddress($from);
        }

        $contentSid = config('services.twilio.whatsapp_content_sid');

        if (filled($contentSid)) {
            $payload['ContentSid'] = $contentSid;
            $payload['ContentVariables'] = json_encode([
                config('services.twilio.otp_variable', '1') => $otp,
            ]);
        } else {
            $payload['Body'] = str_replace(':otp', $otp, config('services.twilio.otp_message'));
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

        if ($response->failed()) {
            Log::error('Twilio WhatsApp OTP failed.', [
                'status' => $response->status(),
                'response' => $response->json() ?: $response->body(),
            ]);

            throw new RuntimeException('Failed to send OTP via WhatsApp.');
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

    private function formatWhatsAppAddress(string $number): string
    {
        $number = trim($number);

        if (str_starts_with($number, 'whatsapp:')) {
            return $number;
        }

        if (! str_starts_with($number, '+')) {
            $number = '+'.preg_replace('/\D+/', '', $number);
        }

        return 'whatsapp:'.$number;
    }
}
