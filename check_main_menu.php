<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Main Menu Template (ID: 1) Details ===\n";
$template = DB::table('menu_templates')->where('id', 1)->first();
echo "Template: {$template->name}\n";
echo "User ID: {$template->user_id}\n";
echo "Currency: {$template->currency}\n\n";

echo "=== Categories for Main Menu ===\n";
$categories = DB::table('menu_template_categories')
    ->where('template_id', 1)
    ->get();
    
if ($categories->isEmpty()) {
    echo "NO CATEGORIES FOUND!\n";
} else {
    foreach ($categories as $c) {
        echo "  Category: {$c->name} (ID: {$c->id})\n";
    }
}

echo "\n=== Items for Main Menu ===\n";
$items = DB::table('menu_template_items')
    ->where('template_id', 1)
    ->get();
    
if ($items->isEmpty()) {
    echo "NO ITEMS FOUND!\n";
} else {
    foreach ($items as $i) {
        echo "  Item: {$i->name} | Price: {$i->price}\n";
    }
}

echo "\n\n=== Template 'me' (ID: 3) Details ===\n";
$categories3 = DB::table('menu_template_categories')
    ->where('template_id', 3)
    ->get();

echo "Categories:\n";
foreach ($categories3 as $c) {
    echo "  {$c->name} (ID: {$c->id})\n";
    
    $items = DB::table('menu_template_items')
        ->where('category_id', $c->id)
        ->get();
    foreach ($items as $i) {
        echo "    - {$i->name} | Price: {$i->price}\n";
    }
}
