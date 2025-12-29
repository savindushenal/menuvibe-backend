<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MenuEndpoint;
use App\Models\Location;
use App\Models\Franchise;

echo "=== Checking Menu Endpoint ===\n\n";

$code = $argv[1] ?? 'mfKkHTOD';

echo "Looking for endpoint with code: $code\n\n";

$endpoint = MenuEndpoint::where('short_code', $code)->first();

if (!$endpoint) {
    echo "❌ Endpoint not found!\n";
    
    // Show available endpoints
    echo "\nAvailable endpoints:\n";
    $endpoints = MenuEndpoint::with(['location', 'franchise'])->get();
    foreach ($endpoints as $ep) {
        echo "- Code: {$ep->short_code} | Type: {$ep->type} | Name: {$ep->name} | Location ID: {$ep->location_id} | Franchise ID: {$ep->franchise_id}\n";
    }
    exit(1);
}

echo "✓ Endpoint found:\n";
echo "  ID: {$endpoint->id}\n";
echo "  Code: {$endpoint->short_code}\n";
echo "  Name: {$endpoint->name}\n";
echo "  Type: {$endpoint->type}\n";
echo "  Identifier: {$endpoint->identifier}\n";
echo "  Active: " . ($endpoint->is_active ? 'Yes' : 'No') . "\n";
echo "  Location ID: {$endpoint->location_id}\n";
echo "  Franchise ID: {$endpoint->franchise_id}\n";
echo "  Template ID: {$endpoint->template_id}\n\n";

// Check location
if ($endpoint->location_id) {
    $location = Location::find($endpoint->location_id);
    if ($location) {
        echo "✓ Location found:\n";
        echo "  ID: {$location->id}\n";
        echo "  Name: {$location->name}\n";
        echo "  Active: " . ($location->is_active ? 'Yes' : 'No') . "\n";
        echo "  Franchise ID: {$location->franchise_id}\n\n";
    } else {
        echo "❌ Location not found (ID: {$endpoint->location_id})\n\n";
    }
} else {
    echo "❌ Endpoint has no location_id\n\n";
}

// Check franchise
if ($endpoint->franchise_id) {
    $franchise = Franchise::find($endpoint->franchise_id);
    if ($franchise) {
        echo "✓ Franchise found:\n";
        echo "  ID: {$franchise->id}\n";
        echo "  Name: {$franchise->name}\n";
        echo "  Slug: {$franchise->slug}\n";
        echo "  Status: {$franchise->status}\n";
        echo "  Template Type: {$franchise->template_type}\n\n";
    } else {
        echo "❌ Franchise not found (ID: {$endpoint->franchise_id})\n\n";
    }
} else {
    echo "❌ Endpoint has no franchise_id\n\n";
}

// Check menu items
if ($endpoint->location_id) {
    $location = Location::find($endpoint->location_id);
    if ($location) {
        $menu = $location->menus()->where('is_active', true)->with('categories.items')->first();
        if ($menu) {
            echo "✓ Active menu found:\n";
            echo "  Menu ID: {$menu->id}\n";
            echo "  Menu Name: {$menu->name}\n";
            echo "  Categories: " . $menu->categories->count() . "\n";
            $itemCount = $menu->categories->sum(fn($cat) => $cat->items->count());
            echo "  Total Items: {$itemCount}\n\n";
        } else {
            echo "❌ No active menu found for location\n\n";
        }
    }
}

echo "=== Fix Suggestion ===\n";
if (!$endpoint->franchise_id || !$endpoint->location_id) {
    echo "Run this SQL to fix the endpoint:\n";
    if ($endpoint->location_id && !$endpoint->franchise_id) {
        $loc = Location::find($endpoint->location_id);
        if ($loc) {
            echo "UPDATE menu_endpoints SET franchise_id = {$loc->franchise_id} WHERE id = {$endpoint->id};\n";
        }
    } elseif (!$endpoint->location_id) {
        echo "The endpoint needs a location_id. Find the correct location and run:\n";
        echo "UPDATE menu_endpoints SET location_id = <location_id>, franchise_id = <franchise_id> WHERE id = {$endpoint->id};\n";
    }
}
