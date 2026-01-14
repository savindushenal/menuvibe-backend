<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AbstercoPaymentService;

$service = new AbstercoPaymentService();

echo "Verifying payment with session_id: 74\n";
echo "==========================================\n\n";

try {
    $result = $service->verifyPayment('74');
    
    echo "Verification Result:\n";
    print_r($result);
    
    echo "\n\nOrder Reference: " . ($result['order_reference'] ?? 'NOT FOUND') . "\n";
    
    if (isset($result['order_reference'])) {
        $orderRef = $result['order_reference'];
        
        if (preg_match('/USER_(\d+)_PLAN_(\d+)_/', $orderRef, $matches)) {
            echo "User ID: " . $matches[1] . "\n";
            echo "Plan ID: " . $matches[2] . "\n";
        } else {
            echo "ERROR: Cannot parse order_reference format\n";
            echo "Expected: USER_{userId}_PLAN_{planId}_{timestamp}\n";
            echo "Got: " . $orderRef . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
