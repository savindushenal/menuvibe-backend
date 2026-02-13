<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "DIRECT DATABASE CHECK\n\n";

// Check if franchise_branches exists
$tables = DB::select("SHOW TABLES LIKE 'franchise_branches'");
if (count($tables) > 0) {
    echo "❌ franchise_branches: EXISTS\n";
    $count = DB::table('franchise_branches')->count();
    echo "   Records: {$count}\n";
} else {
    echo "✅ franchise_branches: DROPPED\n";
}

// Check menu_sync_logs columns
echo "\nmenu_sync_logs columns:\n";
$cols = DB::select("SHOW COLUMNS FROM menu_sync_logs WHERE Field IN ('branch_id', 'location_id')");
foreach ($cols as $col) {
    echo "  - {$col->Field}\n";
}

// Check branch_offer_overrides columns
echo "\nbranch_offer_overrides columns:\n";
$cols = DB::select("SHOW COLUMNS FROM branch_offer_overrides WHERE Field IN ('branch_id', 'location_id')");
foreach ($cols as $col) {
    echo "  - {$col->Field}\n";
}

echo "\n";
