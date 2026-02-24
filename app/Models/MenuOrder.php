<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MenuOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'session_id',
        'location_id',
        'franchise_id',
        'items',
        'subtotal',
        'total',
        'currency',
        'status',
        'notes',
        'staff_notes',
        'table_identifier',
        'confirmed_at',
        'preparing_at',
        'ready_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'items'        => 'array',
        'subtotal'     => 'decimal:2',
        'total'        => 'decimal:2',
        'confirmed_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at'     => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Active statuses (order still in progress)
    const ACTIVE_STATUSES = ['pending', 'preparing', 'ready'];

    // Terminal statuses (order done)
    const DONE_STATUSES = ['delivered', 'completed', 'cancelled'];

    // ---------- Relationships ----------

    public function session(): BelongsTo
    {
        return $this->belongsTo(QrScanSession::class, 'session_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    // ---------- Helpers ----------

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(Str::random(6));
        } while (self::where('order_number', $number)->exists());

        return $number;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES);
    }

    public function isDone(): bool
    {
        return in_array($this->status, self::DONE_STATUSES);
    }

    public function updateStatus(string $newStatus): void
    {
        $timestamps = [
            'preparing' => 'preparing_at',
            'ready'     => 'ready_at',
            'delivered' => 'delivered_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
        ];

        $data = ['status' => $newStatus];
        if (isset($timestamps[$newStatus])) {
            $data[$timestamps[$newStatus]] = now();
        }

        $this->update($data);
    }

    public function toSummary(): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status,
            'items'            => $this->items,
            'total'            => $this->total,
            'currency'         => $this->currency,
            'table_identifier' => $this->table_identifier,
            'notes'            => $this->notes,
            'is_active'        => $this->isActive(),
            'placed_at'        => $this->created_at->toIso8601String(),
            'preparing_at'     => $this->preparing_at?->toIso8601String(),
            'ready_at'         => $this->ready_at?->toIso8601String(),
            'delivered_at'     => $this->delivered_at?->toIso8601String(),
        ];
    }

    // ---------- Scopes ----------

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }
}
