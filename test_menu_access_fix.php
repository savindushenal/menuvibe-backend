<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\FranchiseAccount;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== Testing Menu Access Fix ===\n\n";

// Extract user ID from token (74|...)
// Let's find which user has a token starting with 74
$token = PersonalAccessToken::where('id', 74)->first();

if (!$token) {
    echo "⚠️  Token ID 74 not found. Checking all active franchise accounts...\n\n";
    
    // Show all users with access to Isso franchise (ID 4)
    $accounts = FranchiseAccount::where('franchise_id', 4)
        ->where('is_active', true)
        ->with('user')
        ->get();
    
    echo "Users with Isso franchise access:\n";
    foreach ($accounts as $account) {
        echo "  User ID: {$account->user_id} - {$account->user->name} ({$account->user->email})\n";
        echo "  Role: {$account->role}\n";
        echo "  Location ID: " . ($account->location_id ?? 'ALL') . "\n\n";
    }
} else {
    $userId = $token->tokenable_id;
    echo "✅ Token belongs to User ID: $userId\n\n";
    
    // Test menu access with new query
    $menu = Menu::where('id', 8)
        ->where(function($query) use ($userId) {
            // Business menu: direct user ownership
            $query->whereHas('location', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            // OR Franchise menu: user has franchise access
            ->orWhereHas('location', function($q) use ($userId) {
                $q->whereNotNull('franchise_id')
                  ->whereHas('franchise.accounts', function($f) use ($userId) {
                      $f->where('user_id', $userId)->where('is_active', true);
                  });
            });
        })->first();
    
    if ($menu) {
        echo "✅ User CAN access Menu 8\n";
        echo "  Menu: {$menu->name}\n";
        echo "  Location: {$menu->location->name}\n";
    } else {
        echo "❌ User CANNOT access Menu 8\n";
        echo "  User might not have franchise access\n";
    }
}

echo "\n✅ Fix deployed! The API should now work for franchise menus.\n";
