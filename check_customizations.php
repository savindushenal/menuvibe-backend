<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Items with Customizations ===\n\n";

// Check master menu items
$masterItems = DB::table('master_menu_items')
    ->whereNotNull('customizations')
    ->get(['id', 'name', 'customizations']);

echo "Master Menu Items with customizations:\n";
if ($masterItems->isEmpty()) {
    echo "  No items found with customizations\n";
} else {
    foreach ($masterItems as $item) {
        echo "  - Item #{$item->id}: {$item->name}\n";
        $customizations = json_decode($item->customizations, true);
        if (is_array($customizations)) {
            echo "    Sections: " . count($customizations) . "\n";
            foreach ($customizations as $section) {
                echo "      • {$section['name']} (" . count($section['options'] ?? []) . " options)\n";
            }
        } else {
            echo "    Customizations: {$item->customizations}\n";
        }
        echo "\n";
    }
}

// Check branch menu items too
echo "\nBranch Menu Items with customizations:\n";
$branchItems = DB::table('menu_items')
    ->whereNotNull('customizations')
    ->get(['id', 'name', 'customizations']);

if ($branchItems->isEmpty()) {
    echo "  No branch items found with customizations\n";
} else {
    foreach ($branchItems as $item) {
        echo "  - Item #{$item->id}: {$item->name}\n";
        $customizations = json_decode($item->customizations, true);
        if (is_array($customizations)) {
            echo "    Sections: " . count($customizations) . "\n";
        }
        echo "\n";
    }
}
