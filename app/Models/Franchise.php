<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Franchise extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'description',
        'logo_url',
        'favicon_url',
        'primary_color',
        'secondary_color',
        'accent_color',
        'custom_css',
        'support_email',
        'support_phone',
        'website_url',
        'settings',
        'design_tokens',
        'template_type',
        'domain_verification_token',
        'domain_verified',
        'domain_verified_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'settings' => 'array',
        'design_tokens' => 'array',
        'domain_verified' => 'boolean',
        'domain_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['owner'];

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'primary_color' => '#000000',
        'secondary_color' => '#FFFFFF',
        'is_active' => true,
        'domain_verified' => false,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($franchise) {
            if (empty($franchise->slug)) {
                $franchise->slug = Str::slug($franchise->name);
            }
            
            // Generate domain verification token
            if (empty($franchise->domain_verification_token)) {
                $franchise->domain_verification_token = 'MenuVire-verify-' . Str::random(32);
            }
        });
    }

    /* =========================================
     * RELATIONSHIPS
     * ========================================= */

    /**
     * Get all locations belonging to this franchise.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get all franchise user pivot records.
     */
    public function franchiseUsers(): HasMany
    {
        return $this->hasMany(FranchiseUser::class);
    }

    /**
     * Get all pricing configurations for this franchise.
     */
    public function pricing(): HasMany
    {
        return $this->hasMany(FranchisePricing::class);
    }

    /**
     * Get the active pricing configuration.
     */
    public function activePricing()
    {
        return $this->hasOne(FranchisePricing::class)->where('is_active', true)->latest();
    }

    /**
     * Get all users belonging to this franchise.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'franchise_users')
                    ->withPivot(['role', 'permissions', 'location_ids', 'is_active'])
                    ->withTimestamps();
    }

    /**
     * Get franchise owner(s).
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Get the primary franchise owner (first owner).
     * This is an accessor that returns the first owner for convenience.
     */
    public function getOwnerAttribute()
    {
        return $this->owners()->first();
    }

    /**
     * Get franchise admins.
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    /**
     * Get franchise managers.
     */
    public function managers(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'manager');
    }

    /* =========================================
     * BRANDING METHODS
     * ========================================= */

    /**
     * Get branding data for frontend consumption.
     */
    public function getBrandingData(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'favicon_url' => $this->favicon_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'custom_css' => $this->custom_css,
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'website_url' => $this->website_url,
            'settings' => $this->settings,
        ];
    }

    /**
     * Get CSS variables for dynamic theming.
     */
    public function getCssVariables(): string
    {
        $css = ":root {\n";
        $css .= "  --franchise-primary: {$this->primary_color};\n";
        $css .= "  --franchise-secondary: {$this->secondary_color};\n";
        
        if ($this->accent_color) {
            $css .= "  --franchise-accent: {$this->accent_color};\n";
        }
        
        $css .= "}\n";
        
        if ($this->custom_css) {
            $css .= "\n" . $this->custom_css;
        }
        
        return $css;
    }

    /* =========================================
     * DOMAIN METHODS
     * ========================================= */

    /**
     * Check if franchise is accessible via the given domain.
     */
    public function matchesDomain(string $domain): bool
    {
        // Check custom domain
        if ($this->custom_domain && $this->domain_verified) {
            if (strtolower($this->custom_domain) === strtolower($domain)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if franchise is accessible via the given subdomain.
     */
    public function matchesSubdomain(string $subdomain): bool
    {
        return strtolower($this->slug) === strtolower($subdomain);
    }

    /**
     * Get the DNS records needed for custom domain verification.
     */
    public function getDomainVerificationRecords(): array
    {
        return [
            [
                'type' => 'CNAME',
                'name' => 'menu', // For menu.yourdomain.com
                'value' => 'cname.MenuVire.com',
                'description' => 'Points your subdomain to MenuVire',
            ],
            [
                'type' => 'TXT',
                'name' => '_MenuVire-verify',
                'value' => $this->domain_verification_token,
                'description' => 'Verifies domain ownership',
            ],
        ];
    }

    /**
     * Mark domain as verified.
     */
    public function markDomainVerified(): void
    {
        $this->update([
            'domain_verified' => true,
            'domain_verified_at' => now(),
        ]);
    }

    /* =========================================
     * SETTINGS HELPERS
     * ========================================= */

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        return data_get($settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /* =========================================
     * STATISTICS
     * ========================================= */

    /**
     * Get aggregate statistics across all franchise locations.
     */
    public function getStatistics(): array
    {
        $locations = $this->locations()->with(['menus.categories.items'])->get();
        
        return [
            'total_locations' => $locations->count(),
            'active_locations' => $locations->where('is_active', true)->count(),
            'total_menus' => $locations->sum(fn($l) => $l->menus->count()),
            'total_menu_items' => $locations->sum(function($l) {
                return $l->menus->sum(fn($m) => $m->categories->sum(fn($c) => $c->items->count()));
            }),
            'total_team_members' => $this->users()->count(),
        ];
    }

    /* =========================================
     * SCOPES
     * ========================================= */

    /**
     * Scope to only active franchises.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to find by slug or custom domain.
     */
    public function scopeByDomainOrSlug($query, string $identifier)
    {
        return $query->where('slug', $identifier)
                    ->orWhere(function($q) use ($identifier) {
                        $q->where('custom_domain', $identifier)
                          ->where('domain_verified', true);
                    });
    }
}
