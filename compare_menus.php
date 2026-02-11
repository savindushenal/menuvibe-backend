<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MASTER MENU (ID 2) ===\n";
$masterItems = DB::table('master_menu_items')
    ->where('master_menu_id', 2)
    ->orderBy('id')
    ->get();
echo "Total items: " . $masterItems->count() . "\n\n";

$masterCategories = DB::table('master_menu_categories')
    ->where('master_menu_id', 2)
    ->orderBy('sort_order')
    ->get();

foreach ($masterCategories as $cat) {
    $items = DB::table('master_menu_items')
        ->where('category_id', $cat->id)
        ->get();
    echo "{$cat->name} ({$items->count()} items):\n";
    foreach ($items as $item) {
        echo "  - {$item->name}\n";
    }
    echo "\n";
}

echo "\n=== BRANCH MENU (ID 8) ===\n";
$branchItems = DB::table('menu_items')
    ->where('menu_id', 8)
    ->orderBy('id')
    ->get();
echo "Total items: " . $branchItems->count() . "\n\n";

$branchCategories = DB::table('menu_categories')
    ->where('menu_id', 8)
    ->orderBy('sort_order')
    ->get();

foreach ($branchCategories as $cat) {
    $items = DB::table('menu_items')
        ->where('category_id', $cat->id)
        ->get();
    echo "{$cat->name} ({$items->count()} items):\n";
    foreach ($items as $item) {
        echo "  - {$item->name}\n";
    }
    echo "\n";
}
