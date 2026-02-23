<?php

// Quick endpoint to fix duplicate versions - access via browser
// Delete this file after running once!

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain');

echo "=== Fixing Duplicate Menu Versions ===\n\n";

try {
    // Step 1: Find duplicates
    echo "Step 1: Checking for duplicates...\n";
    $duplicates = DB::select("
        SELECT 
            master_menu_id, 
            version_number, 
            COUNT(*) as count
        FROM master_menu_versions
        GROUP BY master_menu_id, version_number
        HAVING COUNT(*) > 1
    ");
    
    if (empty($duplicates)) {
        echo "✓ No duplicates found!\n\n";
    } else {
        echo "Found " . count($duplicates) . " duplicate version(s):\n";
        foreach ($duplicates as $dup) {
            echo "  Menu {$dup->master_menu_id}, Version {$dup->version_number}: {$dup->count} copies\n";
        }
        echo "\n";
        
        // Step 2: Delete duplicates (keep oldest)
        echo "Step 2: Deleting duplicate versions...\n";
        $deleted = DB::delete("
            DELETE v1 FROM master_menu_versions v1
            INNER JOIN master_menu_versions v2 
            WHERE 
                v1.master_menu_id = v2.master_menu_id
                AND v1.version_number = v2.version_number
                AND v1.id > v2.id
        ");
        echo "✓ Deleted {$deleted} duplicate records\n\n";
    }
    
    // Step 3: Update current_version counters
    echo "Step 3: Synchronizing version counters...\n";
    $updated = DB::update("
        UPDATE master_menus m
        SET current_version = (
            SELECT COALESCE(MAX(version_number), 0)
            FROM master_menu_versions v
            WHERE v.master_menu_id = m.id
        )
    ");
    echo "✓ Updated {$updated} menu(s)\n\n";
    
    // Step 4: Verify
    echo "Step 4: Verifying fix...\n";
    $menus = DB::select("
        SELECT 
            m.id,
            m.name,
            m.current_version,
            (SELECT COALESCE(MAX(version_number), 0) FROM master_menu_versions v WHERE v.master_menu_id = m.id) as actual_max_version
        FROM master_menus m
    ");
    
    foreach ($menus as $menu) {
        $status = $menu->current_version == $menu->actual_max_version ? '✓' : '✗';
        echo "  {$status} Menu {$menu->id} ({$menu->name}): current={$menu->current_version}, actual={$menu->actual_max_version}\n";
    }
    
    echo "\n=== Fix Complete! ===\n";
    echo "\nIMPORTANT: Delete fix_versions_endpoint.php from your server now!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
