<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

// Find super admin
$superAdmin = User::where('role', 'super_admin')->first();

if ($superAdmin) {
    echo "Super Admin found:\n";
    echo "ID: " . $superAdmin->id . "\n";
    echo "Name: " . $superAdmin->name . "\n";
    echo "Email: " . $superAdmin->email . "\n";
    echo "\n";
    
    // Reset password to a new one
    $newPassword = 'SuperAdmin@123';
    $superAdmin->password = bcrypt($newPassword);
    $superAdmin->save();
    
    echo "Password has been reset to: " . $newPassword . "\n";
} else {
    echo "No super admin found in the database.\n";
    
    // List all admins
    echo "\nListing all admin/super_admin users:\n";
    $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
    foreach ($admins as $admin) {
        echo "- ID: {$admin->id}, Name: {$admin->name}, Email: {$admin->email}, Role: {$admin->role}\n";
    }
}
