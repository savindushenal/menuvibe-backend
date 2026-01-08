<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Checking Subscription Limits vs Actual Usage ===\n\n";

$user = User::find(1);

if (!$user) {
    echo "User not found!\n";
    exit;
}

echo "User: {$user->name} ({$user->email})\n";

// Get subscription plan
$plan = $user->getCurrentSubscriptionPlan();
echo "Plan: " . ($plan ? $plan->name : 'None') . "\n";

if ($plan) {
    echo "Plan Limits:\n";
    $limits = $plan->limits;
    echo "  Max Locations: " . ($limits['max_locations'] ?? 'unlimited') . "\n";
    echo "  Max Menus per Location: " . ($limits['max_menus_per_location'] ?? 'unlimited') . "\n";
    echo "  Max Items per Menu: " . ($limits['max_menu_items_per_menu'] ?? 'unlimited') . "\n";
}

echo "\nActual Usage:\n";

// Get actual locations
$locations = $user->locations()->get();
echo "  Total Locations: " . $locations->count() . "\n";

foreach ($locations as $location) {
    echo "    - {$location->name} (ID: {$location->id}, created: {$location->created_at})\n";
    $menus = $location->menus;
    echo "      Menus: {$menus->count()}\n";
    foreach ($menus as $menu) {
        $itemCount = $menu->menuItems()->count();
        echo "        - {$menu->name}: {$itemCount} items\n";
    }
}

// Check if user exceeded limits
echo "\n=== Limit Violations ===\n";

if ($plan) {
    $maxLocations = $limits['max_locations'] ?? 999;
    $actualLocations = $locations->count();
    
    if ($actualLocations > $maxLocations) {
        echo "⚠️ VIOLATION: User has {$actualLocations} locations but plan allows only {$maxLocations}\n";
        echo "\nSuggested Fix:\n";
        echo "1. Upgrade user to a plan that allows multiple locations\n";
        echo "2. Or delete the extra location(s)\n\n";
        
        echo "To delete the newest location (ID: {$locations->last()->id} - {$locations->last()->name}):\n";
        echo "Would you like to delete it? (This script just reports, run the fix script to delete)\n";
    } else {
        echo "✓ Location count within limits\n";
    }
}
