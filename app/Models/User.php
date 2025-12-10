<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'google_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the business profile associated with the user
     */
    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    /**
     * Get all locations for the user
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class)->ordered();
    }

    /**
     * Get active locations for the user
     */
    public function activeLocations(): HasMany
    {
        return $this->hasMany(Location::class)->active()->ordered();
    }

    /**
     * Get the default location for the user
     */
    public function defaultLocation(): HasOne
    {
        return $this->hasOne(Location::class)->where('is_default', true);
    }

    /**
     * Check if user has completed onboarding
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->businessProfile && $this->businessProfile->isOnboardingCompleted();
    }

    /**
     * Check if user needs onboarding
     */
    public function needsOnboarding(): bool
    {
        return !$this->hasCompletedOnboarding();
    }

    /**
     * Get the user subscriptions
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get the active subscription
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
                    ->where('is_active', true)
                    ->where('status', 'active')
                    ->where(function($query) {
                        $query->whereNull('ends_at')
                              ->orWhere('ends_at', '>', now());
                    })
                    ->latest();
    }

    /**
     * Get all franchises this user belongs to
     */
    public function franchises(): BelongsToMany
    {
        return $this->belongsToMany(Franchise::class, 'franchise_users')
                    ->withPivot(['role', 'permissions', 'location_ids', 'is_active'])
                    ->withTimestamps();
    }

    /**
     * Get franchise user pivot records
     */
    public function franchiseUsers(): HasMany
    {
        return $this->hasMany(FranchiseUser::class);
    }

    /**
     * Get franchises where user is owner
     */
    public function ownedFranchises(): BelongsToMany
    {
        return $this->franchises()->wherePivot('role', 'owner');
    }

    /**
     * Get franchises where user is admin or owner
     */
    public function adminFranchises(): BelongsToMany
    {
        return $this->franchises()->wherePivotIn('role', ['owner', 'admin']);
    }

    /**
     * Check if user is a franchise owner
     */
    public function isFranchiseOwner(): bool
    {
        return $this->ownedFranchises()->exists();
    }

    /**
     * Check if user belongs to a specific franchise
     */
    public function belongsToFranchise(int $franchiseId): bool
    {
        return $this->franchises()->where('franchises.id', $franchiseId)->exists();
    }

    /**
     * Get user's role in a franchise
     */
    public function getRoleInFranchise(int $franchiseId): ?string
    {
        $franchiseUser = $this->franchiseUsers()
                              ->where('franchise_id', $franchiseId)
                              ->first();
        return $franchiseUser?->role;
    }

    /**
     * Get the current subscription plan
     */
    public function getCurrentSubscriptionPlan(): ?SubscriptionPlan
    {
        $activeSubscription = $this->activeSubscription;
        if ($activeSubscription) {
            return $activeSubscription->subscriptionPlan;
        }

        // Default to free plan if no active subscription
        return SubscriptionPlan::where('slug', 'free')->first();
    }

    /**
     * Check if user can perform action based on subscription limits
     */
    public function canPerformAction(string $action, int $currentCount = 0): bool
    {
        $plan = $this->getCurrentSubscriptionPlan();
        if (!$plan) {
            return false;
        }

        $limit = $plan->getLimit($action);
        
        // -1 means unlimited
        if ($limit === -1) {
            return true;
        }

        return $currentCount < $limit;
    }

    /**
     * Check if user can add more locations
     */
    public function canAddLocation(): bool
    {
        $currentCount = $this->locations()->count();
        return $this->canPerformAction('max_locations', $currentCount);
    }

    /**
     * Check if user can add menu to a location
     */
    public function canAddMenuToLocation(Location $location): bool
    {
        $currentCount = $location->menus()->count();
        return $this->canPerformAction('max_menus_per_location', $currentCount);
    }

    /**
     * Check if user can add menu item to a menu
     */
    public function canAddMenuItemToMenu(Menu $menu): bool
    {
        $currentCount = $menu->menuItems()->count();
        return $this->canPerformAction('max_menu_items_per_menu', $currentCount);
    }

    /**
     * Get remaining quota for an action
     */
    public function getRemainingQuota(string $action, int $currentCount = 0): int
    {
        $plan = $this->getCurrentSubscriptionPlan();
        if (!$plan) {
            return 0;
        }

        $limit = $plan->getLimit($action);
        
        // -1 means unlimited
        if ($limit === -1) {
            return -1;
        }

        return max(0, $limit - $currentCount);
    }

    /**
     * Get remaining location quota
     */
    public function getRemainingLocationQuota(): int
    {
        $currentCount = $this->locations()->count();
        return $this->getRemainingQuota('max_locations', $currentCount);
    }

    /**
     * Get the total number of locations
     */
    public function getTotalLocationsCount(): int
    {
        return $this->locations()->count();
    }

    /**
     * Get the total number of menus across all locations
     */
    public function getTotalMenusCount(): int
    {
        return $this->locations()
            ->withCount('menus')
            ->get()
            ->sum('menus_count');
    }

    /**
     * Get the total number of menu items across all locations
     */
    public function getTotalMenuItemsCount(): int
    {
        $total = 0;
        foreach ($this->locations as $location) {
            $total += $location->getTotalMenuItemsCount();
        }
        return $total;
    }

    /**
     * Get the user settings
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }

    /**
     * Get or create user settings
     */
    public function getSettings(): UserSettings
    {
        return $this->settings ?: $this->createDefaultSettings();
    }

    /**
     * Create default settings for user
     */
    public function createDefaultSettings(): UserSettings
    {
        return $this->settings()->create(UserSettings::getDefaultSettings());
    }
}