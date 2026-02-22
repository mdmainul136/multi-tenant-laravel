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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'namecheap' => [
        'api_key' => env('NAMECHEAP_API_KEY'),
        'username' => env('NAMECHEAP_USERNAME'),
        'api_user' => env('NAMECHEAP_API_USER'),
        'client_ip' => env('NAMECHEAP_CLIENT_IP'),
        'sandbox' => env('NAMECHEAP_SANDBOX', true),
    ],

    'sslcommerz' => [
        'store_id'       => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'sandbox'        => env('SSLCOMMERZ_SANDBOX', true),
    ],

    // ── Middle East Gateways ────────────────────────────────────────────────

    // Moyasar — KSA-native, supports MADA + Visa/MC + Apple/Google Pay
    'moyasar' => [
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
        'secret_key'      => env('MOYASAR_SECRET_KEY'),
        'webhook_secret'  => env('MOYASAR_WEBHOOK_SECRET'),
        'sandbox'         => env('MOYASAR_SANDBOX', true),
    ],

    // STC Pay — Saudi Arabia telco wallet
    'stc_pay' => [
        'merchant_id' => env('STC_PAY_MERCHANT_ID'),
        'api_key'     => env('STC_PAY_API_KEY'),
        'sandbox'     => env('STC_PAY_SANDBOX', true),
    ],

    // Tabby — BNPL, 4 splits, strong in UAE + KSA
    'tabby' => [
        'public_key'    => env('TABBY_PUBLIC_KEY'),
        'secret_key'    => env('TABBY_SECRET_KEY'),
        'merchant_code' => env('TABBY_MERCHANT_CODE'),
        'sandbox'       => env('TABBY_SANDBOX', true),
    ],

    // Tamara — BNPL, 3 splits, very strong in KSA
    'tamara' => [
        'api_token'     => env('TAMARA_API_TOKEN'),
        'notify_token'  => env('TAMARA_NOTIFY_TOKEN'),
        'sandbox'       => env('TAMARA_SANDBOX', true),
    ],

    // Postpay — BNPL focused on UAE
    'postpay' => [
        'api_key' => env('POSTPAY_API_KEY'),
        'sandbox' => env('POSTPAY_SANDBOX', true),
    ],

    'python_scraper' => [
        // FastAPI Microservice (replaces subprocess approach)
        'base_url' => env('SCRAPER_SERVICE_URL', 'http://localhost:8001'),
        'api_key'  => env('SCRAPER_API_KEY', 'ior-scraper-secret-change-me'),

        // Legacy subprocess config (deprecated — kept for reference)
        // 'python_path' => env('PYTHON_PATH', 'python'),
        // 'script_path' => env('PYTHON_SCRAPER_SCRIPT_PATH', base_path('../python/sourcing_scraper.py')),
    ],

];
