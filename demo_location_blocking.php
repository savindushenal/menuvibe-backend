<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use App\Services\LocationAccessService;

echo "=== Location Blocking Demo ===\n\n";

$user = User::find(1);
$service = new LocationAccessService();

echo "Scenario: User '{$user->name}' has a FREE plan (1 location allowed) but has 2 locations\n\n";

// Current state
$limitInfo = $service->getLocationLimitInfo($user);
echo "Current State:\n";
echo "  Plan: Free (max {$limitInfo['max_allowed']} location)\n";
echo "  Total locations owned: {$limitInfo['current_count']}\n";
echo "  Accessible: {$limitInfo['accessible_count']}\n";
echo "  Blocked: {$limitInfo['blocked_count']}\n\n";

$accessible = $service->getAccessibleLocations($user);
$blocked = $service->getBlockedLocations($user);

echo "✓ ACCESSIBLE LOCATIONS:\n";
foreach ($accessible as $location) {
    echo "  - {$location->name} (ID: {$location->id})\n";
    echo "    → User can view menus, manage items, access QR codes\n";
}

echo "\n✗ BLOCKED LOCATIONS:\n";
foreach ($blocked as $location) {
    echo "  - {$location->name} (ID: {$location->id})\n";
    echo "    → Location preserved but inaccessible\n";
    echo "    → Will show 'Upgrade to Premium' message\n";
    echo "    → Data is NOT deleted, just blocked\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "What happens when user UPGRADES to Premium (5 locations):\n";
echo str_repeat('=', 60) . "\n";
echo "  ✓ piliyandala location becomes accessible again\n";
echo "  ✓ All data (menus, items) is still there\n";
echo "  ✓ User can create 3 more locations (total 5)\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "What happens if user DOWNGRADES back to Free:\n";
echo str_repeat('=', 60) . "\n";
echo "  ✓ Only default location remains accessible\n";
echo "  ✗ Other locations blocked again (but data preserved)\n";
echo "  ⚠️ No data loss - just access restriction\n";

echo "\n=== Benefits of This Approach ===\n";
echo "1. No data loss when downgrading\n";
echo "2. Smooth upgrade experience - everything returns\n";
echo "3. Clear monetization path - users see what they're missing\n";
echo "4. Better customer retention - data is safe\n";
echo "5. Flexible subscription management\n";
