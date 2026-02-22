<?php
$pdo = new PDO('mysql:host=uniform.de.hostns.io;dbname=menuVibe_prod', 'menuVibe_user', 'menuVibe@2025');

// Check template items
$items = $pdo->query('SELECT id, name, variations FROM menu_template_items WHERE name LIKE "%Hot Butter%" OR name LIKE "%Black Pepper%"')->fetchAll(PDO::FETCH_ASSOC);

echo "=== TEMPLATE ITEMS ===\n";
foreach($items as $item) {
  echo "Item: " . $item['name'] . "\n";
  echo "Has variations: " . ($item['variations'] ? 'YES' : 'NO') . "\n";
  if ($item['variations']) {
    $vars = json_decode($item['variations'], true);
    echo "  Sections: " . count($vars) . "\n";
  }
  echo "\n";
}

// Check menu items
$items2 = $pdo->query('SELECT id, name, variations FROM menu_items WHERE name LIKE "%Hot Butter%" OR name LIKE "%Black Pepper%"')->fetchAll(PDO::FETCH_ASSOC);

echo "=== MENU ITEMS ===\n";
foreach($items2 as $item) {
  echo "Item: " . $item['name'] . "\n";
  echo "Has variations: " . ($item['variations'] ? 'YES' : 'NO') . "\n";
  if ($item['variations']) {
    $vars = json_decode($item['variations'], true);
    echo "  Sections: " . count($vars) . "\n";
  }
  echo "\n";
}
?>
