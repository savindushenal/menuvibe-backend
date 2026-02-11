<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Franchise;
use App\Models\Location;
use App\Models\Menu;

echo "=== CHECKING ISSO LOCATIONS ===\n\n";

// Get isso franchise
$franchise = Franchise::where('slug', 'isso')->first();

if (!$franchise) {
    echo "❌ Isso franchise not found\n";
    exit(1);
}

echo "Franchise: {$franchise->name}\n";
echo "Franchise ID: {$franchise->id}\n\n";

// Check franchise branches (locations with branch_code)
echo "=== FRANCHISE BRANCHES ===\n";
$branches = Location::where('franchise_id', $franchise->id)
    ->whereNotNull('branch_code')
    ->get();
echo "Total branches: " . $branches->count() . "\n";
if ($branches->count() > 0) {
    foreach ($branches as $branch) {
        echo "  - Branch ID: {$branch->id}\n";
        echo "    Name: {$branch->branch_name}\n";
        echo "    Code: {$branch->branch_code}\n";
        echo "    Is Active: " . ($branch->is_active ? 'Yes' : 'No') . "\n";
        echo "    Is Paid: " . ($branch->is_paid ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
}

// Check locations
echo "\n=== LOCATIONS ===\n";
$locations = Location::where('franchise_id', $franchise->id)->get();
echo "Total locations: " . $locations->count() . "\n";
if ($locations->count() > 0) {
    foreach ($locations as $location) {
        echo "  - Location ID: {$location->id}\n";
        echo "    Name: {$location->name}\n";
        echo "    User ID (Owner): {$location->user_id}\n";
        echo "    Franchise ID: {$location->franchise_id}\n";
        echo "    Is Active: " . ($location->is_active ? 'Yes' : 'No') . "\n";
        
        // Check menus for this location
        $menus = Menu::where('location_id', $location->id)->get();
        echo "    Menus: " . $menus->count() . "\n";
        if ($menus->count() > 0) {
            foreach ($menus as $menu) {
                echo "      - Menu ID: {$menu->id}\n";
                echo "        Name: {$menu->name}\n";
                echo "        Is Active: " . ($menu->is_active ? 'Yes' : 'No') . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "❌ No locations found!\n";
}

// Check what stats would be returned by API
echo "\n=== DASHBOARD STATS (API Returns) ===\n";
$stats = [
    'branches' => Location::where('franchise_id', $franchise->id)
        ->whereNotNull('branch_code')
        ->count(),
    'locations' => Location::where('franchise_id', $franchise->id)->count(),
    'menus' => Menu::whereHas('location', function($query) use ($franchise) {
        $query->where('franchise_id', $franchise->id);
    })->count(),
];

echo "Branches count: {$stats['branches']}\n";
echo "Locations count: {$stats['locations']}\n";
echo "Menus count: {$stats['menus']}\n";

// Check if there are any locations NOT linked to franchise
echo "\n=== CHECKING FOR ORPHANED LOCATIONS ===\n";
$allLocations = Location::all();
$orphanedLocations = [];
foreach ($allLocations as $loc) {
    if (stripos($loc->name, 'isso') !== false && $loc->franchise_id != $franchise->id) {
        $orphanedLocations[] = $loc;
    }
}

if (count($orphanedLocations) > 0) {
    echo "⚠️ Found " . count($orphanedLocations) . " locations with 'isso' in name but wrong franchise_id:\n";
    foreach ($orphanedLocations as $loc) {
        echo "  - Location ID: {$loc->id}\n";
        echo "    Name: {$loc->name}\n";
        echo "    Franchise ID: {$loc->franchise_id} (should be {$franchise->id})\n";
        echo "    User ID: {$loc->user_id}\n\n";
    }
} else {
    echo "✅ No orphaned locations found\n";
}

