<?php
$pdo = new PDO('mysql:host=uniform.de.hostns.io;dbname=menuVibe_prod', 'menuVibe_user', 'menuVibe@2025');

// Find Isso franchise
$franchise = $pdo->query('SELECT * FROM franchises WHERE slug = "isso"')->fetch(PDO::FETCH_ASSOC);
echo "Isso Franchise ID: " . $franchise['id'] . "\n\n";

// Find Isso locations
$locations = $pdo->query('SELECT id, name FROM locations WHERE franchise_id = ' . $franchise['id'])->fetchAll(PDO::FETCH_ASSOC);

echo "Isso Locations:\n";
foreach($locations as $loc) {
  echo "  - ID: {$loc['id']}, Name: {$loc['name']}\n";
  
  // Find menus for this location
  $menus = $pdo->query('SELECT id, name, template_key FROM menus WHERE location_id = ' . $loc['id'])->fetchAll(PDO::FETCH_ASSOC);
  echo "    Menus:\n";
  foreach($menus as $menu) {
    echo "      - ID: {$menu['id']}, Name: {$menu['name']}, Template: {$menu['template_key']}\n";
  }
}
?>
