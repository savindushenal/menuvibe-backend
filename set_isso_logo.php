<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Franchise;

echo "=== Setting Isso Logo ===\n\n";

$isso = Franchise::where('slug', 'isso')->first();

if (!$isso) {
    echo "❌ Isso franchise not found!\n";
    exit(1);
}

echo "Current Configuration:\n";
echo "  Franchise: {$isso->name}\n";
echo "  Logo URL (column): " . ($isso->logo_url ?? 'NULL') . "\n";
echo "  Design Tokens Logo: " . ($isso->design_tokens['brand']['logo'] ?? 'NULL') . "\n\n";

// Set logo URL in design tokens
$designTokens = $isso->design_tokens ?? [];
if (!isset($designTokens['brand'])) {
    $designTokens['brand'] = [];
}
$designTokens['brand']['logo'] = 'https://app.menuvire.com/isso-logo.png';

// Also set logo_url column
$isso->logo_url = 'https://app.menuvire.com/isso-logo.png';
$isso->design_tokens = $designTokens;
$isso->save();

echo "✅ Logo updated!\n\n";

echo "New Configuration:\n";
$isso->refresh();
echo "  Logo URL (column): {$isso->logo_url}\n";
echo "  Design Tokens Logo: {$isso->design_tokens['brand']['logo']}\n\n";

echo "The logo will now display in the isso template at:\n";
echo "  - Header navigation\n";
echo "  - All isso franchise locations\n";
