<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MasterMenuOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'master_menu_id',
        'offer_type',
        'title',
        'slug',
        'description',
        'image_url',
        'badge_text',
        'badge_color',
        'discount_type',
        'discount_value',
        'bundle_price',
        'minimum_order',
        'applicable_items',
        'applicable_categories',
        'apply_to_all',
        'starts_at',
        'ends_at',
        'available_days',
        'available_time_start',
        'available_time_end',
        'usage_limit',
        'usage_count',
        'is_active',
        'is_featured',
        'sort_order',
        'terms_conditions',
        'created_by',
    ];

    protected $casts = [
        'applicable_items' => 'array',
        'applicable_categories' => 'array',
        'available_days' => 'array',
        'terms_conditions' => 'array',
        'apply_to_all' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'discount_value' => 'decimal:2',
        'bundle_price' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($offer) {
            if (empty($offer->slug)) {
                $offer->slug = Str::slug($offer->title);
            }
        });
    }

    /**
     * Get the franchise that owns the offer
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Get the master menu for this offer
     */
    public function masterMenu(): BelongsTo
    {
        return $this->belongsTo(MasterMenu::class);
    }

    /**
     * Get branch overrides for this offer
     */
    public function branchOverrides(): HasMany
    {
        return $this->hasMany(BranchOfferOverride::class, 'master_offer_id');
    }

    /**
     * Get the user who created this offer
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if offer is currently valid
     */
    public function getIsValidAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        // Check date range
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        // Check usage limit
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        // Check day of week
        if ($this->available_days && !empty($this->available_days)) {
            $today = strtolower($now->format('l'));
            if (!in_array($today, $this->available_days)) {
                return false;
            }
        }

        // Check time range
        if ($this->available_time_start && $this->available_time_end) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $this->available_time_start || $currentTime > $this->available_time_end) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if offer is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->ends_at) {
            return false;
        }
        return Carbon::now()->gt($this->ends_at);
    }

    /**
     * Check if offer is upcoming
     */
    public function getIsUpcomingAttribute(): bool
    {
        if (!$this->starts_at) {
            return false;
        }
        return Carbon::now()->lt($this->starts_at);
    }

    /**
     * Get remaining time display
     */
    public function getRemainingTimeAttribute(): ?string
    {
        if (!$this->ends_at || $this->is_expired) {
            return null;
        }
        
        return Carbon::now()->diffForHumans($this->ends_at, ['parts' => 2]);
    }

    /**
     * Calculate discount for a given price
     */
    public function calculateDiscount(float $originalPrice): float
    {
        switch ($this->discount_type) {
            case 'percentage':
                return $originalPrice * ($this->discount_value / 100);
            case 'fixed_amount':
                return min($this->discount_value, $originalPrice);
            case 'bundle_price':
                return $originalPrice - $this->bundle_price;
            case 'bogo':
                return $originalPrice; // Full price of second item
            default:
                return 0;
        }
    }

    /**
     * Check if offer applies to specific item
     */
    public function appliesToItem($itemId): bool
    {
        if ($this->apply_to_all) {
            return true;
        }

        if ($this->applicable_items && in_array($itemId, $this->applicable_items)) {
            return true;
        }

        return false;
    }

    /**
     * Check if offer applies to specific category
     */
    public function appliesToCategory($categoryId): bool
    {
        if ($this->apply_to_all) {
            return true;
        }

        if ($this->applicable_categories && in_array($categoryId, $this->applicable_categories)) {
            return true;
        }

        return false;
    }

    /**
     * Scope for active offers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Scope by offer type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('offer_type', $type);
    }

    /**
     * Get offer type badge
     */
    public function getTypeBadgeAttribute(): array
    {
        $badges = [
            'special' => ['text' => 'Special Offer', 'color' => '#ef4444'],
            'instant' => ['text' => 'Instant Deal', 'color' => '#f59e0b'],
            'seasonal' => ['text' => 'Seasonal', 'color' => '#10b981'],
            'combo' => ['text' => 'Combo', 'color' => '#8b5cf6'],
            'happy_hour' => ['text' => 'Happy Hour', 'color' => '#3b82f6'],
        ];

        return $badges[$this->offer_type] ?? ['text' => 'Offer', 'color' => '#6b7280'];
    }
}
