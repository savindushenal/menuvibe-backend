<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Location;
use App\Models\Franchise;

echo "=== Updating Isso Menu with Missing Fields ===\n\n";

// Find isso franchise and menu
$franchise = Franchise::where('slug', 'isso')->first();
$location = Location::where('franchise_id', $franchise->id)->first();
$menu = Menu::where('location_id', $location->id)->first();

if (!$menu) {
    echo "❌ No menu found for isso!\n";
    exit;
}

echo "Found menu: {$menu->name} (ID: {$menu->id})\n";
echo "Current fields:\n";
echo "  - style: " . ($menu->style ?? 'NULL') . "\n";
echo "  - currency: " . ($menu->currency ?? 'NULL') . "\n";
echo "  - settings: " . ($menu->settings ? json_encode($menu->settings) : 'NULL') . "\n";
echo "  - slug: " . ($menu->slug ?? 'NULL') . "\n";
echo "  - public_id: " . ($menu->public_id ?? 'NULL') . "\n\n";

// Update with proper fields
$menu->update([
    'style' => 'modern',
    'currency' => 'LKR',
    'slug' => 'master-menu',
    'public_id' => \Illuminate\Support\Str::random(8),
    'settings' => [
        'show_prices' => true,
        'show_images' => true,
        'show_descriptions' => true,
        'show_categories' => true,
        'allow_search' => true,
        'theme' => 'premium',
    ],
]);

echo "✅ Menu updated successfully!\n\n";
echo "Updated fields:\n";
echo "  - style: {$menu->style}\n";
echo "  - currency: {$menu->currency}\n";
echo "  - settings: " . json_encode($menu->settings, JSON_PRETTY_PRINT) . "\n";
echo "  - slug: {$menu->slug}\n";
echo "  - public_id: {$menu->public_id}\n";
