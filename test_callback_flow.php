<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test the callback flow
$sessionId = '74';

echo "Testing payment callback flow for session_id: {$sessionId}\n";
echo "=======================================================\n\n";

// Step 1: Verify payment
$service = new \App\Services\AbstercoPaymentService();

try {
    echo "1. Verifying payment...\n";
    $verification = $service->verifyPayment($sessionId);
    
    echo "✓ Verification successful!\n";
    echo "Status: " . $verification['status'] . "\n";
    echo "Order Reference: " . ($verification['order_reference'] ?? 'NOT FOUND') . "\n";
    echo "Amount: " . ($verification['amount'] ?? 'N/A') . "\n";
    echo "Currency: " . ($verification['currency'] ?? 'N/A') . "\n\n";
    
    // Step 2: Parse order reference
    $orderReference = $verification['order_reference'] ?? null;
    
    if (!$orderReference) {
        echo "✗ ERROR: order_reference not found in verification response\n";
        echo "Full verification response:\n";
        print_r($verification);
        exit(1);
    }
    
    echo "2. Parsing order reference: {$orderReference}\n";
    
    if (preg_match('/USER_(\d+)_PLAN_(\d+)_/', $orderReference, $matches)) {
        $userId = $matches[1];
        $planId = $matches[2];
        
        echo "✓ Successfully parsed!\n";
        echo "User ID: {$userId}\n";
        echo "Plan ID: {$planId}\n\n";
        
        // Step 3: Check if user exists
        echo "3. Checking user...\n";
        $user = \App\Models\User::find($userId);
        
        if ($user) {
            echo "✓ User found: {$user->name} ({$user->email})\n";
            echo "Business Profile ID: " . ($user->businessProfile?->id ?? 'NONE') . "\n\n";
        } else {
            echo "✗ User not found with ID: {$userId}\n";
        }
        
        // Step 4: Check if plan exists
        echo "4. Checking subscription plan...\n";
        $plan = \App\Models\SubscriptionPlan::find($planId);
        
        if ($plan) {
            echo "✓ Plan found: {$plan->name}\n";
            echo "Price: {$plan->price} {$plan->currency}\n";
        } else {
            echo "✗ Plan not found with ID: {$planId}\n";
        }
        
    } else {
        echo "✗ ERROR: Could not parse order_reference format\n";
        echo "Expected format: USER_{userId}_PLAN_{planId}_{timestamp}\n";
        echo "Got: {$orderReference}\n";
    }
    
} catch (\Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
    exit(1);
}

echo "\n=======================================================\n";
echo "Test completed!\n";
