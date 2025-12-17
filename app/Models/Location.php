<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'franchise_id',
        'name',
        'description',
        'is_active',
        'sort_order',
        'phone',
        'email',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'cuisine_type',
        'seating_capacity',
        'operating_hours',
        'services',
        'logo_url',
        'primary_color',
        'secondary_color',
        'social_media',
        'latitude',
        'longitude',
        'is_default',
        // Branch-specific fields (unified from FranchiseBranch)
        'branch_name',
        'branch_code',
        'is_paid',
        'activated_at',
        'deactivated_at',
        'added_by',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'services' => 'array',
        'social_media' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_paid' => 'boolean',
        'sort_order' => 'integer',
        'seating_capacity' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'activated_at' => 'date',
        'deactivated_at' => 'date',
    ];

    /**
     * Get the user that owns the location
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the franchise this location belongs to
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Get the user who added this branch
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Get the franchise accounts (staff) for this location
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(FranchiseAccount::class, 'location_id');
    }

    /**
     * Get the invitations for this location
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(FranchiseInvitation::class, 'location_id');
    }

    /**
     * Get the menu overrides for this location
     */
    public function menuOverrides(): HasMany
    {
        return $this->hasMany(BranchMenuOverride::class, 'location_id');
    }

    /**
     * Get the sync logs for this location
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(MenuSyncLog::class, 'location_id');
    }

    /**
     * Get the menus for this location
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class)->orderBy('sort_order');
    }

    /**
     * Get active menus for this location
     */
    public function activeMenus(): HasMany
    {
        return $this->hasMany(Menu::class)->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default location
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for ordering locations
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope for franchise locations
     */
    public function scopeForFranchise($query, int $franchiseId)
    {
        return $query->where('franchise_id', $franchiseId);
    }

    /**
     * Scope for franchise branches (locations with branch_code)
     */
    public function scopeBranches($query)
    {
        return $query->whereNotNull('franchise_id')->whereNotNull('branch_code');
    }

    /**
     * Scope for paid branches
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    /**
     * Check if location belongs to a franchise
     */
    public function belongsToFranchise(): bool
    {
        return !is_null($this->franchise_id);
    }

    /**
     * Get the full address as a string
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if this is the user's default location
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Set this location as the default for the user
     */
    public function setAsDefault(): void
    {
        // Remove default from other locations
        $this->user->locations()->where('id', '!=', $this->id)->update(['is_default' => false]);
        
        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Get the total number of menu items for this location
     */
    public function getTotalMenuItemsCount(): int
    {
        return $this->menus()
            ->withCount('menuItems')
            ->get()
            ->sum('menu_items_count');
    }

    /**
     * Check if this is a franchise branch
     */
    public function isBranch(): bool
    {
        return !is_null($this->franchise_id) && !is_null($this->branch_code);
    }

    /**
     * Generate a unique branch code for a franchise
     */
    public static function generateBranchCode(int $franchiseId): string
    {
        $count = self::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->count();
        return 'BR' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Activate the branch
     */
    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
    }

    /**
     * Deactivate the branch
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure only one default location per user
        static::creating(function ($location) {
            if ($location->is_default) {
                static::where('user_id', $location->user_id)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function ($location) {
            if ($location->is_default && $location->isDirty('is_default')) {
                static::where('user_id', $location->user_id)
                    ->where('id', '!=', $location->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
