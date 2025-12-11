<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class MenuOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_id',
        'location_id',
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
        'applicable_endpoints',
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
        'discount_value' => 'decimal:2',
        'bundle_price' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'applicable_items' => 'array',
        'applicable_categories' => 'array',
        'applicable_endpoints' => 'array',
        'available_days' => 'array',
        'terms_conditions' => 'array',
        'apply_to_all' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'sort_order' => 'integer',
    ];

    const OFFER_TYPES = [
        'special' => 'Special Offer',
        'instant' => 'Instant Deal',
        'seasonal' => 'Seasonal',
        'combo' => 'Combo',
        'happy_hour' => 'Happy Hour',
        'loyalty' => 'Loyalty Reward',
        'first_order' => 'First Order',
    ];

    const DISCOUNT_TYPES = [
        'percentage' => 'Percentage Off',
        'fixed_amount' => 'Fixed Amount Off',
        'bogo' => 'Buy One Get One',
        'bundle_price' => 'Bundle Price',
        'free_item' => 'Free Item',
    ];

    // ===========================================
    // RELATIONSHIPS
    // ===========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MenuTemplate::class, 'template_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===========================================
    // SCOPES
    // ===========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeValid($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereRaw('usage_count < usage_limit');
            });
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('offer_type', $type);
    }

    // ===========================================
    // ACCESSORS
    // ===========================================

    public function getIsValidAttribute(): bool
    {
        if (!$this->is_active) return false;
        
        $now = now();
        
        // Check date range
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;
        
        // Check usage limit
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        
        // Check day of week
        if ($this->available_days && !in_array(strtolower($now->format('l')), array_map('strtolower', $this->available_days))) {
            return false;
        }
        
        // Check time of day
        if ($this->available_time_start && $this->available_time_end) {
            $currentTime = $now->format('H:i');
            if ($currentTime < $this->available_time_start || $currentTime > $this->available_time_end) {
                return false;
            }
        }
        
        return true;
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->ends_at && now()->gt($this->ends_at)) return true;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return true;
        return false;
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->starts_at && now()->lt($this->starts_at);
    }

    public function getRemainingTimeAttribute(): ?string
    {
        if (!$this->ends_at) return null;
        
        $diff = now()->diff($this->ends_at);
        
        if ($diff->invert) return 'Expired';
        if ($diff->days > 0) return $diff->days . ' days left';
        if ($diff->h > 0) return $diff->h . ' hours left';
        if ($diff->i > 0) return $diff->i . ' minutes left';
        
        return 'Ending soon';
    }

    public function getTypeNameAttribute(): string
    {
        return self::OFFER_TYPES[$this->offer_type] ?? ucfirst($this->offer_type);
    }

    public function getDiscountTypeNameAttribute(): string
    {
        return self::DISCOUNT_TYPES[$this->discount_type] ?? ucfirst($this->discount_type);
    }

    public function getTypeBadgeAttribute(): array
    {
        $badges = [
            'special' => ['text' => 'Special Offer', 'color' => '#ef4444'],
            'instant' => ['text' => 'Instant Deal', 'color' => '#f59e0b'],
            'seasonal' => ['text' => 'Seasonal', 'color' => '#10b981'],
            'combo' => ['text' => 'Combo', 'color' => '#8b5cf6'],
            'happy_hour' => ['text' => 'Happy Hour', 'color' => '#3b82f6'],
            'loyalty' => ['text' => 'Loyalty', 'color' => '#ec4899'],
            'first_order' => ['text' => 'First Order', 'color' => '#06b6d4'],
        ];

        return $badges[$this->offer_type] ?? ['text' => 'Offer', 'color' => '#6b7280'];
    }

    // ===========================================
    // METHODS
    // ===========================================

    /**
     * Check if offer applies to a specific endpoint
     */
    public function appliesToEndpoint(int $endpointId): bool
    {
        if ($this->apply_to_all) return true;
        if (empty($this->applicable_endpoints)) return true;
        
        return in_array($endpointId, $this->applicable_endpoints);
    }

    /**
     * Check if offer applies to a specific item
     */
    public function appliesToItem(int $itemId, int $categoryId = null): bool
    {
        if ($this->apply_to_all) return true;
        
        // Check item IDs
        if (!empty($this->applicable_items) && in_array($itemId, $this->applicable_items)) {
            return true;
        }
        
        // Check category IDs
        if ($categoryId && !empty($this->applicable_categories) && in_array($categoryId, $this->applicable_categories)) {
            return true;
        }
        
        return empty($this->applicable_items) && empty($this->applicable_categories);
    }

    /**
     * Calculate discounted price for an item
     */
    public function calculateDiscountedPrice(float $originalPrice): float
    {
        switch ($this->discount_type) {
            case 'percentage':
                return $originalPrice * (1 - ($this->discount_value / 100));
            case 'fixed_amount':
                return max(0, $originalPrice - $this->discount_value);
            case 'bundle_price':
                return $this->bundle_price ?? $originalPrice;
            default:
                return $originalPrice;
        }
    }

    /**
     * Increment usage count
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
    }
}
