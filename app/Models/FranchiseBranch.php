<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchiseBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'location_id',
        'branch_name',
        'branch_code',
        'address',
        'city',
        'phone',
        'is_active',
        'is_paid',
        'activated_at',
        'deactivated_at',
        'added_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
        'activated_at' => 'date',
        'deactivated_at' => 'date',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(FranchiseAccount::class, 'branch_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(FranchiseInvitation::class, 'branch_id');
    }

    /**
     * Generate a unique branch code
     */
    public static function generateBranchCode(int $franchiseId): string
    {
        $count = self::where('franchise_id', $franchiseId)->count();
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
     * Scope for active branches
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for paid branches
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }
}
