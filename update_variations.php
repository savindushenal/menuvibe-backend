<?php
/**
 * Script to update variations in menu_template_items from menu_items
 * Run: php update_variations.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Updating variations in template items...\n\n";

// Get all menu items with variations
$items = DB::table('menu_items')
    ->whereNotNull('variations')
    ->get();

echo "Found " . $items->count() . " items with variations\n\n";

$updated = 0;

foreach ($items as $item) {
    echo "Processing: {$item->name}\n";
    echo "  Variations: {$item->variations}\n";
    
    // Find matching template item by name
    $templateItem = DB::table('menu_template_items')
        ->where('name', $item->name)
        ->first();
    
    if ($templateItem) {
        DB::table('menu_template_items')
            ->where('id', $templateItem->id)
            ->update(['variations' => $item->variations]);
        echo "  ✅ Updated template item ID: {$templateItem->id}\n";
        $updated++;
    } else {
        echo "  ⚠️ No matching template item found\n";
    }
}

echo "\n✅ Done! Updated $updated template items with variations.\n";

// Show final state
echo "\nTemplate items with variations:\n";
$withVariations = DB::table('menu_template_items')
    ->whereNotNull('variations')
    ->get(['id', 'name', 'variations']);

foreach ($withVariations as $item) {
    echo "- {$item->name}: {$item->variations}\n";
}
