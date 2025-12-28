<?php

namespace App\Services\Franchise;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Log;

class PizzaHutService implements FranchiseServiceInterface
{
    public function getCustomMenuFields(): array
    {
        return [
            'crust_type' => ['thin', 'thick', 'stuffed', 'cheese_burst'],
            'size' => ['personal', 'medium', 'large', 'family'],
            'extra_toppings' => ['cheese', 'pepperoni', 'mushrooms', 'olives'],
        ];
    }

    public function validateMenuItem(MenuItem $item): bool
    {
        // Pizza must have crust type and size
        $customData = is_string($item->custom_data) 
            ? json_decode($item->custom_data, true) 
            : $item->custom_data;
            
        return isset($customData['crust_type']) && isset($customData['size']);
    }

    public function processMenuItem(MenuItem $item): void
    {
        // Pizza Hut specific processing
        Log::info("Processing Pizza Hut menu item: {$item->name}");
        
        // Add loyalty points calculation
        $customData = is_string($item->custom_data) 
            ? json_decode($item->custom_data, true) 
            : ($item->custom_data ?? []);
            
        $customData['loyalty_points'] = round($item->price * 0.1);
        
        $item->custom_data = $customData;
        $item->save();
    }
}
