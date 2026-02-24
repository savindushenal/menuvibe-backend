<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;

class QrScanSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'endpoint_id',
        'location_id',
        'franchise_id',
        'loyalty_number',
        'loyalty_provider',
        'loyalty_data',
        'device_fingerprint',
        'device_type',
        'user_agent',
        'ip_address',
        'metadata',
        'cart_data',
        'table_identifier',
        'scan_count',
        'order_count',
        'total_spent',
        'first_scan_at',
        'last_activity_at',
        'expires_at',
        'has_ordered',
        'first_order_at',
    ];

    protected $casts = [
        'loyalty_data' => 'array',
        'metadata' => 'array',
        'cart_data' => 'array',
        'scan_count' => 'integer',
        'order_count' => 'integer',
        'total_spent' => 'decimal:2',
        'first_scan_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'has_ordered' => 'boolean',
        'first_order_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::creating(function ($session) {
            if (empty($session->session_token)) {
                $session->session_token = self::generateToken($session->location_id);
            }
            
            if (empty($session->expires_at)) {
                $session->expires_at = Carbon::now()->addHours(24);
            }
        });
    }

    /**
     * Generate a unique session token
     */
    public static function generateToken(int $locationId): string
    {
        $randomPart = Str::random(32);
        $timestamp = time();
        return "{$randomPart}_{$locationId}_{$timestamp}";
    }

    /**
     * Create or update a session based on scan context
     */
    public static function createOrUpdateSession(
        MenuEndpoint $endpoint,
        ?string $existingToken = null,
        ?string $deviceFingerprint = null,
        ?string $loyaltyNumber = null
    ): self {
        // Try to find existing session
        $session = null;
        
        if ($existingToken) {
            $session = self::where('session_token', $existingToken)
                ->where('expires_at', '>', Carbon::now())
                ->first();
        }
        
        // If no valid session found, check by device fingerprint
        if (!$session && $deviceFingerprint) {
            $session = self::where('device_fingerprint', $deviceFingerprint)
                ->where('location_id', $endpoint->location_id)
                ->where('expires_at', '>', Carbon::now())
                ->latest('last_activity_at')
                ->first();
        }
        
        if ($session) {
            // Session exists - determine action
            if ($session->endpoint_id === $endpoint->id) {
                // Same endpoint - just update activity
                $session->updateActivity();
            } elseif ($session->location_id === $endpoint->location_id) {
                // Different endpoint, same location - customer moved tables
                $session->moveToEndpoint($endpoint);
            } else {
                // Different location - create new session
                $session = self::createNewSession($endpoint, $deviceFingerprint, $loyaltyNumber);
            }
        } else {
            // No existing session - create new one
            $session = self::createNewSession($endpoint, $deviceFingerprint, $loyaltyNumber);
        }
        
        return $session;
    }

    /**
     * Create a new session
     */
    protected static function createNewSession(
        MenuEndpoint $endpoint,
        ?string $deviceFingerprint = null,
        ?string $loyaltyNumber = null
    ): self {
        return self::create([
            'endpoint_id' => $endpoint->id,
            'location_id' => $endpoint->location_id,
            'franchise_id' => $endpoint->franchise_id,
            'loyalty_number' => $loyaltyNumber,
            'device_fingerprint' => $deviceFingerprint,
            'device_type' => request()->header('X-Device-Type'),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'first_scan_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
        ]);
    }

    /**
     * Update session activity
     */
    public function updateActivity(): void
    {
        $this->increment('scan_count');
        $this->update([
            'last_activity_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHours(24), // Extend expiry
        ]);
    }

    /**
     * Move session to a different endpoint (table change)
     */
    public function moveToEndpoint(MenuEndpoint $newEndpoint): void
    {
        // Record the change
        EndpointChange::create([
            'session_id' => $this->id,
            'from_endpoint_id' => $this->endpoint_id,
            'to_endpoint_id' => $newEndpoint->id,
            'location_id' => $this->location_id,
            'change_type' => 'moved',
        ]);
        
        // Update session
        $this->update([
            'endpoint_id' => $newEndpoint->id,
            'last_activity_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        
        $this->increment('scan_count');
    }

    /**
     * Link loyalty account to session
     */
    public function linkLoyalty(string $loyaltyNumber, string $provider = 'internal', ?array $loyaltyData = null): void
    {
        $this->update([
            'loyalty_number' => $loyaltyNumber,
            'loyalty_provider' => $provider,
            'loyalty_data' => $loyaltyData,
        ]);
    }

    /**
     * Record an order placed in this session
     */
    public function recordOrder(float $amount): void
    {
        $this->increment('order_count');
        $this->increment('total_spent', $amount);
        
        if (!$this->has_ordered) {
            $this->update([
                'has_ordered' => true,
                'first_order_at' => Carbon::now(),
            ]);
        }
        
        $this->updateActivity();
    }

    /**
     * Mark session as converted (made a purchase)
     */
    public function markAsConverted(): void
    {
        if (!$this->has_ordered) {
            $this->update([
                'has_ordered' => true,
                'first_order_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Get session summary for analytics
     */
    public function getSummary(): array
    {
        return [
            'session_token' => $this->session_token,
            'duration_minutes' => $this->first_scan_at->diffInMinutes(Carbon::now()),
            'scan_count' => $this->scan_count,
            'order_count' => $this->order_count,
            'total_spent' => (float) $this->total_spent,
            'has_ordered' => $this->has_ordered,
            'loyalty_linked' => !is_null($this->loyalty_number),
            'endpoint' => $this->endpoint->name,
            'location' => $this->location->name,
            'table_changes' => $this->endpointChanges()->count(),
        ];
    }

    /**
     * Check if session is still valid
     */
    public function isValid(): bool
    {
        return $this->expires_at > Carbon::now();
    }

    /**
     * Scope for active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope for expired sessions
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    // Relationships

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(MenuEndpoint::class, 'endpoint_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function menuOrders(): HasMany
    {
        return $this->hasMany(MenuOrder::class, 'session_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'qr_session_id');
    }

    public function endpointChanges(): HasMany
    {
        return $this->hasMany(EndpointChange::class, 'session_id');
    }
}
