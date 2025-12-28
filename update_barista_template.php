<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$barista = \App\Models\Franchise::where('slug', 'barista')->first();
$barista->template_type = 'barista';
$barista->save();

echo "✅ Updated Barista template_type to: " . $barista->template_type . PHP_EOL;
