<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Full Business Profile Details ===\n\n";

$profiles = DB::table('business_profiles')->get();

foreach ($profiles as $profile) {
    echo "Full Profile Data:\n";
    echo json_encode($profile, JSON_PRETTY_PRINT) . "\n\n";
}

// Also check if there are any recent logs about business profile updates
echo "\n=== Checking Recent Laravel Logs ===\n";
$logFile = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -100); // Get last 100 lines
    
    $relevantLines = array_filter($recentLines, function($line) {
        return stripos($line, 'business profile') !== false || 
               stripos($line, 'onboarding') !== false;
    });
    
    if (!empty($relevantLines)) {
        echo "Recent business profile related logs:\n";
        foreach ($relevantLines as $line) {
            echo $line;
        }
    } else {
        echo "No recent business profile logs found.\n";
    }
}
