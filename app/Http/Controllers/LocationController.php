<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\LocationAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    protected $locationAccessService;

    public function __construct(LocationAccessService $locationAccessService)
    {
        $this->locationAccessService = $locationAccessService;
    }

    /**
     * Display a listing of the user's locations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Get accessible locations based on subscription
        $accessibleLocations = $this->locationAccessService->getAccessibleLocations($user);
        $blockedLocations = $this->locationAccessService->getBlockedLocations($user);
        $limitInfo = $this->locationAccessService->getLocationLimitInfo($user);

        // Load menus for accessible locations (Collections need to load individually)
        $accessibleLocations->each(function($location) {
            $location->load(['menus' => function($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }]);
        });

        return response()->json([
            'success' => true,
            'data' => $accessibleLocations->values(),
            'blocked_locations' => $blockedLocations->map(function($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'blocked_reason' => 'Upgrade your plan to access this location',
                ];
            })->values(),
            'meta' => [
                'total_locations' => $limitInfo['current_count'],
                'accessible_count' => $limitInfo['accessible_count'],
                'blocked_count' => $limitInfo['blocked_count'],
                'max_allowed' => $limitInfo['max_allowed'],
                'can_create_more' => $limitInfo['can_create_more'],
                'is_over_limit' => $limitInfo['is_over_limit'],
            ]
        ]);
    }

    /**
     * Store a newly created location.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Check if user can add more locations using the service
        if (!$this->locationAccessService->canCreateLocation($user)) {
            $limitInfo = $this->locationAccessService->getLocationLimitInfo($user);
            
            return response()->json([
                'success' => false,
                'message' => "You have reached your location limit ({$limitInfo['max_allowed']} locations). Please upgrade your subscription to add more locations.",
                'error_code' => 'LOCATION_LIMIT_EXCEEDED',
                'limit_info' => $limitInfo,
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'cuisine_type' => 'nullable|string|max:100',
            'seating_capacity' => 'nullable|integer|min:1|max:10000',
            'operating_hours' => 'nullable|array',
            'services' => 'nullable|array',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'social_media' => 'nullable|array',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $validated['user_id'] = $user->id;
        $validated['sort_order'] = $user->locations()->count();

        $location = Location::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Location created successfully.',
            'data' => $location->load('menus')
        ], 201);
    }

    /**
     * Display the specified location.
     */
    public function show(Request $request, Location $location): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Check if user owns this location
        if ($location->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to location',
            ], 403);
        }

        // Check if user can access this location based on subscription
        if (!$this->locationAccessService->canAccessLocation($user, $location->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This location is blocked due to subscription limits. Please upgrade your plan to access it.',
                'error_code' => 'LOCATION_BLOCKED',
            ], 403);
        }

        $location->load(['menus' => function($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }]);

        return response()->json([
            'success' => true,
            'data' => $location
        ]);
    }

    /**
     * Update the specified location.
     */
    public function update(Request $request, Location $location): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Check if user owns this location
        if ($location->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to location',
            ], 403);
        }

        // Check if user can access this location based on subscription
        if (!$this->locationAccessService->canAccessLocation($user, $location->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This location is blocked due to subscription limits. Please upgrade your plan to access it.',
                'error_code' => 'LOCATION_BLOCKED',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'cuisine_type' => 'nullable|string|max:100',
            'seating_capacity' => 'nullable|integer|min:1|max:10000',
            'operating_hours' => 'nullable|array',
            'services' => 'nullable|array',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'social_media' => 'nullable|array',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $location->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully.',
            'data' => $location->load('menus')
        ]);
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Request $request, Location $location): JsonResponse
    {
        $user = $this->getUserFromToken($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Check if user owns this location
        if ($location->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this location',
                'error_code' => 'UNAUTHORIZED'
            ], 403);
        }

        // Prevent deletion if it's the only location
        if ($user->locations()->count() === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the only location. You must have at least one location.',
                'error_code' => 'LAST_LOCATION_CANNOT_BE_DELETED'
            ], 422);
        }

        // If deleting the default location, set another as default
        if ($location->is_default) {
            $newDefaultLocation = $user->locations()
                ->where('id', '!=', $location->id)
                ->first();
            
            if ($newDefaultLocation) {
                $newDefaultLocation->setAsDefault();
            }
        }

        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Location deleted successfully.'
        ]);
    }

    /**
     * Set a location as the default location.
     */
    public function setDefault(Location $location): JsonResponse
    {
        $this->authorize('update', $location);

        $location->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Default location updated successfully.',
            'data' => $location
        ]);
    }

    /**
     * Update the sort order of locations.
     */
    public function updateSortOrder(Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }
        
        $validated = $request->validate([
            'location_ids' => 'required|array',
            'location_ids.*' => 'exists:locations,id'
        ]);

        foreach ($validated['location_ids'] as $index => $locationId) {
            $user->locations()
                ->where('id', $locationId)
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Location order updated successfully.'
        ]);
    }

    /**
     * Get location statistics.
     */
    public function statistics(Location $location): JsonResponse
    {
        $this->authorize('view', $location);

        $stats = [
            'total_menus' => $location->menus()->count(),
            'active_menus' => $location->activeMenus()->count(),
            'total_menu_items' => $location->getTotalMenuItemsCount(),
            'featured_menus' => $location->menus()->where('is_featured', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Manual auth method (bypass CSRF middleware)
     */
    private function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return null;
        }

        return $personalAccessToken->tokenable;
    }
}
