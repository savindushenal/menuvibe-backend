<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Menu Endpoints ===\n";
$endpoints = DB::table('menu_endpoints')
    ->leftJoin('menu_templates', 'menu_endpoints.template_id', '=', 'menu_templates.id')
    ->select('menu_endpoints.id', 'menu_endpoints.name', 'menu_endpoints.type', 
             'menu_endpoints.short_code', 'menu_endpoints.is_active',
             'menu_templates.name as template_name')
    ->get();

foreach ($endpoints as $e) {
    echo "ID: {$e->id} | {$e->name} | {$e->type} | Code: {$e->short_code} | Template: " . ($e->template_name ?? 'NONE') . " | Active: " . ($e->is_active ? 'Yes' : 'No') . "\n";
}

echo "\n=== Templates ===\n";
$templates = DB::table('menu_templates')->get(['id', 'name', 'slug', 'is_active']);
foreach ($templates as $t) {
    echo "ID: {$t->id} | {$t->name} | Slug: {$t->slug} | Active: " . ($t->is_active ? 'Yes' : 'No') . "\n";
}

echo "\n=== Template Items Count ===\n";
$counts = DB::table('menu_template_items')
    ->join('menu_templates', 'menu_template_items.template_id', '=', 'menu_templates.id')
    ->select('menu_templates.name', DB::raw('COUNT(*) as item_count'))
    ->groupBy('menu_templates.name')
    ->get();

foreach ($counts as $c) {
    echo "{$c->name}: {$c->item_count} items\n";
}
