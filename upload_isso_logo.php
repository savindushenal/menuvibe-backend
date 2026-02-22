<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Franchise;

echo "=== Uploading Isso Logo to Backend Storage ===\n\n";

$isso = Franchise::where('slug', 'isso')->first();

if (!$isso) {
    echo "❌ Isso franchise not found!\n";
    exit(1);
}

// Ensure logos directory exists
$logosDir = storage_path('app/public/logos');
if (!is_dir($logosDir)) {
    mkdir($logosDir, 0755, true);
    echo "✅ Created logos directory\n";
}

// Check current logo
echo "Current Logo URL: " . ($isso->logo_url ?? 'NULL') . "\n\n";

// Option 1: If you have a logo file, copy it here
$logoSourcePath = __DIR__ . '/isso-logo.png';
$logoDestPath = $logosDir . '/isso_logo.png';

if (file_exists($logoSourcePath)) {
    copy($logoSourcePath, $logoDestPath);
    echo "✅ Copied logo from root directory\n";
} else {
    // Option 2: Download from current URL
    $currentUrl = 'https://app.menuvire.com/isso-logo.png';
    echo "Attempting to download logo from: $currentUrl\n";
    
    $logoContent = @file_get_contents($currentUrl);
    if ($logoContent !== false) {
        file_put_contents($logoDestPath, $logoContent);
        echo "✅ Downloaded logo from external URL\n";
    } else {
        echo "⚠️  Could not download logo. Please add isso-logo.png to:\n";
        echo "   $logosDir/isso_logo.png\n";
        echo "\n";
        echo "You can upload via:\n";
        echo "1. Copy logo file manually to storage/app/public/logos/\n";
        echo "2. Use the franchise settings upload feature\n";
        exit(1);
    }
}

// Verify file exists
if (file_exists($logoDestPath)) {
    $fileSize = filesize($logoDestPath);
    echo "✅ Logo file created:\n";
    echo "   Path: $logoDestPath\n";
    echo "   Size: " . number_format($fileSize / 1024, 2) . " KB\n\n";
    
    // Update database to use backend URL
    $filename = 'isso_logo.png';
    $apiUrl = config('app.url') . '/api/logos/' . $filename;
    
    $isso->logo_url = $apiUrl;
    
    // Also update design tokens
    $designTokens = $isso->design_tokens ?? [];
    if (!isset($designTokens['brand'])) {
        $designTokens['brand'] = [];
    }
    $designTokens['brand']['logo'] = $apiUrl;
    $isso->design_tokens = $designTokens;
    
    $isso->save();
    
    echo "✅ Database updated!\n";
    echo "   Logo URL: $apiUrl\n";
    echo "   Design Tokens Logo: {$isso->design_tokens['brand']['logo']}\n\n";
    
    echo "✅ Logo is now served from backend storage!\n";
    echo "   Local path: $logoDestPath\n";
    echo "   Public URL: $apiUrl\n";
} else {
    echo "❌ Logo file was not created\n";
    exit(1);
}
