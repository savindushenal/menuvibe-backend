<?php

namespace App\Events;

use App\Models\SupportTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportTicket $ticket;
    public string $action;
    public ?int $triggeredBy;

    /**
     * Create a new event instance.
     * 
     * @param SupportTicket $ticket
     * @param string $action - 'created', 'assigned', 'status_changed', 'reply', 'priority_changed'
     * @param int|null $triggeredBy - User ID who triggered the action
     */
    public function __construct(SupportTicket $ticket, string $action, ?int $triggeredBy = null)
    {
        $this->ticket = $ticket;
        $this->action = $action;
        $this->triggeredBy = $triggeredBy;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to all support staff channel
        return [
            new PrivateChannel('admin.tickets'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'subject' => $this->ticket->subject,
                'priority' => $this->ticket->priority,
                'status' => $this->ticket->status,
                'category' => $this->ticket->category,
                'assigned_to' => $this->ticket->assigned_to,
                'assigned_to_name' => $this->ticket->assignedTo?->name,
                'user' => [
                    'id' => $this->ticket->user?->id,
                    'name' => $this->ticket->user?->name,
                    'email' => $this->ticket->user?->email,
                ],
                'created_at' => $this->ticket->created_at->toISOString(),
                'updated_at' => $this->ticket->updated_at->toISOString(),
            ],
            'action' => $this->action,
            'triggered_by' => $this->triggeredBy,
        ];
    }
}
