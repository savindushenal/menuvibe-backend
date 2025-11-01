<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'is_active',
        'status',
        'payment_method',
        'external_subscription_id',
        'last_payment_at',
        'next_payment_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the subscription
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>', now());
                    });
    }

    /**
     * Scope for trial subscriptions
     */
    public function scopeTrial($query)
    {
        return $query->where('status', 'trial')
                    ->where('trial_ends_at', '>', now());
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        if (!$this->is_active || $this->status !== 'active') {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if subscription is in trial
     */
    public function isInTrial(): bool
    {
        return $this->status === 'trial' && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Check if trial is expired
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get days remaining in subscription
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->ends_at) {
            return null; // Unlimited
        }

        return max(0, now()->diffInDays($this->ends_at, false));
    }

    /**
     * Get days remaining in trial
     */
    public function getTrialDaysRemainingAttribute(): ?int
    {
        if (!$this->trial_ends_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Cancel subscription
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'is_active' => false,
        ]);
    }

    /**
     * Expire subscription
     */
    public function expire(): void
    {
        $this->update([
            'status' => 'expired',
            'is_active' => false,
        ]);
    }
}
