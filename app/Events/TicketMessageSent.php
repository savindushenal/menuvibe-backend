<?php

namespace App\Events;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportTicketMessage $message;
    public SupportTicket $ticket;

    /**
     * Create a new event instance.
     */
    public function __construct(SupportTicketMessage $message, SupportTicket $ticket)
    {
        $this->message = $message;
        $this->ticket = $ticket;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to ticket-specific channel and admin tickets channel
        return [
            new PrivateChannel('ticket.' . $this->ticket->id),
            new PrivateChannel('admin.tickets'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'ticket_id' => $this->message->ticket_id,
                'message' => $this->message->message,
                'is_internal' => $this->message->is_internal,
                'created_at' => $this->message->created_at->toISOString(),
                'user' => [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                    'role' => $this->message->user->role,
                ],
            ],
            'ticket' => [
                'id' => $this->ticket->id,
                'ticket_number' => $this->ticket->ticket_number,
                'subject' => $this->ticket->subject,
                'priority' => $this->ticket->priority,
                'status' => $this->ticket->status,
            ],
        ];
    }
}
