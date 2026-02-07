<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all payment gateway configurations for MenuVire.
    | Follows MenuVire architectural pattern: Configuration Over Code
    |
    */

    'default_gateway' => env('PAYMENT_GATEWAY', 'absterco'),
    
    'gateways' => [
        'absterco' => [
            'name' => 'Absterco Payment Gateway',
            'enabled' => env('ABSTERCO_ENABLED', true),
            'api_key' => env('ABSTERCO_API_KEY'),
            'base_url' => env('ABSTERCO_BASE_URL', 'https://api.dev-gateway.absterco.com'),
            'organization_id' => env('ABSTERCO_ORGANIZATION_ID'),
            'test_mode' => env('ABSTERCO_TEST_MODE', true),
            
            // Feature flags
            'features' => [
                'saved_cards' => true,
                'recurring_payments' => true,
                'refunds' => true,
                'webhooks' => true,
            ],
            
            // Settings
            'settings' => [
                'currency' => 'LKR',
                'allow_save_card' => true,
                'payment_link_expiry' => 3600, // 1 hour in seconds
                'max_saved_cards' => 5,
            ],
        ],
        
        // Future payment gateways can be added here
        // 'stripe' => [...],
        // 'paypal' => [...],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Subscription Payment Settings
    |--------------------------------------------------------------------------
    |
    | Settings specific to subscription payments
    |
    */
    
    'subscription' => [
        'charge_setup_fee_on_upgrade' => false,
        'prorate_upgrades' => true,
        'prorate_downgrades' => false,
        'allow_downgrade' => true,
        'allow_cancel' => true,
        'grace_period_days' => 3,
        
        'return_urls' => [
            'success' => env('FRONTEND_URL', env('APP_FRONTEND_URL')) . '/dashboard/subscription/payment-callback',
            'cancel' => env('FRONTEND_URL', env('APP_FRONTEND_URL')) . '/dashboard/subscription?payment=cancelled',
            'webhook' => env('APP_URL') . '/api/webhooks/payment',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Franchise Payment Settings
    |--------------------------------------------------------------------------
    |
    | Default payment settings that can be overridden in franchise.php
    |
    */
    
    'franchise_defaults' => [
        'payment_methods' => ['card', 'saved_card'],
        'tax_included_in_price' => false,
        'setup_fee_enabled' => true,
    ],
];
