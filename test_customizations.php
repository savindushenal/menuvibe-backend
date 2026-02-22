<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MenuItem;
use App\Models\Menu;

echo "=== Testing Customizations Field ===\n\n";

// Find a menu item to test with
$menuItem = MenuItem::where('menu_id', 8)->first();

if (!$menuItem) {
    die("No menu item found in menu 8\n");
}

echo "Testing with Menu Item:\n";
echo "  ID: {$menuItem->id}\n";
echo "  Name: {$menuItem->name}\n";
echo "  Menu ID: {$menuItem->menu_id}\n\n";

// Test setting customizations
$testCustomizations = [
    [
        'id' => 'custom-1',
        'title' => 'Choose Size',
        'required' => true,
        'multi_select' => false,
        'options' => [
            ['id' => 'opt-1', 'label' => 'Small', 'price_modifier' => 0],
            ['id' => 'opt-2', 'label' => 'Medium', 'price_modifier' => 2],
            ['id' => 'opt-3', 'label' => 'Large', 'price_modifier' => 4]
        ]
    ],
    [
        'id' => 'custom-2',
        'title' => 'Add-ons',
        'required' => false,
        'multi_select' => true,
        'options' => [
            ['id' => 'opt-4', 'label' => 'Extra Cheese', 'price_modifier' => 1.5],
            ['id' => 'opt-5', 'label' => 'Bacon', 'price_modifier' => 2]
        ]
    ]
];

$menuItem->customizations = $testCustomizations;
$menuItem->save();

echo "✅ Customizations saved successfully!\n\n";

// Retrieve and verify
$retrieved = MenuItem::find($menuItem->id);
echo "Retrieved customizations:\n";
print_r($retrieved->customizations);

echo "\n=== Test Complete ===\n";
