<?php

namespace App\Services;

use App\Models\Franchise;
use Illuminate\Support\Facades\Cache;

class FeatureService
{
    /**
     * Check if franchise has a feature enabled
     */
    public static function hasFeature(string $feature, ?Franchise $franchise = null): bool
    {
        $franchise = $franchise ?? auth()->user()?->franchise;
        
        if (!$franchise) {
            return false;
        }

        // Check database features first
        $dbFeatures = json_decode($franchise->features, true) ?? [];
        if (isset($dbFeatures[$feature])) {
            return (bool) $dbFeatures[$feature];
        }

        // Fallback to config
        $cacheKey = "franchise.{$franchise->slug}.features";
        
        $features = Cache::remember($cacheKey, 3600, function () use ($franchise) {
            $config = config("franchise.{$franchise->slug}", config('franchise.default'));
            return $config['features'] ?? [];
        });

        return $features[$feature] ?? false;
    }

    /**
     * Get setting value
     */
    public static function getSetting(string $key, $default = null, ?Franchise $franchise = null)
    {
        $franchise = $franchise ?? auth()->user()?->franchise;
        
        if (!$franchise) {
            return $default;
        }

        // Check database settings first
        $dbSettings = json_decode($franchise->settings, true) ?? [];
        if (isset($dbSettings[$key])) {
            return $dbSettings[$key];
        }

        // Fallback to config
        $cacheKey = "franchise.{$franchise->slug}.config";
        
        $config = Cache::remember($cacheKey, 3600, function () use ($franchise) {
            return config("franchise.{$franchise->slug}", config('franchise.default'));
        });

        return $config[$key] ?? $default;
    }

    /**
     * Enable a feature for a franchise
     */
    public static function enableFeature(Franchise $franchise, string $feature): void
    {
        $features = json_decode($franchise->features, true) ?? [];
        $features[$feature] = true;
        
        $franchise->update(['features' => json_encode($features)]);
        
        Cache::forget("franchise.{$franchise->slug}.features");
        Cache::forget("franchise.{$franchise->slug}.config");
    }

    /**
     * Disable a feature for a franchise
     */
    public static function disableFeature(Franchise $franchise, string $feature): void
    {
        $features = json_decode($franchise->features, true) ?? [];
        $features[$feature] = false;
        
        $franchise->update(['features' => json_encode($features)]);
        
        Cache::forget("franchise.{$franchise->slug}.features");
        Cache::forget("franchise.{$franchise->slug}.config");
    }

    /**
     * Get all available features for franchise
     */
    public static function getAvailableFeatures(?Franchise $franchise = null): array
    {
        $franchise = $franchise ?? auth()->user()?->franchise;
        
        if (!$franchise) {
            return [];
        }

        $config = config("franchise.{$franchise->slug}", config('franchise.default'));
        
        return array_keys(array_filter($config['features'] ?? []));
    }
}
