<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get the admin who last updated this setting
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get a setting value
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = 'platform_setting_' . $key;
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }
            
            return $setting->getCastedValue();
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value, ?User $admin = null): self
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'updated_by' => $admin?->id,
            ]
        );
        
        // Clear cache
        Cache::forget('platform_setting_' . $key);
        Cache::forget('platform_settings_all');
        Cache::forget('platform_settings_public');
        
        return $setting;
    }

    /**
     * Get all settings
     */
    public static function getAll(): array
    {
        return Cache::remember('platform_settings_all', 3600, function () {
            return self::all()->pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get all public settings
     */
    public static function getPublic(): array
    {
        return Cache::remember('platform_settings_public', 3600, function () {
            return self::where('is_public', true)->get()->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->getCastedValue()];
            })->toArray();
        });
    }

    /**
     * Get settings by group
     */
    public static function getByGroup(string $group): array
    {
        return self::where('group', $group)->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->getCastedValue()];
        })->toArray();
    }

    /**
     * Get the casted value based on type
     */
    public function getCastedValue()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget('platform_setting_' . $setting->key);
        }
        Cache::forget('platform_settings_all');
        Cache::forget('platform_settings_public');
    }
}
