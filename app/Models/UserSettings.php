<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        // Notification preferences
        'email_notifications',
        'push_notifications',
        'weekly_reports',
        'marketing_emails',
        'order_notifications',
        'inventory_alerts',
        'team_updates',
        // Security settings
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'login_notifications',
        'suspicious_activity_alerts',
        // Privacy settings
        'profile_public',
        'analytics_tracking',
        'data_collection',
        // Display preferences
        'timezone',
        'date_format',
        'time_format',
        'language',
        'currency',
        // Business settings
        'working_hours',
        'auto_accept_orders',
        'order_preparation_time',
        'weekend_mode',
    ];

    protected $casts = [
        // Notification preferences
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'weekly_reports' => 'boolean',
        'marketing_emails' => 'boolean',
        'order_notifications' => 'boolean',
        'inventory_alerts' => 'boolean',
        'team_updates' => 'boolean',
        // Security settings
        'two_factor_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'array',
        'login_notifications' => 'boolean',
        'suspicious_activity_alerts' => 'boolean',
        // Privacy settings
        'profile_public' => 'boolean',
        'analytics_tracking' => 'boolean',
        'data_collection' => 'boolean',
        // Business settings
        'working_hours' => 'array',
        'auto_accept_orders' => 'boolean',
        'order_preparation_time' => 'integer',
        'weekend_mode' => 'boolean',
    ];

    protected $hidden = [
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the user that owns the settings
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get notification preferences as array
     */
    public function getNotificationPreferences(): array
    {
        return [
            'email_notifications' => $this->email_notifications,
            'push_notifications' => $this->push_notifications,
            'weekly_reports' => $this->weekly_reports,
            'marketing_emails' => $this->marketing_emails,
            'order_notifications' => $this->order_notifications,
            'inventory_alerts' => $this->inventory_alerts,
            'team_updates' => $this->team_updates,
        ];
    }

    /**
     * Get security settings as array
     */
    public function getSecuritySettings(): array
    {
        return [
            'two_factor_enabled' => $this->two_factor_enabled,
            'login_notifications' => $this->login_notifications,
            'suspicious_activity_alerts' => $this->suspicious_activity_alerts,
        ];
    }

    /**
     * Get privacy settings as array
     */
    public function getPrivacySettings(): array
    {
        return [
            'profile_public' => $this->profile_public,
            'analytics_tracking' => $this->analytics_tracking,
            'data_collection' => $this->data_collection,
        ];
    }

    /**
     * Get display settings as array
     */
    public function getDisplaySettings(): array
    {
        return [
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'language' => $this->language,
            'currency' => $this->currency,
        ];
    }

    /**
     * Get business settings as array
     */
    public function getBusinessSettings(): array
    {
        return [
            'working_hours' => $this->working_hours,
            'auto_accept_orders' => $this->auto_accept_orders,
            'order_preparation_time' => $this->order_preparation_time,
            'weekend_mode' => $this->weekend_mode,
        ];
    }

    /**
     * Check if user has notifications enabled for a specific type
     */
    public function hasNotificationEnabled(string $type): bool
    {
        return $this->getAttribute($type) ?? false;
    }

    /**
     * Get default settings for new users
     */
    public static function getDefaultSettings(): array
    {
        return [
            'email_notifications' => true,
            'push_notifications' => true,
            'weekly_reports' => false,
            'marketing_emails' => false,
            'order_notifications' => true,
            'inventory_alerts' => true,
            'team_updates' => true,
            'two_factor_enabled' => false,
            'login_notifications' => true,
            'suspicious_activity_alerts' => true,
            'profile_public' => false,
            'analytics_tracking' => true,
            'data_collection' => true,
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'language' => 'en',
            'currency' => 'USD',
            'working_hours' => [
                'monday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
                'tuesday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
                'wednesday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
                'thursday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
                'friday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
                'saturday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
                'sunday' => ['open' => '09:00', 'close' => '22:00', 'closed' => false],
            ],
            'auto_accept_orders' => false,
            'order_preparation_time' => 30,
            'weekend_mode' => false,
        ];
    }
}