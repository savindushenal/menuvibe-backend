<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$logoUrl = 'https://api.menuvire.com/api/logos/isso_logo.png';

// Find isso franchise
$franchise = DB::table('franchises')
    ->where('slug', 'isso')
    ->orWhere('name', 'LIKE', '%isso%')
    ->first();

if (!$franchise) {
    echo "ERROR: Isso franchise not found!" . PHP_EOL;
    $all = DB::table('franchises')->select('id', 'name', 'slug')->get();
    foreach ($all as $f) echo "  {$f->id}: {$f->name} ({$f->slug})" . PHP_EOL;
    exit(1);
}

echo "Found: ID={$franchise->id} | {$franchise->name} | current logo: " . ($franchise->logo_url ?? 'NULL') . PHP_EOL;

// Update logo_url
DB::table('franchises')
    ->where('id', $franchise->id)
    ->update(['logo_url' => $logoUrl]);

// Also update design_tokens brand.logo
$dt = json_decode($franchise->design_tokens ?? '{}', true) ?: [];
$dt['brand']['logo'] = $logoUrl;
DB::table('franchises')
    ->where('id', $franchise->id)
    ->update(['design_tokens' => json_encode($dt)]);

// Verify
$updated = DB::table('franchises')->where('id', $franchise->id)->first();
echo "Updated logo_url: " . $updated->logo_url . PHP_EOL;
$dtCheck = json_decode($updated->design_tokens, true);
echo "Updated design_tokens.brand.logo: " . ($dtCheck['brand']['logo'] ?? 'NULL') . PHP_EOL;
echo "Done!" . PHP_EOL;
