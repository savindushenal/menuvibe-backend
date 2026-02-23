<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Item #23 Customizations ===\n\n";

$item = DB::table('master_menu_items')
    ->where('id', 23)
    ->first(['id', 'name', 'customizations']);

if (!$item) {
    echo "Item #23 not found!\n";
} else {
    echo "Item: {$item->name} (ID: {$item->id})\n";
    echo "Customizations field: " . ($item->customizations ? "NOT NULL" : "NULL") . "\n\n";
    
    if ($item->customizations) {
        echo "Raw value:\n{$item->customizations}\n\n";
        
        $decoded = json_decode($item->customizations, true);
        if (is_array($decoded)) {
            echo "Decoded successfully:\n";
            echo "  Sections: " . count($decoded) . "\n";
            foreach ($decoded as $section) {
                echo "    • {$section['name']} - " . count($section['options'] ?? []) . " options\n";
            }
        } else {
            echo "Failed to decode JSON\n";
        }
    } else {
        echo "No customizations set for this item\n";
    }
}
