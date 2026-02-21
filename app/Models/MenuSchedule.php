<?php

namespace App\Models;

use App\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MenuSchedule extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'menu_id',
        'location_id',
        'franchise_id',
        'start_time',
        'end_time',
        'days',
        'start_date',
        'end_date',
        'priority',
        'timezone',
        'allow_overlap',
        'is_active',
    ];

    protected $casts = [
        'days' => 'array', // JSON array of weekday numbers (0-6)
        'is_active' => 'boolean',
        'allow_overlap' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'priority' => 'integer',
    ];

    /**
     * Get the menu that owns this schedule
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get the location that owns this schedule
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the franchise that owns this schedule
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Check if this schedule is active at a given datetime (in the schedule's timezone)
     *
     * @param Carbon $dateTime DateTime to check (will be converted to schedule timezone)
     * @return bool
     */
    public function isActiveAt(Carbon $dateTime): bool
    {
        // Convert the datetime to the schedule's timezone
        $dtInTimezone = $dateTime->setTimezone($this->timezone);

        // Check date range if specified
        if ($this->start_date && $dtInTimezone->toDate() < $this->start_date) {
            return false;
        }
        if ($this->end_date && $dtInTimezone->toDate() > $this->end_date) {
            return false;
        }

        // Check day of week (0-6)
        $dayOfWeek = $dtInTimezone->dayOfWeek; // 0=Sunday, 6=Saturday
        if (!in_array($dayOfWeek, $this->days ?? [])) {
            return false;
        }

        // Check time window
        $currentTime = $dtInTimezone->format('H:i:s');
        $startTime = $this->start_time; // Already stored as 'H:i:s'
        $endTime = $this->end_time;

        // Handle case where end_time is on the next day (e.g., 22:00 to 02:00)
        if ($endTime < $startTime) {
            return $currentTime >= $startTime || $currentTime < $endTime;
        }

        return $currentTime >= $startTime && $currentTime < $endTime;
    }

    /**
     * Get the next datetime when this schedule will become active or change
     * Useful for caching/TTL decisions in the QR serving logic
     *
     * @param Carbon $fromTime Current datetime (will be converted to schedule timezone)
     * @return Carbon|null Datetime of next change, or null if schedule is no longer relevant
     */
    public function getNextChangeAt(Carbon $fromTime): ?Carbon
    {
        $dtInTimezone = $fromTime->setTimezone($this->timezone);

        // If schedule has already ended and has an end_date, return null
        if ($this->end_date && $dtInTimezone->toDate() > $this->end_date) {
            return null;
        }

        // Find next occurrence of start_time on a valid day
        // This is a simplified version; full implementation would handle complex recurring schedules
        
        $nextTime = Carbon::parse($dtInTimezone->format('Y-m-d') . ' ' . $this->start_time, $this->timezone);
        
        // If start_time has already passed today, try tomorrow
        if ($nextTime <= $dtInTimezone) {
            $nextTime = $nextTime->addDay();
        }

        // Keep adding days until we find a valid day
        $maxIterations = 14; // Check up to 2 weeks ahead
        while ($maxIterations-- > 0) {
            if (in_array($nextTime->dayOfWeek, $this->days ?? [])) {
                if (!$this->start_date || $nextTime->toDate() >= $this->start_date) {
                    if (!$this->end_date || $nextTime->toDate() <= $this->end_date) {
                        return $nextTime;
                    }
                }
            }
            $nextTime = $nextTime->addDay();
        }

        return null;
    }

    /**
     * Scope to get active schedules only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get schedules for a specific location
     */
    public function scopeForLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    /**
     * Scope to get schedules for a specific menu
     */
    public function scopeForMenu($query, $menuId)
    {
        return $query->where('menu_id', $menuId);
    }
}
