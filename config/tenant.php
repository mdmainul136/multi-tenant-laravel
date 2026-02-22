<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Database Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when creating new tenant databases.
    | For example, if prefix is 'tenant_' and tenant ID is 'acme',
    | the database name will be 'tenant_acme'.
    |
    */
    'database_prefix' => env('TENANT_DB_PREFIX', 'tenant_'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Identification Methods
    |--------------------------------------------------------------------------
    |
    | Define how tenants should be identified. Available methods:
    | - 'header': X-Tenant-ID header
    | - 'subdomain': Extract from subdomain (e.g., tenant1.example.com)
    |
    */
    'identification_methods' => ['header', 'subdomain'],

    /*
    |--------------------------------------------------------------------------
    | Tenant Database Connection
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant-specific database connections.
    |
    */
    'database' => [
        'host' => env('TENANT_DB_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('TENANT_DB_PORT', env('DB_PORT', '3306')),
        'username' => env('TENANT_DB_USERNAME', env('DB_USERNAME', 'root')),
        'password' => env('TENANT_DB_PASSWORD', env('DB_PASSWORD', '')),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => 'InnoDB',
    ],

    /*
    |--------------------------------------------------------------------------
    | Master Database Connection
    |--------------------------------------------------------------------------
    |
    | The master database stores tenant information and metadata.
    |
    */
    'master_database' => env('DB_DATABASE', 'tenant_master'),
];
