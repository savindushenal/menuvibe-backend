<?php

namespace App\Services\Franchise;

use App\Models\MenuItem;

interface FranchiseServiceInterface
{
    /**
     * Get custom menu fields for this franchise
     */
    public function getCustomMenuFields(): array;
    
    /**
     * Validate menu item according to franchise rules
     */
    public function validateMenuItem(MenuItem $item): bool;
    
    /**
     * Process menu item with franchise-specific logic
     */
    public function processMenuItem(MenuItem $item): void;
}
