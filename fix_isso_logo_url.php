<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Franchise;

echo "=== Fixing Isso Logo URL for Production ===\n\n";

$isso = Franchise::where('slug', 'isso')->first();

if (!$isso) {
    echo "❌ Isso franchise not found!\n";
    exit(1);
}

// Check if logo file exists in storage
$logoPath = storage_path('app/public/logos/isso_logo.png');
if (!file_exists($logoPath)) {
    echo "❌ Logo file not found at: $logoPath\n";
    echo "Run: php upload_isso_logo.php first\n";
    exit(1);
}

echo "✅ Logo file exists: $logoPath\n";
echo "   Size: " . number_format(filesize($logoPath) / 1024, 2) . " KB\n\n";

// Get the correct API URL from environment
$apiUrl = rtrim(config('app.url'), '/');
echo "Current APP_URL: $apiUrl\n\n";

// Update to use production API URL
$filename = 'isso_logo.png';
$logoUrl = $apiUrl . '/api/logos/' . $filename;

echo "New Logo URL: $logoUrl\n\n";

// Update database
$isso->logo_url = $logoUrl;

// Update design tokens
$designTokens = $isso->design_tokens ?? [];
if (!isset($designTokens['brand'])) {
    $designTokens['brand'] = [];
}
$designTokens['brand']['logo'] = $logoUrl;
$isso->design_tokens = $designTokens;

$isso->save();

echo "✅ Database updated!\n";
echo "   franchises.logo_url: {$isso->logo_url}\n";
echo "   design_tokens.brand.logo: {$isso->design_tokens['brand']['logo']}\n\n";

echo "✅ Logo Configuration Complete!\n";
echo "   Backend Storage: $logoPath\n";
echo "   Public API URL: $logoUrl\n";
echo "   Accessible at: {$apiUrl}/api/logos/{$filename}\n";
