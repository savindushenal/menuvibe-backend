<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Fixing Location Limit Violation ===\n\n";

$user = User::find(1);

if (!$user) {
    echo "User not found!\n";
    exit;
}

echo "User: {$user->name}\n";

$locations = $user->locations()->get();
echo "Current locations: {$locations->count()}\n\n";

// Keep the oldest location (the first one created)
$keepLocation = $locations->sortBy('created_at')->first();
$deleteLocations = $locations->sortBy('created_at')->slice(1);

echo "Will keep: {$keepLocation->name} (ID: {$keepLocation->id})\n";
echo "Will delete:\n";

foreach ($deleteLocations as $location) {
    echo "  - {$location->name} (ID: {$location->id})\n";
}

echo "\nStarting deletion process...\n\n";

DB::beginTransaction();

try {
    foreach ($deleteLocations as $location) {
        echo "Deleting location: {$location->name}\n";
        
        // Delete associated menus and menu items
        $menus = $location->menus;
        foreach ($menus as $menu) {
            $itemCount = $menu->menuItems()->count();
            echo "  - Deleting menu '{$menu->name}' with {$itemCount} items\n";
            $menu->menuItems()->delete();
            $menu->delete();
        }
        
        // Delete the location
        $location->delete();
        echo "  ✓ Location deleted\n\n";
    }
    
    // Set the remaining location as default if not already
    if (!$user->default_location_id || $user->default_location_id !== $keepLocation->id) {
        $user->default_location_id = $keepLocation->id;
        $user->save();
        echo "✓ Set '{$keepLocation->name}' as default location\n";
    }
    
    DB::commit();
    
    echo "\n=== Fix Complete ===\n";
    echo "User now has " . $user->locations()->count() . " location(s)\n";
    echo "Default location: " . ($user->defaultLocation ? $user->defaultLocation->name : 'None') . "\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back, no changes made.\n";
}
