<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Updating Isso Franchise Template ===\n\n";

$franchise = DB::table('franchises')->where('slug', 'isso')->first();

echo "Current Settings:\n";
echo "  Name: {$franchise->name}\n";
echo "  Template Type: {$franchise->template_type}\n\n";

echo "Updating to 'isso' template...\n";

DB::table('franchises')
    ->where('id', $franchise->id)
    ->update([
        'template_type' => 'isso',
        'updated_at' => now(),
    ]);

$updated = DB::table('franchises')->where('slug', 'isso')->first();

echo "\n✅ Updated successfully!\n";
echo "  New Template Type: {$updated->template_type}\n\n";

echo "Now isso franchise will use the custom 'isso-seafood' template!\n";
