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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mpesa' => [
        // Comma-separated allow-list of Safaricom callback IPs. Empty in local
        // development / sandbox, set in production.
        'allowed_ips' => env('MPESA_ALLOWED_IPS', ''),

        // Daraja credentials, needed to register the C2B callback URLs.
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),   // sandbox | production
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),

        // What Safaricom should do when the Validation URL is unreachable:
        // Completed accepts the payment anyway, Cancelled rejects it.
        'response_type' => env('MPESA_RESPONSE_TYPE', 'Completed'),
    ],

];
