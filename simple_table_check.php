<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Database Tables Check ===\n\n";

try {
    // Get all tables
    $tables = DB::select('SHOW TABLES');
    $dbName = env('DB_DATABASE', 'menuvibe');
    $tableKey = "Tables_in_{$dbName}";
    
    echo "Searching for 'master' tables:\n";
    $found = false;
    foreach ($tables as $table) {
        $tableName = $table->$tableKey;
        if (stripos($tableName, 'master') !== false) {
            echo "  ✅ {$tableName}\n";
            $found = true;
        }
    }
    
    if (!$found) {
        echo "  ❌ NO 'master' tables exist!\n";
    }
    
    echo "\n=== Barista Coffee Check ===\n";
    $barista = DB::table('franchises')->where('name', 'like', '%Barista%')->first();
    
    if ($barista) {
        echo "Franchise: {$barista->name} (ID: {$barista->id})\n\n";
        
        // Check location menus
        $location = DB::table('locations')->where('franchise_id', $barista->id)->first();
        if ($location) {
            echo "Location: {$location->name} (ID: {$location->id})\n";
            
            $menus = DB::table('menus')->where('location_id', $location->id)->get();
            echo "Menus in 'menus' table: " . count($menus) . "\n";
            foreach ($menus as $menu) {
                echo "  - Menu ID {$menu->id}: {$menu->name}\n";
                $cats = DB::table('menu_categories')->where('menu_id', $menu->id)->count();
                $items = DB::table('menu_items')->where('menu_id', $menu->id)->count();
                echo "    Categories: {$cats}, Items: {$items}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
