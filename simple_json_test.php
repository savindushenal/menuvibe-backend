<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;

echo "=== Simple JSON Test ===\n\n";

// Get the menu exactly how the API does
$menu = Menu::with(['location:id,name,branch_name,branch_code', 'categories.items'])
    ->find(8);

if (!$menu) {
    echo "Menu not found!\n";
    exit(1);
}

echo "Menu loaded successfully\n";
echo "Settings on model: " . ($menu->settings ? 'EXISTS' : 'NULL') . "\n\n";

// Test JSON encoding

echo "JSON encode (what API returns):\n";
echo "---\n";
$json = json_encode($menu, JSON_PRETTY_PRINT);
echo $json;
echo "\n---\n\n";

// Check if settings is in the JSON
$data = json_decode($json, true);
echo "Has settings in JSON: " . (isset($data['settings']) ? 'YES' : 'NO') . "\n";
if (isset($data['settings'])) {
    echo "Settings value: " . json_encode($data['settings']) . "\n";
} else {
    echo "Settings value: NULL or MISSING\n";
}
