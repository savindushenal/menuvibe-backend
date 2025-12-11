<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'franchise_id',
        'name',
        'slug',
        'description',
        'image_url',
        'currency',
        'is_active',
        'is_default',
        'availability_hours',
        'settings',
        'last_synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'availability_hours' => 'array',
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    // ===========================================
    // RELATIONSHIPS
    // ===========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(MenuTemplateCategory::class, 'template_id')->orderBy('sort_order');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuTemplateItem::class, 'template_id');
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(MenuEndpoint::class, 'template_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(MenuOffer::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ===========================================
    // SCOPES
    // ===========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    // ===========================================
    // ACCESSORS
    // ===========================================

    public function getItemCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getCategoryCountAttribute(): int
    {
        return $this->categories()->count();
    }

    public function getEndpointCountAttribute(): int
    {
        return $this->endpoints()->count();
    }

    // ===========================================
    // METHODS
    // ===========================================

    /**
     * Get full menu structure with categories and items
     */
    public function getFullMenu(): array
    {
        $this->load(['categories.items' => function ($query) {
            $query->where('is_available', true)->orderBy('sort_order');
        }]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'currency' => $this->currency,
            'categories' => $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'image_url' => $category->image_url,
                    'icon' => $category->icon,
                    'items' => $category->items->map(function ($item) {
                        return $item->toPublicArray();
                    }),
                ];
            }),
        ];
    }

    /**
     * Duplicate template with all categories and items
     */
    public function duplicate(string $newName = null): MenuTemplate
    {
        $newTemplate = $this->replicate();
        $newTemplate->name = $newName ?? $this->name . ' (Copy)';
        $newTemplate->slug = \Str::slug($newTemplate->name);
        $newTemplate->is_default = false;
        $newTemplate->save();

        foreach ($this->categories as $category) {
            $newCategory = $category->replicate();
            $newCategory->template_id = $newTemplate->id;
            $newCategory->save();

            foreach ($category->items as $item) {
                $newItem = $item->replicate();
                $newItem->template_id = $newTemplate->id;
                $newItem->category_id = $newCategory->id;
                $newItem->save();
            }
        }

        return $newTemplate;
    }
}
