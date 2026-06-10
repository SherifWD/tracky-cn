<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'from' => env('TWILIO_SMS_FROM', env('TWILIO_FROM', env('TWILIO_PHONE_NUMBER', env('TWILIO_WHATSAPP_FROM')))),
        'otp_ttl' => env('TWILIO_OTP_TTL', 10),
        'otp_message' => env('TWILIO_OTP_MESSAGE', 'Your OTP code is: :otp'),
    ],
    'ocean_tracking' => [
        'url' => env('OCEAN_API_URL'),
        'app_id' => env('OCEAN_APP_ID'),
        'secret' => env('OCEAN_SECRET_ID'),
    ],

];
