<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'from_endpoint_id',
        'to_endpoint_id',
        'location_id',
        'change_type',
        'reason',
    ];

    /**
     * Get the session
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QrScanSession::class, 'session_id');
    }

    /**
     * Get the from endpoint
     */
    public function fromEndpoint(): BelongsTo
    {
        return $this->belongsTo(MenuEndpoint::class, 'from_endpoint_id');
    }

    /**
     * Get the to endpoint
     */
    public function toEndpoint(): BelongsTo
    {
        return $this->belongsTo(MenuEndpoint::class, 'to_endpoint_id');
    }

    /**
     * Get the location
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
