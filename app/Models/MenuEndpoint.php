<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'franchise_id',
        'template_id',
        'type',
        'name',
        'identifier',
        'description',
        'short_code',
        'qr_code_url',
        'short_url',
        'settings',
        'is_active',
        'last_scanned_at',
        'scan_count',
        'capacity',
        'floor',
        'section',
        'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'position' => 'array',
        'last_scanned_at' => 'datetime',
        'scan_count' => 'integer',
        'capacity' => 'integer',
    ];

    const TYPES = [
        'table' => 'Table',
        'room' => 'Room',
        'area' => 'Area',
        'branch' => 'Branch',
        'kiosk' => 'Kiosk',
        'takeaway' => 'Takeaway',
        'delivery' => 'Delivery',
        'drive_thru' => 'Drive Thru',
        'bar' => 'Bar',
        'patio' => 'Patio',
        'private' => 'Private Dining',
    ];

    // ===========================================
    // BOOT
    // ===========================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($endpoint) {
            if (empty($endpoint->short_code)) {
                $endpoint->short_code = self::generateShortCode();
            }
            if (empty($endpoint->short_url)) {
                $endpoint->short_url = config('app.frontend_url', 'https://menuvibe.com') . '/m/' . $endpoint->short_code;
            }
        });
    }

    /**
     * Generate unique short code
     */
    public static function generateShortCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('short_code', $code)->exists());

        return $code;
    }

    // ===========================================
    // RELATIONSHIPS
    // ===========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MenuTemplate::class, 'template_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(EndpointOverride::class, 'endpoint_id');
    }

    // ===========================================
    // SCOPES
    // ===========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    // ===========================================
    // ACCESSORS
    // ===========================================

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->type_name . ' ' . $this->identifier;
    }

    public function getMenuUrlAttribute(): string
    {
        return config('app.frontend_url', 'https://menuvibe.com') . '/m/' . $this->short_code;
    }

    // ===========================================
    // METHODS
    // ===========================================

    /**
     * Record a scan
     */
    public function recordScan(): void
    {
        $this->increment('scan_count');
        $this->update(['last_scanned_at' => now()]);
    }

    /**
     * Get menu with overrides applied
     */
    public function getMenuWithOverrides(): array
    {
        $template = $this->template;
        if (!$template) {
            return [];
        }

        $menu = $template->getFullMenu();

        // Apply overrides
        $overrides = $this->overrides->keyBy('item_id');

        foreach ($menu['categories'] as &$category) {
            foreach ($category['items'] as &$item) {
                if (isset($overrides[$item['id']])) {
                    $override = $overrides[$item['id']];
                    if ($override->price_override !== null) {
                        $item['price'] = $override->price_override;
                    }
                    if ($override->is_available !== null) {
                        $item['is_available'] = $override->is_available;
                    }
                    if ($override->is_featured !== null) {
                        $item['is_featured'] = $override->is_featured;
                    }
                }
            }
            // Filter out unavailable items
            $category['items'] = array_filter($category['items'], fn($item) => $item['is_available']);
            $category['items'] = array_values($category['items']);
        }

        // Filter out empty categories
        $menu['categories'] = array_filter($menu['categories'], fn($cat) => count($cat['items']) > 0);
        $menu['categories'] = array_values($menu['categories']);

        // Add endpoint info
        $menu['endpoint'] = [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->display_name,
            'identifier' => $this->identifier,
        ];

        return $menu;
    }

    /**
     * Regenerate QR code URL
     */
    public function regenerateQrCode(): void
    {
        $this->short_code = self::generateShortCode();
        $this->short_url = config('app.frontend_url', 'https://menuvibe.com') . '/m/' . $this->short_code;
        $this->qr_code_url = null; // Will be regenerated on next request
        $this->save();
    }
}
