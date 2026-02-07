<?php

/**
 * Fix Endpoint Franchise IDs
 * This script updates all menu_endpoints to set franchise_id based on their location's franchise_id
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MenuEndpoint;
use App\Models\Location;

echo "Starting to fix endpoint franchise_ids...\n\n";

// Get all endpoints
$endpoints = MenuEndpoint::with('location')->get();
$updated = 0;
$unchanged = 0;

foreach ($endpoints as $endpoint) {
    $oldFranchiseId = $endpoint->franchise_id;
    $newFranchiseId = null;
    
    if ($endpoint->location) {
        $newFranchiseId = $endpoint->location->franchise_id;
    }
    
    // Only update if franchise_id has changed
    if ($oldFranchiseId !== $newFranchiseId) {
        $endpoint->franchise_id = $newFranchiseId;
        $endpoint->save();
        
        $context = $newFranchiseId ? "franchise #{$newFranchiseId}" : "business (no franchise)";
        echo "✓ Updated endpoint #{$endpoint->id} ({$endpoint->name}): {$context}\n";
        $updated++;
    } else {
        $unchanged++;
    }
}

echo "\n";
echo "=====================================\n";
echo "Summary:\n";
echo "- Total endpoints: " . count($endpoints) . "\n";
echo "- Updated: {$updated}\n";
echo "- Unchanged: {$unchanged}\n";
echo "=====================================\n";
echo "\nDone!\n";
