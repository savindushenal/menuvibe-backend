<?php

use Illuminate\Support\Facades\Route;
use App\Models\MenuEndpoint;
use App\Models\Location;

Route::get('/', function () {
    return response()->json([
        'message' => 'MenuVibe API',
        'version' => '1.0.0'
    ]);
});

// Maintenance route to fix endpoint franchise IDs
Route::get('/fix-endpoint-franchise-ids', function () {
    $output = [];
    $output[] = "Starting to fix endpoint franchise_ids...\n";
    
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
            $output[] = "✓ Updated endpoint #{$endpoint->id} ({$endpoint->name}): {$context}";
            $updated++;
        } else {
            $unchanged++;
        }
    }
    
    $output[] = "\n=====================================";
    $output[] = "Summary:";
    $output[] = "- Total endpoints: " . count($endpoints);
    $output[] = "- Updated: {$updated}";
    $output[] = "- Unchanged: {$unchanged}";
    $output[] = "=====================================";
    
    return response('<pre>' . implode("\n", $output) . '</pre>');
});