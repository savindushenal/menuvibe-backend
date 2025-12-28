<?php

namespace App\Traits;

use App\Models\Location;
use App\Models\Menu;

trait HasMenuSync
{
    /**
     * Sync this menu to specified locations
     */
    public function syncToLocations(array $locationIds): array
    {
        $synced = [];
        
        foreach ($locationIds as $locationId) {
            $synced[] = $this->duplicateTo($locationId);
        }
        
        return $synced;
    }

    /**
     * Duplicate this menu to a location
     */
    public function duplicateTo(int $locationId): self
    {
        $newMenu = $this->replicate();
        $newMenu->location_id = $locationId;
        $newMenu->save();

        // Copy categories
        foreach ($this->categories as $category) {
            $newCategory = $category->replicate();
            $newCategory->menu_id = $newMenu->id;
            $newCategory->save();
        }

        // Copy items
        foreach ($this->menuItems as $item) {
            $newItem = $item->replicate();
            $newItem->menu_id = $newMenu->id;
            $newItem->save();
        }

        return $newMenu;
    }

    /**
     * Check if menu can be synced
     */
    public function canSync(): bool
    {
        return $this->is_active && $this->menuItems()->count() > 0;
    }
}
