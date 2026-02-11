<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FranchiseBranch;
use App\Models\Location;

echo "=== FIXING BRANCH NAME ===\n\n";

$branch = FranchiseBranch::find(1);
$location = Location::find(4);

if ($branch && $location) {
    echo "Before:\n";
    echo "  Branch name: " . ($branch->branch_name ?? 'NULL') . "\n";
    echo "  Location name: {$location->name}\n\n";
    
    $branch->update([
        'branch_name' => $location->name,
    ]);
    
    echo "After:\n";
    echo "  Branch name: {$branch->fresh()->branch_name}\n";
    echo "\n✅ Branch name updated!\n";
} else {
    echo "❌ Branch or location not found\n";
}

