<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TESTING FRANCHISE SETTINGS ===\n\n";

// Get isso franchise
$franchise = DB::table('franchises')->where('slug', 'isso')->first();

if (!$franchise) {
    echo "❌ Isso franchise not found\n";
    exit(1);
}

echo "Franchise: {$franchise->name}\n";
echo "Slug: {$franchise->slug}\n";
echo "ID: {$franchise->id}\n\n";

echo "=== GENERAL INFO ===\n";
echo "Name: " . ($franchise->name ?? 'NULL') . "\n";
echo "Description: " . ($franchise->description ?? 'NULL') . "\n";
echo "Support Email: " . ($franchise->support_email ?? 'NULL') . "\n";
echo "Support Phone: " . ($franchise->support_phone ?? 'NULL') . "\n";
echo "Website URL: " . ($franchise->website_url ?? 'NULL') . "\n\n";

echo "=== BRANDING ===\n";
echo "Template Type: " . ($franchise->template_type ?? 'NULL') . "\n";
echo "Primary Color: " . ($franchise->primary_color ?? 'NULL') . "\n";
echo "Secondary Color: " . ($franchise->secondary_color ?? 'NULL') . "\n";
echo "Logo URL: " . ($franchise->logo_url ?? 'NULL') . "\n\n";

echo "=== DESIGN TOKENS ===\n";
if ($franchise->design_tokens) {
    $tokens = json_decode($franchise->design_tokens, true);
    echo "Brand Name: " . ($tokens['brand']['name'] ?? 'NULL') . "\n";
    echo "Brand Tagline: " . ($tokens['brand']['tagline'] ?? 'NULL') . "\n";
    echo "Brand Logo: " . ($tokens['brand']['logo'] ?? 'NULL') . "\n";
    echo "Colors:\n";
    if (isset($tokens['colors'])) {
        foreach ($tokens['colors'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    }
    echo "Contact:\n";
    if (isset($tokens['contact'])) {
        foreach ($tokens['contact'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    }
} else {
    echo "No design tokens found\n";
}

echo "\n=== FRANCHISE SETTINGS ===\n";
if ($franchise->settings) {
    $settings = json_decode(json_encode($franchise->settings), true);
    echo "Address: " . ($settings['address'] ?? 'NULL') . "\n";
    echo "City: " . ($settings['city'] ?? 'NULL') . "\n";
    echo "State: " . ($settings['state'] ?? 'NULL') . "\n";
    echo "Country: " . ($settings['country'] ?? 'NULL') . "\n";
    echo "Postal Code: " . ($settings['postal_code'] ?? 'NULL') . "\n";
    echo "Timezone: " . ($settings['timezone'] ?? 'NULL') . "\n";
    echo "Currency: " . ($settings['currency'] ?? 'NULL') . "\n";
    echo "Allow Branch Customization: " . (($settings['allow_branch_customization'] ?? false) ? 'Yes' : 'No') . "\n";
    echo "Require Menu Approval: " . (($settings['require_menu_approval'] ?? false) ? 'Yes' : 'No') . "\n";
    echo "Auto Sync Pricing: " . (($settings['auto_sync_pricing'] ?? false) ? 'Yes' : 'No') . "\n";
    echo "Notification Email: " . ($settings['notification_email'] ?? 'NULL') . "\n";
} else {
    echo "No franchise settings found\n";
}

echo "\n=== TEST SUMMARY ===\n";
$issues = [];

if (empty($franchise->template_type)) {
    $issues[] = "❌ Template type not set";
} else {
    echo "✅ Template type: {$franchise->template_type}\n";
}

if (empty($franchise->design_tokens)) {
    $issues[] = "❌ Design tokens not set";
} else {
    echo "✅ Design tokens configured\n";
}

$tokens = json_decode($franchise->design_tokens, true);
if (!empty($tokens['colors']['primary']) && !empty($tokens['colors']['secondary'])) {
    echo "✅ Brand colors configured\n";
} else {
    $issues[] = "❌ Brand colors missing in design_tokens";
}

if (!empty($franchise->settings)) {
    echo "✅ Franchise settings configured\n";
} else {
    $issues[] = "⚠️  Franchise settings empty (will use defaults)";
}

if (count($issues) > 0) {
    echo "\n=== ISSUES FOUND ===\n";
    foreach ($issues as $issue) {
        echo $issue . "\n";
    }
} else {
    echo "\n✅ All settings properly configured!\n";
}

