<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchisePricing extends Model
{
    use HasFactory;

    protected $table = 'franchise_pricing';

    protected $fillable = [
        'franchise_id',
        'pricing_type',
        'yearly_price',
        'per_branch_price',
        'initial_branches',
        'setup_fee',
        'custom_terms',
        'contract_start_date',
        'contract_end_date',
        'billing_cycle',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'yearly_price' => 'decimal:2',
        'per_branch_price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'initial_branches' => 'integer',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    const PRICING_TYPE_FIXED_YEARLY = 'fixed_yearly';
    const PRICING_TYPE_PAY_AS_YOU_GO = 'pay_as_you_go';
    const PRICING_TYPE_CUSTOM = 'custom';

    const BILLING_CYCLE_MONTHLY = 'monthly';
    const BILLING_CYCLE_QUARTERLY = 'quarterly';
    const BILLING_CYCLE_YEARLY = 'yearly';

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FranchisePayment::class);
    }

    /**
     * Calculate monthly cost for a given number of branches
     */
    public function calculateMonthlyCost(int $branches): float
    {
        if ($this->pricing_type === self::PRICING_TYPE_FIXED_YEARLY) {
            return $this->yearly_price / 12;
        }

        if ($this->pricing_type === self::PRICING_TYPE_PAY_AS_YOU_GO) {
            return $branches * $this->per_branch_price;
        }

        return 0;
    }

    /**
     * Calculate yearly cost for a given number of branches
     */
    public function calculateYearlyCost(int $branches): float
    {
        if ($this->pricing_type === self::PRICING_TYPE_FIXED_YEARLY) {
            return $this->yearly_price;
        }

        if ($this->pricing_type === self::PRICING_TYPE_PAY_AS_YOU_GO) {
            return $branches * $this->per_branch_price * 12;
        }

        return 0;
    }

    /**
     * Get formatted pricing summary
     */
    public function getPricingSummary(): string
    {
        if ($this->pricing_type === self::PRICING_TYPE_FIXED_YEARLY) {
            return "Fixed Yearly: " . number_format($this->yearly_price, 2);
        }

        if ($this->pricing_type === self::PRICING_TYPE_PAY_AS_YOU_GO) {
            return "Pay as you go: " . number_format($this->per_branch_price, 2) . " per branch/month";
        }

        return "Custom pricing";
    }
}
