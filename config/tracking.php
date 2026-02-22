<?php

// Tracking Module Configuration
return [

    /*
    |--------------------------------------------------------------------------
    | Docker Orchestrator Settings
    |--------------------------------------------------------------------------
    */
    'docker' => [
        // Custom Node.js tracking proxy image
        // Build: docker build -t sgtm-tracking-proxy:latest ./docker/sgtm
        'image'            => env('SGTM_DOCKER_IMAGE', 'sgtm-tracking-proxy:latest'),
        
        // Docker network for inter-container communication
        'network'          => env('SGTM_DOCKER_NETWORK', 'tracking_network'),
        
        // NGINX config output directory
        'nginx_config_path' => env('SGTM_NGINX_CONFIG_PATH', '/etc/nginx/sites-enabled'),
        
        // Base domain for auto-generated subdomains
        // e.g. track-{slug}.yourdomain.com
        'base_domain'      => env('SGTM_BASE_DOMAIN', 'track.yourdomain.com'),
        
        // Port range start for container allocation
        'port_range_start' => (int) env('SGTM_PORT_RANGE_START', 9000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked IPs for Bot Filtering (Power-Up: bot_filter)
    |--------------------------------------------------------------------------
    */
    'blocked_ips' => array_filter(explode(',', env('TRACKING_BLOCKED_IPS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Usage & Billing Tiers
    |--------------------------------------------------------------------------
    | Monthly event quotas for different subscription levels.
    */
    'tiers' => [
        'free' => [
            'event_limit' => 100000, // 100k events/month
            'power_ups'   => ['dedupe', 'pii_hash', 'consent_mode'],
        ],
        'pro' => [
            'event_limit' => 5000000, // 5M events/month
            'power_ups'   => ['dedupe', 'pii_hash', 'consent_mode', 'cookie_extend', 'geo_enrich', 'bot_filter'],
        ],
    ],

];
