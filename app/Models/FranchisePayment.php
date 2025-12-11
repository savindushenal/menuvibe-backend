<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchisePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'franchise_pricing_id',
        'amount',
        'payment_type',
        'status',
        'due_date',
        'paid_date',
        'payment_method',
        'transaction_reference',
        'notes',
        'branches_count',
        'period_start',
        'period_end',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'branches_count' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    const TYPE_SETUP = 'setup';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_QUARTERLY = 'quarterly';
    const TYPE_YEARLY = 'yearly';
    const TYPE_BRANCH_ADDITION = 'branch_addition';
    const TYPE_CUSTOM = 'custom';

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(FranchisePricing::class, 'franchise_pricing_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Mark payment as paid
     */
    public function markAsPaid(?string $paymentMethod = null, ?string $transactionRef = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_date' => now(),
            'payment_method' => $paymentMethod ?? $this->payment_method,
            'transaction_reference' => $transactionRef ?? $this->transaction_reference,
        ]);
    }

    /**
     * Check if payment is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->due_date < now();
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for paid payments
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope for overdue payments
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('due_date', '<', now());
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PAID => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_OVERDUE => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_REFUNDED => 'blue',
            default => 'gray',
        };
    }
}
