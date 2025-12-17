<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FranchiseInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'email',
        'name',
        'role',
        'branch_id',
        'location_id',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'message',
        'send_credentials',
        'temp_password',
        'invited_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'send_credentials' => 'boolean',
    ];

    protected $hidden = [
        'temp_password',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    const ROLE_FRANCHISE_OWNER = 'franchise_owner';
    const ROLE_FRANCHISE_MANAGER = 'franchise_manager';
    const ROLE_BRANCH_MANAGER = 'branch_manager';
    const ROLE_STAFF = 'staff';

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FranchiseBranch::class, 'branch_id');
    }

    /**
     * Get the location (unified with branch)
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Generate a unique invitation token
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Generate a temporary password
     */
    public static function generateTempPassword(): string
    {
        return Str::random(12);
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now() || $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if invitation is valid
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    /**
     * Accept the invitation
     */
    public function accept(): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Cancel the invitation
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Mark as expired
     */
    public function markExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('expires_at', '>', now());
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
}
