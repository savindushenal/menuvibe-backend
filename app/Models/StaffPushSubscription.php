<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPushSubscription extends Model
{
    protected $fillable = [
        'location_id',
        'franchise_id',
        'endpoint',
        'p256dh_key',
        'auth_key',
        'device_label',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public static function forLocation(int $locationId)
    {
        return static::where('location_id', $locationId)->get();
    }

    /**
     * Upsert a subscription (register or update by endpoint).
     */
    public static function register(array $data): static
    {
        return static::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'location_id'  => $data['location_id'],
                'franchise_id' => $data['franchise_id'] ?? null,
                'p256dh_key'   => $data['p256dh_key'],
                'auth_key'     => $data['auth_key'],
                'device_label' => $data['device_label'] ?? null,
                'last_used_at' => now(),
            ]
        );
    }
}
