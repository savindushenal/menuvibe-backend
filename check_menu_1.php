<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get menu 1 and count items
$menu = App\Models\Menu::find(1);

if (!$menu) {
    echo "Menu #1 not found!\n";
    exit;
}

echo "Menu ID: " . $menu->id . "\n";
echo "Menu Name: " . $menu->name . "\n";
echo "Location ID: " . $menu->location_id . "\n";

$menuItems = $menu->menuItems;
echo "Menu Items Count: " . $menuItems->count() . "\n";

if ($menuItems->count() > 0) {
    echo "\nExisting Menu Items:\n";
    foreach ($menuItems as $item) {
        echo "  - ID: {$item->id}, Name: {$item->name}\n";
    }
}

// Check user who owns this menu
$location = $menu->location;
$user = $location->user;

echo "\nUser: " . $user->email . "\n";
$plan = $user->getCurrentSubscriptionPlan();
echo "Plan: " . $plan->name . "\n";
echo "Limit for max_menu_items_per_menu: " . $plan->getLimit('max_menu_items_per_menu') . "\n";
echo "Can add item: " . ($user->canAddMenuItemToMenu($menu) ? 'YES' : 'NO') . "\n";
