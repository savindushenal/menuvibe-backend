<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$updated = \App\Models\MenuEndpoint::where('short_code', 'ZISYSS')
    ->update(['template_key' => 'barista-style']);

echo "Updated: " . ($updated ? 'Yes' : 'No') . "\n";

$endpoint = \App\Models\MenuEndpoint::where('short_code', 'ZISYSS')->first();
echo "Current template_key: " . $endpoint->template_key . "\n";
echo "\nOpen: http://localhost:3000/menu/ZISYSS\n";
