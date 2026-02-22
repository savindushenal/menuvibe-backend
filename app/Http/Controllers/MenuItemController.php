<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class MenuItemController extends Controller
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
     * Find a menu with proper access control
     * Admin roles have full access, others need ownership or franchise membership
     */
    private function findMenuWithAccess($menuId, $user)
    {
        // Super admin, admin, and support team have full access to all menus
        if (in_array($user->role, ['super_admin', 'admin', 'support_team'])) {
            return Menu::find($menuId);
        }

        // Regular users need ownership or franchise access
        return Menu::where('id', $menuId)
            ->where(function($query) use ($user) {
                // Business menu: direct user ownership
                $query->whereHas('location', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                // OR Franchise menu: user has franchise access
                ->orWhereHas('location', function($q) use ($user) {
                    $q->whereNotNull('franchise_id')
                      ->whereHas('franchise.accounts', function($f) use ($user) {
                          $f->where('user_id', $user->id)->where('is_active', true);
                      });
                });
            })->first();
    }

    /**
     * Display a listing of menu items for a specific menu.
     */
    public function index(Request $request, $menuId)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menuItems = $menu->menuItems()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'menu_items' => $menuItems,
                'menu' => $menu
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created menu item.
     */
    public function store(Request $request, $menuId)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check subscription limits for menu items per menu
        $currentMenuItemCount = $menu->menuItems()->count();
        $subscription = $user->getCurrentSubscriptionPlan();
        $limit = $subscription ? $subscription->getLimit('max_menu_items_per_menu') : 10;
        
        \Log::info('Menu Item Subscription Check', [
            'user_id' => $user->id,
            'menu_id' => $menu->id,
            'current_count' => $currentMenuItemCount,
            'limit' => $limit,
            'plan' => $subscription ? $subscription->name : 'None',
            'can_add' => $user->canPerformAction('max_menu_items_per_menu', $currentMenuItemCount)
        ]);
        
        if (!$user->canPerformAction('max_menu_items_per_menu', $currentMenuItemCount)) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item limit reached for this menu',
                'error_type' => 'subscription_limit',
                'data' => [
                    'current_plan' => $subscription ? $subscription->name : 'Free',
                    'limit' => $limit,
                    'current_count' => $currentMenuItemCount,
                    'upgrade_required' => true,
                    'feature' => 'max_menu_items_per_menu'
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        // Convert FormData string values to proper types
        $input = $request->all();
        
        // Convert boolean strings to actual booleans
        if (isset($input['is_available'])) {
            $input['is_available'] = filter_var($input['is_available'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($input['is_featured'])) {
            $input['is_featured'] = filter_var($input['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($input['is_spicy'])) {
            $input['is_spicy'] = filter_var($input['is_spicy'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Convert JSON string arrays to actual arrays
        if (isset($input['allergens']) && is_string($input['allergens'])) {
            $input['allergens'] = json_decode($input['allergens'], true) ?: [];
        }
        if (isset($input['dietary_info']) && is_string($input['dietary_info'])) {
            $input['dietary_info'] = json_decode($input['dietary_info'], true) ?: [];
        }
        if (isset($input['variations']) && is_string($input['variations'])) {
            $input['variations'] = json_decode($input['variations'], true) ?: null;
        }
        if (isset($input['customizations']) && is_string($input['customizations'])) {
            $input['customizations'] = json_decode($input['customizations'], true) ?: null;
        }
        
        // Convert numeric strings
        if (isset($input['spice_level'])) {
            $input['spice_level'] = $input['spice_level'] === '0' ? null : (int)$input['spice_level'];
        }
        if (isset($input['preparation_time'])) {
            $input['preparation_time'] = $input['preparation_time'] ? (int)$input['preparation_time'] : null;
        }

                $validator = Validator::make($input, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|size:3|in:USD,EUR,GBP,JPY,CAD,AUD,CHF,CNY,INR,SGD,HKD,NZD,SEK,NOK,DKK,MXN,BRL,ZAR,AED,SAR',
            'category_id' => 'required|integer|exists:menu_categories,id',
            'card_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'heading_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_available' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_spicy' => 'sometimes|boolean',
            'spice_level' => 'nullable|integer|min:1|max:5',
            'preparation_time' => 'nullable|integer|min:0',
            'sort_order' => 'sometimes|integer|min:0',
            'allergens' => 'nullable|array',
            'allergens.*' => 'string',
            'dietary_info' => 'nullable|array',
            'dietary_info.*' => 'string',
            'variations' => 'nullable|array',
            'customizations' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            \Log::error('Menu Item Validation Failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except('image')
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        $data['menu_id'] = $menu->id;

        // Handle image upload
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imagePath = $imageFile->store('menu-items', 'public');
            $data['image_url'] = '/storage/' . $imagePath;
        }

        $menuItem = MenuItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu item created successfully',
            'data' => [
                'menu_item' => $menuItem
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified menu item.
     */
    public function show(Request $request, $menuId, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menuItem = MenuItem::where('menu_id', $menu->id)->find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'menu_item' => $menuItem
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified menu item.
     */
    public function update(Request $request, $menuId, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menuItem = MenuItem::where('menu_id', $menu->id)->find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Convert FormData string values to proper types
        $input = $request->all();
        
        \Log::info('Menu Item Update Request', [
            'item_id' => $id,
            'menu_id' => $menuId,
            'input' => $input
        ]);
        
        // Convert boolean strings to actual booleans
        if (isset($input['is_available'])) {
            $input['is_available'] = filter_var($input['is_available'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($input['is_featured'])) {
            $input['is_featured'] = filter_var($input['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($input['is_spicy'])) {
            $input['is_spicy'] = filter_var($input['is_spicy'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Convert JSON string arrays to actual arrays
        if (isset($input['allergens']) && is_string($input['allergens'])) {
            $input['allergens'] = json_decode($input['allergens'], true) ?: [];
        }
        if (isset($input['dietary_info']) && is_string($input['dietary_info'])) {
            $input['dietary_info'] = json_decode($input['dietary_info'], true) ?: [];
        }
        if (isset($input['variations']) && is_string($input['variations'])) {
            $input['variations'] = json_decode($input['variations'], true) ?: null;
        }
        if (isset($input['customizations']) && is_string($input['customizations'])) {
            $input['customizations'] = json_decode($input['customizations'], true) ?: null;
        }
        
        // Convert numeric strings
        if (isset($input['spice_level'])) {
            $input['spice_level'] = $input['spice_level'] === '0' ? null : (int)$input['spice_level'];
        }
        if (isset($input['preparation_time'])) {
            $input['preparation_time'] = $input['preparation_time'] ? (int)$input['preparation_time'] : null;
        }

        $validator = Validator::make($input, [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3|in:USD,EUR,GBP,JPY,CAD,AUD,CHF,CNY,INR,SGD,HKD,NZD,SEK,NOK,DKK,MXN,BRL,ZAR,AED,SAR',
            'category_id' => 'sometimes|required|integer|exists:menu_categories,id',
            'card_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'heading_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'is_spicy' => 'boolean',
            'spice_level' => 'nullable|integer|min:1|max:5',
            'preparation_time' => 'nullable|integer|min:0',
            'sort_order' => 'integer|min:0',
            'allergens' => 'nullable|array',
            'dietary_info' => 'nullable|array',
            'variations' => 'nullable|array',
            'customizations' => 'nullable|array',
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
            $imagePath = $imageFile->store('menu-items', 'public');
            $data['image_url'] = '/storage/' . $imagePath;
        }

        $menuItem->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Menu item updated successfully',
            'data' => [
                'menu_item' => $menuItem->fresh()
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified menu item.
     */
    public function destroy(Request $request, $menuId, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menuItem = MenuItem::where('menu_id', $menu->id)->find($id);

        if (!$menuItem) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $menuItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu item deleted successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Check menu item limits for a specific menu.
     */
    public function checkLimits(Request $request, $menuId)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $subscription = $user->getCurrentSubscriptionPlan();
        $currentCount = $menu->menuItems()->count();
        $limit = $subscription ? $subscription->getLimit('max_menu_items_per_menu') : 10;
        $canAdd = $user->canPerformAction('max_menu_items_per_menu', $currentCount);

        return response()->json([
            'success' => true,
            'data' => [
                'can_add' => $canAdd,
                'current_count' => $currentCount,
                'limit' => $limit,
                'remaining' => max(0, $limit - $currentCount),
                'current_plan' => $subscription ? $subscription->name : 'Free',
                'plan_slug' => $subscription ? $subscription->slug : 'free'
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Reorder menu items
     */
    public function reorder(Request $request, $menuId)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Find menu with access control (admins have full access)
        $menu = $this->findMenuWithAccess($menuId, $user);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:menu_items,id',
            'items.*.sort_order' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            foreach ($request->items as $itemData) {
                MenuItem::where('id', $itemData['id'])
                    ->where('menu_id', $menuId)
                    ->update(['sort_order' => $itemData['sort_order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Menu items reordered successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error('Error reordering menu items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder menu items'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
