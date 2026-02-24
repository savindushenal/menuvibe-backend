<?php

namespace App\Models;

use App\Traits\HasMenuSync;
use App\Traits\HasQRCode;
use App\Traits\HasVersioning;
use App\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Menu extends Model
{
    use HasFactory, TenantAware, HasMenuSync, HasQRCode, HasVersioning;

    protected $fillable = [
        'location_id',
        'franchise_id', // Add franchise_id for TenantAware trait
        'name',
        'slug',
        'public_id',
        'description',
        'style',
        'currency',
        'is_active',
        'sort_order',
        'availability_hours',
        'is_featured',
        'image_url',
        'settings',
        'version', // Add version for HasVersioning trait
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'availability_hours' => 'array',
        'settings' => 'array',
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
     * Get the schedules for this menu (time-based availability)
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(MenuSchedule::class);
    }

    /**
     * Get active schedules for this menu
     */
    public function activeSchedules(): HasMany
    {
        return $this->hasMany(MenuSchedule::class)->where('is_active', true);
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
