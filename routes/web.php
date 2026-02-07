<?php

use Illuminate\Support\Facades\Route;
use App\Models\Location;

Route::get('/', function () {
    return response()->json([
        'message' => 'MenuVibe API',
        'version' => '1.0.0'
    ]);
});

// Fix Moratuwa location and its endpoints to be business (non-franchise)
Route::get('/fix-moratuwa-business', function () {
    $output = [];
    $output[] = "<h2>Setting Moratuwa as Business Location</h2>\n";
    
    $location = Location::where('name', 'Moratuwa')->first();
    
    if ($location) {
        $oldFranchiseId = $location->franchise_id;
        $location->franchise_id = null;
        $location->save();
        
        $output[] = "✓ Updated Location: {$location->name} (ID: {$location->id})";
        $output[] = "  - Old franchise_id: " . ($oldFranchiseId ?? 'NULL');
        $output[] = "  - New franchise_id: NULL (BUSINESS)";
        
        // Also fix all endpoints in this location
        $endpoints = \App\Models\MenuEndpoint::where('location_id', $location->id)->get();
        $endpointCount = 0;
        
        foreach ($endpoints as $endpoint) {
            $endpoint->franchise_id = null;
            $endpoint->save();
            $output[] = "  ✓ Fixed endpoint: {$endpoint->name}";
            $endpointCount++;
        }
        
        $output[] = "\n<strong>Success!</strong>";
        $output[] = "- Moratuwa is now a business location";
        $output[] = "- {$endpointCount} endpoints updated to business context";
    } else {
        $output[] = "✗ Location 'Moratuwa' not found";
    }
    
    return response('<pre style="line-height: 1.6;">' . implode("\n", $output) . '</pre>');
});