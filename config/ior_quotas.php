<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IOR Module Quotas & Rate Limits
    |--------------------------------------------------------------------------
    |
    | This file defines the limits for various services in the IOR module
    | based on the tenant's subscription tier.
    |
    */

    'tiers' => [
        'free' => [
            'scraping_daily_limit' => 5,      // Max products per day
            'ai_daily_limit'        => 3,      // Max AI generations per day
            'api_rpm_limit'         => 10,     // 10 requests per minute
            'bulk_import_limit'     => 1,      // Max 1 bulk import per month
        ],
        'pro' => [
            'scraping_daily_limit' => 50,
            'ai_daily_limit'        => 50,
            'api_rpm_limit'         => 60,
            'bulk_import_limit'     => 10,
        ],
        'enterprise' => [
            'scraping_daily_limit' => 500,
            'ai_daily_limit'        => 500,
            'api_rpm_limit'         => 300,
            'bulk_import_limit'     => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Key Prefixes
    |--------------------------------------------------------------------------
    */
    'redis_prefix' => 'ior_quota:',

    /*
    |--------------------------------------------------------------------------
    | Service Specific Costs (USD)
    |--------------------------------------------------------------------------
    */
    'costs' => [
        'hs_lookup'    => 0.18, // $0.18 per selection (~20 BDT)
        'hs_inference' => 0.10, // $0.10 per AI suggestion
    ],

];
