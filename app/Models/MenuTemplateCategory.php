<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuTemplateCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
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
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ===========================================
    // RELATIONSHIPS
    // ===========================================

    public function template(): BelongsTo
    {
        return $this->belongsTo(MenuTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuTemplateItem::class, 'category_id')->orderBy('sort_order');
    }

    // ===========================================
    // SCOPES
    // ===========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ===========================================
    // ACCESSORS
    // ===========================================

    public function getItemCountAttribute(): int
    {
        return $this->items()->count();
    }
}
