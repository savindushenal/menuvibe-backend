<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;

// Get user 30
$user = User::find(30);

if (!$user) {
    echo "User not found\n";
    exit(1);
}

echo "User: {$user->email}\n";

// Check current subscription
$currentSub = $user->getCurrentSubscriptionPlan();
echo "Current plan: " . ($currentSub ? $currentSub->name : "None") . "\n";

// Get or create free plan
$freePlan = SubscriptionPlan::where('slug', 'free')->first();

if (!$freePlan) {
    echo "Creating free plan...\n";
    $freePlan = SubscriptionPlan::create([
        'name' => 'Free',
        'slug' => 'free',
        'price' => 0,
        'billing_period' => 'monthly',
        'features' => json_encode([
            'max_menus_per_location' => 1,
            'max_menu_items_per_menu' => 10,
            'max_locations' => 1,
            'qr_codes' => true,
            'photo_uploads' => false,
            'custom_branding' => false,
            'analytics' => false,
            'api_access' => false,
        ]),
        'is_active' => true,
    ]);
}

// Check if user already has a subscription
$existingSub = UserSubscription::where('user_id', $user->id)->first();

if ($existingSub) {
    echo "User already has subscription: {$existingSub->subscriptionPlan->name}\n";
    echo "Updating to active...\n";
    $existingSub->update([
        'is_active' => true,
        'status' => 'active',
    ]);
} else {
    echo "Assigning free subscription...\n";
    UserSubscription::create([
        'user_id' => $user->id,
        'subscription_plan_id' => $freePlan->id,
        'starts_at' => now(),
        'ends_at' => null,
        'trial_ends_at' => null,
        'is_active' => true,
        'status' => 'active',
    ]);
}

echo "Done! Checking subscription...\n";
$newSub = $user->fresh()->getCurrentSubscriptionPlan();
echo "New plan: " . ($newSub ? $newSub->name : "None") . "\n";

if ($newSub) {
    echo "Max menu items per menu: " . $newSub->getLimit('max_menu_items_per_menu') . "\n";
}
