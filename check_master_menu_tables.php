<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Database Tables Check ===\n\n";

// Check if master_menus table exists
$tables = DB::select('SHOW TABLES');
$tableNames = array_map(function($table) {
    $key = 'Tables_in_' . env('DB_DATABASE', 'menuvibe');
    return $table->$key ?? null;
}, $tables);

echo "Looking for master menu tables...\n";
$masterMenuTables = array_filter($tableNames, function($name) {
    return strpos($name, 'master') !== false;
});

if (empty($masterMenuTables)) {
    echo "❌ No master menu tables found!\n\n";
} else {
    echo "✅ Master menu tables found:\n";
    foreach ($masterMenuTables as $table) {
        echo "  - {$table}\n";
        
        // Show table structure
        $columns = DB::select("DESCRIBE {$table}");
        foreach ($columns as $col) {
            echo "    * {$col->Field} ({$col->Type})\n";
        }
        echo "\n";
    }
}

echo "\nAll tables in database:\n";
foreach ($tableNames as $table) {
    echo "  - {$table}\n";
}
