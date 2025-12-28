<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuService
{
    /**
     * Sync menu items to multiple locations
     * Works for ALL franchises automatically
     */
    public function syncMenuToLocations(Menu $masterMenu, array $locationIds): array
    {
        $results = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($locationIds as $locationId) {
                $results[] = $this->duplicateMenuToLocation($masterMenu, $locationId);
            }
            
            DB::commit();
            
            Log::info("Menu synced successfully", [
                'menu_id' => $masterMenu->id,
                'location_count' => count($locationIds),
            ]);
            
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Menu sync failed: ' . $e->getMessage(), [
                'menu_id' => $masterMenu->id,
                'locations' => $locationIds,
            ]);
            
            throw $e;
        }
    }

    /**
     * Duplicate menu to a specific location
     */
    private function duplicateMenuToLocation(Menu $sourceMenu, int $locationId): Menu
    {
        // Create new menu
        $newMenu = $sourceMenu->replicate();
        $newMenu->location_id = $locationId;
        $newMenu->save();

        // Copy categories
        foreach ($sourceMenu->categories as $category) {
            $newCategory = $category->replicate();
            $newCategory->menu_id = $newMenu->id;
            $newCategory->save();
        }

        // Copy all items
        foreach ($sourceMenu->menuItems as $item) {
            $newItem = $item->replicate();
            $newItem->menu_id = $newMenu->id;
            $newItem->save();
        }

        return $newMenu;
    }

    /**
     * Calculate final price with franchise-specific tax
     */
    public function calculateFinalPrice(MenuItem $item, ?float $taxRate = null): float
    {
        $franchise = auth()->user()->franchise;
        $config = config("franchise.{$franchise->slug}", config('franchise.default'));
        
        $taxRate = $taxRate ?? $config['tax_rate'];
        
        return round($item->price * (1 + $taxRate), 2);
    }

    /**
     * Apply franchise-specific business rules
     */
    public function validateMenuItem(array $data): array
    {
        $franchise = auth()->user()->franchise;
        $config = config("franchise.{$franchise->slug}", config('franchise.default'));
        
        // Add franchise-specific required fields
        $requiredFields = array_merge(
            ['name', 'price', 'menu_id'],
            $config['required_menu_fields'] ?? []
        );
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Field {$field} is required for this franchise");
            }
        }
        
        return $data;
    }

    /**
     * Generate QR code for any menu
     */
    public function generateQRCode(Location $location): string
    {
        $url = route('menu.view', [
            'franchise' => $location->franchise->slug,
            'location' => $location->slug
        ]);
        
        return \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(300)
            ->generate($url);
    }

    /**
     * Get custom menu fields for current franchise
     */
    public function getCustomFields(): array
    {
        $franchise = auth()->user()->franchise;
        $config = config("franchise.{$franchise->slug}", config('franchise.default'));
        
        return $config['custom_fields'] ?? [];
    }
}
