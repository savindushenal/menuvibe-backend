<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Isso Franchise Branding ===\n\n";

$franchise = DB::table('franchises')->where('slug', 'isso')->first();

echo "Franchise: {$franchise->name}\n";
echo "Logo URL: " . ($franchise->logo_url ?? 'NULL') . "\n";
echo "Template Type: {$franchise->template_type}\n";
echo "\nDesign Tokens:\n";
if ($franchise->design_tokens) {
    $tokens = json_decode($franchise->design_tokens, true);
    echo json_encode($tokens, JSON_PRETTY_PRINT);
} else {
    echo "NULL - No design tokens set!\n";
}
