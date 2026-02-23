<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MasterMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_menu_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'compare_at_price',
        'currency',
        'image_url',
        'gallery_images',
        'card_color',
        'text_color',
        'heading_color',
        'is_available',
        'is_featured',
        'sort_order',
        'allergens',
        'dietary_info',
        'preparation_time',
        'is_spicy',
        'spice_level',
        'variations',
        'customizations',
        'addons',
        'sku',
        'calories',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'gallery_images' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_spicy' => 'boolean',
        'sort_order' => 'integer',
        'preparation_time' => 'integer',
        'spice_level' => 'integer',
        'calories' => 'integer',
        'allergens' => 'array',
        'dietary_info' => 'array',
        'variations' => 'array',
        'customizations' => 'array',
        'addons' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->slug)) {
                $item->slug = Str::slug($item->name);
            }
        });
    }

    /**
     * Get the master menu that owns the item
     */
    public function masterMenu(): BelongsTo
    {
        return $this->belongsTo(MasterMenu::class);
    }

    /**
     * Get the category that owns the item
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MasterMenuCategory::class, 'category_id');
    }

    /**
     * Get branch overrides for this item
     */
    public function branchOverrides(): HasMany
    {
        return $this->hasMany(BranchMenuOverride::class, 'master_item_id');
    }

    /**
     * Get the price for a specific branch (with override if exists)
     */
    public function getPriceForBranch($branchId): float
    {
        $override = $this->branchOverrides()->where('branch_id', $branchId)->first();
        
        if ($override && $override->price_override !== null) {
            return (float) $override->price_override;
        }
        
        return (float) $this->price;
    }

    /**
     * Check availability for a specific branch
     */
    public function isAvailableForBranch($branchId): bool
    {
        $override = $this->branchOverrides()->where('branch_id', $branchId)->first();
        
        if ($override) {
            return $override->is_available;
        }
        
        return $this->is_available;
    }

    /**
     * Scope for available items
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope for featured items
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Check if item has discount
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->has_discount) {
            return null;
        }
        
        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }
}
