<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

echo "Creating test user for settings API..." . PHP_EOL;

try {
    // Clean up any existing test user
    App\Models\User::where('email', 'settings-api-test@example.com')->delete();
    echo "Cleaned up existing test user" . PHP_EOL;
    
    // Create a test user
    $user = App\Models\User::create([
        'email' => 'settings-api-test@example.com',
        'name' => 'Settings Test User',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'email_verified_at' => now()
    ]);
    
    echo "User created: " . $user->email . PHP_EOL;
    
    // Create a token for API testing
    $token = $user->createToken('settings-test')->plainTextToken;
    echo "Auth token created: " . substr($token, 0, 20) . "..." . PHP_EOL;
    
    // Test user settings relationship
    $settings = $user->getSettings();
    echo "Settings created for user: " . ($settings ? 'Yes' : 'No') . PHP_EOL;
    
    if ($settings) {
        echo "Settings ID: " . $settings->id . PHP_EOL;
        echo "Email notifications: " . ($settings->email_notifications ? 'Yes' : 'No') . PHP_EOL;
        echo "Weekly reports: " . ($settings->weekly_reports ? 'Yes' : 'No') . PHP_EOL;
    }
    
    echo "COPY THIS TOKEN FOR API TESTING:" . PHP_EOL;
    echo $token . PHP_EOL;
    echo "User ID: " . $user->id . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}