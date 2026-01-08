<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Log;

echo "=== Testing Dashboard Stats ===\n\n";

// Get user 1
$user = User::find(1);

if (!$user) {
    echo "User 1 not found!\n";
    exit;
}

echo "User: {$user->name} ({$user->email})\n\n";

try {
    echo "Fetching locations...\n";
    $locations = $user->locations()->with(['menus.menuItems'])->get();
    echo "Found {$locations->count()} location(s)\n\n";
    
    // Calculate stats
    $totalLocations = $locations->count();
    $totalMenus = 0;
    $totalMenuItems = 0;
    $activeMenus = 0;
    
    foreach ($locations as $location) {
        echo "Location: {$location->name}\n";
        $menus = $location->menus;
        echo "  Menus: {$menus->count()}\n";
        $totalMenus += $menus->count();
        
        foreach ($menus as $menu) {
            echo "    Menu: {$menu->name}\n";
            if ($menu->is_active ?? false) {
                $activeMenus++;
            }
            $itemCount = $menu->menuItems->count();
            echo "      Items: {$itemCount}\n";
            $totalMenuItems += $itemCount;
        }
    }
    
    echo "\nTotals:\n";
    echo "  Locations: {$totalLocations}\n";
    echo "  Menus: {$totalMenus}\n";
    echo "  Active Menus: {$activeMenus}\n";
    echo "  Menu Items: {$totalMenuItems}\n\n";
    
    // Get subscription info
    echo "Fetching subscription...\n";
    $subscription = $user->activeSubscription;
    echo "Subscription: " . ($subscription ? $subscription->status : 'none') . "\n";
    
    echo "\nFetching plan...\n";
    $plan = $user->getCurrentSubscriptionPlan();
    echo "Plan: " . ($plan ? $plan->name : 'none') . "\n";
    
    if ($plan) {
        echo "Plan limits: " . json_encode($plan->limits) . "\n";
    }
    
    echo "\n✓ Dashboard stats working correctly!\n";
    
} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
