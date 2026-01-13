<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AbstercoPaymentService;
use App\Models\SubscriptionPlan;
use App\Models\User;

echo "=== Testing Absterco Payment Gateway Integration ===\n\n";

try {
    // Test 1: Check if service can be instantiated
    echo "1. Instantiating AbstercoPaymentService...\n";
    $paymentService = new AbstercoPaymentService();
    echo "   ✓ Service created successfully\n\n";

    // Test 2: Check configuration
    echo "2. Checking configuration...\n";
    echo "   API Key: " . (config('services.absterco.api_key') ? '✓ Set' : '✗ Missing') . "\n";
    echo "   Base URL: " . (config('services.absterco.base_url') ?: 'Not set') . "\n";
    echo "   Organization ID: " . (config('services.absterco.organization_id') ?: 'Not set') . "\n\n";

    // Test 3: Get a test user
    echo "3. Getting test user...\n";
    $user = User::first();
    if (!$user) {
        echo "   ✗ No users found in database\n";
        exit(1);
    }
    echo "   ✓ User found: {$user->name} (ID: {$user->id})\n\n";

    // Test 4: Get a subscription plan
    echo "4. Getting subscription plan...\n";
    $plan = SubscriptionPlan::where('is_active', 1)->where('price', '>', 0)->first();
    if (!$plan) {
        echo "   ✗ No active paid subscription plans found\n";
        exit(1);
    }
    echo "   ✓ Plan found: {$plan->name} (Price: {$plan->price})\n\n";

    // Test 5: Calculate amount
    echo "5. Calculating payment amount...\n";
    $amount = $paymentService->calculateSubscriptionAmount($plan, true);
    echo "   ✓ Amount calculated: LKR {$amount}\n\n";

    // Test 6: Generate payment reference
    echo "6. Generating payment reference...\n";
    $reference = $paymentService->generatePaymentReference($user->id, $plan->id);
    echo "   ✓ Reference: {$reference}\n\n";

    // Test 7: Create payment link
    echo "7. Creating payment link with Absterco...\n";
    $paymentData = $paymentService->createSubscriptionPayment([
        'amount' => $amount,
        'currency' => 'LKR',
        'description' => "Test Payment: {$plan->name}",
        'order_reference' => $reference,
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'customer_phone' => $user->phone,
        'external_customer_id' => (string) $user->id,
        'allow_save_card' => true,
        'return_url' => config('app.frontend_url') . '/dashboard/subscription/payment-callback',
        'subscription_plan_id' => $plan->id,
        'user_id' => $user->id,
        'payment_type' => 'test',
    ]);

    echo "   ✓ Payment link created successfully!\n";
    echo "   Link Token: {$paymentData['link_token']}\n";
    echo "   Payment URL: {$paymentData['payment_url']}\n";
    echo "   Expires At: {$paymentData['expires_at']}\n\n";

    echo "=== ALL TESTS PASSED ✓ ===\n";

} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
