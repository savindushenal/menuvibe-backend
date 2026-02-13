<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking foreign keys referencing franchise_branches:\n\n";

$fks = DB::select("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME = 'franchise_branches'
");

if (count($fks) > 0) {
    echo "⚠️  Found foreign keys pointing TO franchise_branches:\n";
    foreach ($fks as $fk) {
        echo "  - {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} ({$fk->CONSTRAINT_NAME})\n";
    }
    echo "\nCannot drop franchise_branches until these are removed!\n";
} else {
    echo "✅ No foreign keys pointing to franchise_branches\n";
    echo "Safe to drop the table.\n";
}

echo "\n";
