<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FranchiseUser extends Model
{
    use HasFactory;

    /**
     * Role constants.
     */
    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_VIEWER = 'viewer';

    /**
     * Permission constants.
     */
    const PERMISSION_VIEW = 'view';
    const PERMISSION_CREATE = 'create';
    const PERMISSION_EDIT = 'edit';
    const PERMISSION_DELETE = 'delete';
    const PERMISSION_MANAGE = 'manage';
    const PERMISSION_EXPORT = 'export';
    const PERMISSION_INVITE = 'invite';
    const PERMISSION_REMOVE = 'remove';

    /**
     * Default permissions by role.
     */
    const DEFAULT_PERMISSIONS = [
        self::ROLE_OWNER => [
            'locations' => ['view', 'create', 'edit', 'delete'],
            'menus' => ['view', 'create', 'edit', 'delete'],
            'staff' => ['view', 'invite', 'remove'],
            'analytics' => ['view', 'export'],
            'billing' => ['view', 'manage'],
            'branding' => ['view', 'edit'],
            'settings' => ['view', 'edit'],
        ],
        self::ROLE_ADMIN => [
            'locations' => ['view', 'create', 'edit', 'delete'],
            'menus' => ['view', 'create', 'edit', 'delete'],
            'staff' => ['view', 'invite', 'remove'],
            'analytics' => ['view', 'export'],
            'billing' => ['view'],
            'branding' => ['view', 'edit'],
            'settings' => ['view', 'edit'],
        ],
        self::ROLE_MANAGER => [
            'locations' => ['view', 'edit'],
            'menus' => ['view', 'create', 'edit'],
            'staff' => ['view'],
            'analytics' => ['view'],
            'billing' => [],
            'branding' => ['view'],
            'settings' => ['view'],
        ],
        self::ROLE_VIEWER => [
            'locations' => ['view'],
            'menus' => ['view'],
            'staff' => [],
            'analytics' => ['view'],
            'billing' => [],
            'branding' => ['view'],
            'settings' => ['view'],
        ],
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'franchise_id',
        'user_id',
        'role',
        'permissions',
        'location_ids',
        'invited_by',
        'invited_at',
        'accepted_at',
        'invitation_token',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'permissions' => 'array',
        'location_ids' => 'array',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Default values.
     */
    protected $attributes = [
        'role' => self::ROLE_VIEWER,
        'is_active' => true,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default permissions based on role
        static::creating(function ($franchiseUser) {
            if (empty($franchiseUser->permissions)) {
                $franchiseUser->permissions = self::DEFAULT_PERMISSIONS[$franchiseUser->role] ?? [];
            }
            
            // Generate invitation token if this is a new invite
            if (empty($franchiseUser->accepted_at) && empty($franchiseUser->invitation_token)) {
                $franchiseUser->invitation_token = Str::random(64);
                $franchiseUser->invited_at = now();
            }
        });
    }

    /* =========================================
     * RELATIONSHIPS
     * ========================================= */

    /**
     * Get the franchise.
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* =========================================
     * ROLE CHECKS
     * ========================================= */

    /**
     * Check if user is owner.
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Check if user is admin or owner.
     */
    public function isAdminOrAbove(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    /**
     * Check if user is manager or above.
     */
    public function isManagerOrAbove(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    /**
     * Get role display name.
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            self::ROLE_OWNER => 'Owner',
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_VIEWER => 'Viewer',
            default => ucfirst($this->role),
        };
    }

    /* =========================================
     * PERMISSION CHECKS
     * ========================================= */

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $resource, string $action): bool
    {
        // Owners always have all permissions
        if ($this->isOwner()) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        $resourcePermissions = $permissions[$resource] ?? [];
        
        return in_array($action, $resourcePermissions);
    }

    /**
     * Check if user can view a resource.
     */
    public function canView(string $resource): bool
    {
        return $this->hasPermission($resource, self::PERMISSION_VIEW);
    }

    /**
     * Check if user can create a resource.
     */
    public function canCreate(string $resource): bool
    {
        return $this->hasPermission($resource, self::PERMISSION_CREATE);
    }

    /**
     * Check if user can edit a resource.
     */
    public function canEdit(string $resource): bool
    {
        return $this->hasPermission($resource, self::PERMISSION_EDIT);
    }

    /**
     * Check if user can delete a resource.
     */
    public function canDelete(string $resource): bool
    {
        return $this->hasPermission($resource, self::PERMISSION_DELETE);
    }

    /**
     * Update specific permissions.
     */
    public function updatePermissions(array $newPermissions): void
    {
        $this->update(['permissions' => array_merge($this->permissions ?? [], $newPermissions)]);
    }

    /* =========================================
     * LOCATION SCOPE
     * ========================================= */

    /**
     * Check if user is scoped to specific locations.
     */
    public function hasLocationScope(): bool
    {
        return !empty($this->location_ids);
    }

    /**
     * Check if user can access a specific location.
     */
    public function canAccessLocation(int $locationId): bool
    {
        // If no scope, user can access all locations
        if (!$this->hasLocationScope()) {
            return true;
        }

        return in_array($locationId, $this->location_ids ?? []);
    }

    /**
     * Get accessible location IDs.
     */
    public function getAccessibleLocationIds(): array
    {
        // If no scope, return all franchise location IDs
        if (!$this->hasLocationScope()) {
            return $this->franchise->locations()->pluck('id')->toArray();
        }

        return $this->location_ids ?? [];
    }

    /* =========================================
     * INVITATION METHODS
     * ========================================= */

    /**
     * Check if invitation is pending.
     */
    public function isPending(): bool
    {
        return empty($this->accepted_at) && !empty($this->invitation_token);
    }

    /**
     * Accept the invitation.
     */
    public function acceptInvitation(): void
    {
        $this->update([
            'accepted_at' => now(),
            'invitation_token' => null,
        ]);
    }

    /**
     * Resend invitation.
     */
    public function resendInvitation(): void
    {
        $this->update([
            'invitation_token' => Str::random(64),
            'invited_at' => now(),
            'accepted_at' => null,
        ]);
    }

    /* =========================================
     * SCOPES
     * ========================================= */

    /**
     * Scope to only active memberships.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to accepted invitations only.
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope to pending invitations.
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
                    ->whereNotNull('invitation_token');
    }

    /**
     * Scope by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
