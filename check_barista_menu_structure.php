<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking ALL Database Tables ===\n\n";

// Get all tables
$tables = DB::select('SHOW TABLES');
$dbName = env('DB_DATABASE', 'menuvibe');
$tableKey = "Tables_in_{$dbName}";

echo "All tables in database:\n";
foreach ($tables as $table) {
    $tableName = $table->$tableKey;
    echo "  - {$tableName}\n";
}

// Check specifically for master menu related tables
echo "\n=== Checking for Master Menu Tables ===\n";
$hasMasterMenus = false;
foreach ($tables as $table) {
    $tableName = $table->$tableKey;
    if (stripos($tableName, 'master') !== false) {
        echo "✅ Found: {$tableName}\n";
        $hasMasterMenus = true;
        
        // Get row count
        $count = DB::table($tableName)->count();
        echo "   Rows: {$count}\n";
        
        if ($count > 0) {
            $sample = DB::table($tableName)->limit(5)->get();
            foreach ($sample as $row) {
                echo "   - ";
                if (isset($row->id)) echo "ID: {$row->id} ";
                if (isset($row->name)) echo "Name: {$row->name} ";
                if (isset($row->franchise_id)) echo "Franchise: {$row->franchise_id} ";
                echo "\n";
            }
        }
        echo "\n";
    }
}

if (!$hasMasterMenus) {
    echo "❌ NO master menu tables found!\n\n";
}

// Check what Barista franchise has
echo "\n=== Checking Barista Franchise ===\n";
$barista = DB::table('franchises')->where('name', 'like', '%Barista%')->first();
if ($barista) {
    echo "Franchise: {$barista->name} (ID: {$barista->id})\n\n";
    
    // Check locations
    $locations = DB::table('locations')->where('franchise_id', $barista->id)->get();
    echo "Locations: " . count($locations) . "\n";
    foreach ($locations as $loc) {
        echo "  - {$loc->name} (ID: {$loc->id})\n";
    }
    
    // Check menus (location-based)
    echo "\n=== Location-based Menus (menus table) ===\n";
    foreach ($locations as $loc) {
        $menus = DB::table('menus')->where('location_id', $loc->id)->get();
        foreach ($menus as $menu) {
            echo "  Menu ID: {$menu->id} | Name: {$menu->name} | Location: {$loc->name}\n";
            
            // Check categories
            $categories = DB::table('menu_categories')->where('menu_id', $menu->id)->count();
            echo "    Categories: {$categories}\n";
            
            // Check items
            $items = DB::table('menu_items')->where('menu_id', $menu->id)->count();
            echo "    Items: {$items}\n";
        }
    }
    
    // Check if there are master menus
    if ($hasMasterMenus) {
        echo "\n=== Master Menus (master_menus table) ===\n";
        $masterMenus = DB::table('master_menus')->where('franchise_id', $barista->id)->get();
        if (count($masterMenus) > 0) {
            foreach ($masterMenus as $mm) {
                echo "  Master Menu ID: {$mm->id} | Name: {$mm->name}\n";
            }
        } else {
            echo "  No master menus found for Barista\n";
        }
    }
}
