<?php

namespace App\Events;

use App\Models\MenuOrder;
use App\Models\StaffPushSubscription;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $orderData;

    public function __construct(public MenuOrder $order)
    {
        $this->orderData = $order->toSummary();
    }

    /** Pusher channel: pos.{locationId} */
    public function broadcastOn(): Channel
    {
        return new Channel('pos.' . $this->order->location_id);
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => $this->orderData,
        ];
    }

    /**
     * After broadcasting, also send Web Push to registered staff devices.
     * Called synchronously (QUEUE_CONNECTION=sync).
     */
    public function handle(): void
    {
        $this->sendWebPush();
    }

    public function sendWebPush(): void
    {
        $subs = StaffPushSubscription::forLocation($this->order->location_id);
        if ($subs->isEmpty()) return;

        $auth = [
            'VAPID' => [
                'subject'    => env('VAPID_SUBJECT', 'mailto:no-reply@menuvire.com'),
                'publicKey'  => env('VAPID_PUBLIC_KEY'),
                'privateKey' => env('VAPID_PRIVATE_KEY'),
            ],
        ];

        $webPush = new WebPush($auth);
        $payload = json_encode([
            'title' => '🍽 New Order — ' . $this->order->order_number,
            'body'  => 'Table ' . ($this->order->table_identifier ?: '?') . ' · ' . count($this->orderData['items']) . ' item(s)',
            'icon'  => '/icons/icon-192x192.png',
            'badge' => '/icons/icon-192x192.png',
            'data'  => ['orderId' => $this->order->id, 'locationId' => $this->order->location_id],
        ]);

        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint'       => $sub->endpoint,
                    'publicKey'      => $sub->p256dh_key,
                    'authToken'      => $sub->auth_key,
                    'contentEncoding'=> 'aesgcm',
                ]);
                $webPush->queueNotification($subscription, $payload);
                $sub->update(['last_used_at' => now()]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('WebPush queue failed', ['endpoint' => $sub->endpoint, 'error' => $e->getMessage()]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                // Remove stale subscriptions (expired/invalid)
                if ($report->isSubscriptionExpired()) {
                    StaffPushSubscription::where('endpoint', $report->getRequest()->getUri()->__toString())->delete();
                }
            }
        }
    }
}
