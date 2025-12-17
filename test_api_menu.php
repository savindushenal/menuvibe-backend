<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing API-Driven Menu System ===\n\n";

// Get first menu endpoint
$endpoint = \App\Models\MenuEndpoint::with('location.user.businessProfile')
    ->where('is_active', true)
    ->first();

if (!$endpoint) {
    echo "❌ No active menu endpoints found\n";
    echo "Creating a test endpoint...\n\n";
    
    // Find or create test user
    $user = \App\Models\User::first();
    if (!$user) {
        echo "❌ No users found. Please create a user first.\n";
        exit;
    }
    
    // Find or create location
    $location = \App\Models\Location::where('user_id', $user->id)->first();
    if (!$location) {
        echo "❌ No location found for user. Please create a location first.\n";
        exit;
    }
    
    // Create menu endpoint
    $endpoint = \App\Models\MenuEndpoint::create([
        'name' => 'Test Menu',
        'short_code' => 'TEST' . rand(100, 999),
        'location_id' => $location->id,
        'template_id' => 1,
        'is_active' => true,
        'scan_count' => 0,
    ]);
    
    echo "✅ Created test endpoint: {$endpoint->short_code}\n\n";
    $endpoint->load('location.user.businessProfile');
}

echo "📋 Menu Endpoint Details:\n";
echo "   Code: {$endpoint->short_code}\n";
echo "   Name: {$endpoint->name}\n";
echo "   Business: " . ($endpoint->location->user->businessProfile->name ?? 'N/A') . "\n";
echo "   Active: " . ($endpoint->is_active ? 'Yes' : 'No') . "\n\n";

// Test API endpoints
$baseUrl = 'http://localhost:8000';
$code = $endpoint->short_code;

echo "🔗 Test URLs:\n\n";

echo "1️⃣  Full Menu API (with config):\n";
echo "   {$baseUrl}/api/menu/{$code}\n\n";

echo "2️⃣  Config Only API:\n";
echo "   {$baseUrl}/api/menu/{$code}/config\n\n";

echo "3️⃣  Dynamic QR Code (SVG):\n";
echo "   {$baseUrl}/api/qr/{$code}\n\n";

echo "4️⃣  Download QR Code (PNG):\n";
echo "   {$baseUrl}/api/qr/{$code}/download?format=png&size=512\n\n";

echo "5️⃣  Frontend Menu View:\n";
echo "   http://localhost:3000/menu/{$code}\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test the API call
echo "🧪 Testing Full Menu API...\n";

$controller = new \App\Http\Controllers\PublicMenuController();
$request = \Illuminate\Http\Request::create("/api/menu/{$code}", 'GET');

try {
    $response = $controller->getMenu($request, $code);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "✅ API Response: SUCCESS\n\n";
        
        echo "📦 Response Structure:\n";
        echo "   - menu.categories: " . count($data['data']['menu']['categories'] ?? []) . " categories\n";
        echo "   - offers: " . count($data['data']['offers'] ?? []) . " offers\n";
        echo "   - business.name: " . ($data['data']['business']['name'] ?? 'N/A') . "\n";
        echo "   - template.config.version: " . ($data['data']['template']['config']['version'] ?? 'N/A') . "\n";
        echo "   - template.config.layout: " . ($data['data']['template']['config']['layout'] ?? 'N/A') . "\n";
        echo "   - template.config.components: " . count($data['data']['template']['config']['components'] ?? []) . " components\n\n";
        
        echo "🎨 Design Variables:\n";
        $design = $data['data']['template']['config']['design'] ?? [];
        echo "   Primary Color: " . ($design['colors']['primary'] ?? 'N/A') . "\n";
        echo "   Secondary Color: " . ($design['colors']['secondary'] ?? 'N/A') . "\n";
        echo "   Font Family: " . ($design['typography']['fontFamily'] ?? 'N/A') . "\n\n";
        
        echo "🧩 Components:\n";
        foreach ($data['data']['template']['config']['components'] ?? [] as $comp) {
            $status = $comp['enabled'] ? '✅' : '❌';
            echo "   {$status} {$comp['type']} (id: {$comp['id']})\n";
        }
        echo "\n";
        
        echo "⚡ Features:\n";
        $features = $data['data']['template']['config']['features'] ?? [];
        foreach ($features as $key => $value) {
            $status = $value ? '✅' : '❌';
            echo "   {$status} {$key}\n";
        }
        
    } else {
        echo "❌ API Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📝 Next Steps:\n";
echo "1. Start Laravel: php artisan serve\n";
echo "2. Start Frontend: cd ../menuvibe-frontend && npm run dev\n";
echo "3. Open: http://localhost:3000/menu/{$code}\n";
echo "4. See the dynamic menu render with API config!\n";
