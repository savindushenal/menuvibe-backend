<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Finding Isso Endpoint ===\n\n";

$endpoints = DB::table('menu_endpoints')
    ->where('franchise_id', 4)
    ->get();

if ($endpoints->isEmpty()) {
    echo "❌ No endpoints found for Isso franchise!\n";
    echo "Creating one...\n\n";
    
    $shortCode = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $endpointId = DB::table('menu_endpoints')->insertGetId([
        'franchise_id' => 4,
        'location_id' => 4,
        'identifier' => 'Table-01',
        'short_code' => $shortCode,
        'qr_code_url' => "https://api.qrserver.com/v1/create-qr-code/?data=https://app.menuvire.com/m/{$shortCode}",
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✅ Created endpoint!\n";
    echo "   ID: {$endpointId}\n";
    echo "   Short Code: {$shortCode}\n";
    echo "   URL: https://app.menuvire.com/m/{$shortCode}\n";
} else {
    echo "✅ Found " . $endpoints->count() . " endpoint(s):\n\n";
    foreach ($endpoints as $endpoint) {
        echo "  ID: {$endpoint->id}\n";
        echo "  Identifier: {$endpoint->identifier}\n";
        echo "  Short Code: {$endpoint->short_code}\n";
        echo "  Location ID: {$endpoint->location_id}\n";
        echo "  Active: " . ($endpoint->is_active ? 'Yes' : 'No') . "\n";
        echo "  URL: https://app.menuvire.com/m/{$endpoint->short_code}\n";
        echo "\n";
    }
}
