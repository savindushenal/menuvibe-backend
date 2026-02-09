<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "ISSO Franchise Design Configuration\n";
echo "====================================\n\n";

$isso = App\Models\Franchise::find(4);

if (!$isso) {
    echo "❌ Isso franchise not found (ID: 4)\n";
    exit(1);
}

echo "Franchise: {$isso->name}\n";
echo "Slug: {$isso->slug}\n";
echo "Template Type: " . ($isso->template_type ?? 'null') . "\n\n";

echo "Design Tokens:\n";
echo json_encode($isso->design_tokens, JSON_PRETTY_PRINT) . "\n\n";

$template = App\Models\MenuTemplate::where('franchise_id', 4)
    ->where('name', 'Isso Main Menu')
    ->first();

if ($template) {
    echo "Menu Template: {$template->name}\n";
    echo "Settings:\n";
    echo json_encode($template->settings, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "Available Frontend Templates:\n";
    echo "  - barista (Coffee shop style)\n";
    echo "  - premium (Premium restaurant style)\n";
    echo "  - classic (Traditional list layout)\n";
    echo "  - minimal (Simple minimal design)\n\n";
    
    echo "Current template_type: " . ($template->settings['template_type'] ?? 'null') . "\n";
    echo "⚠️  'isso' template does NOT exist in frontend!\n";
} else {
    echo "❌ No menu template found\n";
}
