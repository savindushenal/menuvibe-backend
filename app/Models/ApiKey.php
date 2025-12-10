<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'key_type',
        'scopes',
        'rate_limit_per_hour',
        'environment',
        'whitelisted_ips',
        'last_used_at',
        'expires_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'whitelisted_ips' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key_hash',
    ];

    /**
     * Key type configurations
     */
    public static $keyTypes = [
        'public_read' => [
            'name' => 'Public Read',
            'permissions' => ['menus:read', 'items:read', 'locations:read', 'public:*'],
            'rate_limit' => 1000,
            'description' => 'Read-only access to public menu data',
        ],
        'standard' => [
            'name' => 'Standard',
            'permissions' => ['menus:*', 'items:*', 'categories:*', 'locations:read'],
            'rate_limit' => 10000,
            'description' => 'Full CRUD for menus and items',
        ],
        'premium' => [
            'name' => 'Premium',
            'permissions' => ['*:*', 'webhooks:*', 'analytics:*', 'qr_codes:*'],
            'rate_limit' => 100000,
            'description' => 'All features including webhooks and analytics',
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'permissions' => ['*:*'],
            'rate_limit' => -1, // Unlimited
            'description' => 'Unlimited access with white-label support',
        ],
    ];

    /**
     * Generate a new API key
     */
    public static function generate($userId, $name, $keyType = 'standard', $environment = 'production')
    {
        $prefix = $environment === 'production' ? 'mvb_live_' : 'mvb_test_';
        $randomKey = Str::random(32);
        $fullKey = $prefix . $randomKey;
        
        $config = self::$keyTypes[$keyType] ?? self::$keyTypes['standard'];

        return self::create([
            'user_id' => $userId,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $fullKey),
            'key_type' => $keyType,
            'scopes' => $config['permissions'],
            'rate_limit_per_hour' => $config['rate_limit'],
            'environment' => $environment,
            'is_active' => true,
        ]);
    }

    /**
     * Verify if key has permission
     */
    public function hasPermission($permission)
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        $scopes = $this->scopes ?? [];

        // Check for wildcard permission
        if (in_array('*:*', $scopes)) {
            return true;
        }

        // Check for specific permission
        if (in_array($permission, $scopes)) {
            return true;
        }

        // Check for resource wildcard (e.g., menus:* matches menus:read)
        [$resource, $action] = explode(':', $permission);
        if (in_array("{$resource}:*", $scopes)) {
            return true;
        }

        return false;
    }

    /**
     * Check if IP is whitelisted
     */
    public function isIpAllowed($ip)
    {
        if (empty($this->whitelisted_ips)) {
            return true; // No whitelist = allow all
        }

        return in_array($ip, $this->whitelisted_ips);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function usage()
    {
        return $this->hasMany(ApiUsage::class);
    }

    /**
     * Get usage stats
     */
    public function getUsageStats($period = '24h')
    {
        $since = match($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };

        return $this->usage()
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_status < 400 THEN 1 ELSE 0 END) as successful_requests,
                SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as failed_requests,
                AVG(response_time_ms) as avg_response_time
            ')
            ->first();
    }
}
