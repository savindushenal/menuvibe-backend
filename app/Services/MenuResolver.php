<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * MenuResolver Service
 * 
 * Handles smart menu selection logic:
 * - Finds active menus for a location at a given time
 * - Resolves conflicts when multiple menus are available
 * - Provides corridor payload when user should see multiple menu options
 * - Includes caching hints (next_change_at) for optimization
 */
class MenuResolver
{
    /**
     * Resolve which menu(s) are active for a location at a given time
     *
     * @param Location $location The location/branch
     * @param Carbon|null $dateTime Current datetime (defaults to now). Will be converted to location timezone.
     * @return array {
     *   'action': 'redirect'|'corridor',
     *   'menu': Menu|null (if action=redirect),
     *   'menus': Collection|null (if action=corridor), 
     *   'next_change_at': Carbon|null,
     *   'cache_ttl_seconds': int|null,
     * }
     */
    public function resolve(Location $location, ?Carbon $dateTime = null): array
    {
        $dateTime = $dateTime ?? Carbon::now();
        
        // Use location timezone if available, fall back to app timezone
        $locationTimezone = $location->timezone ?? config('app.timezone', 'UTC');
        $dateTime = $dateTime->setTimezone($locationTimezone);

        // Fetch all active menus with their active schedules for this location
        $activeMenus = $this->getActiveMenusAtTime($location, $dateTime);

        if ($activeMenus->isEmpty()) {
            // No active menus found
            return [
                'action' => 'error',
                'message' => 'No menus available at this time',
                'menus' => collect(),
                'next_change_at' => $this->getNextMenuChangeAt($location, $dateTime),
                'cache_ttl_seconds' => 3600, // Cache for 1 hour if no menus
            ];
        }

        if ($activeMenus->count() === 1) {
            // Single menu active - redirect directly
            $menu = $activeMenus->first();
            return [
                'action' => 'redirect',
                'menu' => $menu,
                'menu_slug' => $menu->slug,
                'next_change_at' => $this->getNextMenuChangeAt($location, $dateTime),
                'cache_ttl_seconds' => $this->calculateCacheTTL($location, $dateTime),
            ];
        }

        // Multiple menus active - return corridor
        return [
            'action' => 'corridor',
            'menus' => $activeMenus->map(fn($menu) => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'description' => $menu->description,
                'image_url' => $menu->image_url,
                'style' => $menu->style ?? 'premium',
                'active_schedule' => $this->getActiveScheduleForMenu($menu, $dateTime),
            ]),
            'next_change_at' => $this->getNextMenuChangeAt($location, $dateTime),
            'cache_ttl_seconds' => $this->calculateCacheTTL($location, $dateTime),
        ];
    }

    /**
     * Get all menus that are active at a given time for a location
     * Handles priority and conflict resolution
     *
     * @param Location $location
     * @param Carbon $dateTime (already in location timezone)
     * @return Collection of Menu models
     */
    private function getActiveMenusAtTime(Location $location, Carbon $dateTime): Collection
    {
        // Get all menus for this location
        $menus = Menu::where('location_id', $location->id)
            ->where('is_active', true)
            ->get();

        // Filter menus by schedule
        $activeMenus = $menus->filter(function ($menu) use ($dateTime) {
            return $this->isMenuActiveAtTime($menu, $dateTime);
        });

        // Sort by priority (descending) then by specificity
        return $activeMenus->sort(function ($a, $b) {
            $aPriority = $this->getMaxSchedulePriority($a, Carbon::now());
            $bPriority = $this->getMaxSchedulePriority($b, Carbon::now());

            if ($aPriority !== $bPriority) {
                return $bPriority <=> $aPriority; // Higher priority first
            }

            // If same priority, sort by created_at (newer first)
            return $b->created_at <=> $a->created_at;
        });
    }

    /**
     * Check if a menu is active at a given time
     *
     * @param Menu $menu
     * @param Carbon $dateTime
     * @return bool
     */
    private function isMenuActiveAtTime(Menu $menu, Carbon $dateTime): bool
    {
        // If menu has no schedules, consider it always active
        if ($menu->schedules()->where('is_active', true)->count() === 0) {
            return true;
        }

        // Check if any active schedule matches
        return $menu->schedules()
            ->where('is_active', true)
            ->get()
            ->some(fn($schedule) => $schedule->isActiveAt($dateTime));
    }

    /**
     * Get the maximum priority among active schedules for a menu
     *
     * @param Menu $menu
     * @param Carbon $dateTime
     * @return int
     */
    private function getMaxSchedulePriority(Menu $menu, Carbon $dateTime): int
    {
        return $menu->schedules()
            ->where('is_active', true)
            ->get()
            ->filter(fn($schedule) => $schedule->isActiveAt($dateTime))
            ->max('priority') ?? 0;
    }

    /**
     * Get the active schedule details for a menu at a given time
     *
     * @param Menu $menu
     * @param Carbon $dateTime
     * @return array|null
     */
    private function getActiveScheduleForMenu(Menu $menu, Carbon $dateTime): ?array
    {
        $schedule = $menu->schedules()
            ->where('is_active', true)
            ->get()
            ->first(fn($s) => $s->isActiveAt($dateTime));

        if (!$schedule) {
            return null;
        }

        return [
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'priority' => $schedule->priority,
            'days' => $schedule->days,
        ];
    }

    /**
     * Calculate the next time a menu change will occur for a location
     * Used to set cache TTL and inform client of refresh timing
     *
     * @param Location $location
     * @param Carbon $dateTime (in location timezone)
     * @return Carbon|null
     */
    private function getNextMenuChangeAt(Location $location, Carbon $dateTime): ?Carbon
    {
        $schedules = MenuSchedule::where('location_id', $location->id)
            ->where('is_active', true)
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $nextChanges = $schedules
            ->map(fn($schedule) => $schedule->getNextChangeAt($dateTime))
            ->filter(fn($date) => $date !== null);

        return $nextChanges->isNotEmpty() ? $nextChanges->min() : null;
    }

    /**
     * Calculate cache TTL in seconds based on next menu change time
     *
     * @param Location $location
     * @param Carbon $dateTime (in location timezone)
     * @return int|null Seconds, or null if no next change
     */
    private function calculateCacheTTL(Location $location, Carbon $dateTime): ?int
    {
        $nextChange = $this->getNextMenuChangeAt($location, $dateTime);

        if (!$nextChange) {
            return 3600; // Default 1 hour if no next change found
        }

        $ttl = (int) $nextChange->diffInSeconds($dateTime);
        
        // Ensure TTL is positive and reasonable
        return max(60, min($ttl, 86400)); // Between 1 minute and 1 day
    }

    /**
     * Get all menus for a location with their schedule information
     * Useful for admin UI showing menu availability
     *
     * @param Location $location
     * @return Collection
     */
    public function getMenusWithSchedules(Location $location): Collection
    {
        return Menu::where('location_id', $location->id)
            ->where('is_active', true)
            ->with('activeSchedules')
            ->get()
            ->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'description' => $menu->description,
                    'is_active' => $menu->is_active,
                    'schedules' => $menu->activeSchedules->map(fn($s) => [
                        'id' => $s->id,
                        'start_time' => $s->start_time,
                        'end_time' => $s->end_time,
                        'days' => $s->days,
                        'start_date' => $s->start_date,
                        'end_date' => $s->end_date,
                        'priority' => $s->priority,
                        'timezone' => $s->timezone,
                    ]),
                ];
            });
    }
}
