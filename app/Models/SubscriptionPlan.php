<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'setup_fee',
        'billing_period',
        'contract_months',
        'features',
        'limits',
        'custom_features',
        'custom_limits',
        'is_active',
        'is_custom',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'contract_months' => 'integer',
        'features' => 'array',
        'limits' => 'array',
        'custom_limits' => 'array',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the user subscriptions for this plan
     */
    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering plans
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Check if plan allows feature
     */
    public function allowsFeature(string $feature): bool
    {
        $limits = $this->limits ?? [];
        return isset($limits[$feature]) && $limits[$feature] === true;
    }

    /**
     * Get limit for a specific feature
     */
    public function getLimit(string $feature): int
    {
        $limits = $this->limits ?? [];
        return $limits[$feature] ?? 0;
    }

    /**
     * Check if plan has unlimited for a feature
     */
    public function hasUnlimited(string $feature): bool
    {
        $limit = $this->getLimit($feature);
        return $limit === -1;
    }

    /**
     * Check if plan is free
     */
    public function isFree(): bool
    {
        return $this->price == 0 && !$this->is_custom;
    }

    /**
     * Check if plan is custom
     */
    public function isCustom(): bool
    {
        return $this->is_custom;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->isCustom()) {
            return 'Custom Pricing';
        }
        if ($this->isFree()) {
            return 'Free';
        }
        
        $period = $this->billing_period === 'yearly' ? '/year' : '/month';
        return '$' . number_format($this->price, 0) . $period;
    }

    /**
     * Scope for non-custom plans
     */
    public function scopeStandard($query)
    {
        return $query->where('is_custom', false);
    }

    /**
     * Scope for custom plans
     */
    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }
}
