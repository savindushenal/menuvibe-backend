<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait TenantAware
{
    /**
     * Boot the TenantAware trait for a model.
     */
    protected static function bootTenantAware()
    {
        // Automatically scope queries to current franchise
        static::addGlobalScope('franchise', function (Builder $builder) {
            if (auth()->check() && auth()->user()->franchise_id) {
                $builder->where('franchise_id', auth()->user()->franchise_id);
            }
        });

        // Set franchise_id on creation
        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->franchise_id && !$model->franchise_id) {
                $model->franchise_id = auth()->user()->franchise_id;
            }
        });
    }

    /**
     * Scope to a specific tenant/franchise
     */
    public function scopeTenant($query, $franchiseId = null)
    {
        $franchiseId = $franchiseId ?? auth()->user()?->franchise_id;
        
        return $franchiseId ? $query->where('franchise_id', $franchiseId) : $query;
    }

    /**
     * Get tenant database connection
     */
    public function getTenantConnection(): string
    {
        if (!$this->franchise) {
            return config('database.default');
        }

        $slug = $this->franchise->slug;
        $config = config("franchise.{$slug}", config('franchise.default'));
        
        return $config['database_connection'] ?? config('database.default');
    }
}
