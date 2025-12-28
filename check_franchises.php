<?php

// Quick script to check franchises and update features

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Franchises ===" . PHP_EOL . PHP_EOL;

$franchises = \App\Models\Franchise::all();

if ($franchises->isEmpty()) {
    echo "No franchises found in database." . PHP_EOL;
} else {
    foreach ($franchises as $franchise) {
        echo "ID: {$franchise->id}" . PHP_EOL;
        echo "Name: {$franchise->name}" . PHP_EOL;
        echo "Slug: {$franchise->slug}" . PHP_EOL;
        echo "Features: " . ($franchise->features ? json_encode($franchise->features, JSON_PRETTY_PRINT) : 'None') . PHP_EOL;
        echo "---" . PHP_EOL;
    }
}

echo PHP_EOL . "Total franchises: " . $franchises->count() . PHP_EOL;
