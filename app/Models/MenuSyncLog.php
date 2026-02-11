<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_menu_id',
        'branch_id',
        'location_id',
        'sync_type',
        'status',
        'items_synced',
        'categories_synced',
        'changes',
        'error_message',
        'synced_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'items_synced' => 'integer',
        'categories_synced' => 'integer',
        'changes' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the master menu
     */
    public function masterMenu(): BelongsTo
    {
        return $this->belongsTo(MasterMenu::class);
    }

    /**
     * Get the branch (now points to Location)
     */
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

    /**
     * Get the user who triggered the sync
     */
    public function syncedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Check if sync is in progress
     */
    public function getIsInProgressAttribute(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if sync failed
     */
    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }
}
