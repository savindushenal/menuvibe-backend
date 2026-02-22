<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== Testing Admin Access to Franchise Menus ===\n\n";

// Get token 74 user
$token = PersonalAccessToken::where('id', 74)->first();
if (!$token) {
    die("Token 74 not found\n");
}

$user = User::find($token->tokenable_id);
echo "User Details:\n";
echo "  ID: {$user->id}\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n";
echo "  Role: {$user->role}\n\n";

// Test admin access logic
$menuId = 8;
$menu = null;

if (in_array($user->role, ['super_admin', 'admin', 'support_team'])) {
    echo "✅ User has admin role - full access granted\n";
    $menu = Menu::find($menuId);
} else {
    echo "❌ User does not have admin role - checking ownership...\n";
    $menu = Menu::where('id', $menuId)
        ->where(function($query) use ($user) {
            $query->whereHas('location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orWhereHas('location', function($q) use ($user) {
                $q->whereNotNull('franchise_id')
                  ->whereHas('franchise.accounts', function($f) use ($user) {
                      $f->where('user_id', $user->id)->where('is_active', true);
                  });
            });
        })->first();
}

if ($menu) {
    echo "\n✅ SUCCESS: User CAN access Menu {$menuId}\n";
    echo "  Menu Name: {$menu->name}\n";
    echo "  Menu ID: {$menu->id}\n";
    $location = $menu->location;
    if ($location) {
        echo "  Location: {$location->name}\n";
        echo "  Franchise ID: " . ($location->franchise_id ?? 'None') . "\n";
    }
} else {
    echo "\n❌ FAILED: User CANNOT access Menu {$menuId}\n";
}

echo "\n=== Test Complete ===\n";
