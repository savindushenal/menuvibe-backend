<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Fixing Isso Menu Template\n";
echo "========================\n\n";

$template = App\Models\MenuTemplate::where('franchise_id', 4)
    ->where('name', 'Isso Main Menu')
    ->first();

if (!$template) {
    echo "❌ Isso Main Menu template not found\n";
    exit(1);
}

echo "Before:\n";
echo "  template_type: " . ($template->settings['template_type'] ?? 'null') . "\n\n";

// Update settings to use 'premium' template which exists in frontend
$settings = $template->settings ?? [];
$settings['template_type'] = 'premium'; // Use premium restaurant template
$template->settings = $settings;
$template->save();

echo "After:\n";
echo "  template_type: {$template->settings['template_type']}\n\n";

echo "✅ Template updated successfully!\n\n";

echo "The isso design tokens (colors, branding) will still apply:\n";
echo "  - Primary Color:   #FF6B35 (Coral/Orange)\n";
echo "  - Secondary Color: #004E89 (Deep Blue)\n";
echo "  - Accent Color:    #F77F00 (Warm Orange)\n\n";

echo "Now the menu will display with the Premium template styled with Isso colors!\n";
