<?php

namespace App\Models;

use App\Events\StaffStatusChanged;
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
        'role',
        'is_active',
        'is_online',
        'last_seen_at',
        'active_tickets_count',
        'last_login_at',
        'last_login_ip',
        'created_by',
    ];
    
    /**
     * Role constants
     */
    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_SUPPORT_OFFICER = 'support_officer';

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
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if user is an admin (or super admin)
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN]);
    }

    /**
     * Check if user is a support officer
     */
    public function isSupportOfficer(): bool
    {
        return $this->role === self::ROLE_SUPPORT_OFFICER;
    }

    /**
     * Check if user can access the admin panel
     * Admins, super admins, and support officers can access
     */
    public function canAccessAdminPanel(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN, 
            self::ROLE_SUPER_ADMIN, 
            self::ROLE_SUPPORT_OFFICER
        ]);
    }

    /**
     * Check if user can handle support tickets (admin, super_admin, or support_officer)
     */
    public function canHandleSupportTickets(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN, 
            self::ROLE_SUPER_ADMIN, 
            self::ROLE_SUPPORT_OFFICER
        ]);
    }

    /**
     * Check if user can manage business users (support officers can help reset passwords, etc.)
     */
    public function canManageBusinessUsers(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN, 
            self::ROLE_SUPER_ADMIN, 
            self::ROLE_SUPPORT_OFFICER
        ]);
    }

    /**
     * Check if user can manage franchises (support officers can view and help)
     */
    public function canManageFranchises(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN, 
            self::ROLE_SUPER_ADMIN, 
            self::ROLE_SUPPORT_OFFICER
        ]);
    }

    /**
     * Check if user can manage subscriptions
     */
    public function canManageSubscriptions(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN, 
            self::ROLE_SUPER_ADMIN, 
            self::ROLE_SUPPORT_OFFICER
        ]);
    }

    /**
     * Check if user is a regular user
     */
    public function isRegularUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user can manage another user
     */
    public function canManageUser(User $target): bool
    {
        // Super admin can manage everyone
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Admin can manage regular users and support officers but not other admins
        if ($this->isAdmin()) {
            return $target->isRegularUser() || $target->isSupportOfficer();
        }
        
        // Support officers can manage regular users (for password resets, account help, etc.)
        if ($this->isSupportOfficer() && $target->isRegularUser()) {
            return true;
        }
        
        return false;
    }

    /**
     * Update last login info
     */
    public function updateLastLogin(?string $ipAddress = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
    }

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
     * OPTIMIZED: Cache free plan lookup to avoid repeated queries
     */
    public function getCurrentSubscriptionPlan(): ?SubscriptionPlan
    {
        $activeSubscription = $this->activeSubscription;
        if ($activeSubscription) {
            return $activeSubscription->subscriptionPlan;
        }

        // Default to free plan if no active subscription - cached for 1 hour
        return \Illuminate\Support\Facades\Cache::remember('subscription_plan_free', 3600, function () {
            return SubscriptionPlan::where('slug', 'free')->first();
        });
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
     * Optimized: Single query instead of N+1
     */
    public function getTotalMenuItemsCount(): int
    {
        return MenuItem::whereIn('menu_id', function ($query) {
            $query->select('id')
                  ->from('menus')
                  ->whereIn('location_id', function ($q) {
                      $q->select('id')
                        ->from('locations')
                        ->where('user_id', $this->id);
                  });
        })->count();
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

    /**
     * Get notifications for this user
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }

    /**
     * Get unread notifications
     */
    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->unread();
    }

    /**
     * Get assigned tickets
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    /**
     * Mark user as online
     */
    public function goOnline(): void
    {
        $this->update([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
        
        // Broadcast status change to other admins
        if ($this->canHandleSupportTickets()) {
            broadcast(new StaffStatusChanged($this, 'online'))->toOthers();
        }
    }

    /**
     * Mark user as offline
     */
    public function goOffline(): void
    {
        $this->update([
            'is_online' => false,
            'last_seen_at' => now(),
        ]);
        
        // Broadcast status change to other admins
        if ($this->canHandleSupportTickets()) {
            broadcast(new StaffStatusChanged($this, 'offline'))->toOthers();
        }
    }

    /**
     * Update last seen timestamp (heartbeat)
     */
    public function heartbeat(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Update active tickets count
     */
    public function updateActiveTicketsCount(): void
    {
        $count = SupportTicket::where('assigned_to', $this->id)
            ->whereIn('status', ['open', 'in_progress', 'waiting_on_customer'])
            ->count();
        
        $this->update(['active_tickets_count' => $count]);
    }

    /**
     * Scope for online support staff
     */
    public function scopeOnlineSupportStaff($query)
    {
        return $query->where('is_online', true)
            ->where('is_active', true)
            ->whereIn('role', [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN, self::ROLE_SUPPORT_OFFICER]);
    }

    /**
     * Scope for support staff (admin, super_admin, support_officer)
     */
    public function scopeSupportStaff($query)
    {
        return $query->whereIn('role', [
            self::ROLE_ADMIN, 
            self::ROLE_SUPER_ADMIN, 
            self::ROLE_SUPPORT_OFFICER
        ]);
    }

    /**
     * Get the maximum tickets allowed per support staff member
     */
    public static function getMaxTicketsPerStaff(): int
    {
        return (int) PlatformSetting::get('support.max_tickets_per_staff', 5);
    }

    /**
     * Check if this staff member has capacity for more tickets
     */
    public function hasTicketCapacity(): bool
    {
        if (!$this->canHandleSupportTickets()) {
            return false;
        }
        return $this->getOpenTicketCount() < self::getMaxTicketsPerStaff();
    }

    /**
     * Get actual open ticket count for this user
     */
    public function getOpenTicketCount(): int
    {
        return SupportTicket::where('assigned_to', $this->id)
            ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
            ->count();
    }

    /**
     * Check if this staff member is at max capacity
     */
    public function isAtMaxCapacity(): bool
    {
        return $this->getOpenTicketCount() >= self::getMaxTicketsPerStaff();
    }

    /**
     * Get the best available support staff for auto-assignment
     * Prioritizes: 1) Support officers first, 2) Online, 3) Lowest ticket count, 4) Has capacity
     */
    public static function getBestAvailableSupportStaff(): ?self
    {
        // Check if auto-assign is enabled
        if (!PlatformSetting::get('support.auto_assign_enabled', true)) {
            return null;
        }

        $maxTickets = self::getMaxTicketsPerStaff();
        $prioritizeOnline = PlatformSetting::get('support.prioritize_online_staff', true);

        // First try online support officers with capacity (if prioritizing online)
        if ($prioritizeOnline) {
            $staff = self::where('role', self::ROLE_SUPPORT_OFFICER)
                ->where('is_online', true)
                ->where('is_active', true)
                ->whereRaw('(SELECT COUNT(*) FROM support_tickets WHERE assigned_to = users.id AND status NOT IN (?, ?)) < ?', 
                    [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED, $maxTickets])
                ->orderByRaw('(SELECT COUNT(*) FROM support_tickets WHERE assigned_to = users.id AND status NOT IN (?, ?)) ASC',
                    [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
                ->first();

            if ($staff) {
                return $staff;
            }

            // Then try online admins/super_admins with capacity
            $staff = self::whereIn('role', [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN])
                ->where('is_online', true)
                ->where('is_active', true)
                ->whereRaw('(SELECT COUNT(*) FROM support_tickets WHERE assigned_to = users.id AND status NOT IN (?, ?)) < ?', 
                    [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED, $maxTickets])
                ->orderByRaw('(SELECT COUNT(*) FROM support_tickets WHERE assigned_to = users.id AND status NOT IN (?, ?)) ASC',
                    [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
                ->first();

            if ($staff) {
                return $staff;
            }
        }

        // Finally try any active support staff with capacity (includes offline if prioritizing online,
        // or all staff if not prioritizing online)
        $staff = self::supportStaff()
            ->where('is_active', true)
            ->whereRaw('(SELECT COUNT(*) FROM support_tickets WHERE assigned_to = users.id AND status NOT IN (?, ?)) < ?', 
                [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED, $maxTickets])
            ->orderByRaw('(SELECT COUNT(*) FROM support_tickets WHERE assigned_to = users.id AND status NOT IN (?, ?)) ASC',
                [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
            ->first();

        return $staff;
    }

    /**
     * Get the oldest unassigned ticket
     */
    public static function getOldestUnassignedTicket(): ?SupportTicket
    {
        return SupportTicket::whereNull('assigned_to')
            ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
            ->orderBy('priority', 'desc') // Urgent first
            ->orderBy('created_at', 'asc') // Oldest first
            ->first();
    }

    /**
     * Try to assign an unassigned ticket to this staff member
     * Called when a ticket is closed and staff has capacity
     */
    public function tryAssignUnassignedTicket(): ?SupportTicket
    {
        // Check if auto-reassign on close is enabled
        if (!PlatformSetting::get('support.auto_reassign_on_close', true)) {
            return null;
        }

        if (!$this->hasTicketCapacity()) {
            return null;
        }

        $ticket = self::getOldestUnassignedTicket();
        
        if ($ticket) {
            $ticket->assignToWithTracking($this, null, 'auto', 'Auto-assigned when staff capacity became available');
            return $ticket;
        }

        return null;
    }
}