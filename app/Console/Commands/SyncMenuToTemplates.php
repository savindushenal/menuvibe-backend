<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncMenuToTemplates extends Command
{
    protected $signature = 'menus:sync-to-templates {--force : Force sync even if data exists}';
    protected $description = 'Sync old menu data to new template system';

    public function handle()
    {
        $this->info('Starting menu to template sync...');

        // Get mapping of old menus to new templates by slug
        $menus = DB::table('menus')->get();
        $templates = DB::table('menu_templates')->get()->keyBy('slug');

        $synced = 0;
        $errors = 0;

        foreach ($menus as $menu) {
            $this->info("\nProcessing menu: {$menu->name} (ID: {$menu->id})");

            // Find matching template by slug
            $template = $templates->get($menu->slug);

            if (!$template) {
                $this->warn("  No matching template found for menu slug: {$menu->slug}");
                continue;
            }

            $this->info("  Found template ID: {$template->id}");

            // Get categories for this menu
            $categories = DB::table('menu_categories')
                ->where('menu_id', $menu->id)
                ->get();

            foreach ($categories as $category) {
                $this->info("  Processing category: {$category->name}");

                // Check if category already exists in template
                $existingCategory = DB::table('menu_template_categories')
                    ->where('template_id', $template->id)
                    ->where('name', $category->name)
                    ->first();

                if ($existingCategory) {
                    $templateCategoryId = $existingCategory->id;
                    $this->info("    Category already exists (ID: {$templateCategoryId})");
                } else {
                    // Create category
                    $templateCategoryId = DB::table('menu_template_categories')->insertGetId([
                        'template_id' => $template->id,
                        'name' => $category->name,
                        'slug' => Str::slug($category->name) . '-' . Str::random(4),
                        'description' => $category->description,
                        'background_color' => $category->background_color,
                        'text_color' => $category->text_color,
                        'sort_order' => $category->sort_order,
                        'is_active' => $category->is_active,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->info("    Created category (ID: {$templateCategoryId})");
                }

                // Get items for this category
                $items = DB::table('menu_items')
                    ->where('category_id', $category->id)
                    ->get();

                foreach ($items as $item) {
                    // Check if item already exists
                    $existingItem = DB::table('menu_template_items')
                        ->where('template_id', $template->id)
                        ->where('category_id', $templateCategoryId)
                        ->where('name', $item->name)
                        ->first();

                    if ($existingItem) {
                        $this->info("      Item '{$item->name}' already exists, skipping");
                        continue;
                    }

                    // Create item
                    DB::table('menu_template_items')->insert([
                        'template_id' => $template->id,
                        'category_id' => $templateCategoryId,
                        'name' => $item->name,
                        'slug' => Str::slug($item->name) . '-' . Str::random(4),
                        'description' => $item->description,
                        'price' => $item->price,
                        'compare_at_price' => null,
                        'image_url' => $item->image_url,
                        'is_available' => $item->is_available ?? 1,
                        'is_featured' => $item->is_featured ?? 0,
                        'sort_order' => $item->sort_order ?? 0,
                        'allergens' => $item->allergens,
                        'dietary_info' => $item->dietary_info,
                        'is_spicy' => $item->is_spicy ?? 0,
                        'spice_level' => $item->spice_level,
                        'preparation_time' => $item->preparation_time,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->info("      Created item: {$item->name}");
                    $synced++;
                }
            }
        }

        $this->info("\n✅ Sync completed! Items synced: {$synced}");

        // Show final counts
        $this->info("\nFinal counts:");
        $this->info("  Templates: " . DB::table('menu_templates')->count());
        $this->info("  Categories: " . DB::table('menu_template_categories')->count());
        $this->info("  Items: " . DB::table('menu_template_items')->count());

        return 0;
    }
}
