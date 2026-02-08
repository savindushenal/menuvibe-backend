<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking Isso (franchise_id 4) templates:\n";
echo "==========================================\n\n";

$templates = App\Models\MenuTemplate::where('franchise_id', 4)->get();

echo "Total templates: " . $templates->count() . "\n\n";

foreach ($templates as $template) {
    echo "Template ID: {$template->id}\n";
    echo "Name: {$template->name}\n";
    echo "Settings: " . json_encode($template->settings, JSON_PRETTY_PRINT) . "\n";
    echo "---\n";
}

if ($templates->count() === 0) {
    echo "No templates found. Seeder should create one.\n";
}
