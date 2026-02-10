<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ISSO FRANCHISE CONFIGURATION ===\n\n";

$franchise = DB::table('franchises')->where('id', 4)->first();

if ($franchise) {
    echo "Franchise: {$franchise->name}\n";
    echo "Slug: {$franchise->slug}\n";
    echo "Current template_type column: " . ($franchise->template_type ?? 'NULL') . "\n\n";
    
    if (!isset($franchise->template_type) || $franchise->template_type !== 'isso') {
        echo "⚠️  FIXING: Need to set template_type to 'isso'\n\n";
        
        DB::table('franchises')
            ->where('id', 4)
            ->update(['template_type' => 'isso']);
        
        $updated = DB::table('franchises')->where('id', 4)->first();
        echo "✅ Updated! template_type is now: {$updated->template_type}\n";
    } else {
        echo "✅ template_type is already 'isso' - Custom isso-seafood template will load\n";
    }
} else {
    echo "❌ Isso franchise not found!\n";
}

echo "\n=== HOW IT WORKS ===\n";
echo "1. Franchise table has template_type column\n";
echo "2. When template_type = 'isso', app loads templates/isso-seafood/MenuView.tsx\n";
echo "3. When template_type = 'barista', app loads templates/barista-style/MenuView.tsx\n";
echo "4. When template_type = 'premium', app loads built-in premium template\n";
