<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();
echo "User Email: " . $user->email . "\n";

$plan = $user->getCurrentSubscriptionPlan();
echo "Plan Name: " . $plan->name . "\n";
echo "Plan Slug: " . $plan->slug . "\n";
echo "\nLimits:\n";
echo json_encode($plan->limits, JSON_PRETTY_PRINT) . "\n";

// Check max_menu_items_per_menu limit
$limit = $plan->getLimit('max_menu_items_per_menu');
echo "\nmax_menu_items_per_menu limit: " . $limit . "\n";

// Check menu count
$menu = App\Models\Menu::first();
if ($menu) {
    $menuItemCount = $menu->menuItems()->count();
    echo "Menu ID: " . $menu->id . "\n";
    echo "Current menu items count: " . $menuItemCount . "\n";
    echo "Can add more items: " . ($user->canAddMenuItemToMenu($menu) ? 'Yes' : 'No') . "\n";
}
