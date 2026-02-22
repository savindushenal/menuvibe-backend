<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== User Role Check ===\n\n";

// Check token 74
$token = PersonalAccessToken::where('id', 74)->first();
if ($token) {
    $user = User::find($token->tokenable_id);
    
    echo "Token 74 User Details:\n";
    echo "  ID: {$user->id}\n";
    echo "  Name: {$user->name}\n";
    echo "  Email: {$user->email}\n";
    echo "  Role: {$user->role}\n";
    echo "  Is Superadmin: " . ($user->role === 'superadmin' ? 'YES ✅' : 'NO') . "\n";
    echo "  Is Admin: " . ($user->role === 'admin' ? 'YES ✅' : 'NO') . "\n";
    echo "  Is Support: " . ($user->role === 'support' ? 'YES ✅' : 'NO') . "\n";
}

echo "\n=== All Admin Users ===\n";
$admins = User::whereIn('role', ['superadmin', 'admin', 'support'])->get();
foreach ($admins as $admin) {
    echo "  ID: {$admin->id} - {$admin->name} ({$admin->role})\n";
}
