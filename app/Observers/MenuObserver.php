<?php

namespace App\Observers;

use App\Models\Menu;
use Illuminate\Support\Str;

class MenuObserver
{
    /**
     * Handle the Menu "creating" event.
     */
    public function creating(Menu $menu): void
    {
        // Generate slug if not provided
        if (empty($menu->slug)) {
            $menu->slug = Str::slug($menu->name);
            
            // Ensure slug is unique within the location
            $count = 1;
            $originalSlug = $menu->slug;
            while (Menu::where('location_id', $menu->location_id)
                ->where('slug', $menu->slug)
                ->exists()) {
                $menu->slug = $originalSlug . '-' . $count++;
            }
        }

        // Generate public_id if not provided
        if (empty($menu->public_id)) {
            do {
                $menu->public_id = Str::random(12);
            } while (Menu::where('public_id', $menu->public_id)->exists());
        }
    }
}
