<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::find(8);
echo "Creating location for user: " . $user->email . "\n";

// Create a test location
$location = App\Models\Location::create([
    'user_id' => $user->id,
    'name' => 'My Restaurant',
    'description' => 'Test restaurant location',
    'is_active' => true,
    'is_default' => true,
    'sort_order' => 1,
    'address_line_1' => '123 Main St',
    'city' => 'Test City',
    'state' => 'Test State',
    'postal_code' => '12345',
    'country' => 'USA',
]);

echo "✓ Location created: {$location->name} (ID: {$location->id})\n";

// Create a test menu
$menu = App\Models\Menu::create([
    'location_id' => $location->id,
    'name' => 'Main Menu',
    'description' => 'Our main menu',
    'is_active' => true,
    'is_featured' => true,
    'sort_order' => 1,
]);

echo "✓ Menu created: {$menu->name} (ID: {$menu->id})\n";

echo "\nNow you can access the menu management page!\n";
