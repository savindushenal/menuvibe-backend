<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

// Initialize Laravel application
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Multi-Location Feature Implementation\n";
echo "=" . str_repeat("=", 45) . "\n\n";

// Test 1: Check if users have locations
echo "1. Checking migrated locations...\n";
$users = User::with(['locations', 'businessProfile'])->get();
foreach ($users as $user) {
    echo "   User: {$user->name} (ID: {$user->id})\n";
    echo "   Locations: {$user->locations->count()}\n";
    if ($user->locations->count() > 0) {
        foreach ($user->locations as $location) {
            echo "     - {$location->name} (Default: " . ($location->is_default ? 'Yes' : 'No') . ")\n";
        }
    }
    echo "\n";
}

// Test 2: Check subscription plans
echo "2. Checking subscription plans...\n";
$plans = SubscriptionPlan::all();
foreach ($plans as $plan) {
    echo "   Plan: {$plan->name}\n";
    $limits = json_decode($plan->limits, true);
    echo "   Max Locations: " . ($limits['max_locations'] ?? 'Not set') . "\n";
    echo "   Max Menus per Location: " . ($limits['max_menus_per_location'] ?? 'Not set') . "\n";
    echo "   Max Menu Items per Menu: " . ($limits['max_menu_items_per_menu'] ?? 'Not set') . "\n";
    echo "\n";
}

// Test 3: Test user location quota checking
echo "3. Testing location quota checking...\n";
$testUser = User::first();
if ($testUser) {
    echo "   User: {$testUser->name}\n";
    echo "   Current locations: {$testUser->getTotalLocationsCount()}\n";
    echo "   Can add location: " . ($testUser->canAddLocation() ? 'Yes' : 'No') . "\n";
    echo "   Remaining quota: " . $testUser->getRemainingLocationQuota() . "\n";
    $plan = $testUser->getCurrentSubscriptionPlan();
    echo "   Current plan: " . ($plan ? $plan->name : 'None') . "\n";
    echo "\n";
}

// Test 4: Check menu relationships
echo "4. Checking menu relationships...\n";
$locations = Location::with(['menus', 'user'])->get();
foreach ($locations as $location) {
    echo "   Location: {$location->name} (User: {$location->user->name})\n";
    echo "   Menus: {$location->menus->count()}\n";
    foreach ($location->menus as $menu) {
        echo "     - {$menu->name}\n";
    }
    echo "\n";
}

echo "Testing completed!\n";