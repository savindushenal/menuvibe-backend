<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== COMPLETE TABLE STRUCTURE CHECK ===\n\n";

$tables = [
    'locations',
    'franchise_branches',
    'franchise_invitations',
    'franchise_accounts',
    'menu_sync_logs',
    'branch_offer_overrides',
    'branch_menu_overrides'
];

foreach ($tables as $table) {
    echo "=== {$table} ===\n";
    
    if (!Schema::hasTable($table)) {
        echo "  ❌ Table does NOT exist\n\n";
        continue;
    }
    
    $columns = DB::select("SHOW COLUMNS FROM {$table}");
    
    $hasBranchId = false;
    $hasLocationId = false;
    
    foreach ($columns as $col) {
        if ($col->Field === 'branch_id') {
            $hasBranchId = true;
            echo "  ✓ branch_id ({$col->Type})\n";
        }
        if ($col->Field === 'location_id') {
            $hasLocationId = true;
            echo "  ✓ location_id ({$col->Type})\n";
        }
        
        // For locations, also show franchise columns
        if ($table === 'locations' && in_array($col->Field, ['franchise_id', 'branch_code', 'is_paid', 'activated_at', 'deactivated_at'])) {
            echo "  ✓ {$col->Field} ({$col->Type})\n";
        }
    }
    
    if (!$hasBranchId && !$hasLocationId && $table !== 'locations' && $table !== 'franchise_branches') {
        echo "  ℹ️  No branch_id or location_id column\n";
    }
    
    $count = DB::table($table)->count();
    echo "  Records: {$count}\n";
    
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Next steps:\n";

if (!Schema::hasTable('franchise_branches')) {
    echo "✅ franchise_branches already dropped!\n";
} else {
    echo "⏳ Need to drop franchise_branches table\n";
}

echo "\n";

