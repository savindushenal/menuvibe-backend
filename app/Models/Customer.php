<?php

namespace App\Models;

use App\Models\Scopes\FranchiseScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'location_id',
        'franchise_id',
        'name',
        'phone',
        'email',
        'external_customer_id',
        'loyalty_number',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $hidden = [
        'external_customer_id', // Don't expose in API responses by default
    ];

    /**
     * Boot the model and apply global scopes
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new FranchiseScope);
    }

    /**
     * Get the location
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the franchise
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Get QR scan sessions
     */
    public function qrSessions(): HasMany
    {
        return $this->hasMany(QrScanSession::class, 'loyalty_number', 'loyalty_number');
    }
}
