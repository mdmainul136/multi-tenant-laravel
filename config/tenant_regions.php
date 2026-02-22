<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Region-Wise Module Strategy
    |--------------------------------------------------------------------------
    | Define which modules are 'core' (included in base plan) and which are 
    | 'addons' (purchasable) per region.
    */

    'MENA' => [
        'name' => 'Middle East (MENA)',
        'countries' => ['SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'EG', 'JO'],
        'currency' => 'SAR',
        'modules' => [
            'core' => [
                'ecommerce', 'inventory', 'crm', 'pos', 'marketing',
                'zatca', 'maroof', 'national-address', 'sadad',
                'tracking', 'analytics', 'pages', 'seo-manager'
            ],
            'addons' => [
                'marketplace' => 49.00,
                'marketing-pro' => 19.00, // Advanced marketing
                'erp' => 79.00,
                'hrm' => 49.00,
                'branches' => 29.00,
                'purchase-orders' => 19.00,
                'warehouse' => 29.00,
                'expenses' => 15.00,
            ]
        ],
        'payment_methods' => ['mada', 'stcpay', 'tamara', 'tabby', 'sadad', 'apple_pay', 'cod']
    ],

    'SOUTH_ASIA' => [
        'name' => 'South Asia',
        'countries' => ['BD', 'IN', 'PK', 'LK', 'NP'],
        'currency' => 'USD',
        'modules' => [
            'core' => [
                'ecommerce', 'whatsapp', 'flash-sales', 'marketing',
                'tracking', 'pages', 'seo-manager'
            ],
            'addons' => [
                'pos' => 15.00,
                'crm' => 12.00,
                'marketplace' => 29.00,
                'marketing-pro' => 12.00,
            ]
        ],
        'payment_methods' => ['razorpay', 'bkash', 'jazzcash', 'upi', 'cod', 'bank_transfer']
    ],

    'GLOBAL' => [
        'name' => 'Global (Default)',
        'countries' => '*',
        'currency' => 'USD',
        'modules' => [
            'core' => [
                'ecommerce', 'inventory', 'marketing', 'tracking', 
                'pages', 'seo-manager', 'analytics'
            ],
            'addons' => [
                'pos' => 29.00,
                'crm' => 29.00,
                'marketplace' => 49.00,
                'erp' => 79.00,
                'hrm' => 49.00,
                'gdpr' => 19.00,
            ]
        ],
        'payment_methods' => ['stripe', 'paypal', 'apple_pay', 'google_pay']
    ]
];
