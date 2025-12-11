<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_id',
        'item_id',
        'price_override',
        'is_available',
        'is_featured',
        'notes',
    ];

    protected $casts = [
        'price_override' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
    ];

    // ===========================================
    // RELATIONSHIPS
    // ===========================================

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(MenuEndpoint::class, 'endpoint_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuTemplateItem::class, 'item_id');
    }
}
