<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Business Type → Module Mapping (40 Business Types)
    |--------------------------------------------------------------------------
    | Defines which modules are automatically activated for each business type
    | during tenant onboarding. 'core' modules are auto-subscribed as trial,
    | 'recommended' are suggested but not auto-activated.
    */

    // ── General Business Types ──────────────────────────────────────────

    'sole_proprietorship' => [
        'label' => 'Sole Proprietorship',
        'core'  => ['ecommerce', 'crm', 'marketing', 'tracking', 'pages', 'seo-manager'],
        'recommended' => ['pos', 'inventory', 'whatsapp', 'flash-sales', 'analytics'],
    ],

    'partnership' => [
        'label' => 'Partnership',
        'core'  => ['ecommerce', 'crm', 'inventory', 'finance', 'marketing', 'tracking'],
        'recommended' => ['pos', 'hrm', 'whatsapp', 'marketplace', 'contracts'],
    ],

    'llc' => [
        'label' => 'LLC',
        'core'  => ['ecommerce', 'crm', 'inventory', 'finance', 'hrm', 'marketing', 'tracking'],
        'recommended' => ['pos', 'marketplace', 'manufacturing', 'whatsapp', 'security', 'contracts'],
    ],

    'corporation' => [
        'label' => 'Corporation',
        'core'  => ['ecommerce', 'crm', 'inventory', 'finance', 'hrm', 'pos', 'marketing', 'tracking', 'security'],
        'recommended' => ['marketplace', 'manufacturing', 'cross-border-ior', 'loyalty', 'contracts', 'branches', 'analytics'],
    ],

    'startup' => [
        'label' => 'Startup',
        'core'  => ['ecommerce', 'crm', 'marketing', 'tracking', 'pages', 'seo-manager'],
        'recommended' => ['inventory', 'finance', 'whatsapp', 'flash-sales', 'analytics'],
    ],

    'nonprofit' => [
        'label' => 'Non-profit / NGO',
        'core'  => ['crm', 'finance', 'hrm', 'marketing', 'notifications', 'tracking'],
        'recommended' => ['ecommerce', 'events', 'whatsapp', 'contracts'],
    ],

    'franchise' => [
        'label' => 'Franchise',
        'core'  => ['ecommerce', 'pos', 'inventory', 'finance', 'hrm', 'branches', 'marketing', 'tracking'],
        'recommended' => ['crm', 'loyalty', 'manufacturing', 'analytics', 'security'],
    ],

    'cooperative' => [
        'label' => 'Cooperative',
        'core'  => ['ecommerce', 'crm', 'finance', 'inventory', 'hrm', 'tracking'],
        'recommended' => ['marketplace', 'pos', 'contracts', 'marketing'],
    ],

    // ── Retail & Commerce ───────────────────────────────────────────────

    'ecommerce' => [
        'label' => 'E-Commerce',
        'core'  => ['ecommerce', 'crm', 'inventory', 'marketing', 'tracking', 'pages', 'seo-manager'],
        'recommended' => ['pos', 'flash-sales', 'loyalty', 'whatsapp', 'marketplace', 'reviews'],
    ],

    'retail' => [
        'label' => 'Retail Store',
        'core'  => ['pos', 'inventory', 'ecommerce', 'crm', 'finance', 'tracking'],
        'recommended' => ['hrm', 'loyalty', 'marketing', 'branches', 'flash-sales'],
    ],

    'wholesale' => [
        'label' => 'Wholesale / Distribution',
        'core'  => ['inventory', 'finance', 'crm', 'ecommerce', 'tracking'],
        'recommended' => ['manufacturing', 'cross-border-ior', 'pos', 'branches', 'contracts'],
    ],

    'fashion' => [
        'label' => 'Fashion & Apparel',
        'core'  => ['ecommerce', 'inventory', 'crm', 'marketing', 'tracking', 'pages'],
        'recommended' => ['pos', 'loyalty', 'flash-sales', 'marketplace', 'reviews', 'seo-manager'],
    ],

    'grocery' => [
        'label' => 'Grocery & Supermarket',
        'core'  => ['pos', 'inventory', 'ecommerce', 'crm', 'finance', 'tracking'],
        'recommended' => ['loyalty', 'flash-sales', 'marketing', 'branches', 'whatsapp'],
    ],

    'electronics' => [
        'label' => 'Electronics & Gadgets',
        'core'  => ['ecommerce', 'inventory', 'crm', 'marketing', 'tracking', 'pages'],
        'recommended' => ['pos', 'reviews', 'flash-sales', 'marketplace', 'cross-border-ior'],
    ],

    'dropshipping' => [
        'label' => 'Dropshipping',
        'core'  => ['ecommerce', 'marketing', 'tracking', 'crm', 'pages', 'seo-manager'],
        'recommended' => ['cross-border-ior', 'flash-sales', 'whatsapp', 'reviews', 'analytics'],
    ],

    'handmade' => [
        'label' => 'Handmade & Crafts',
        'core'  => ['ecommerce', 'crm', 'marketing', 'tracking', 'pages', 'seo-manager'],
        'recommended' => ['inventory', 'marketplace', 'reviews', 'flash-sales', 'loyalty'],
    ],

    // ── Food & Hospitality ──────────────────────────────────────────────

    'restaurant' => [
        'label' => 'Restaurant',
        'core'  => ['pos', 'inventory', 'crm', 'finance', 'tracking', 'restaurant'],
        'recommended' => ['hrm', 'loyalty', 'marketing', 'whatsapp', 'reviews'],
    ],

    'cafe' => [
        'label' => 'Café / Coffee Shop',
        'core'  => ['pos', 'inventory', 'crm', 'tracking', 'loyalty'],
        'recommended' => ['marketing', 'finance', 'whatsapp', 'reviews'],
    ],

    'bakery' => [
        'label' => 'Bakery & Confectionery',
        'core'  => ['pos', 'inventory', 'ecommerce', 'crm', 'tracking'],
        'recommended' => ['marketing', 'flash-sales', 'loyalty', 'whatsapp'],
    ],

    'catering' => [
        'label' => 'Catering & Events',
        'core'  => ['crm', 'finance', 'inventory', 'events', 'tracking'],
        'recommended' => ['ecommerce', 'marketing', 'whatsapp', 'contracts', 'hrm'],
    ],

    'hotel' => [
        'label' => 'Hotel & Hospitality',
        'core'  => ['crm', 'finance', 'hrm', 'pos', 'tracking'],
        'recommended' => ['ecommerce', 'loyalty', 'marketing', 'reviews', 'events', 'branches'],
    ],

    // ── Services & Professional ─────────────────────────────────────────

    'salon' => [
        'label' => 'Salon & Spa',
        'core'  => ['pos', 'crm', 'marketing', 'tracking', 'salon'],
        'recommended' => ['loyalty', 'whatsapp', 'finance', 'hrm', 'reviews'],
    ],

    'healthcare' => [
        'label' => 'Healthcare / Clinic',
        'core'  => ['crm', 'finance', 'hrm', 'tracking', 'healthcare'],
        'recommended' => ['inventory', 'whatsapp', 'marketing', 'notifications', 'security'],
    ],

    'dental' => [
        'label' => 'Dental Clinic',
        'core'  => ['crm', 'finance', 'hrm', 'tracking', 'healthcare'],
        'recommended' => ['inventory', 'marketing', 'whatsapp', 'notifications'],
    ],

    'pharmacy' => [
        'label' => 'Pharmacy',
        'core'  => ['pos', 'inventory', 'ecommerce', 'finance', 'tracking'],
        'recommended' => ['crm', 'marketing', 'whatsapp', 'branches'],
    ],

    'freelancer' => [
        'label' => 'Freelancer',
        'core'  => ['crm', 'finance', 'marketing', 'tracking', 'freelancer'],
        'recommended' => ['ecommerce', 'whatsapp', 'contracts', 'pages'],
    ],

    'consulting' => [
        'label' => 'Consulting Firm',
        'core'  => ['crm', 'finance', 'hrm', 'tracking', 'contracts'],
        'recommended' => ['marketing', 'ecommerce', 'whatsapp', 'analytics', 'security'],
    ],

    'agency' => [
        'label' => 'Agency (Marketing/Design)',
        'core'  => ['crm', 'finance', 'hrm', 'marketing', 'tracking', 'contracts'],
        'recommended' => ['ecommerce', 'whatsapp', 'freelancer', 'analytics'],
    ],

    'legal' => [
        'label' => 'Law Firm / Legal',
        'core'  => ['crm', 'finance', 'contracts', 'hrm', 'tracking', 'security'],
        'recommended' => ['marketing', 'whatsapp', 'ecommerce', 'analytics'],
    ],

    // ── Education & Training ────────────────────────────────────────────

    'education' => [
        'label' => 'Education / School',
        'core'  => ['crm', 'finance', 'hrm', 'tracking', 'education', 'notifications'],
        'recommended' => ['ecommerce', 'marketing', 'whatsapp', 'lms', 'events'],
    ],

    'coaching' => [
        'label' => 'Coaching / Tutoring',
        'core'  => ['crm', 'finance', 'marketing', 'tracking', 'lms'],
        'recommended' => ['ecommerce', 'whatsapp', 'events', 'pages', 'seo-manager'],
    ],

    'online_courses' => [
        'label' => 'Online Courses',
        'core'  => ['ecommerce', 'lms', 'marketing', 'crm', 'tracking', 'pages'],
        'recommended' => ['whatsapp', 'finance', 'loyalty', 'reviews', 'seo-manager'],
    ],

    // ── Fitness & Wellness ──────────────────────────────────────────────

    'fitness' => [
        'label' => 'Fitness & Gym',
        'core'  => ['pos', 'crm', 'marketing', 'tracking', 'fitness'],
        'recommended' => ['loyalty', 'whatsapp', 'hrm', 'finance', 'ecommerce', 'reviews'],
    ],

    'yoga' => [
        'label' => 'Yoga & Studio',
        'core'  => ['crm', 'marketing', 'tracking', 'fitness', 'loyalty'],
        'recommended' => ['pos', 'ecommerce', 'whatsapp', 'events', 'reviews'],
    ],

    // ── Real Estate & Property ──────────────────────────────────────────

    'real_estate' => [
        'label' => 'Real Estate',
        'core'  => ['crm', 'finance', 'marketing', 'tracking', 'real-estate', 'contracts'],
        'recommended' => ['ecommerce', 'whatsapp', 'inventory', 'analytics', 'pages'],
    ],

    'property_management' => [
        'label' => 'Property Management',
        'core'  => ['crm', 'finance', 'contracts', 'tracking', 'real-estate'],
        'recommended' => ['hrm', 'whatsapp', 'notifications', 'expenses', 'security'],
    ],

    // ── Manufacturing & Industry ────────────────────────────────────────

    'manufacturing' => [
        'label' => 'Manufacturing',
        'core'  => ['inventory', 'finance', 'hrm', 'manufacturing', 'tracking'],
        'recommended' => ['ecommerce', 'pos', 'branches', 'cross-border-ior', 'expenses', 'contracts'],
    ],

    'construction' => [
        'label' => 'Construction',
        'core'  => ['finance', 'hrm', 'inventory', 'contracts', 'expenses', 'tracking'],
        'recommended' => ['crm', 'manufacturing', 'branches', 'security'],
    ],

    // ── Automotive & Transport ──────────────────────────────────────────

    'automotive' => [
        'label' => 'Automotive / Car Dealer',
        'core'  => ['crm', 'inventory', 'finance', 'pos', 'tracking', 'automotive'],
        'recommended' => ['marketing', 'hrm', 'reviews', 'branches', 'contracts'],
    ],

    'logistics' => [
        'label' => 'Logistics & Shipping',
        'core'  => ['inventory', 'finance', 'crm', 'tracking', 'cross-border-ior'],
        'recommended' => ['hrm', 'branches', 'contracts', 'security', 'analytics'],
    ],

    // ── Travel & Events ─────────────────────────────────────────────────

    'travel' => [
        'label' => 'Travel & Tourism',
        'core'  => ['ecommerce', 'crm', 'marketing', 'tracking', 'travel'],
        'recommended' => ['finance', 'whatsapp', 'loyalty', 'cross-border-ior', 'reviews'],
    ],

    'events' => [
        'label' => 'Events & Entertainment',
        'core'  => ['crm', 'finance', 'marketing', 'tracking', 'events'],
        'recommended' => ['ecommerce', 'pos', 'whatsapp', 'loyalty', 'reviews'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Relationships (Related Modules)
    |--------------------------------------------------------------------------
    | When a tenant has a specific module active, these related modules
    | are suggested as complementary additions.
    */

    'relationships' => [
        'ecommerce'        => ['inventory', 'crm', 'marketing', 'pos', 'flash-sales', 'loyalty', 'reviews', 'pages', 'seo-manager'],
        'pos'              => ['inventory', 'ecommerce', 'crm', 'finance', 'loyalty', 'branches'],
        'crm'              => ['ecommerce', 'marketing', 'whatsapp', 'loyalty', 'contracts'],
        'inventory'        => ['ecommerce', 'pos', 'manufacturing', 'finance', 'branches'],
        'finance'          => ['hrm', 'inventory', 'ecommerce', 'pos', 'expenses', 'contracts'],
        'hrm'              => ['finance', 'pos', 'branches', 'expenses', 'security'],
        'marketing'        => ['ecommerce', 'crm', 'whatsapp', 'flash-sales', 'loyalty', 'seo-manager'],
        'tracking'         => ['ecommerce', 'marketing', 'crm', 'analytics'],
        'whatsapp'         => ['crm', 'marketing', 'ecommerce', 'notifications'],
        'flash-sales'      => ['ecommerce', 'marketing', 'inventory', 'loyalty'],
        'loyalty'          => ['ecommerce', 'crm', 'pos', 'marketing'],
        'marketplace'      => ['ecommerce', 'inventory', 'finance', 'reviews'],
        'manufacturing'    => ['inventory', 'finance', 'hrm', 'branches'],
        'cross-border-ior' => ['ecommerce', 'inventory', 'finance', 'tracking'],
        'zatca'            => ['ecommerce', 'finance', 'pos'],
        'notifications'    => ['ecommerce', 'crm', 'marketing', 'whatsapp'],
        'restaurant'       => ['pos', 'inventory', 'loyalty', 'reviews', 'hrm'],
        'salon'            => ['pos', 'crm', 'loyalty', 'marketing', 'reviews'],
        'healthcare'       => ['crm', 'finance', 'notifications', 'security'],
        'education'        => ['lms', 'crm', 'finance', 'events'],
        'lms'              => ['education', 'ecommerce', 'crm', 'marketing'],
        'fitness'          => ['pos', 'crm', 'loyalty', 'marketing', 'ecommerce'],
        'real-estate'      => ['crm', 'finance', 'contracts', 'marketing'],
        'automotive'       => ['inventory', 'crm', 'finance', 'pos'],
        'travel'           => ['ecommerce', 'crm', 'marketing', 'loyalty'],
        'events'           => ['crm', 'marketing', 'ecommerce', 'pos'],
        'freelancer'       => ['crm', 'finance', 'contracts', 'ecommerce'],
        'reviews'          => ['ecommerce', 'crm', 'marketing'],
        'analytics'        => ['tracking', 'ecommerce', 'crm', 'marketing'],
        'security'         => ['hrm', 'finance', 'analytics'],
        'contracts'        => ['crm', 'finance', 'hrm'],
        'expenses'         => ['finance', 'hrm', 'contracts'],
        'branches'         => ['pos', 'inventory', 'hrm'],
        'pages'            => ['ecommerce', 'seo-manager', 'marketing'],
        'seo-manager'      => ['ecommerce', 'pages', 'marketing'],
        'maroof'           => ['ecommerce', 'zatca'],
        'national-address' => ['ecommerce', 'crm'],
        'sadad'            => ['ecommerce', 'finance'],
        'app-marketplace'  => ['ecommerce', 'crm'],
        'landlord'         => [],
    ],
];
