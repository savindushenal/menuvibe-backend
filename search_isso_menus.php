<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Location;
use App\Models\Franchise;

echo "=== Searching for Isso-related Menus ===\n\n";

// Find isso franchise
$franchise = Franchise::where('slug', 'isso')->first();
echo "Isso Franchise ID: {$franchise->id}\n";
echo "Isso Location ID: " . Location::where('franchise_id', $franchise->id)->first()->id . "\n\n";

// Check all menus with 'isso' in the name
echo "=== Menus with 'isso' in name ===\n";
$issoMenus = Menu::where('name', 'like', '%isso%')->get();
echo "Found: {$issoMenus->count()}\n";
foreach ($issoMenus as $menu) {
    echo "  Menu ID: {$menu->id}\n";
    echo "  Name: {$menu->name}\n";
    echo "  Location ID: {$menu->location_id}\n";
    if ($menu->location) {
        echo "  Location: {$menu->location->name}\n";
        echo "  Franchise ID: {$menu->location->franchise_id}\n";
    } else {
        echo "  Location: NULL\n";
    }
    $categories = $menu->categories()->count();
    echo "  Categories: {$categories}\n";
    
    if ($categories > 0) {
        $items = $menu->categories()->with('items')->get()->sum(function($cat) {
            return $cat->items->count();
        });
        echo "  Total Items: {$items}\n";
    }
    echo "\n";
}

// Check all locations
echo "\n=== All Locations ===\n";
$locations = Location::with('franchise')->get();
foreach ($locations as $loc) {
    $menus = Menu::where('location_id', $loc->id)->count();
    echo "Location ID: {$loc->id} | {$loc->name}";
    if ($loc->franchise) {
        echo " | Franchise: {$loc->franchise->name}";
    }
    echo " | Menus: {$menus}\n";
}

// Check all menus (limited to first 20)
echo "\n=== All Menus (first 20) ===\n";
$allMenus = Menu::with('location.franchise')->limit(20)->get();
echo "Total menus in DB: " . Menu::count() . "\n\n";
foreach ($allMenus as $menu) {
    echo "Menu ID: {$menu->id} | Name: {$menu->name}";
    if ($menu->location && $menu->location->franchise) {
        echo " | Franchise: {$menu->location->franchise->name}";
    } else if ($menu->location) {
        echo " | Location: {$menu->location->name} (no franchise)";
    } else {
        echo " | NO LOCATION";
    }
    echo "\n";
}
