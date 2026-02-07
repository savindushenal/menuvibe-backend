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

// Debug route to check endpoint data
Route::get('/debug-endpoints', function () {
    $output = [];
    $output[] = "<h2>Location Debug Information</h2>\n";
    
    $locations = \App\Models\Location::all();
    foreach ($locations as $location) {
        $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $output[] = "<strong>Location #{$location->id}: {$location->name}</strong>";
        $output[] = "  User ID: {$location->user_id}";
        $output[] = "  <strong>Franchise ID: " . ($location->franchise_id ?? 'NULL (BUSINESS)') . "</strong>";
        $output[] = "  Is Default: " . ($location->is_default ? 'Yes' : 'No');
        $output[] = "";
    }
    
    $output[] = "\n<h2>Endpoint Debug Information</h2>\n";
    
    $endpoints = MenuEndpoint::with(['location', 'template'])->get();
    
    foreach ($endpoints as $endpoint) {
        $output[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $output[] = "<strong>Endpoint #{$endpoint->id}: {$endpoint->name}</strong>";
        $output[] = "  Type: {$endpoint->type}";
        $output[] = "  Identifier: {$endpoint->identifier}";
        $output[] = "  Location ID: " . ($endpoint->location_id ?? 'NULL');
        $output[] = "  <strong>Endpoint Franchise ID: " . ($endpoint->franchise_id ?? 'NULL (BUSINESS)') . "</strong>";
        
        if ($endpoint->location) {
            $output[] = "  Location: {$endpoint->location->name} (ID: {$endpoint->location->id})";
            $output[] = "  Location Franchise ID: " . ($endpoint->location->franchise_id ?? 'NULL (BUSINESS)');
        } else {
            $output[] = "  Location: <span style='color:red'>NOT FOUND</span>";
        }
        
        if ($endpoint->template) {
            $output[] = "  Template: {$endpoint->template->name} (ID: {$endpoint->template->id})";
        }
        $output[] = "";
    }
    
    return response('<pre style="font-family: monospace; line-height: 1.6;">' . implode("\n", $output) . '</pre>');
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

// Manual fix: Set specific endpoints as business (non-franchise)
Route::get('/fix-business-endpoints', function () {
    $output = [];
    $output[] = "Setting Table-1 and Table-2 as business endpoints...\n";
    
    // Find endpoints by identifier
    $endpoint3 = MenuEndpoint::find(3); // Table 1 (table-1)
    $endpoint4 = MenuEndpoint::find(4); // Table-2 (table-2)
    
    $updated = 0;
    
    if ($endpoint3) {
        $endpoint3->franchise_id = null;
        $endpoint3->save();
        $output[] = "✓ Updated Endpoint #3 ({$endpoint3->name}) - Set to BUSINESS (franchise_id = NULL)";
        $updated++;
    } else {
        $output[] = "✗ Endpoint #3 not found";
    }
    
    if ($endpoint4) {
        $endpoint4->franchise_id = null;
        $endpoint4->save();
        $output[] = "✓ Updated Endpoint #4 ({$endpoint4->name}) - Set to BUSINESS (franchise_id = NULL)";
        $updated++;
    } else {
        $output[] = "✗ Endpoint #4 not found";
    }
    
    $output[] = "\n=====================================";
    $output[] = "Summary: {$updated} endpoints updated to business context";
    $output[] = "=====================================";
    
    return response('<pre>' . implode("\n", $output) . '</pre>');
});