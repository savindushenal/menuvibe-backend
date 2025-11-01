<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'description',
        'is_active',
        'sort_order',
        'availability_hours',
        'is_featured',
        'image_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'availability_hours' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Get the location that owns the menu
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the business profile through location (for backward compatibility)
     */
    public function businessProfile()
    {
        return $this->location->user->businessProfile();
    }

    /**
     * Get the menu items for the menu
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /**
     * Get the categories for the menu
     */
    public function categories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('sort_order');
    }

    /**
     * Get active categories for the menu
     */
    public function activeCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get available menu items
     */
    public function availableMenuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->where('is_available', true)->orderBy('sort_order');
    }

    /**
     * Get featured menu items
     */
    public function featuredMenuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->where('is_featured', true)->orderBy('sort_order');
    }

    /**
     * Scope for active menus
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured menus
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for ordering menus by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
