<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "Checking franchise_branches table:\n";
if (Schema::hasTable('franchise_branches')) {
    echo "❌ franchise_branches table STILL EXISTS\n";
} else {
    echo "✅ franchise_branches table DROPPED!\n";
}

echo "\n";
