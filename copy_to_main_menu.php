<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$targetTemplateId = 1; // Main Menu
$sourceTemplateId = 3; // "me" template that has data

echo "Copying data from template {$sourceTemplateId} to template {$targetTemplateId}...\n\n";

// Get categories from source
$sourceCategories = DB::table('menu_template_categories')
    ->where('template_id', $sourceTemplateId)
    ->get();

$categoryMapping = []; // old_id => new_id

foreach ($sourceCategories as $cat) {
    // Check if category already exists in target
    $exists = DB::table('menu_template_categories')
        ->where('template_id', $targetTemplateId)
        ->where('name', $cat->name)
        ->first();
    
    if ($exists) {
        echo "Category '{$cat->name}' already exists in target template\n";
        $categoryMapping[$cat->id] = $exists->id;
        continue;
    }
    
    // Create new category
    $newCatId = DB::table('menu_template_categories')->insertGetId([
        'template_id' => $targetTemplateId,
        'name' => $cat->name,
        'slug' => Str::slug($cat->name) . '-' . Str::random(4),
        'description' => $cat->description,
        'background_color' => $cat->background_color,
        'text_color' => $cat->text_color,
        'sort_order' => $cat->sort_order,
        'is_active' => $cat->is_active,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "Created category '{$cat->name}' (new ID: {$newCatId})\n";
    $categoryMapping[$cat->id] = $newCatId;
}

echo "\n";

// Copy items
$sourceItems = DB::table('menu_template_items')
    ->where('template_id', $sourceTemplateId)
    ->get();

$itemsCopied = 0;
foreach ($sourceItems as $item) {
    if (!isset($categoryMapping[$item->category_id])) {
        echo "Skipping item '{$item->name}' - no category mapping\n";
        continue;
    }
    
    $newCatId = $categoryMapping[$item->category_id];
    
    // Check if item already exists
    $exists = DB::table('menu_template_items')
        ->where('template_id', $targetTemplateId)
        ->where('category_id', $newCatId)
        ->where('name', $item->name)
        ->first();
    
    if ($exists) {
        echo "Item '{$item->name}' already exists\n";
        continue;
    }
    
    DB::table('menu_template_items')->insert([
        'template_id' => $targetTemplateId,
        'category_id' => $newCatId,
        'name' => $item->name,
        'slug' => Str::slug($item->name) . '-' . Str::random(4),
        'description' => $item->description,
        'price' => $item->price,
        'compare_at_price' => $item->compare_at_price,
        'image_url' => $item->image_url,
        'is_available' => $item->is_available ?? 1,
        'is_featured' => $item->is_featured ?? 0,
        'sort_order' => $item->sort_order ?? 0,
        'allergens' => $item->allergens,
        'dietary_info' => $item->dietary_info,
        'is_spicy' => $item->is_spicy ?? 0,
        'spice_level' => $item->spice_level,
        'preparation_time' => $item->preparation_time,
        'variations' => $item->variations,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "Created item '{$item->name}'\n";
    $itemsCopied++;
}

echo "\n✅ Done! Copied {$itemsCopied} items to Main Menu template.\n";

// Verify
$finalCount = DB::table('menu_template_items')
    ->where('template_id', $targetTemplateId)
    ->count();
echo "Main Menu now has {$finalCount} items.\n";
