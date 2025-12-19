<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'assigned_to',
        'subject',
        'description',
        'priority',
        'status',
        'category',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Priority levels
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Status levels
     */
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_WAITING_ON_CUSTOMER = 'waiting_on_customer';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    /**
     * Categories
     */
    const CATEGORY_BILLING = 'billing';
    const CATEGORY_TECHNICAL = 'technical';
    const CATEGORY_FEATURE_REQUEST = 'feature_request';
    const CATEGORY_ACCOUNT = 'account';
    const CATEGORY_FRANCHISE = 'franchise';
    const CATEGORY_OTHER = 'other';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = self::generateTicketNumber();
            }
        });
    }

    /**
     * Generate unique ticket number
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'MV';
        $date = now()->format('ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get the ticket creator
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the assigned admin
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get all messages for this ticket
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    /**
     * Get public messages (visible to customer)
     */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    /**
     * Get internal messages (admin notes)
     */
    public function internalMessages(): HasMany
    {
        return $this->messages()->where('is_internal', true);
    }

    /**
     * Add a message to the ticket
     */
    public function addMessage(User $user, string $message, bool $isInternal = false, ?array $attachments = null): SupportTicketMessage
    {
        return $this->messages()->create([
            'user_id' => $user->id,
            'message' => $message,
            'is_internal' => $isInternal,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Assign ticket to admin
     */
    public function assignTo(User $admin): self
    {
        $this->update([
            'assigned_to' => $admin->id,
            'status' => self::STATUS_IN_PROGRESS,
        ]);
        return $this;
    }

    /**
     * Mark ticket as resolved
     */
    public function resolve(): self
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
        return $this;
    }

    /**
     * Close the ticket
     */
    public function close(): self
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
        return $this;
    }

    /**
     * Reopen the ticket
     */
    public function reopen(): self
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'resolved_at' => null,
            'closed_at' => null,
        ]);
        return $this;
    }

    /**
     * Check if ticket is open
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_ON_CUSTOMER,
        ]);
    }

    /**
     * Scope for open tickets
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_ON_CUSTOMER,
        ]);
    }

    /**
     * Scope for unassigned tickets
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope for assigned to specific admin
     */
    public function scopeAssignedTo($query, int $adminId)
    {
        return $query->where('assigned_to', $adminId);
    }

    /**
     * Scope for filtering by priority
     */
    public function scopeWithPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for filtering by category
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get assignment history for this ticket
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'ticket_id')->orderBy('assigned_at', 'desc');
    }

    /**
     * Get views for this ticket
     */
    public function views(): HasMany
    {
        return $this->hasMany(TicketView::class, 'ticket_id')->orderBy('viewed_at', 'desc');
    }

    /**
     * Get users who viewed this ticket
     */
    public function viewedBy()
    {
        return $this->belongsToMany(User::class, 'ticket_views', 'ticket_id', 'user_id')
            ->withPivot('viewed_at')
            ->orderByPivot('viewed_at', 'desc');
    }

    /**
     * Record a view of this ticket
     */
    public function recordView(User $user): TicketView
    {
        return TicketView::recordView($this->id, $user->id);
    }

    /**
     * Assign ticket with full tracking
     */
    public function assignToWithTracking(User $assignee, ?User $assigner = null, string $type = 'manual', ?string $notes = null): self
    {
        // End current assignment if exists
        TicketAssignment::where('ticket_id', $this->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        // Create new assignment record
        TicketAssignment::create([
            'ticket_id' => $this->id,
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner?->id,
            'assignment_type' => $type,
            'notes' => $notes,
        ]);

        // Update the ticket
        $this->update([
            'assigned_to' => $assignee->id,
            'status' => self::STATUS_IN_PROGRESS,
        ]);

        // Update assignee's active ticket count
        $assignee->updateActiveTicketsCount();

        // Create notification for assignee
        if ($assignee->id !== $assigner?->id) {
            Notification::ticketAssigned($assignee->id, $this, $assigner);
        }

        return $this;
    }

    /**
     * Auto-assign ticket to best available support staff
     */
    public function autoAssign(): ?self
    {
        $staff = User::getBestAvailableSupportStaff();
        
        if ($staff) {
            return $this->assignToWithTracking($staff, null, 'auto', 'Auto-assigned based on availability');
        }

        return null;
    }

    /**
     * Notify all support staff about this ticket
     */
    public function notifySupportStaff(): void
    {
        $staffIds = User::supportStaff()
            ->where('is_active', true)
            ->pluck('id');

        foreach ($staffIds as $staffId) {
            Notification::newTicket($staffId, $this);
        }

        // Special notification for urgent tickets
        if ($this->priority === self::PRIORITY_URGENT) {
            foreach ($staffIds as $staffId) {
                Notification::urgentTicket($staffId, $this);
            }
        }
    }
}
