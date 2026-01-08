<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;

echo "=== Testing Updated Dashboard Stats ===\n\n";

// Get user 1
$user = User::find(1);

if (!$user) {
    echo "User 1 not found!\n";
    exit;
}

echo "User: {$user->name}\n\n";

// Create a mock request with token
$request = Request::create('/api/dashboard/stats', 'GET');
$token = $user->createToken('test-token')->plainTextToken;
$request->headers->set('Authorization', 'Bearer ' . $token);

// Call the controller
$controller = new DashboardController();
$response = $controller->stats($request);

// Get response data
$content = $response->getContent();
$data = json_decode($content, true);

echo "Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n";

// Clean up test token
$user->tokens()->where('name', 'test-token')->delete();
