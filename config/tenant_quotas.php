<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Tiers and Quotas
    |--------------------------------------------------------------------------
    |
    | Definitions for resource limits across different plans.
    |
    */
    'tiers' => [
        'free' => [
            'api_rpm_limit' => 30,
            'scraping_daily_limit' => 5,
            'ai_daily_limit' => 3,
            'whatsapp_monthly_limit' => 10,
            'max_users' => 1,
            'max_products' => 50,
        ],
        'business' => [
            'api_rpm_limit' => 120,
            'scraping_daily_limit' => 50,
            'ai_daily_limit' => 25,
            'whatsapp_monthly_limit' => 500,
            'max_users' => 5,
            'max_products' => 1000,
        ],
        'pro' => [
            'api_rpm_limit' => 600,
            'scraping_daily_limit' => 500,
            'ai_daily_limit' => 250,
            'whatsapp_monthly_limit' => 5000,
            'max_users' => 20,
            'max_products' => 10000,
        ],
        'enterprise' => [
            'api_rpm_limit' => 3000,
            'scraping_daily_limit' => 9999,
            'ai_daily_limit' => 9999,
            'whatsapp_monthly_limit' => 100000,
            'max_users' => 100,
            'max_products' => 1000000,
        ],
    ],

    'redis_prefix' => 'tenant_quota:',
];
