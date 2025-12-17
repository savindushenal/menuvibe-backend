<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Assigning Premium Restaurant Template ===\n\n";

// Get the endpoint
$endpoint = \App\Models\MenuEndpoint::where('short_code', 'ZISYSS')->first();

if (!$endpoint) {
    echo "❌ Endpoint not found\n";
    exit;
}

echo "📋 Current Setup:\n";
echo "   Code: {$endpoint->short_code}\n";
echo "   Name: {$endpoint->name}\n";
echo "   Current Template: " . ($endpoint->template_key ?? 'default') . "\n\n";

// Assign premium template
$endpoint->template_key = 'premium-restaurant';
$endpoint->save();

echo "✅ Updated to Premium Restaurant Template!\n\n";

echo "🌐 View URLs:\n";
echo "   Default: http://localhost:3000/menu/ZISYSS (will load premium-restaurant)\n";
echo "   API Config: http://localhost:8000/api/menu/ZISYSS/config\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "🎨 Template Features:\n";
echo "   ✅ Full-screen hero with background\n";
echo "   ✅ Search and category filter\n";
echo "   ✅ Featured items section\n";
echo "   ✅ Elegant menu cards\n";
echo "   ✅ Floating cart sidebar\n";
echo "   ✅ Quantity controls\n";
echo "   ✅ Restaurant info display\n\n";

echo "🔄 To switch back to default:\n";
echo "   \$endpoint->template_key = 'default';\n";
echo "   \$endpoint->save();\n";
