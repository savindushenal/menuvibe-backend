<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$franchise = App\Models\Franchise::where('slug', 'isso')->first();

if (!$franchise) {
    echo "❌ Isso franchise not found!\n";
    exit(1);
}

echo "ISSO FRANCHISE DATA\n";
echo "===================\n\n";
echo "ID: " . $franchise->id . "\n";
echo "Name: " . $franchise->name . "\n";
echo "Slug: " . $franchise->slug . "\n";
echo "Template Type: " . ($franchise->template_type ?? 'NULL') . "\n\n";

echo "Design Tokens:\n";
echo json_encode($franchise->design_tokens, JSON_PRETTY_PRINT) . "\n\n";

// Check endpoint
$endpoint = App\Models\MenuEndpoint::where('short_code', 'IYZFQY')->first();
if ($endpoint) {
    echo "ENDPOINT IYZFQY DATA\n";
    echo "====================\n\n";
    echo "ID: " . $endpoint->id . "\n";
    echo "Franchise ID: " . ($endpoint->franchise_id ?? 'NULL') . "\n";
    echo "Template ID: " . ($endpoint->template_id ?? 'NULL') . "\n";
    
    if ($endpoint->franchise) {
        echo "\nLinked Franchise: " . $endpoint->franchise->name . "\n";
        echo "Franchise Template Type: " . ($endpoint->franchise->template_type ?? 'NULL') . "\n";
    }
    
    if ($endpoint->template) {
        echo "\nLinked Template: " . $endpoint->template->name . "\n";
        echo "Template Settings: " . json_encode($endpoint->template->settings, JSON_PRETTY_PRINT) . "\n";
    }
}
