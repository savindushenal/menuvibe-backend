<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Testing Business Profile API Response ===\n\n";

// Get user 1 (the one with a business profile)
$user = User::find(1);

if (!$user) {
    echo "User 1 not found!\n";
    exit;
}

echo "User: {$user->name} ({$user->email})\n\n";

// Get business profile via relationship
$businessProfile = $user->businessProfile;

if (!$businessProfile) {
    echo "No business profile found via relationship!\n\n";
    
    // Check if it exists in DB
    $dbProfile = DB::table('business_profiles')->where('user_id', $user->id)->first();
    if ($dbProfile) {
        echo "But profile EXISTS in database! This is a MODEL RELATIONSHIP issue.\n";
        echo "Database profile: " . json_encode($dbProfile, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Business Profile found via relationship:\n";
    echo json_encode($businessProfile->toArray(), JSON_PRETTY_PRINT) . "\n\n";
    
    echo "isOnboardingCompleted(): " . ($businessProfile->isOnboardingCompleted() ? 'true' : 'false') . "\n";
}

// Check the User model for the relationship
echo "\n=== Checking User Model Relationship ===\n";
$userModelPath = __DIR__ . '/app/Models/User.php';
if (file_exists($userModelPath)) {
    $content = file_get_contents($userModelPath);
    if (strpos($content, 'businessProfile') !== false) {
        echo "✓ businessProfile relationship exists in User model\n";
        
        // Extract the relationship method
        preg_match('/public function businessProfile.*?\{.*?\}/s', $content, $matches);
        if (!empty($matches)) {
            echo "Relationship definition:\n" . $matches[0] . "\n";
        }
    } else {
        echo "✗ businessProfile relationship NOT found in User model!\n";
    }
}
