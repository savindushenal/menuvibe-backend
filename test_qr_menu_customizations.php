<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MenuEndpoint;
use App\Models\MenuItem;

echo "=== Testing QR Menu API Returns Customizations ===\n\n";

// Find an active endpoint
$endpoint = MenuEndpoint::where('is_active', true)->first();

if (!$endpoint) {
    die("No active endpoint found\n");
}

echo "Testing endpoint: {$endpoint->short_code}\n";
echo "Endpoint type: {$endpoint->type}\n\n";

// Simulate API call
$controller = new \App\Http\Controllers\PublicMenuController(
    new \App\Services\MenuResolver()
);

$request = \Illuminate\Http\Request::create('/api/m/' . $endpoint->short_code, 'GET');
$response = $controller->getMenu($request, $endpoint->short_code);
$data = json_decode($response->getContent(), true);

if (!$data['success']) {
    die("API call failed: " . $data['message'] . "\n");
}

echo "✅ API call successful\n\n";

// Check if customizations field exists in items
$hasCustomizations = false;
$itemsChecked = 0;

if (isset($data['data']['menu']['categories'])) {
    foreach ($data['data']['menu']['categories'] as $category) {
        if (isset($category['items'])) {
            foreach ($category['items'] as $item) {
                $itemsChecked++;
                if (array_key_exists('customizations', $item)) {
                    $hasCustomizations = true;
                    echo "✅ Item '{$item['name']}' has customizations field\n";
                    if ($item['customizations']) {
                        echo "   Customizations: " . json_encode($item['customizations']) . "\n";
                    }
                } else {
                    echo "❌ Item '{$item['name']}' MISSING customizations field\n";
                }
                
                // Also check variations
                if (array_key_exists('variations', $item)) {
                    if ($item['variations']) {
                        echo "✅ Item '{$item['name']}' has variations: " . count($item['variations']) . " variants\n";
                    }
                } else {
                    echo "❌ Item '{$item['name']}' MISSING variations field\n";
                }
                
                if ($itemsChecked >= 3) break 2; // Check only first 3 items
            }
        }
    }
}

echo "\n=== Summary ===\n";
echo "Items checked: {$itemsChecked}\n";
echo "Has customizations field: " . ($hasCustomizations ? 'YES ✅' : 'NO ❌') . "\n";

echo "\n=== Test Complete ===\n";
