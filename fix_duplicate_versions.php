<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking for duplicate menu versions...\n\n";

// Find all master menus with version issues
$menus = DB::table('master_menus')->select('id', 'current_version')->get();

foreach ($menus as $menu) {
    echo "Checking Menu ID: {$menu->id}, Current Version: {$menu->current_version}\n";
    
    // Get all versions for this menu
    $versions = DB::table('master_menu_versions')
        ->where('master_menu_id', $menu->id)
        ->orderBy('version_number')
        ->get();
    
    echo "  Found " . count($versions) . " version(s)\n";
    
    // Find the actual max version number
    $maxVersion = $versions->max('version_number') ?? 0;
    
    // Check for duplicates
    $versionNumbers = $versions->pluck('version_number')->toArray();
    $uniqueVersions = array_unique($versionNumbers);
    
    if (count($versionNumbers) !== count($uniqueVersions)) {
        echo "  ⚠️  DUPLICATES FOUND!\n";
        
        // Get duplicate version numbers
        $duplicates = array_diff_assoc($versionNumbers, $uniqueVersions);
        foreach ($duplicates as $dup) {
            echo "    Duplicate version: {$dup}\n";
            
            // Keep the first one, delete others
            $duplicateVersions = DB::table('master_menu_versions')
                ->where('master_menu_id', $menu->id)
                ->where('version_number', $dup)
                ->orderBy('id')
                ->get();
            
            // Skip first, delete rest
            $first = true;
            foreach ($duplicateVersions as $ver) {
                if ($first) {
                    echo "      Keeping version ID: {$ver->id}\n";
                    $first = false;
                } else {
                    echo "      Deleting duplicate ID: {$ver->id}\n";
                    DB::table('master_menu_versions')->where('id', $ver->id)->delete();
                }
            }
        }
    }
    
    // Update the current_version if needed
    if ($menu->current_version != $maxVersion) {
        echo "  Updating current_version from {$menu->current_version} to {$maxVersion}\n";
        DB::table('master_menus')
            ->where('id', $menu->id)
            ->update(['current_version' => $maxVersion]);
    }
    
    echo "\n";
}

echo "✓ Version cleanup complete!\n";
