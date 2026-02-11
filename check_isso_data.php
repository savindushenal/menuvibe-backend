<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Franchise;
use App\Models\FranchiseBranch;
use App\Models\Location;
use App\Models\Menu;
use App\Models\FranchiseAccount;

echo "=== Checking Isso Franchise Data ===\n\n";

// Find isso franchise
$franchise = Franchise::where('slug', 'isso')->first();

if (!$franchise) {
    echo "❌ Isso franchise not found!\n";
    exit;
}

echo "✅ Franchise Found:\n";
echo "   ID: {$franchise->id}\n";
echo "   Name: {$franchise->name}\n";
echo "   Slug: {$franchise->slug}\n";
echo "   Active: " . ($franchise->is_active ? 'Yes' : 'No') . "\n";
echo "   Template: {$franchise->template_type}\n\n";

// Check locations
echo "=== Locations ===\n";
$locations = Location::where('franchise_id', $franchise->id)->get();
echo "Total locations: " . $locations->count() . "\n";
if ($locations->count() > 0) {
    foreach ($locations as $location) {
        echo "\n  Location ID: {$location->id}\n";
        echo "  Name: {$location->name}\n";
        echo "  Branch: {$location->branch_name}\n";
        echo "  Branch Code: {$location->branch_code}\n";
        
        // Check menus for this location
        $menus = Menu::where('location_id', $location->id)->get();
        echo "  Menus: {$menus->count()}\n";
        if ($menus->count() > 0) {
            foreach ($menus as $menu) {
                echo "    - Menu ID: {$menu->id}, Name: {$menu->name}\n";
                $categories = $menu->categories()->count();
                echo "      Categories: {$categories}\n";
                if ($categories > 0) {
                    $items = $menu->categories()->with('items')->get()->sum(function($cat) {
                        return $cat->items->count();
                    });
                    echo "      Total Items: {$items}\n";
                }
            }
        }
    }
} else {
    echo "❌ No locations found for isso franchise!\n";
}

// Check franchise accounts (staff)
echo "\n\n=== Franchise Accounts ===\n";
$accounts = FranchiseAccount::where('franchise_id', $franchise->id)->get();
echo "Total accounts: " . $accounts->count() . "\n";
if ($accounts->count() > 0) {
    foreach ($accounts as $account) {
        echo "\n  User ID: {$account->user_id}\n";
        echo "  Role: {$account->role}\n";
        echo "  Active: " . ($account->is_active ? 'Yes' : 'No') . "\n";
        echo "  Location ID: {$account->location_id}\n";
    }
}

echo "\n\n=== Dashboard Query Test ===\n";
// Simulate what the dashboard does
$stats = [
    'branches' => FranchiseBranch::where('franchise_id', $franchise->id)->count(),
    'locations' => Location::where('franchise_id', $franchise->id)->count(),
    'menus' => Menu::whereHas('location', function ($q) use ($franchise) {
        $q->where('franchise_id', $franchise->id);
    })->count(),
    'staff' => FranchiseAccount::where('franchise_id', $franchise->id)
        ->where('is_active', true)
        ->count(),
];

echo "Stats that would be returned:\n";
echo "  Branches: {$stats['branches']}\n";
echo "  Locations: {$stats['locations']}\n";
echo "  Menus: {$stats['menus']}\n";
echo "  Staff: {$stats['staff']}\n";

echo "\n✅ Check complete!\n";
