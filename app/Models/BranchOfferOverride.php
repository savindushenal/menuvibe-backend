<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOfferOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'master_offer_id',
        'is_active',
        'discount_override',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'discount_override' => 'decimal:2',
    ];

    /**
     * Get the branch that owns the override (now Location)
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the master offer being overridden
     */
    public function masterOffer(): BelongsTo
    {
        return $this->belongsTo(MasterMenuOffer::class, 'master_offer_id');
    }
}
