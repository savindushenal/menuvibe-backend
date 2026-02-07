<?php

use Illuminate\Support\Facades\Route;
use App\Models\Location;

Route::get('/', function () {
    return response()->json([
        'message' => 'MenuVibe API',
        'version' => '1.0.0'
    ]);
});

// Fix Moratuwa location to be business (non-franchise)
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
        $output[] = "\n<strong>Success!</strong> Moratuwa is now a business location.";
    } else {
        $output[] = "✗ Location 'Moratuwa' not found";
    }
    
    return response('<pre>' . implode("\n", $output) . '</pre>');
});