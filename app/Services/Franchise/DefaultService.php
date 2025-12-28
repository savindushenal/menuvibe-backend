<?php

namespace App\Services\Franchise;

use App\Models\MenuItem;

class DefaultService implements FranchiseServiceInterface
{
    public function getCustomMenuFields(): array
    {
        return []; // No custom fields for default
    }

    public function validateMenuItem(MenuItem $item): bool
    {
        return true; // Basic validation only
    }

    public function processMenuItem(MenuItem $item): void
    {
        // Standard processing - no special logic
    }
}
