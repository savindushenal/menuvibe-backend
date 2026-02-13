<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CHECKING TABLE STRUCTURE ===\n\n";

// Check locations table
echo "=== locations table ===\n";
$locationsColumns = DB::select("SHOW COLUMNS FROM locations");
echo "Columns:\n";
foreach ($locationsColumns as $col) {
    if (in_array($col->Field, ['branch_code', 'is_paid', 'activated_at', 'deactivated_at', 'franchise_id'])) {
        echo "  ✓ {$col->Field} ({$col->Type})\n";
    }
}

// Check if franchise_branches exists
echo "\n=== franchise_branches table ===\n";
if (Schema::hasTable('franchise_branches')) {
    echo "✓ Table exists\n";
    $count = DB::table('franchise_branches')->count();
    echo "  Records: {$count}\n";
} else {
    echo "❌ Table does NOT exist (already dropped)\n";
}

// Check franchise_invitations
echo "\n=== franchise_invitations table ===\n";
if (Schema::hasTable('franchise_invitations')) {
    $columns = DB::select("SHOW COLUMNS FROM franchise_invitations");
    $hasLocation = false;
    $hasBranch = false;
    foreach ($columns as $col) {
        if ($col->Field === 'location_id') {
            $hasLocation = true;
            echo "  ✓ location_id exists\n";
        }
        if ($col->Field === 'branch_id') {
            $hasBranch = true;
            echo "  ✓ branch_id exists\n";
        }
    }
    
    if ($hasLocation && $hasBranch) {
        echo "  ⚠️  BOTH location_id AND branch_id exist! Need to clean up.\n";
    } elseif ($hasLocation) {
        echo "  ✅ Already migrated (has location_id)\n";
    } elseif ($hasBranch) {
        echo "  ⏳ Needs migration (has branch_id)\n";
    }
}

// Check franchise_accounts
echo "\n=== franchise_accounts table ===\n";
if (Schema::hasTable('franchise_accounts')) {
    $columns = DB::select("SHOW COLUMNS FROM franchise_accounts");
    $hasLocation = false;
    $hasBranch = false;
    foreach ($columns as $col) {
        if ($col->Field === 'location_id') {
            $hasLocation = true;
            echo "  ✓ location_id exists\n";
        }
        if ($col->Field === 'branch_id') {
            $hasBranch = true;
            echo "  ✓ branch_id exists\n";
        }
    }
    
    if ($hasLocation && $hasBranch) {
        echo "  ⚠️  BOTH location_id AND branch_id exist! Need to clean up.\n";
    } elseif ($hasLocation) {
        echo "  ✅ Already migrated (has location_id)\n";
    } elseif ($hasBranch) {
        echo "  ⏳ Needs migration (has branch_id)\n";
    }
}

echo "\n=== RECOMMENDATION ===\n";
echo "The migration seems to be partially run.\n";
echo "Options:\n";
echo "1. Rollback this migration: php artisan migrate:rollback --step=1\n";
echo "2. Or manually fix the duplicate columns\n";

