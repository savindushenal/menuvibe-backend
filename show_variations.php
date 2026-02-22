<?php
$pdo = new PDO('mysql:host=uniform.de.hostns.io;dbname=menuVibe_prod', 'menuVibe_user', 'menuVibe@2025');

// Test all items to show dynamic variations
$items = $pdo->query('SELECT id, name, variations FROM menu_template_items WHERE template_id = 4 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

echo "=== ISSO/DEMO TEMPLATE ITEMS (DYNAMIC VARIATIONS) ===\n\n";
foreach($items as $item) {
  echo "📌 Item: " . $item['name'] . "\n";
  
  if ($item['variations']) {
    $vars = json_decode($item['variations'], true);
    if (count($vars) > 0) {
      echo "   ✅ HAS CUSTOMIZATIONS (" . count($vars) . " sections)\n";
      foreach($vars as $section) {
        echo "      ├─ " . $section['name'] . " [Required: " . ($section['required'] ? 'YES' : 'OPTIONAL') . "] - " . count($section['options']) . " options\n";
      }
    } else {
      echo "   ❌ NO CUSTOMIZATIONS (fixed item)\n";
    }
  } else {
    echo "   ❌ NO CUSTOMIZATIONS (fixed item)\n";
  }
  echo "\n";
}
?>

