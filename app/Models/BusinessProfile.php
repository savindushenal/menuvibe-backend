<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'description',
        'phone',
        'email',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'cuisine_type',
        'seating_capacity',
        'operating_hours',
        'services',
        'onboarding_completed',
        'onboarding_completed_at',
        'logo_url',
        'primary_color',
        'secondary_color',
        'social_media',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'services' => 'array',
        'social_media' => 'array',
        'onboarding_completed' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'seating_capacity' => 'integer',
    ];

    /**
     * Get the user that owns the business profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if onboarding is completed
     */
    public function isOnboardingCompleted(): bool
    {
        return $this->onboarding_completed;
    }

    /**
     * Mark onboarding as completed
     */
    public function completeOnboarding(): void
    {
        $this->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);
    }

    /**
     * Get the menus for the business profile
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class)->orderBy('sort_order');
    }

    /**
     * Get active menus
     */
    public function activeMenus(): HasMany
    {
        return $this->hasMany(Menu::class)->where('is_active', true)->orderBy('sort_order');
    }
}
