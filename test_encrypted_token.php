<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test encryption and URL encoding
$paymentData = [
    'user_id' => 1,
    'plan_id' => 2,
    'business_profile_id' => 1,
    'expires_at' => now()->addHours(2)->timestamp,
];

echo "Testing encrypted token for URL...\n";
echo "=====================================\n\n";

echo "1. Original data:\n";
print_r($paymentData);
echo "\n";

echo "2. Encrypted token:\n";
$token = encrypt($paymentData);
echo "Length: " . strlen($token) . " characters\n";
echo "Token: " . substr($token, 0, 100) . "...\n\n";

echo "3. URL encoded:\n";
$encoded = urlencode($token);
echo "Length: " . strlen($encoded) . " characters\n";
echo "Encoded: " . substr($encoded, 0, 100) . "...\n\n";

echo "4. Full return URL:\n";
$baseUrl = config('payment.subscription.return_urls.success');
$fullUrl = $baseUrl . '?token=' . $encoded;
echo "Length: " . strlen($fullUrl) . " characters\n";
echo "URL: " . $fullUrl . "\n\n";

echo "5. Test decryption:\n";
try {
    $decrypted = decrypt($token);
    echo "✓ Decryption successful!\n";
    print_r($decrypted);
} catch (\Exception $e) {
    echo "✗ Decryption failed: " . $e->getMessage() . "\n";
}

echo "\n6. Check if URL is too long:\n";
if (strlen($fullUrl) > 2048) {
    echo "⚠ WARNING: URL is " . strlen($fullUrl) . " characters (max recommended: 2048)\n";
    echo "This may cause issues with some browsers/gateways\n";
} else {
    echo "✓ URL length is acceptable (" . strlen($fullUrl) . " characters)\n";
}
