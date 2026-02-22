<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FranchiseAccount;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== Franchise Access Summary ===\n\n";

// Check which users have access to Isso franchise (ID 4)
$accounts = FranchiseAccount::where('franchise_id', 4)
    ->where('is_active', true)
    ->with('user')
    ->get();

echo "Users with Isso Franchise access:\n";
foreach ($accounts as $account) {
    echo "  User ID: {$account->user_id} - {$account->user->name} ({$account->user->email})\n";
    echo "    Role: {$account->role}\n";
    echo "    Location: " . ($account->location_id ?? 'ALL locations') . "\n\n";
}

// Check token 74
echo "\n=== Token Analysis ===\n";
$token = PersonalAccessToken::where('id', 74)->first();
if ($token) {
    echo "Token 74 belongs to User ID: {$token->tokenable_id}\n";
    
    // Check if this user has franchise access
    $hasAccess = FranchiseAccount::where('franchise_id', 4)
        ->where('user_id', $token->tokenable_id)
        ->where('is_active', true)
        ->exists();
    
    if ($hasAccess) {
        echo "✅ User HAS franchise access\n";
    } else {
        echo "❌ User does NOT have franchise access\n";
        echo "   Solution: Add user to franchise_accounts table\n";
    }
} else {
    echo "Token 74 not found\n";
}
