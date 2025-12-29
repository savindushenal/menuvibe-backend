<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Fixing Barista Franchise Status ===\n\n";

$result = DB::table('franchises')
    ->where('slug', 'barista')
    ->update(['status' => 'active']);

echo "Updated $result franchise(s)\n";

// Verify
$franchise = DB::table('franchises')->where('slug', 'barista')->first();
echo "\nBarista franchise:\n";
echo "  ID: {$franchise->id}\n";
echo "  Name: {$franchise->name}\n";
echo "  Status: {$franchise->status}\n";
echo "  Template Type: {$franchise->template_type}\n";

echo "\n✓ Done!\n";
