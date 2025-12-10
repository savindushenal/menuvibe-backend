<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'events',
        'is_active',
        'max_retries',
        'timeout_seconds',
        'description',
        'headers',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Available webhook events
     */
    public static $availableEvents = [
        // Menu events
        'menu.created',
        'menu.updated',
        'menu.deleted',
        'menu.published',
        
        // Menu item events
        'menu_item.created',
        'menu_item.updated',
        'menu_item.deleted',
        'menu_item.out_of_stock',
        
        // Location events
        'location.created',
        'location.updated',
        'location.deleted',
        
        // Order events (if ordering enabled)
        'order.placed',
        'order.confirmed',
        'order.completed',
        'order.cancelled',
        
        // QR code events
        'qr_code.scanned',
        'qr_code.created',
        
        // Analytics events
        'analytics.menu_view',
        'analytics.item_view',
        
        // Subscription events
        'subscription.upgraded',
        'subscription.downgraded',
        'subscription.cancelled',
    ];

    /**
     * Check if webhook is subscribed to event
     */
    public function isSubscribedTo($event)
    {
        if (!$this->is_active) {
            return false;
        }

        $events = $this->events ?? [];
        
        // Check for wildcard subscription
        if (in_array('*', $events)) {
            return true;
        }

        // Check for specific event
        if (in_array($event, $events)) {
            return true;
        }

        // Check for resource wildcard (e.g., menu.* matches menu.created)
        [$resource, $action] = explode('.', $event);
        if (in_array("{$resource}.*", $events)) {
            return true;
        }

        return false;
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Get delivery stats
     */
    public function getDeliveryStats($period = '24h')
    {
        $since = match($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        return $this->deliveries()
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN failed_at IS NOT NULL THEN 1 ELSE 0 END) as failed,
                AVG(duration_ms) as avg_duration
            ')
            ->first();
    }
}
