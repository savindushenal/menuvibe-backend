<?php

/**
 * API Configuration
 * 
 * Enterprise API settings for MenuVire
 */

return [

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | Current API version. Used for routing and documentation.
    |
    */
    'version' => env('API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | API Secret Key
    |--------------------------------------------------------------------------
    |
    | Secret key for request signing (HMAC-SHA256).
    | MUST be 256 bits (32 bytes) for security.
    |
    */
    'secret_key' => env('API_SECRET_KEY', config('app.key')),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Default rate limits for API requests per API key type.
    |
    */
    'rate_limits' => [
        'public_read' => 1000,    // requests per hour
        'standard' => 10000,
        'premium' => 100000,
        'enterprise' => -1,       // unlimited
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Signature
    |--------------------------------------------------------------------------
    |
    | Settings for request signature validation.
    |
    */
    'signature' => [
        'enabled' => env('API_SIGNATURE_REQUIRED', true),
        'algorithm' => 'sha256',
        'timestamp_tolerance' => 300, // 5 minutes in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Settings
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing configuration for API.
    |
    */
    'cors' => [
        'allowed_origins' => explode(',', env('API_ALLOWED_ORIGINS', 'http://localhost:3000')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['*'],
        'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],
        'max_age' => 3600,
        'supports_credentials' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Documentation
    |--------------------------------------------------------------------------
    |
    | Settings for API documentation generation.
    |
    */
    'documentation' => [
        'title' => 'MenuVire API',
        'description' => 'Enterprise API for digital menu management',
        'version' => '1.0.0',
        'contact' => [
            'name' => 'MenuVire API Support',
            'email' => 'api-support@MenuVire.com',
            'url' => 'https://docs.MenuVire.com',
        ],
    ],

];
