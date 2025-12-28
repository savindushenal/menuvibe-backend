<?php

namespace App\Services\Franchise;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Log;

class BaristaService implements FranchiseServiceInterface
{
    public function getCustomMenuFields(): array
    {
        return [
            'milk_type' => ['regular', 'soy', 'almond', 'oat'],
            'sugar_level' => ['none', 'low', 'medium', 'high'],
            'temperature' => ['hot', 'cold', 'iced'],
            'shot_count' => [1, 2, 3],
        ];
    }

    public function validateMenuItem(MenuItem $item): bool
    {
        // Coffee must have milk type and temperature
        if ($item->category === 'coffee') {
            $customData = is_string($item->custom_data) 
                ? json_decode($item->custom_data, true) 
                : $item->custom_data;
                
            return isset($customData['milk_type']) && isset($customData['temperature']);
        }
        
        return true;
    }

    public function processMenuItem(MenuItem $item): void
    {
        // Barista specific processing
        Log::info("Processing Barista menu item: {$item->name}");
        
        // Add rewards points for coffee items
        if ($item->category === 'coffee') {
            $customData = is_string($item->custom_data) 
                ? json_decode($item->custom_data, true) 
                : ($item->custom_data ?? []);
                
            $customData['reward_points'] = round($item->price * 0.05);
            
            $item->custom_data = $customData;
            $item->save();
        }
    }
}
