<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Updating Isso Franchise template_type\n";
echo "========================================\n\n";

$franchise = App\Models\Franchise::where('slug', 'isso')->first();

if (!$franchise) {
    echo "❌ Isso franchise not found!\n";
    exit(1);
}

echo "BEFORE UPDATE:\n";
echo "  Franchise: {$franchise->name}\n";
echo "  template_type: " . ($franchise->template_type ?? 'NULL') . "\n";
echo "  design_tokens.template: " . ($franchise->design_tokens['template'] ?? 'N/A') . "\n\n";

// Update the franchise to use 'isso' template_type
$franchise->template_type = 'isso';
$franchise->save();

echo "AFTER UPDATE:\n";
$franchise->refresh();
echo "  template_type: {$franchise->template_type}\n";
echo "  design_tokens.template: " . ($franchise->design_tokens['template'] ?? 'N/A') . "\n\n";

echo "✅ Franchise updated successfully!\n\n";
echo "The IYZFQY endpoint will now:\n";
echo "  1. Return franchise.template_type = 'isso' in API response\n";
echo "  2. Frontend will detect isFranchise = true\n";
echo "  3. Frontend will use templateType = 'isso'\n";
echo "  4. Route to isso-seafood template component\n";
