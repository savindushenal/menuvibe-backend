<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FranchiseAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'user_id',
        'role',
        'branch_id',
        'location_id',
        'permissions',
        'is_active',
        'created_by',
        'invitation_token',
        'accepted_at',
        'invitation_expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'accepted_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
    ];

    const ROLE_FRANCHISE_OWNER = 'franchise_owner';
    const ROLE_FRANCHISE_MANAGER = 'franchise_manager';
    const ROLE_BRANCH_MANAGER = 'branch_manager';
    const ROLE_STAFF = 'staff';

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the location (unified with branch)
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if account has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === self::ROLE_FRANCHISE_OWNER) {
            return true; // Owner has all permissions
        }

        if (!$this->permissions) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if account can manage branches
     */
    public function canManageBranches(): bool
    {
        return in_array($this->role, [
            self::ROLE_FRANCHISE_OWNER,
            self::ROLE_FRANCHISE_MANAGER,
        ]);
    }

    /**
     * Check if account can manage staff
     */
    public function canManageStaff(): bool
    {
        return in_array($this->role, [
            self::ROLE_FRANCHISE_OWNER,
            self::ROLE_FRANCHISE_MANAGER,
            self::ROLE_BRANCH_MANAGER,
        ]);
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            self::ROLE_FRANCHISE_OWNER => 'Franchise Owner',
            self::ROLE_FRANCHISE_MANAGER => 'Franchise Manager',
            self::ROLE_BRANCH_MANAGER => 'Branch Manager',
            self::ROLE_STAFF => 'Staff',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if invitation is pending
     */
    public function isPendingInvitation(): bool
    {
        return empty($this->accepted_at) && !empty($this->invitation_token);
    }

    /**
     * Check if invitation is expired
     */
    public function isInvitationExpired(): bool
    {
        if (!$this->invitation_expires_at) {
            return false;
        }
        return $this->invitation_expires_at->isPast();
    }

    /**
     * Accept the invitation
     */
    public function acceptInvitation(): void
    {
        $this->update([
            'accepted_at' => now(),
            'invitation_token' => null,
            'invitation_expires_at' => null,
        ]);
    }

    /**
     * Generate invitation token
     */
    public function generateInvitationToken(int $expiresInDays = 7): string
    {
        $token = Str::random(64);
        $this->update([
            'invitation_token' => $token,
            'invitation_expires_at' => now()->addDays($expiresInDays),
            'accepted_at' => null,
        ]);
        return $token;
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
                    ->whereNotNull('invitation_token');
    }

    /**
     * Scope for accepted accounts
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }
}
