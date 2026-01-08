<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;

echo "=== Testing Dashboard API Response Structure ===\n\n";

// Test with different users
$userIds = [1, 2, 3];

foreach ($userIds as $userId) {
    $user = User::find($userId);
    
    if (!$user) {
        echo "User ID {$userId}: Not found, skipping\n\n";
        continue;
    }
    
    echo "Testing User ID {$userId}: {$user->name} ({$user->email})\n";
    
    // Create a mock request with token
    $request = Request::create('/api/dashboard/stats', 'GET');
    $token = $user->createToken('test-token')->plainTextToken;
    $request->headers->set('Authorization', 'Bearer ' . $token);
    
    // Call the controller
    $controller = new DashboardController();
    
    try {
        $response = $controller->stats($request);
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        echo "Status Code: " . $response->getStatusCode() . "\n";
        echo "Response Structure:\n";
        echo json_encode([
            'success' => $data['success'] ?? null,
            'has_data' => isset($data['data']),
            'has_stats' => isset($data['data']['stats']),
            'has_recentActivity' => isset($data['data']['recentActivity']),
            'has_popularItems' => isset($data['data']['popularItems']),
            'stats_keys' => isset($data['data']['stats']) ? array_keys($data['data']['stats']) : [],
            'recentActivity_count' => isset($data['data']['recentActivity']) ? count($data['data']['recentActivity']) : 0,
            'popularItems_count' => isset($data['data']['popularItems']) ? count($data['data']['popularItems']) : 0,
        ], JSON_PRETTY_PRINT) . "\n";
        
        if ($response->getStatusCode() !== 200) {
            echo "ERROR Response:\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
        
    } catch (\Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
    
    // Clean up test token
    $user->tokens()->where('name', 'test-token')->delete();
    
    echo str_repeat('-', 70) . "\n\n";
}

echo "=== Frontend Expected Structure ===\n";
echo json_encode([
    'success' => true,
    'data' => [
        'stats' => [
            'totalViews' => ['value' => 0, 'change' => 0, 'trend' => 'up', 'formatted' => '0'],
            'qrScans' => ['value' => 0, 'change' => 0, 'trend' => 'up', 'formatted' => '0'],
            'menuItems' => ['value' => 0, 'change' => 0, 'trend' => 'up', 'formatted' => '0'],
            'activeCustomers' => ['value' => 0, 'change' => 0, 'trend' => 'up', 'formatted' => '0'],
        ],
        'recentActivity' => [
            ['description' => '...', 'timeAgo' => '...', 'type' => '...']
        ],
        'popularItems' => [
            ['id' => 1, 'name' => '...', 'viewCount' => 100]
        ]
    ]
], JSON_PRETTY_PRINT) . "\n";
