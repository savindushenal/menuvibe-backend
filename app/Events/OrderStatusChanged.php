<?php

namespace App\Events;

use App\Models\MenuOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\MultipleChannels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $orderData;

    public function __construct(
        public MenuOrder $order,
        public string $oldStatus
    ) {
        $this->orderData = $order->toSummary();
    }

    /** Broadcast on both POS channel and per-order channel */
    public function broadcastOn(): array
    {
        return [
            new Channel('pos.' . $this->order->location_id),
            new Channel('order.' . $this->order->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'order'      => $this->orderData,
            'old_status' => $this->oldStatus,
            'new_status' => $this->order->status,
        ];
    }
}
