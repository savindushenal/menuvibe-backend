<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Menu Items Table Structure ===\n\n";

$columns = DB::select("DESCRIBE menu_items");

foreach ($columns as $column) {
    echo "{$column->Field} - {$column->Type}" . ($column->Null === 'YES' ? ' (nullable)' : ' (required)') . "\n";
}

echo "\n=== Check for customizations field ===\n";
$hasCustomizations = Schema::hasColumn('menu_items', 'customizations');
echo "Has customizations column: " . ($hasCustomizations ? 'YES ✅' : 'NO ❌') . "\n";

if (!$hasCustomizations) {
    echo "\n⚠️  customizations field is MISSING from menu_items table\n";
    echo "   This field is needed to store item customizations data\n";
}
