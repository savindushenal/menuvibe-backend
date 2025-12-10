<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Update Pro plan
DB::table('subscription_plans')->where('id', 2)->update([
    'limits' => json_encode([
        'max_locations' => 5,
        'max_menus_per_location' => 5,
        'max_menu_items_total' => -1,
        'max_menu_items_per_menu' => -1,
        'max_qr_codes' => -1,
        'photo_uploads' => true,
        'custom_qr_codes' => true,
        'table_specific_qr' => true,
        'analytics' => true,
        'online_ordering' => true,
        'priority_support' => true
    ])
]);

echo "Pro plan updated\n";

// Update Enterprise plan
DB::table('subscription_plans')->where('id', 3)->update([
    'limits' => json_encode([
        'max_locations' => 10,
        'max_menus_per_location' => -1,
        'max_menu_items_total' => -1,
        'max_menu_items_per_menu' => -1,
        'max_qr_codes' => -1,
        'photo_uploads' => true,
        'custom_qr_codes' => true,
        'table_specific_qr' => true,
        'analytics' => true,
        'advanced_analytics' => true,
        'online_ordering' => true,
        'api_access' => true,
        'white_label' => true,
        'priority_support' => true,
        'dedicated_support' => true
    ])
]);

echo "Enterprise plan updated\n";

// Update Custom Enterprise plan
DB::table('subscription_plans')->where('id', 4)->update([
    'limits' => json_encode([
        'max_locations' => -1,
        'max_menus_per_location' => -1,
        'max_menu_items_total' => -1,
        'max_menu_items_per_menu' => -1,
        'max_qr_codes' => -1,
        'photo_uploads' => true,
        'custom_qr_codes' => true,
        'table_specific_qr' => true,
        'analytics' => true,
        'advanced_analytics' => true,
        'online_ordering' => true,
        'api_access' => true,
        'white_label' => true,
        'priority_support' => true,
        'dedicated_support' => true,
        'sla_guarantee' => true,
        'custom_integrations' => true
    ])
]);

echo "Custom Enterprise plan updated\n";
echo "All plans now have max_menu_items_per_menu limit!\n";
