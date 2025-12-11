<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MasterMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'name',
        'slug',
        'description',
        'image_url',
        'currency',
        'availability_hours',
        'settings',
        'is_active',
        'is_default',
        'sort_order',
        'last_synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'availability_hours' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($menu) {
            if (empty($menu->slug)) {
                $menu->slug = Str::slug($menu->name);
            }
        });
    }

    /**
     * Get the franchise that owns the menu
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Get the categories for this menu
     */
    public function categories(): HasMany
    {
        return $this->hasMany(MasterMenuCategory::class)->orderBy('sort_order');
    }

    /**
     * Get active categories
     */
    public function activeCategories(): HasMany
    {
        return $this->hasMany(MasterMenuCategory::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Get all items for this menu
     */
    public function items(): HasMany
    {
        return $this->hasMany(MasterMenuItem::class)->orderBy('sort_order');
    }

    /**
     * Get available items
     */
    public function availableItems(): HasMany
    {
        return $this->hasMany(MasterMenuItem::class)
            ->where('is_available', true)
            ->orderBy('sort_order');
    }

    /**
     * Get offers for this menu
     */
    public function offers(): HasMany
    {
        return $this->hasMany(MasterMenuOffer::class);
    }

    /**
     * Get active offers
     */
    public function activeOffers(): HasMany
    {
        return $this->hasMany(MasterMenuOffer::class)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order');
    }

    /**
     * Get sync logs for this menu
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(MenuSyncLog::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the user who created this menu
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this menu
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get total items count
     */
    public function getTotalItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get total categories count
     */
    public function getTotalCategoriesCountAttribute(): int
    {
        return $this->categories()->count();
    }
}
