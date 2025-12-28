<?php

return [
    'pizzahut' => [
        'name' => 'Pizza Hut',
        'features' => [
            'loyalty_points' => true,
            'delivery_tracking' => true,
            'kitchen_display' => true,
            'custom_modifiers' => true,
            'buy_2_get_1' => true,
            'menu_versioning' => true,
            'bulk_sync' => true,
        ],
        'custom_fields' => [
            'crust_type' => ['thin', 'thick', 'stuffed', 'cheese_burst'],
            'size' => ['personal', 'medium', 'large', 'family'],
            'extra_toppings' => ['cheese', 'pepperoni', 'mushrooms', 'olives', 'onions', 'peppers'],
        ],
        'required_menu_fields' => ['crust_type', 'size'],
        'tax_rate' => 0.15,
        'currency' => 'LKR',
        'discount_rules' => [
            'buy_2_get_1' => [
                'enabled' => true,
                'category' => 'pizza',
                'minimum_quantity' => 3,
            ],
        ],
        'max_locations' => 200,
        'support_email' => 'support@pizzahut.lk',
        'database_connection' => 'tenant_pizzahut',
    ],

    'barista' => [
        'name' => 'Barista',
        'features' => [
            'table_booking' => true,
            'mobile_order' => true,
            'rewards_program' => true,
            'custom_coffee_preferences' => true,
            'menu_versioning' => true,
            'bulk_sync' => true,
        ],
        'custom_fields' => [
            'milk_type' => ['regular', 'soy', 'almond', 'oat'],
            'sugar_level' => ['none', 'low', 'medium', 'high'],
            'temperature' => ['hot', 'cold', 'iced'],
            'shot_count' => [1, 2, 3],
        ],
        'required_menu_fields' => ['milk_type', 'temperature'],
        'tax_rate' => 0.12,
        'currency' => 'LKR',
        'discount_rules' => [
            'loyalty_discount' => [
                'enabled' => true,
                'percentage' => 0.10,
            ],
        ],
        'max_locations' => 150,
        'support_email' => 'support@barista.lk',
        'database_connection' => 'tenant_barista',
    ],

    'default' => [
        'name' => 'MenuVibe Client',
        'features' => [
            'basic_menu' => true,
            'qr_code' => true,
            'menu_versioning' => false,
            'bulk_sync' => false,
        ],
        'custom_fields' => [],
        'required_menu_fields' => [],
        'tax_rate' => 0.10,
        'currency' => 'LKR',
        'discount_rules' => [],
        'max_locations' => 20,
        'support_email' => 'support@menuvibe.com',
        'database_connection' => 'mysql',
    ],
];
