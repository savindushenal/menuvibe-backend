<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QRCode extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'qr_codes';

    protected $fillable = [
        'location_id',
        'menu_id',
        'name',
        'table_number',
        'qr_url',
        'qr_image',
        'scan_count',
        'last_scanned_at',
    ];

    protected $casts = [
        'scan_count' => 'integer',
        'last_scanned_at' => 'datetime',
    ];

    /**
     * Get the location that owns the QR code
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the menu associated with the QR code
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
