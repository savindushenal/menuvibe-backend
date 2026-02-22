<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

echo "=== Checking Menu 8 ===\n\n";

$menu = Menu::with('location.franchise')->find(8);

if ($menu) {
    echo "✅ Menu Found\n";
    echo "  ID: {$menu->id}\n";
    echo "  Name: {$menu->name}\n";
    echo "  Location ID: " . ($menu->location_id ?? 'NULL') . "\n";
    
    if ($menu->location) {
        echo "\n  Location Details:\n";
        echo "    Name: {$menu->location->name}\n";
        echo "    User ID: " . ($menu->location->user_id ?? 'NULL') . "\n";
        echo "    Franchise ID: " . ($menu->location->franchise_id ?? 'NULL') . "\n";
        
        if ($menu->location->franchise_id) {
            echo "\n  This is a FRANCHISE menu\n";
            echo "    Franchise: " . ($menu->location->franchise->name ?? 'N/A') . "\n";
        } else {
            echo "\n  This is a BUSINESS menu (non-franchise)\n";
        }
    } else {
        echo "  ⚠️  No location found for this menu!\n";
    }
} else {
    echo "❌ Menu ID 8 not found in database\n";
}
