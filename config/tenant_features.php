<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature to Module Mapping
    |--------------------------------------------------------------------------
    |
    | Map specific features to their parent modules. This allows the 
    | FeatureFlagService to check if a tenant has access to a feature
    | by checking their module subscriptions.
    |
    */
    'module_map' => [
        // Analytics
        'advanced_analytics' => 'analytics',
        'real_time_reports' => 'analytics',
        
        // E-Commerce Extensions
        'flash_sales' => 'flash-sales',
        'loyalty_program' => 'loyalty',
        'whatsapp_notifications' => 'whatsapp',
        
        // Saudi Compliance
        'zatca_einvoicing' => 'zatca',
        'maroof_verification' => 'maroof',
        'sadad_payments' => 'sadad',
        
        // Multi-Vendor
        'vendor_payouts' => 'marketplace',
        'commission_rules' => 'marketplace',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Feature Flags
    |--------------------------------------------------------------------------
    |
    | Features that are enabled by default for all tenants regardless of
    | module subscription.
    |
    */
    'defaults' => [
        'basic_crm' => true,
        'standard_reports' => true,
        'mobile_api' => true,
    ],
];
