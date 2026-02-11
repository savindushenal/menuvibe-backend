<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Search Feature Settings ===\n\n";

$menu = DB::table('menus')->where('id', 8)->first();

echo "Menu ID: {$menu->id}\n";
echo "Menu Name: {$menu->name}\n\n";

echo "Settings JSON:\n";
$settings = json_decode($menu->settings, true);
echo json_encode($settings, JSON_PRETTY_PRINT) . "\n\n";

echo "Search Status:\n";
if (isset($settings['allow_search']) && $settings['allow_search'] === true) {
    echo "  ✅ ENABLED - Search bar will show on public menu\n";
} else {
    echo "  ❌ DISABLED - Search bar hidden\n";
}

echo "\n=== How Search Works ===\n";
echo "1. Database: 'menus' table → 'settings' column → 'allow_search' field\n";
echo "2. Frontend template checks: data.menu?.settings?.allow_search\n";
echo "3. If TRUE: Search bar appears at top of menu\n";
echo "4. If FALSE: Search bar is hidden\n\n";

echo "Current value: " . ($settings['allow_search'] ? 'TRUE (enabled)' : 'FALSE (disabled)') . "\n";
