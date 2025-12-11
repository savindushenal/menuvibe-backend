<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MasterMenuCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_menu_id',
        'name',
        'slug',
        'description',
        'image_url',
        'icon',
        'background_color',
        'text_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get the master menu that owns the category
     */
    public function masterMenu(): BelongsTo
    {
        return $this->belongsTo(MasterMenu::class);
    }

    /**
     * Get the items for this category
     */
    public function items(): HasMany
    {
        return $this->hasMany(MasterMenuItem::class, 'category_id')->orderBy('sort_order');
    }

    /**
     * Get available items
     */
    public function availableItems(): HasMany
    {
        return $this->hasMany(MasterMenuItem::class, 'category_id')
            ->where('is_available', true)
            ->orderBy('sort_order');
    }

    /**
     * Get items count
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }
}
