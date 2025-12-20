<?php

namespace App\Models;

use App\Events\NewNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Broadcast notification when created
        static::created(function (Notification $notification) {
            broadcast(new NewNotification($notification))->toOthers();
        });
    }

    /**
     * Notification types
     */
    const TYPE_TICKET_ASSIGNED = 'ticket_assigned';
    const TYPE_TICKET_NEW = 'ticket_new';
    const TYPE_TICKET_REPLY = 'ticket_reply';
    const TYPE_TICKET_STATUS_CHANGED = 'ticket_status_changed';
    const TYPE_TICKET_PRIORITY_CHANGED = 'ticket_priority_changed';
    const TYPE_TICKET_URGENT = 'ticket_urgent';
    const TYPE_SYSTEM = 'system';

    /**
     * Get the user this notification belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): self
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
        return $this;
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for specific types
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Create a ticket assigned notification
     */
    public static function ticketAssigned(int $userId, SupportTicket $ticket, ?User $assignedBy = null): self
    {
        $assignerName = $assignedBy ? $assignedBy->name : 'System';
        
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_TICKET_ASSIGNED,
            'title' => 'Ticket Assigned to You',
            'message' => "Ticket #{$ticket->ticket_number} has been assigned to you by {$assignerName}",
            'link' => "/admin/tickets?view={$ticket->id}",
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'assigned_by' => $assignedBy?->id,
            ],
        ]);
    }

    /**
     * Create a new ticket notification for support staff
     */
    public static function newTicket(int $userId, SupportTicket $ticket): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_TICKET_NEW,
            'title' => 'New Support Ticket',
            'message' => "New ticket #{$ticket->ticket_number}: {$ticket->subject}",
            'link' => "/admin/tickets?view={$ticket->id}",
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
            ],
        ]);
    }

    /**
     * Create a ticket reply notification
     */
    public static function ticketReply(int $userId, SupportTicket $ticket, User $repliedBy): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_TICKET_REPLY,
            'title' => 'New Reply on Ticket',
            'message' => "{$repliedBy->name} replied to ticket #{$ticket->ticket_number}",
            'link' => "/admin/tickets?view={$ticket->id}",
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'replied_by' => $repliedBy->id,
                'replied_by_name' => $repliedBy->name,
            ],
        ]);
    }

    /**
     * Create urgent ticket notification
     */
    public static function urgentTicket(int $userId, SupportTicket $ticket): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_TICKET_URGENT,
            'title' => '🚨 Urgent Ticket',
            'message' => "Urgent ticket #{$ticket->ticket_number} requires immediate attention",
            'link' => "/admin/tickets?view={$ticket->id}",
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
            ],
        ]);
    }

    /**
     * OPTIMIZED: Batch create new ticket notifications for multiple users
     * Uses insert() for efficiency but still broadcasts events
     */
    public static function batchNewTicket(array $userIds, SupportTicket $ticket): void
    {
        if (empty($userIds)) {
            return;
        }

        $now = now();
        $records = [];
        
        foreach ($userIds as $userId) {
            $records[] = [
                'user_id' => $userId,
                'type' => self::TYPE_TICKET_NEW,
                'title' => 'New Support Ticket',
                'message' => "New ticket #{$ticket->ticket_number}: {$ticket->subject}",
                'link' => "/admin/tickets?view={$ticket->id}",
                'data' => json_encode([
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'priority' => $ticket->priority,
                    'category' => $ticket->category,
                ]),
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert
        self::insert($records);

        // Broadcast to each user (still needed for real-time)
        $insertedNotifications = self::where('type', self::TYPE_TICKET_NEW)
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', $now->subSecond())
            ->whereJsonContains('data->ticket_id', $ticket->id)
            ->get();

        foreach ($insertedNotifications as $notification) {
            broadcast(new \App\Events\NewNotification($notification))->toOthers();
        }
    }

    /**
     * OPTIMIZED: Batch create urgent ticket notifications for multiple users
     */
    public static function batchUrgentTicket(array $userIds, SupportTicket $ticket): void
    {
        if (empty($userIds)) {
            return;
        }

        $now = now();
        $records = [];
        
        foreach ($userIds as $userId) {
            $records[] = [
                'user_id' => $userId,
                'type' => self::TYPE_TICKET_URGENT,
                'title' => '🚨 Urgent Ticket',
                'message' => "Urgent ticket #{$ticket->ticket_number} requires immediate attention",
                'link' => "/admin/tickets?view={$ticket->id}",
                'data' => json_encode([
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                ]),
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert
        self::insert($records);

        // Broadcast to each user
        $insertedNotifications = self::where('type', self::TYPE_TICKET_URGENT)
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', $now->subSecond())
            ->whereJsonContains('data->ticket_id', $ticket->id)
            ->get();

        foreach ($insertedNotifications as $notification) {
            broadcast(new \App\Events\NewNotification($notification))->toOthers();
        }
    }
}
