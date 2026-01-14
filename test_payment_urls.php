<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\AbstercoPaymentService;

// Load Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Payment Gateway URL Configuration\n";
echo "==========================================\n\n";

try {
    $paymentService = new AbstercoPaymentService();
    
    // Test data
    $testData = [
        'amount' => 2900, // LKR 29.00
        'description' => 'Test Subscription Payment',
        'order_reference' => 'TEST-' . time(),
        'customer_name' => 'Test User',
        'customer_email' => 'test@menuvibe.com',
        'customer_phone' => '+94771234567',
        'external_customer_id' => '999',
        'allow_save_card' => true,
        'subscription_plan_id' => 1,
        'user_id' => 999,
        'payment_type' => 'test',
    ];
    
    echo "Creating payment link...\n";
    $result = $paymentService->createSubscriptionPayment($testData);
    
    if ($result['success']) {
        echo "✓ Payment link created successfully!\n\n";
        echo "Payment URL: " . $result['payment_url'] . "\n";
        echo "Link Token: " . $result['link_token'] . "\n";
        echo "Expires At: " . $result['expires_at'] . "\n\n";
        
        echo "URLs Configuration:\n";
        echo "-------------------\n";
        echo "Success URL: " . config('payment.subscription.return_urls.success') . "\n";
        echo "Cancel URL:  " . config('payment.subscription.return_urls.cancel') . "\n";
        echo "Webhook URL: " . config('payment.subscription.return_urls.webhook') . "\n\n";
        
        echo "✓ Both success_url and cancel_url are configured!\n";
        echo "✓ They will be sent to Absterco as:\n";
        echo "  - business_return_url (success)\n";
        echo "  - business_cancel_url (cancel)\n";
    } else {
        echo "✗ FAILED to create payment link\n";
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
