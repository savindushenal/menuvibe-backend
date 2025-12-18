<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'name',
        'slug',
        'description',
        'image_url',
        'icon',
        'background_color',
        'text_color',
        'heading_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the menu that owns the category.
     */
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get the menu items for the category.
     */
    public function menuItems()
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }

    /**
     * Alias for menuItems (for consistency with master menu categories)
     */
    public function items()
    {
        return $this->hasMany(MenuItem::class, 'category_id');
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
