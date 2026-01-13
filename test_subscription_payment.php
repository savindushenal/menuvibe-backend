<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AbstercoPaymentService;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;

// Get user ID from command line or use default
$userId = $argv[1] ?? 1;
$planId = $argv[2] ?? 2; // Pro plan

echo "Testing payment for User ID: {$userId}, Plan ID: {$planId}\n\n";

try {
    $user = User::find($userId);
    if (!$user) {
        echo "Error: User not found\n";
        exit(1);
    }

    $plan = SubscriptionPlan::find($planId);
    if (!$plan) {
        echo "Error: Plan not found\n";
        exit(1);
    }

    echo "User: {$user->name} ({$user->email})\n";
    echo "Plan: {$plan->name} - LKR {$plan->price}\n\n";

    $paymentService = new AbstercoPaymentService();
    
    $amount = $paymentService->calculateSubscriptionAmount($plan, true);
    $reference = $paymentService->generatePaymentReference($user->id, $plan->id);
    
    echo "Amount: LKR {$amount}\n";
    echo "Reference: {$reference}\n\n";
    
    echo "Creating payment link...\n";
    
    $paymentData = $paymentService->createSubscriptionPayment([
        'amount' => $amount,
        'currency' => 'LKR',
        'description' => "Subscription: {$plan->name}",
        'order_reference' => $reference,
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'customer_phone' => $user->phone ?? null,
        'external_customer_id' => (string) $user->id,
        'allow_save_card' => true,
        'return_url' => 'https://staging.app.menuvire.com/dashboard/subscription/payment-callback',
        'subscription_plan_id' => $plan->id,
        'user_id' => $user->id,
        'payment_type' => 'subscription_upgrade',
    ]);

    echo "\n✓ SUCCESS!\n";
    echo "Payment URL: {$paymentData['payment_url']}\n";
    echo "Link Token: {$paymentData['link_token']}\n";

} catch (\Exception $e) {
    echo "\n✗ ERROR: {$e->getMessage()}\n";
    echo "\nFull error:\n";
    echo $e->getTraceAsString();
    exit(1);
}
