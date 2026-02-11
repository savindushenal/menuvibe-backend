<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Franchise;
use App\Models\Location;
use App\Models\FranchiseBranch;

echo "=== SYNCING ISSO LOCATIONS TO FRANCHISE BRANCHES ===\n\n";

// Get isso franchise
$franchise = Franchise::where('slug', 'isso')->first();

if (!$franchise) {
    echo "❌ Isso franchise not found\n";
    exit(1);
}

echo "Franchise: {$franchise->name} (ID: {$franchise->id})\n\n";

// Get all locations for this franchise
$locations = Location::where('franchise_id', $franchise->id)->get();

echo "Found {$locations->count()} location(s)\n\n";

if ($locations->count() === 0) {
    echo "No locations to sync\n";
    exit(0);
}

foreach ($locations as $location) {
    echo "Location: {$location->name} (ID: {$location->id})\n";
    
    // Check if franchise_branch already exists for this location
    $branch = FranchiseBranch::where('location_id', $location->id)->first();
    
    if ($branch) {
        echo "  ✓ FranchiseBranch already exists (ID: {$branch->id})\n";
    } else {
        echo "  Creating FranchiseBranch...\n";
        
        // Generate branch code
        $branchCode = FranchiseBranch::generateBranchCode($franchise->id);
        
        $branch = FranchiseBranch::create([
            'franchise_id' => $franchise->id,
            'location_id' => $location->id,
            'branch_name' => $location->name,
            'branch_code' => $branchCode,
            'address' => $location->address,
            'city' => $location->city,
            'phone' => $location->phone,
            'is_active' => $location->is_active,
            'is_paid' => true, // Assume paid for existing locations
            'activated_at' => now(),
            'added_by' => $location->user_id,
        ]);
        
        echo "  ✅ Created FranchiseBranch (ID: {$branch->id}, Code: {$branchCode})\n";
    }
    echo "\n";
}

echo "\n=== VERIFICATION ===\n";
$branchCount = FranchiseBranch::where('franchise_id', $franchise->id)->count();
$locationCount = Location::where('franchise_id', $franchise->id)->count();

echo "Franchise Branches: {$branchCount}\n";
echo "Locations: {$locationCount}\n";

if ($branchCount === $locationCount) {
    echo "\n✅ All locations synced to franchise branches!\n";
} else {
    echo "\n⚠️  Mismatch: {$branchCount} branches vs {$locationCount} locations\n";
}

