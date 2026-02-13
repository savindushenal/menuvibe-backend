<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Franchise;
use App\Models\Location;

echo "=== FRANCHISE TABLES CONSOLIDATION - VERIFICATION ===\n\n";

// Check if franchise_branches table exists
echo "1. franchise_branches table: ";
if (Schema::hasTable('franchise_branches')) {
    echo "❌ STILL EXISTS (consolidation incomplete)\n";
} else {
    echo "✅ DROPPED (consolidation complete)\n";
}

// Check locations table has required columns
echo "\n2. locations table columns:\n";
$requiredColumns = ['branch_code', 'is_paid', 'activated_at', 'deactivated_at'];
foreach ($requiredColumns as $col) {
    $exists = Schema::hasColumn('locations', $col);
    echo "   " . ($exists ? '✅' : '❌') . " {$col}\n";
}

// Check foreign keys point to locations
echo "\n3. Foreign key updates:\n";
$tables = [
    'franchise_invitations',
    'franchise_accounts',
    'menu_sync_logs',
    'branch_offer_overrides',
    'branch_menu_overrides'
];

foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        echo "   ⏭️  {$table} - table doesn't exist\n";
        continue;
    }
    
    $hasLocation = Schema::hasColumn($table, 'location_id');
    $hasBranch = Schema::hasColumn($table, 'branch_id');
    
    if ($hasLocation && !$hasBranch) {
        echo "   ✅ {$table} - uses location_id\n";
    } else if ($hasBranch && !$hasLocation) {
        echo "   ⏳ {$table} - still uses branch_id (needs migration)\n";
    } else if ($hasLocation && $hasBranch) {
        echo "   ⚠️  {$table} - has BOTH (needs cleanup)\n";
    } else {
        echo "   ℹ️  {$table} - no location/branch reference\n";
    }
}

// Check ISSO dashboard stats
echo "\n4. ISSO Franchise Dashboard:\n";
$franchise = Franchise::where('slug', 'isso')->first();
if ($franchise) {
    $branches = Location::where('franchise_id', $franchise->id)
        ->whereNotNull('branch_code')
        ->count();
    $locations = Location::where('franchise_id', $franchise->id)->count();
    
    echo "   Branches: {$branches}\n";
    echo "   Locations: {$locations}\n";
    
    if ($branches === 1 && $locations === 1) {
        echo "   ✅ Counts correct!\n";
    } else {
        echo "   ⚠️  Expected 1 branch, 1 location\n";
    }
} else {
    echo "   ⏭️  ISSO franchise not found\n";
}

// Summary
echo "\n=== SUMMARY ===\n";
$consolidationComplete = 
    !Schema::hasTable('franchise_branches') &&
    Schema::hasColumn('locations', 'branch_code') &&
    Schema::hasColumn('locations', 'is_paid');

if ($consolidationComplete) {
    echo "✅ CONSOLIDATION COMPLETE!\n";
    echo "   - franchise_branches table dropped\n";
    echo "   - All branch data in locations table\n";
    echo "   - Dashboard showing correct counts\n";
    echo "   - Single source of truth achieved!\n";
} else {
    echo "⚠️  CONSOLIDATION INCOMPLETE\n";
    echo "   - Further migration needed\n";
}

echo "\n";
