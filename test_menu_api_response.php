<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

echo "=== Testing Menu API Response ===\n\n";

// Simulate what the API does
$franchiseId = 4; // Isso franchise

$query = Menu::whereHas('location', function ($q) use ($franchiseId) {
    $q->where('franchise_id', $franchiseId);
})->with(['location:id,name,branch_name,branch_code', 'categories.items']);

$menus = $query->get();

echo "Menus found: " . $menus->count() . "\n\n";

foreach ($menus as $menu) {
    echo "Menu ID: {$menu->id}\n";
    echo "Name: {$menu->name}\n";
    echo "Style: " . ($menu->style ?? 'NULL') . "\n";
    echo "Currency: " . ($menu->currency ?? 'NULL') . "\n";
    echo "Settings: " . ($menu->settings ? 'EXISTS' : 'NULL') . "\n";
    
    if ($menu->settings) {
        echo "Settings type: " . gettype($menu->settings) . "\n";
        echo "Settings value: " . json_encode($menu->settings) . "\n";
    }
    
    echo "\nJSON representation (what API returns):\n";
    echo "---\n";
    try {
        $json = $menu->toArray();
        echo "Has settings key: " . (array_key_exists('settings', $json) ? 'YES' : 'NO') . "\n";
        if (isset($json['settings'])) {
            echo "Settings in array: " . json_encode($json['settings']) . "\n";
        } else {
            echo "Settings in array: MISSING\n";
        }
    } catch (\Exception $e) {
        echo "Error converting to array: " . $e->getMessage() . "\n";
    }
    echo "---\n\n";
    
    echo "Categories: " . $menu->categories->count() . "\n";
    $itemCount = 0;
    foreach ($menu->categories as $category) {
        $itemCount += $category->items->count();
    }
    echo "Items: {$itemCount}\n\n";
}

// Also check the raw database value
echo "=== Raw Database Check ===\n";
$raw = DB::table('menus')->where('id', 8)->first();
if ($raw) {
    echo "Raw style: {$raw->style}\n";
    echo "Raw currency: {$raw->currency}\n";
    echo "Raw settings: {$raw->settings}\n";
    echo "Raw slug: {$raw->slug}\n";
    echo "Raw public_id: {$raw->public_id}\n";
}
