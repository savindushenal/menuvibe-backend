<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations - Migrate existing Menu records to MenuTemplate
     */
    public function up(): void
    {
        // Get all existing menus that haven't been migrated
        $menus = DB::table('menus')
            ->leftJoin('locations', 'menus.location_id', '=', 'locations.id')
            ->select('menus.*', 'locations.user_id')
            ->get();

        foreach ($menus as $menu) {
            // Check if template already exists with same slug for this user
            $existingTemplate = DB::table('menu_templates')
                ->where('user_id', $menu->user_id)
                ->where('slug', $menu->slug)
                ->first();

            if ($existingTemplate) {
                // Skip if already migrated
                continue;
            }

            // Create template from menu
            $templateId = DB::table('menu_templates')->insertGetId([
                'user_id' => $menu->user_id,
                'location_id' => $menu->location_id,
                'name' => $menu->name,
                'slug' => $menu->slug ?: Str::slug($menu->name) . '-' . Str::random(6),
                'description' => $menu->description,
                'image_url' => $menu->image_url,
                'currency' => $menu->currency ?? 'USD',
                'is_active' => $menu->is_active ?? true,
                'is_default' => $menu->is_featured ?? false,
                'availability_hours' => $menu->availability_hours,
                'settings' => $menu->settings,
                'created_by' => $menu->user_id,
                'created_at' => $menu->created_at,
                'updated_at' => $menu->updated_at,
            ]);

            // Get categories for this menu
            $categories = DB::table('menu_categories')
                ->where('menu_id', $menu->id)
                ->get();

            foreach ($categories as $category) {
                // Create template category
                $categoryId = DB::table('menu_template_categories')->insertGetId([
                    'template_id' => $templateId,
                    'name' => $category->name,
                    'slug' => Str::slug($category->name),
                    'description' => $category->description,
                    'image_url' => null,
                    'sort_order' => $category->sort_order ?? 0,
                    'is_active' => $category->is_active ?? true,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ]);

                // Get items for this category
                $items = DB::table('menu_items')
                    ->where('category_id', $category->id)
                    ->get();

                foreach ($items as $item) {
                    // Create template item
                    DB::table('menu_template_items')->insert([
                        'template_id' => $templateId,
                        'category_id' => $categoryId,
                        'name' => $item->name,
                        'slug' => Str::slug($item->name) . '-' . Str::random(4),
                        'description' => $item->description,
                        'price' => $item->price ?? 0,
                        'image_url' => $item->image_url,
                        'is_available' => $item->is_available ?? true,
                        'is_featured' => $item->is_featured ?? false,
                        'sort_order' => $item->sort_order ?? 0,
                        'allergens' => $item->allergens ?? null,
                        'dietary_info' => $item->dietary_info ?? null,
                        'preparation_time' => $item->preparation_time ?? null,
                        'variations' => $item->variations ?? null,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data migration, no schema changes to reverse
        // Templates created from this migration could be deleted if needed
    }
};
