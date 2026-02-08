<?php

use App\Models\Franchise;
use App\Models\MenuTemplate;
use App\Models\MenuTemplateCategory;
use App\Models\MenuTemplateItem;

echo "Testing Franchise Templates API Data\n";
echo "======================================\n\n";

$template = MenuTemplate::where('franchise_id', 4)->first();

if (!$template) {
    echo "❌ No template found for franchise ID 4\n";
    return;
}

echo "✅ Template found!\n";
echo "ID: {$template->id}\n";
echo "Name: {$template->name}\n";
echo "Currency: {$template->currency}\n";
echo "Settings: " . json_encode($template->settings) . "\n\n";

$categories = MenuTemplateCategory::where('template_id', $template->id)->get();
echo "Categories: {$categories->count()}\n";
foreach ($categories as $cat) {
    $itemCount = MenuTemplateItem::where('category_id', $cat->id)->count();
    echo "  - {$cat->name} ({$itemCount} items)\n";
}

$totalItems = MenuTemplateItem::where('template_id', $template->id)->count();
echo "\nTotal Items: {$totalItems}\n";
