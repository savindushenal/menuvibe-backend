<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Updating Isso Menu Template to use Isso Seafood Template\n";
echo "========================================================\n\n";

$template = App\Models\MenuTemplate::where('franchise_id', 4)
    ->where('name', 'Isso Main Menu')
    ->first();

if (!$template) {
    echo "❌ Isso Main Menu template not found\n";
    exit(1);
}

echo "Before:\n";
echo "  template_type: " . ($template->settings['template_type'] ?? 'null') . "\n\n";

// Update settings to use dedicated 'isso' template
$settings = $template->settings ?? [];
$settings['template_type'] = 'isso'; // Use dedicated isso-seafood template
$template->settings = $settings;
$template->save();

echo "After:\n";
echo "  template_type: {$template->settings['template_type']}\n\n";

echo "✅ Template updated successfully!\n\n";

echo "The Isso menu will now use the dedicated Isso Seafood template with:\n";
echo "  - Ocean-themed header with coral/blue gradient\n";
echo "  - Category navigation with seafood icons\n";
echo "  - Grid layout optimized for seafood dishes\n";
echo "  - Isso design tokens (colors, branding, contact info)\n";
echo "  - Shopping cart with quantity controls\n\n";

echo "Frontend template: isso-seafood\n";
echo "Template key in router: 'isso-seafood'\n";
echo "Component: templates/isso-seafood/MenuView.tsx\n";
