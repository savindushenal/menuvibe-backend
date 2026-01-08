<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use App\Services\LocationAccessService;

echo "=== Testing Location Access Service ===\n\n";

$user = User::find(1);

if (!$user) {
    echo "User not found!\n";
    exit;
}

echo "User: {$user->name}\n";

$service = new LocationAccessService();

// Get limit info
$limitInfo = $service->getLocationLimitInfo($user);
echo "\n--- Location Limit Info ---\n";
echo "Total locations: {$limitInfo['current_count']}\n";
echo "Max allowed: {$limitInfo['max_allowed']}\n";
echo "Accessible: {$limitInfo['accessible_count']}\n";
echo "Blocked: {$limitInfo['blocked_count']}\n";
echo "Can create more: " . ($limitInfo['can_create_more'] ? 'Yes' : 'No') . "\n";
echo "Is over limit: " . ($limitInfo['is_over_limit'] ? 'Yes' : 'No') . "\n";

// Get accessible locations
$accessible = $service->getAccessibleLocations($user);
echo "\n--- Accessible Locations ---\n";
foreach ($accessible as $location) {
    echo "✓ {$location->name} (ID: {$location->id})\n";
}

// Get blocked locations
$blocked = $service->getBlockedLocations($user);
echo "\n--- Blocked Locations ---\n";
if ($blocked->isEmpty()) {
    echo "None\n";
} else {
    foreach ($blocked as $location) {
        echo "✗ {$location->name} (ID: {$location->id}) - Blocked due to subscription limits\n";
    }
}

// Test access for each location
echo "\n--- Access Check for Each Location ---\n";
$allLocations = $user->locations;
foreach ($allLocations as $location) {
    $canAccess = $service->canAccessLocation($user, $location->id);
    $status = $canAccess ? '✓ Can access' : '✗ Blocked';
    echo "{$location->name} (ID: {$location->id}): {$status}\n";
}

echo "\n=== Test Complete ===\n";
