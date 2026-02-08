<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Isso Menu Status\n";
echo "================\n\n";

// Check templates
$templates = App\Models\MenuTemplate::where('franchise_id', 4)->get();
echo "Templates: " . $templates->count() . "\n";
foreach ($templates as $t) {
    echo "  - {$t->name} (ID: {$t->id})\n";
    echo "    Settings: " . json_encode($t->settings) . "\n";
}
echo "\n";

// Check categories
$categories = App\Models\MenuTemplateCategory::whereIn(
    'template_id', 
    $templates->pluck('id')
)->get();
echo "Categories: " . $categories->count() . "\n";
foreach ($categories as $c) {
    echo "  - {$c->name}\n";
}
echo "\n";

// Check items
$items = App\Models\MenuTemplateItem::whereIn(
    'template_id',
    $templates->pluck('id')
)->get();
echo "Items: " . $items->count() . "\n";
foreach ($items as $item) {
    echo "  - {$item->name} (LKR {$item->price})\n";
}
