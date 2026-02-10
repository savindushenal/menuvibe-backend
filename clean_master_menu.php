<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Cleaning Master Menu - Keeping Only Essential Items ===\n\n";

$masterMenuId = 2;

// Delete all existing items and categories
DB::table('master_menu_items')->where('master_menu_id', $masterMenuId)->delete();
DB::table('master_menu_categories')->where('master_menu_id', $masterMenuId)->delete();

echo "✓ Cleared existing items\n\n";

// Create simplified categories with only core items
$categories = [
    [
        'name' => 'Signature Prawns',
        'sort_order' => 1,
        'items' => [
            ['name' => 'Devilled Prawns', 'price' => 1850, 'description' => 'Spicy prawns tossed with peppers and onions'],
            ['name' => 'Garlic Butter Prawns', 'price' => 1650, 'description' => 'Succulent prawns in garlic butter sauce'],
        ]
    ],
    [
        'name' => 'Crab Specialties',
        'sort_order' => 2,
        'items' => [
            ['name' => 'Pepper Crab', 'price' => 2500, 'description' => 'Fresh crab in black pepper sauce'],
            ['name' => 'Chili Crab', 'price' => 2650, 'description' => 'Spicy chili crab with aromatic spices'],
        ]
    ],
    [
        'name' => 'Fresh Fish',
        'sort_order' => 3,
        'items' => [
            ['name' => 'Grilled Fish', 'price' => 950, 'description' => 'Fresh catch of the day, grilled to perfection'],
        ]
    ],
    [
        'name' => 'Beverages',
        'sort_order' => 4,
        'items' => [
            ['name' => 'Fresh Lime Juice', 'price' => 200, 'description' => 'Freshly squeezed lime juice'],
            ['name' => 'King Coconut', 'price' => 150, 'description' => 'Fresh king coconut water'],
        ]
    ],
];

$totalItems = 0;

foreach ($categories as $categoryData) {
    // Create category
    $categoryId = DB::table('master_menu_categories')->insertGetId([
        'master_menu_id' => $masterMenuId,
        'name' => $categoryData['name'],
        'slug' => strtolower(str_replace(' ', '-', $categoryData['name'])),
        'description' => null,
        'image_url' => null,
        'icon' => null,
        'sort_order' => $categoryData['sort_order'],
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✓ Category: {$categoryData['name']}\n";
    
    // Add items
    foreach ($categoryData['items'] as $index => $itemData) {
        DB::table('master_menu_items')->insert([
            'master_menu_id' => $masterMenuId,
            'category_id' => $categoryId,
            'name' => $itemData['name'],
            'slug' => strtolower(str_replace(' ', '-', $itemData['name'])),
            'description' => $itemData['description'],
            'price' => $itemData['price'],
            'compare_at_price' => null,
            'currency' => 'LKR',
            'image_url' => 'https://images.unsplash.com/photo-1580959375944-0c49cf57efeb?w=500',
            'is_available' => true,
            'is_featured' => true,
            'sort_order' => $index + 1,
            'allergens' => null,
            'dietary_info' => null,
            'preparation_time' => null,
            'is_spicy' => false,
            'spice_level' => null,
            'variations' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "  • {$itemData['name']} (LKR {$itemData['price']})\n";
        $totalItems++;
    }
    echo "\n";
}

echo "✅ Master menu cleaned up!\n";
echo "   Categories: " . count($categories) . "\n";
echo "   Core Items: $totalItems (instead of 18)\n";
echo "\n";
echo "These are signature items to sync across all branches.\n";
echo "Branches can add their own local items separately.\n";
