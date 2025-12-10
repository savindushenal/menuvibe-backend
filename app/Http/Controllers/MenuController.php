<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class MenuController extends Controller
{
    /**
     * Get user from token manually
     */
    private function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        $personalAccessToken = PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return null;
        }

        return $personalAccessToken->tokenable;
    }

    /**
     * Display a listing of the menus.
     */
    public function index(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        \Log::info('MenuController@index - Auth Check', [
            'has_user' => !is_null($user),
            'user_id' => $user ? $user->id : null,
            'token_present' => !is_null($request->bearerToken())
        ]);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get current/default location for the user
        $currentLocation = $user->defaultLocation ?? $user->locations()->first();
        
        \Log::info('MenuController@index - Location Check', [
            'has_default' => !is_null($user->defaultLocation),
            'location_id' => $currentLocation ? $currentLocation->id : null,
            'locations_count' => $user->locations()->count()
        ]);

        if (!$currentLocation) {
            return response()->json([
                'success' => false,
                'message' => 'No location found. Please create a location first.'
            ], Response::HTTP_NOT_FOUND);
        }

        $menus = $currentLocation->menus()->with(['menuItems', 'categories'])->ordered()->get();

        // Get subscription info
        $subscription = $user->getCurrentSubscriptionPlan();
        $menuCount = $menus->count();
        $totalMenuItems = $menus->sum(function($menu) {
            return $menu->menuItems->count();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'menus' => $menus,
                'current_location' => $currentLocation,
                'subscription_info' => [
                    'plan' => $subscription,
                    'limits' => [
                        'max_menus' => $subscription ? $subscription->getLimit('max_menus_per_location') : 1,
                        'max_menu_items' => $subscription ? $subscription->getLimit('max_menu_items_per_menu') : 10,
                        'current_menus' => $menuCount,
                        'current_menu_items' => $totalMenuItems,
                        'can_create_menu' => $user->canPerformAction('max_menus_per_location', $menuCount),
                        'can_upload_photos' => $subscription ? $subscription->allowsFeature('photo_uploads') : false,
                    ]
                ]
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created menu.
     */
    public function store(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get current/default location for the user
        $currentLocation = $user->defaultLocation ?? $user->locations()->first();

        if (!$currentLocation) {
            return response()->json([
                'success' => false,
                'message' => 'No location found. Please create a location first.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check subscription limits
        $currentMenuCount = $currentLocation->menus()->count();
        if (!$user->canPerformAction('max_menus_per_location', $currentMenuCount)) {
            $subscription = $user->getCurrentSubscriptionPlan();
            $limit = $subscription ? $subscription->getLimit('max_menus_per_location') : 1;
            
            return response()->json([
                'success' => false,
                'message' => 'Menu limit reached for this location',
                'error_type' => 'subscription_limit',
                'data' => [
                    'current_plan' => $subscription ? $subscription->name : 'Unknown',
                    'limit' => $limit,
                    'current_count' => $currentMenuCount,
                    'upgrade_required' => true
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'style' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'availability_hours' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        $data['location_id'] = $currentLocation->id;

        // Handle image upload
        if ($request->hasFile('image')) {
            $subscription = $user->getCurrentSubscriptionPlan();
            
            // Check if plan allows photo uploads
            if (!$subscription || !$subscription->allowsFeature('photo_uploads')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo uploads not allowed on your current plan',
                    'error_type' => 'subscription_feature',
                    'data' => [
                        'current_plan' => $subscription ? $subscription->name : 'Unknown',
                        'feature_required' => 'photo_uploads',
                        'upgrade_required' => true
                    ]
                ], Response::HTTP_FORBIDDEN);
            }
            
            $imageFile = $request->file('image');
            $imagePath = $imageFile->store('menus', 'public');
            $data['image_url'] = '/storage/' . $imagePath;
        }

        $menu = Menu::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu created successfully',
            'data' => [
                'menu' => $menu->load('menuItems')
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified menu.
     */
    public function show(Request $request, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get user's location
        $currentLocation = $user->defaultLocation ?? $user->locations()->first();

        if (!$currentLocation) {
            return response()->json([
                'success' => false,
                'message' => 'No location found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menu = Menu::whereHas('location', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => $menu
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified menu.
     */
    public function update(Request $request, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get user's location and find menu
        $currentLocation = $user->defaultLocation ?? $user->locations()->first();

        if (!$currentLocation) {
            return response()->json([
                'success' => false,
                'message' => 'No location found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menu = Menu::whereHas('location', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'style' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'availability_hours' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imagePath = $imageFile->store('menus', 'public');
            $data['image_url'] = '/storage/' . $imagePath;
        }

        $menu->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully',
            'data' => [
                'menu' => $menu->fresh()->load('menuItems')
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified menu.
     */
    public function destroy(Request $request, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get user's location and find menu
        $currentLocation = $user->defaultLocation ?? $user->locations()->first();

        if (!$currentLocation) {
            return response()->json([
                'success' => false,
                'message' => 'No location found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menu = Menu::whereHas('location', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully'
        ], Response::HTTP_OK);
    }
}
