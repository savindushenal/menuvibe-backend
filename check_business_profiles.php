<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Business Profiles Check ===\n\n";

$profiles = DB::table('business_profiles')->get();

if ($profiles->isEmpty()) {
    echo "No business profiles found in database.\n\n";
} else {
    echo "Found " . $profiles->count() . " business profile(s):\n\n";
    foreach ($profiles as $profile) {
        echo "ID: {$profile->id}\n";
        echo "User ID: {$profile->user_id}\n";
        echo "Business Name: " . ($profile->business_name ?? 'NULL') . "\n";
        echo "Business Type: " . ($profile->business_type ?? 'NULL') . "\n";
        echo "City: " . ($profile->city ?? 'NULL') . "\n";
        echo "State: " . ($profile->state ?? 'NULL') . "\n";
        echo "Onboarding Completed: " . ($profile->onboarding_completed ? 'Yes' : 'No') . "\n";
        echo "Created: {$profile->created_at}\n";
        echo "Updated: {$profile->updated_at}\n";
        echo str_repeat('-', 50) . "\n";
    }
}

// Check users
echo "\n=== Users Check ===\n\n";
$users = DB::table('users')->get(['id', 'name', 'email', 'created_at']);

if ($users->isEmpty()) {
    echo "No users found in database.\n";
} else {
    echo "Found " . $users->count() . " user(s):\n\n";
    foreach ($users as $user) {
        echo "ID: {$user->id}\n";
        echo "Name: {$user->name}\n";
        echo "Email: {$user->email}\n";
        echo "Created: {$user->created_at}\n";
        
        // Check if user has a business profile
        $hasProfile = DB::table('business_profiles')->where('user_id', $user->id)->exists();
        echo "Has Business Profile: " . ($hasProfile ? 'Yes' : 'No') . "\n";
        echo str_repeat('-', 50) . "\n";
    }
}
