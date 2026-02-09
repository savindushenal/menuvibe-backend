<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== MenuVibe Admin Access Credentials ===\n\n";

// Check for super admin
$superAdmin = User::where('role', 'super_admin')->first();

if ($superAdmin) {
    echo "✓ Super Admin Account Found!\n";
    echo "--------------------------------\n";
    echo "Email: " . $superAdmin->email . "\n";
    echo "Name: " . $superAdmin->name . "\n";
    echo "Role: " . $superAdmin->role . "\n\n";
    
    // Reset password
    $newPassword = 'Admin@2026';
    $superAdmin->password = bcrypt($newPassword);
    $superAdmin->save();
    
    echo "Password has been reset to: " . $newPassword . "\n";
    echo "--------------------------------\n\n";
}

// List all admin users
echo "All Admin Users:\n";
echo "--------------------------------\n";
$admins = User::whereIn('role', ['admin', 'super_admin'])->get();

if ($admins->count() > 0) {
    foreach ($admins as $admin) {
        echo "• Email: {$admin->email}\n";
        echo "  Name: {$admin->name}\n";
        echo "  Role: {$admin->role}\n";
        echo "  ID: {$admin->id}\n\n";
    }
} else {
    echo "No admin users found.\n\n";
}

// List some regular users for business dashboard access
echo "Sample Business Owners (for business dashboard access):\n";
echo "--------------------------------\n";
$businessOwners = User::where('role', 'business_owner')
    ->orderBy('created_at', 'desc')
    ->limit(3)
    ->get();

foreach ($businessOwners as $owner) {
    echo "• Email: {$owner->email}\n";
    echo "  Name: {$owner->name}\n";
    echo "  Businesses: {$owner->businessProfiles->count()}\n\n";
}

echo "\n=== Login URLs ===\n";
echo "Admin Dashboard: https://app.menuvire.com/login\n";
echo "After login, admins can access all features and business dashboards.\n";
