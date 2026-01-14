<?php

// Test what version is deployed on Railway
$apiUrl = 'https://api.menuvire.com/api/payment-gateway-status';

echo "Checking Railway deployment status...\n";
echo "==========================================\n\n";

$response = file_get_contents($apiUrl);
$data = json_decode($response, true);

echo "Status: " . $data['status'] . "\n";
echo "Git Commit: " . ($data['git_commit'] ?? 'unknown') . "\n";
echo "Service exists: " . ($data['service_class_exists'] ? 'Yes' : 'No') . "\n";
echo "Controller exists: " . ($data['controller_class_exists'] ? 'Yes' : 'No') . "\n\n";

echo "Latest local commit:\n";
echo shell_exec('git log --oneline -1');
echo "\n";

echo "If git commits don't match, Railway is still deploying...\n";
