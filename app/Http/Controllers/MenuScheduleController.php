<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuSchedule;
use App\Services\MenuResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * MenuScheduleController
 * 
 * Manages time-based menu schedules for branches/locations
 * Allows franchise admins to define when specific menus are available
 */
class MenuScheduleController extends Controller
{
    protected MenuResolver $resolver;

    public function __construct(MenuResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get menus with their current schedules for a location
     * 
     * GET /franchise/{slug}/location/{locationId}/menus-schedules
     */
    public function index(Request $request, string $franchiseSlug, int $locationId)
    {
        $franchise = $request->get('franchise');
        $location = Location::where('franchise_id', $franchise->id)
            ->where('id', $locationId)
            ->firstOrFail();

        $menus = $this->resolver->getMenusWithSchedules($location);

        return response()->json([
            'success' => true,
            'data' => [
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'timezone' => $location->timezone ?? config('app.timezone'),
                ],
                'menus' => $menus,
            ],
        ]);
    }

    /**
     * Resolve active menus for a location at a specific time
     * 
     * GET /franchise/{slug}/location/{locationId}/resolve-menus
     * Query params: ?datetime=2025-02-21T15:30:00
     */
    public function resolve(Request $request, string $franchiseSlug, int $locationId)
    {
        $franchise = $request->get('franchise');
        $location = Location::where('franchise_id', $franchise->id)
            ->where('id', $locationId)
            ->firstOrFail();

        $dateTime = null;
        if ($request->has('datetime')) {
            try {
                $dateTime = Carbon::parse($request->input('datetime'));
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid datetime format',
                ], 422);
            }
        }

        $resolution = $this->resolver->resolve($location, $dateTime);

        return response()->json([
            'success' => true,
            'data' => $resolution,
        ]);
    }

    /**
     * Create a new menu schedule
     * 
     * POST /franchise/{slug}/menus/{menuId}/schedules
     */
    public function store(Request $request, string $franchiseSlug, int $menuId)
    {
        $franchise = $request->get('franchise');
        $menu = Menu::where('franchise_id', $franchise->id)
            ->where('id', $menuId)
            ->firstOrFail();

        $location = Location::where('franchise_id', $franchise->id)
            ->where('id', $menu->location_id)
            ->firstOrFail();

        $validated = $request->validate([
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'days' => 'required|array|min:1', // [0, 1, 2, 3, 4, 5, 6] for days of week
            'days.*' => 'integer|min:0|max:6',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:0',
            'timezone' => 'nullable|timezone',
            'allow_overlap' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = MenuSchedule::create([
            'menu_id' => $menu->id,
            'location_id' => $location->id,
            'franchise_id' => $franchise->id,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'days' => $validated['days'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'timezone' => $validated['timezone'] ?? config('app.timezone'),
            'allow_overlap' => $validated['allow_overlap'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu schedule created successfully',
            'data' => $schedule,
        ], 201);
    }

    /**
     * Update a menu schedule
     * 
     * PUT /franchise/{slug}/menus/{menuId}/schedules/{scheduleId}
     */
    public function update(Request $request, string $franchiseSlug, int $menuId, int $scheduleId)
    {
        $franchise = $request->get('franchise');
        $menu = Menu::where('franchise_id', $franchise->id)
            ->where('id', $menuId)
            ->firstOrFail();

        $schedule = MenuSchedule::where('menu_id', $menu->id)
            ->where('id', $scheduleId)
            ->firstOrFail();

        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s',
            'days' => 'sometimes|array|min:1',
            'days.*' => 'integer|min:0|max:6',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:0',
            'timezone' => 'nullable|timezone',
            'allow_overlap' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu schedule updated successfully',
            'data' => $schedule,
        ]);
    }

    /**
     * Delete a menu schedule
     * 
     * DELETE /franchise/{slug}/menus/{menuId}/schedules/{scheduleId}
     */
    public function destroy(Request $request, string $franchiseSlug, int $menuId, int $scheduleId)
    {
        $franchise = $request->get('franchise');
        $menu = Menu::where('franchise_id', $franchise->id)
            ->where('id', $menuId)
            ->firstOrFail();

        $schedule = MenuSchedule::where('menu_id', $menu->id)
            ->where('id', $scheduleId)
            ->firstOrFail();

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu schedule deleted successfully',
        ]);
    }

    /**
     * Get a specific menu schedule
     * 
     * GET /franchise/{slug}/menus/{menuId}/schedules/{scheduleId}
     */
    public function show(Request $request, string $franchiseSlug, int $menuId, int $scheduleId)
    {
        $franchise = $request->get('franchise');
        $menu = Menu::where('franchise_id', $franchise->id)
            ->where('id', $menuId)
            ->firstOrFail();

        $schedule = MenuSchedule::where('menu_id', $menu->id)
            ->where('id', $scheduleId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * Get all schedules for a menu
     * 
     * GET /franchise/{slug}/menus/{menuId}/schedules
     */
    public function getForMenu(Request $request, string $franchiseSlug, int $menuId)
    {
        $franchise = $request->get('franchise');
        $menu = Menu::where('franchise_id', $franchise->id)
            ->where('id', $menuId)
            ->firstOrFail();

        $schedules = MenuSchedule::where('menu_id', $menu->id)
            ->orderBy('is_active', 'desc')
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                ],
                'schedules' => $schedules,
            ],
        ]);
    }
}
