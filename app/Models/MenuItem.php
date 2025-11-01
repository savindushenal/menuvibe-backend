<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'category_id',
        'name',
        'description',
        'price',
        'currency',
        'card_color',
        'text_color',
        'heading_color',
        'image_url',
        'is_available',
        'is_featured',
        'sort_order',
        'allergens',
        'dietary_info',
        'preparation_time',
        'is_spicy',
        'spice_level',
        'variations',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_spicy' => 'boolean',
        'sort_order' => 'integer',
        'preparation_time' => 'integer',
        'spice_level' => 'integer',
        'allergens' => 'array',
        'dietary_info' => 'array',
        'variations' => 'array',
    ];

    /**
     * Get the menu that owns the menu item
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get the category that owns the menu item
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
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
     * Scope for ordering items by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope for spicy items
     */
    public function scopeSpicy($query)
    {
        return $query->where('is_spicy', true);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }
}
