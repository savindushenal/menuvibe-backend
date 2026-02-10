<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Creating Master Menu for Isso Franchise ===\n\n";

$franchiseId = 4; // Isso franchise ID

// Check existing master menus
$existing = DB::table('master_menus')->where('franchise_id', $franchiseId)->count();
echo "Existing master menus: $existing\n\n";

if ($existing > 0) {
    echo "Isso already has master menus. No need to create.\n";
    exit(0);
}

// Create master menu
$masterMenuId = DB::table('master_menus')->insertGetId([
    'franchise_id' => $franchiseId,
    'name' => 'Master Menu',
    'slug' => 'master-menu',
    'description' => 'Isso Seafood Master Menu - syncs to all locations',
    'currency' => 'LKR',
    'is_active' => true,
    'is_default' => true,
    'sort_order' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "✅ Created Master Menu (ID: $masterMenuId)\n\n";

// Copy categories from regular menu (Menu ID 8) to master menu
$categories = DB::table('menu_categories')->where('menu_id', 8)->get();

echo "Copying " . $categories->count() . " categories...\n";

foreach ($categories as $category) {
    $masterCategoryId = DB::table('master_menu_categories')->insertGetId([
        'master_menu_id' => $masterMenuId,
        'name' => $category->name,
        'slug' => $category->slug,
        'description' => $category->description,
        'image_url' => $category->image_url,
        'icon' => $category->icon,
        'sort_order' => $category->sort_order,
        'is_active' => $category->is_active,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "  ✓ Category: {$category->name} (ID: $masterCategoryId)\n";
    
    // Copy items for this category
    $items = DB::table('menu_items')
        ->where('menu_id', 8)
        ->where('category_id', $category->id)
        ->get();
    
    foreach ($items as $item) {
        $masterItemId = DB::table('master_menu_items')->insertGetId([
            'master_menu_id' => $masterMenuId,
            'category_id' => $masterCategoryId,
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'price' => $item->price,
            'compare_at_price' => $item->compare_at_price,
            'currency' => $item->currency,
            'image_url' => $item->image_url,
            'is_available' => $item->is_available,
            'is_featured' => $item->is_featured,
            'sort_order' => $item->sort_order,
            'allergens' => $item->allergens,
            'dietary_info' => $item->dietary_info,
            'preparation_time' => $item->preparation_time,
            'is_spicy' => $item->is_spicy,
            'spice_level' => $item->spice_level,
            'variations' => $item->variations,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "    • Item: {$item->name} (ID: $masterItemId)\n";
    }
}

echo "\n✅ Master Menu created successfully!\n";
echo "   Menu ID: $masterMenuId\n";
echo "   Categories: " . $categories->count() . "\n";
echo "   Total Items: " . DB::table('master_menu_items')->where('master_menu_id', $masterMenuId)->count() . "\n";
