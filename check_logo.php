<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Migrations that exist on DB but aren't tracked — mark them to avoid re-run errors
$alreadyOnDb = [
    '2026_02_08_000001_create_qr_scan_sessions_table',
    '2026_02_10_121833_update_isso_franchise_template_type',
    '2026_02_12_000001_consolidate_franchise_branches_into_locations',
    '2026_02_12_000002_fix_duplicate_columns',
    '2026_02_23_133848_add_customizations_to_menu_items_tables',
];

$maxBatch = DB::table('migrations')->max('batch') ?? 0;

foreach ($alreadyOnDb as $m) {
    if (!DB::table('migrations')->where('migration', $m)->exists()) {
        DB::table('migrations')->insert(['migration' => $m, 'batch' => $maxBatch]);
        echo "Marked: $m\n";
    } else {
        echo "Already tracked: $m\n";
    }
}
echo "Done marking. Run: php artisan migrate\n";
