<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Franchise;
use App\Models\Location;

$franchise = Franchise::where('slug', 'isso')->first();

echo "ISSO DASHBOARD STATS:\n";
echo "  Branches: " . Location::where('franchise_id', $franchise->id)->whereNotNull('branch_code')->count() . "\n";
echo "  Locations: " . Location::where('franchise_id', $franchise->id)->count() . "\n";
