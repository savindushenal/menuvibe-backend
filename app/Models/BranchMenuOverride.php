<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMenuOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'master_item_id',
        'price_override',
        'is_available',
        'is_featured',
        'variation_prices',
        'notes',
    ];

    protected $casts = [
        'price_override' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'variation_prices' => 'array',
    ];

    /**
     * Get the location (branch)
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the master item being overridden
     */
    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(MasterMenuItem::class, 'master_item_id');
    }

    /**
     * Check if this is a price override
     */
    public function getHasPriceOverrideAttribute(): bool
    {
        return $this->price_override !== null;
    }

    /**
     * Get the effective price (override or original)
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->price_override !== null) {
            return (float) $this->price_override;
        }
        
        return (float) $this->masterItem->price;
    }
}
