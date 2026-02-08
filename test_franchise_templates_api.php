<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Franchise;
use App\Models\MenuTemplate;
use App\Models\MenuTemplateCategory;
use App\Models\MenuTemplateItem;

echo "Testing Franchise Templates API Data\n";
echo "=====================================\n\n";

// Get Isso franchise
$isso = Franchise::find(4);
if (!$isso) {
    echo "❌ Isso franchise not found (ID: 4)\n";
    exit(1);
}

echo "Franchise: {$isso->name} (ID: {$isso->id})\n\n";

// Get templates
$templates = MenuTemplate::where('franchise_id', 4)->get();
echo "Templates Count: " . $templates->count() . "\n";

foreach ($templates as $template) {
    echo "\nTemplate ID: {$template->id}\n";
    echo "Name: {$template->name}\n";
    echo "Currency: {$template->currency}\n";
    echo "Settings: " . json_encode($template->settings, JSON_PRETTY_PRINT) . "\n";
    
    // Get categories
    $categories = MenuTemplateCategory::where('template_id', $template->id)->get();
    echo "Categories: {$categories->count()}\n";
    foreach ($categories as $category) {
        echo "  - {$category->name} (ID: {$category->id})\n";
        
        // Get items
        $items = MenuTemplateItem::where('template_id', $template->id)
            ->where('category_id', $category->id)
            ->get();
        echo "    Items: {$items->count()}\n";
        foreach ($items as $item) {
            echo "      · {$item->name} ({$item->currency} {$item->price})\n";
        }
    }
}

echo "\n✅ API data structure verified\n";
