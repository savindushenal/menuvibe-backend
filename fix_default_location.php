<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();
echo "User: " . $user->email . "\n";

$locations = $user->locations;
echo "Locations count: " . $locations->count() . "\n\n";

foreach ($locations as $location) {
    echo "Location ID: " . $location->id . "\n";
    echo "Name: " . $location->name . "\n";
    echo "Is Default: " . ($location->is_default ? 'Yes' : 'No') . "\n";
    echo "Menus: " . $location->menus()->count() . "\n";
    echo "---\n";
}

// Set the first location as default if none is set
if (!$user->defaultLocation) {
    $firstLocation = $locations->first();
    if ($firstLocation) {
        $firstLocation->is_default = true;
        $firstLocation->save();
        echo "\n✓ Set location '{$firstLocation->name}' as default\n";
    }
}
