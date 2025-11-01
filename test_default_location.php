<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();
$loc = $user->defaultLocation;

if ($loc) {
    echo "✓ Default location found: " . $loc->name . " (ID: {$loc->id})\n";
} else {
    echo "✗ No default location found\n";
    echo "Checking all locations:\n";
    foreach ($user->locations as $location) {
        echo "  - ID: {$location->id}, Name: {$location->name}, is_default: " . ($location->is_default ? 'true' : 'false') . "\n";
    }
}

// Also test the fallback
$currentLocation = $user->defaultLocation ?? $user->locations()->first();
if ($currentLocation) {
    echo "\n✓ Using location: " . $currentLocation->name . "\n";
} else {
    echo "\n✗ No location available at all!\n";
}
