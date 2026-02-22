<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'compare_at_price',
        'currency',
        'image_url',
        'icon',
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
        'calories',
        'is_spicy',
        'spice_level',
        'variations',
        'customizations',
        'addons',
        'sku',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_spicy' => 'boolean',
        'sort_order' => 'integer',
        'preparation_time' => 'integer',
        'calories' => 'integer',
        'spice_level' => 'integer',
        'gallery_images' => 'array',
        'allergens' => 'array',
        'dietary_info' => 'array',
        'variations' => 'array',
        'customizations' => 'array',
        'addons' => 'array',
    ];

    // ===========================================
    // RELATIONSHIPS
    // ===========================================

    public function template(): BelongsTo
    {
        return $this->belongsTo(MenuTemplate::class, 'template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuTemplateCategory::class, 'category_id');
    }

    public function endpointOverrides(): HasMany
    {
        return $this->hasMany(EndpointOverride::class, 'item_id');
    }

    // ===========================================
    // SCOPES
    // ===========================================

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ===========================================
    // ACCESSORS
    // ===========================================

    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    public function getSavingsAttribute(): ?float
    {
        if ($this->is_on_sale) {
            return $this->compare_at_price - $this->price;
        }
        return null;
    }

    public function getSavingsPercentAttribute(): ?int
    {
        if ($this->is_on_sale && $this->compare_at_price > 0) {
            return (int) round(($this->savings / $this->compare_at_price) * 100);
        }
        return null;
    }

    // ===========================================
    // METHODS
    // ===========================================

    /**
     * Get price for a specific endpoint (with override)
     */
    public function getPriceForEndpoint(int $endpointId): float
    {
        $override = $this->endpointOverrides()
            ->where('endpoint_id', $endpointId)
            ->first();

        if ($override && $override->price_override !== null) {
            return $override->price_override;
        }

        return $this->price;
    }

    /**
     * Check availability for a specific endpoint
     */
    public function isAvailableForEndpoint(int $endpointId): bool
    {
        $override = $this->endpointOverrides()
            ->where('endpoint_id', $endpointId)
            ->first();

        if ($override && $override->is_available !== null) {
            return $override->is_available;
        }

        return $this->is_available;
    }

    /**
     * Convert to public-facing array
     */
    public function toPublicArray(int $endpointId = null): array
    {
        $price = $endpointId ? $this->getPriceForEndpoint($endpointId) : $this->price;
        $available = $endpointId ? $this->isAvailableForEndpoint($endpointId) : $this->is_available;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $price,
            'compare_at_price' => $this->compare_at_price,
            'is_on_sale' => $this->is_on_sale,
            'savings_percent' => $this->savings_percent,
            'image_url' => $this->image_url,
            'icon' => $this->icon,
            'is_available' => $available,
            'is_featured' => $this->is_featured,
            'is_spicy' => $this->is_spicy,
            'spice_level' => $this->spice_level,
            'preparation_time' => $this->preparation_time,
            'calories' => $this->calories,
            'allergens' => $this->allergens,
            'dietary_info' => $this->dietary_info,
            'variations' => $this->variations,
            'customizations' => $this->customizations,
            'addons' => $this->addons,
        ];
    }
}
