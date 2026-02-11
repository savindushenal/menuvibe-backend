<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Franchise;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;

echo "=== Creating Isso Franchise Menu ===\n\n";

// Find isso franchise and location
$franchise = Franchise::where('slug', 'isso')->first();
$location = Location::where('franchise_id', $franchise->id)->first();

echo "Franchise: {$franchise->name} (ID: {$franchise->id})\n";
echo "Location: {$location->name} (ID: {$location->id})\n\n";

// Check if menu already exists
$existingMenu = Menu::where('location_id', $location->id)->first();
if ($existingMenu) {
    echo "❌ Menu already exists (ID: {$existingMenu->id})!\n";
    echo "Deleting existing menu and recreating...\n\n";
    $existingMenu->categories()->each(function($cat) {
        $cat->items()->delete();
    });
    $existingMenu->categories()->delete();
    $existingMenu->delete();
}

// Create Master Menu for Isso
$menu = Menu::create([
    'name' => 'Master Menu',
    'location_id' => $location->id,
    'is_active' => true,
]);

echo "✅ Created menu: {$menu->name} (ID: {$menu->id})\n\n";

// Create categories with items
$categoriesData = [
    [
        'name' => 'Prawns',
        'sort_order' => 1,
        'items' => [
            ['name' => 'Devilled Prawns', 'description' => 'Spicy prawns tossed with peppers and onions', 'price' => 1850.00, 'image' => 'https://images.unsplash.com/photo-1580959375944-0c49cf57efeb?w=500'],
            ['name' => 'Garlic Butter Prawns', 'description' => 'Succulent prawns in garlic butter sauce', 'price' => 1650.00, 'image' => 'https://images.unsplash.com/photo-1599084993091-1cb5c0721cc6?w=500'],
            ['name' => 'Grilled Prawns', 'description' => 'Fresh grilled prawns with lemon', 'price' => 1750.00, 'image' => 'https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=500'],
        ]
    ],
    [
        'name' => 'Crab',
        'sort_order' => 2,
        'items' => [
            ['name' => 'Pepper Crab', 'description' => 'Fresh crab in black pepper sauce', 'price' => 2500.00, 'image' => 'https://images.unsplash.com/photo-1605367218541-baedaea5757e?w=500'],
            ['name' => 'Butter Garlic Crab', 'description' => 'Crab cooked in rich butter garlic sauce', 'price' => 2650.00, 'image' => 'https://images.unsplash.com/photo-1590597116819-36a898a09543?w=500'],
            ['name' => 'Chili Crab', 'description' => 'Spicy crab in tomato chili sauce', 'price' => 2750.00, 'image' => 'https://images.unsplash.com/photo-1627454820516-c45844baaadd?w=500'],
        ]
    ],
    [
        'name' => 'Fish',
        'sort_order' => 3,
        'items' => [
            ['name' => 'Grilled Fish', 'description' => 'Fresh catch of the day, grilled to perfection', 'price' => 1250.00, 'image' => 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=500'],
            ['name' => 'Fish Curry', 'description' => 'Traditional Sri Lankan fish curry', 'price' => 1150.00, 'image' => 'https://images.unsplash.com/photo-1534939561126-855b8675edd7?w=500'],
            ['name' => 'Fried Fish', 'description' => 'Crispy fried fish with special seasoning', 'price' => 1200.00, 'image' => 'https://images.unsplash.com/photo-1579027989536-b7b1f875659b?w=500'],
        ]
    ],
    [
        'name' => 'Squid',
        'sort_order' => 4,
        'items' => [
            ['name' => 'Devilled Squid', 'description' => 'Tender squid in spicy devilled sauce', 'price' => 950.00, 'image' => 'https://images.unsplash.com/photo-1599084996516-e8c5a2120430?w=500'],
            ['name' => 'Fried Calamari', 'description' => 'Crispy fried calamari rings', 'price' => 850.00, 'image' => 'https://images.unsplash.com/photo-1604909052743-94e838986d24?w=500'],
            ['name' => 'Grilled Squid', 'description' => 'Char-grilled squid with herbs', 'price' => 900.00, 'image' => 'https://images.unsplash.com/photo-1580867958038-3f143e3f2379?w=500'],
        ]
    ],
    [
        'name' => 'Rice & Noodles',
        'sort_order' => 5,
        'items' => [
            ['name' => 'Seafood Fried Rice', 'description' => 'Mixed seafood fried rice', 'price' => 850.00, 'image' => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?w=500'],
            ['name' => 'Prawn Noodles', 'description' => 'Stir-fried noodles with prawns', 'price' => 750.00, 'image' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=500'],
            ['name' => 'Seafood Kottu', 'description' => 'Traditional kottu roti with mixed seafood', 'price' => 950.00, 'image' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=500'],
        ]
    ],
    [
        'name' => 'Drinks',
        'sort_order' => 6,
        'items' => [
            ['name' => 'Fresh Lime Juice', 'description' => 'Refreshing lime juice', 'price' => 250.00, 'image' => 'https://images.unsplash.com/photo-1546171753-97d7676e0da6?w=500'],
            ['name' => 'King Coconut', 'description' => 'Fresh king coconut water', 'price' => 200.00, 'image' => 'https://images.unsplash.com/photo-1551024601-bec78aea704b?w=500'],
            ['name' => 'Soft Drinks', 'description' => 'Assorted soft drinks', 'price' => 150.00, 'image' => 'https://images.unsplash.com/photo-1546173159-315724a31696?w=500'],
        ]
    ],
];

foreach ($categoriesData as $categoryData) {
    $category = MenuCategory::create([
        'menu_id' => $menu->id,
        'name' => $categoryData['name'],
        'description' => null,
        'sort_order' => $categoryData['sort_order'],
        'is_active' => true,
    ]);
    
    echo "  ✅ Category: {$category->name} (ID: {$category->id})\n";
    
    foreach ($categoryData['items'] as $index => $itemData) {
        $item = MenuItem::create([
            'menu_id' => $menu->id,
            'category_id' => $category->id,
            'name' => $itemData['name'],
            'description' => $itemData['description'],
            'price' => $itemData['price'],
            'image_url' => $itemData['image'],
            'is_available' => true,
            'sort_order' => $index + 1,
        ]);
        
        echo "     - {$item->name} (Rs. {$item->price})\n";
    }
}

// Summary
echo "\n=== Menu Created Successfully! ===\n";
$totalCategories = MenuCategory::where('menu_id', $menu->id)->count();
$totalItems = MenuItem::whereHas('category', function($q) use ($menu) {
    $q->where('menu_id', $menu->id);
})->count();

echo "Menu ID: {$menu->id}\n";
echo "Total Categories: {$totalCategories}\n";
echo "Total Items: {$totalItems}\n";

echo "\n✅ Isso franchise now has a complete menu!\n";
echo "   View it at: https://app.menuvire.com/isso/dashboard\n";
echo "   Public page: https://app.menuvire.com/[endpoint-code]\n";
