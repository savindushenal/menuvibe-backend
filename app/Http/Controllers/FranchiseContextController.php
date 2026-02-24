<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerifyFranchiseAccess;
use App\Models\Franchise;
use App\Models\Location;
use App\Models\Menu;
use App\Models\FranchiseAccount;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class FranchiseContextController extends Controller
{
    /**
     * Get franchise dashboard data
     * OPTIMIZED: Use cached franchise account from middleware instead of re-querying
     */
    public function dashboard(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        
        // OPTIMIZED: Use cached role and account from middleware
        $role = $request->get('franchise_role');
        $userAccount = $request->get('franchise_account');

        // Check if user is branch-restricted (branch_manager or staff)
        $isBranchRestricted = in_array($role, ['branch_manager', 'manager', 'staff']);
        $userLocationId = $userAccount?->location_id;

        // Get stats based on user role
        if ($isBranchRestricted && $userLocationId) {
            // Branch managers/staff only see stats for their location
            $stats = [
                'branches' => 1, // Just their branch
                'locations' => 1,
                'menus' => Menu::where('location_id', $userLocationId)->count(),
                'staff' => FranchiseAccount::where('franchise_id', $franchise->id)
                    ->where('location_id', $userLocationId)
                    ->where('is_active', true)
                    ->count(),
            ];
        } else {
            // Owners/admins see all stats
            $stats = [
                'branches' => Location::where('franchise_id', $franchise->id)
                    ->whereNotNull('branch_code')
                    ->count(),
                'locations' => Location::where('franchise_id', $franchise->id)->count(),
                'menus' => Menu::whereHas('location', function ($q) use ($franchise) {
                    $q->where('franchise_id', $franchise->id);
                })->count(),
                'staff' => FranchiseAccount::where('franchise_id', $franchise->id)
                    ->where('is_active', true)
                    ->count(),
            ];
        }

        // Get user's location info if branch-restricted
        $userLocation = null;
        if ($isBranchRestricted && $userLocationId) {
            $location = Location::find($userLocationId);
            if ($location) {
                $userLocation = [
                    'id' => $location->id,
                    'name' => $location->name,
                    'branch_name' => $location->branch_name,
                    'branch_code' => $location->branch_code,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'slug' => $franchise->slug,
                    'logo_url' => $franchise->logo_url,
                    'brand_color' => $franchise->brand_color,
                    'template_type' => $franchise->template_type,
                    'design_tokens' => $franchise->design_tokens,
                ],
                'user_role' => $role,
                'user_location' => $userLocation,
                'is_branch_restricted' => $isBranchRestricted,
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Get franchise branches (now unified with locations)
     * OPTIMIZED: Use cached franchise account from middleware
     */
    public function branches(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        // Branch managers and staff can only see their own branch
        if (in_array($role, ['branch_manager', 'manager', 'staff'])) {
            $account = $request->get('franchise_account');
            
            if (!$account || !$account->location_id) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $branches = Location::where('franchise_id', $franchise->id)
                ->where('id', $account->location_id)
                ->with(['accounts', 'menus'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $branches
            ]);
        }

        // Owners and admins can see all branches
        $branches = Location::where('franchise_id', $franchise->id)
            ->whereNotNull('branch_code')
            ->with(['accounts', 'menus'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }

    /**
     * Get franchise locations
     * OPTIMIZED: Use cached franchise account from middleware
     */
    public function locations(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        $query = Location::where('franchise_id', $franchise->id)
            ->with(['menus:id,location_id,name']);

        // For branch managers and staff, filter by their location
        if (in_array($role, ['branch_manager', 'manager', 'staff'])) {
            $account = $request->get('franchise_account');
            
            if ($account && $account->location_id) {
                $query->where('id', $account->location_id);
            }
        }

        $locations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    /**
     * Get franchise menus
     * OPTIMIZED: Use cached franchise account from middleware
     */
    public function menus(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        $query = Menu::whereHas('location', function ($q) use ($franchise) {
            $q->where('franchise_id', $franchise->id);
        })->with(['location:id,name,branch_name,branch_code', 'categories.items']);

        // For branch managers and staff, filter by their location
        if (in_array($role, ['branch_manager', 'manager', 'staff'])) {
            $account = $request->get('franchise_account');
            
            if ($account && $account->location_id) {
                $query->whereHas('location', function ($q) use ($account) {
                    $q->where('id', $account->location_id);
                });
            }
        }

        $menus = $query->get();

        return response()->json([
            'success' => true,
            'data' => $menus
        ]);
    }

    /**
     * Get a specific menu with items and override information
     */
    public function getMenu(Request $request, string $franchiseSlug, int $menuId)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        // First, check if menu exists at all
        $menu = Menu::with(['location:id,name,branch_name,branch_code,franchise_id', 'categories.items'])
            ->find($menuId);

        \Log::info('Fetching menu', [
            'menu_id' => $menuId,
            'franchise_id' => $franchise->id,
            'menu_exists' => $menu ? 'yes' : 'no',
            'menu_location_franchise' => $menu && $menu->location ? $menu->location->franchise_id : 'N/A'
        ]);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => "Menu with ID {$menuId} not found. Please ensure the menu has been synced from your Master Menu to this branch."
            ], 404);
        }

        // Check if menu belongs to this franchise
        if (!$menu->location || $menu->location->franchise_id !== $franchise->id) {
            return response()->json([
                'success' => false,
                'message' => 'Menu does not belong to this franchise'
            ], 404);
        }

        // Check if branch manager has access
        if (in_array($role, ['branch_manager', 'manager', 'staff'])) {
            $account = $request->get('franchise_account');
            
            if ($account && $account->location_id && $menu->location_id !== $account->location_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this menu'
                ], 403);
            }
        }

        // Check for overrides on items
        if ($menu->location_id) {
            $overrides = \App\Models\BranchMenuOverride::where('location_id', $menu->location_id)
                ->get()
                ->keyBy('master_item_id');

            foreach ($menu->categories as $category) {
                foreach ($category->items as $item) {
                    $override = $overrides->get($item->id);
                    if ($override) {
                        $item->has_override = true;
                        $item->original_price = $item->price;
                        if ($override->price !== null) {
                            $item->price = $override->price;
                        }
                        if ($override->is_available !== null) {
                            $item->is_available = $override->is_available;
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $menu
        ]);
    }

    /**
     * Get menu templates for franchise
     * Used for endpoint assignment and menu template selection
     */
    public function templates(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        $query = \App\Models\MenuTemplate::where('franchise_id', $franchise->id)
            ->with(['location:id,name,branch_name,branch_code'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name');

        // For branch managers and staff, filter by their location
        if (in_array($role, ['branch_manager', 'manager', 'staff'])) {
            $account = $request->get('franchise_account');
            
            if ($account && $account->location_id) {
                $query->where('location_id', $account->location_id);
            }
        }

        $templates = $query->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Bulk update menu items (availability and prices) for branch locations
     */
    public function bulkUpdateMenuItems(Request $request, string $franchiseSlug, int $menuId)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');
        $user = $request->user();

        $menu = Menu::whereHas('location', function ($q) use ($franchise) {
            $q->where('franchise_id', $franchise->id);
        })->find($menuId);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // Check if branch manager has access
        if (in_array($role, ['branch_manager', 'manager', 'staff'])) {
            $account = $request->get('franchise_account');
            
            if ($account && $account->location_id && $menu->location_id !== $account->location_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this menu'
                ], 403);
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.item_id' => 'required|integer',
            'updates.*.price' => 'nullable|numeric|min:0',
            'updates.*.is_available' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updates = $request->input('updates');
        $locationId = $menu->location_id;

        foreach ($updates as $update) {
            $itemId = $update['item_id'];
            
            // Find or create override
            $override = \App\Models\BranchMenuOverride::updateOrCreate(
                [
                    'location_id' => $locationId,
                    'master_item_id' => $itemId,
                ],
                [
                    'price' => $update['price'] ?? null,
                    'is_available' => $update['is_available'] ?? null,
                    'updated_by' => $user->id,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Menu items updated successfully'
        ]);
    }

    /**
     * Delete a branch menu
     */
    public function deleteMenu(Request $request, string $franchiseSlug, int $menuId)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        // Only owners and admins can delete menus
        if (!in_array($role, ['owner', 'franchise_owner', 'admin', 'franchise_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete menus'
            ], 403);
        }

        $menu = Menu::whereHas('location', function ($q) use ($franchise) {
            $q->where('franchise_id', $franchise->id);
        })->find($menuId);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // Delete menu items first
        \App\Models\MenuItem::where('menu_id', $menuId)->delete();

        // Delete menu categories
        \App\Models\MenuCategory::where('menu_id', $menuId)->delete();

        // Delete menu overrides
        if ($menu->location_id) {
            \App\Models\BranchMenuOverride::where('location_id', $menu->location_id)->delete();
        }

        // Delete the menu
        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully'
        ]);
    }

    /**
     * Get franchise staff/team members
     */
    public function staff(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // franchise_admin, branch_manager, and above can see staff
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin', 'branch_manager', 'manager']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view staff'
            ], 403);
        }

        // Build query based on role
        $query = FranchiseAccount::where('franchise_id', $franchise->id)
            ->where('is_active', true)
            ->with(['user:id,name,email', 'branch:id,branch_name', 'location:id,name,branch_name']);

        // Branch managers can only see staff from their location
        if (in_array($role, ['branch_manager', 'manager'])) {
            $account = FranchiseAccount::where('franchise_id', $franchise->id)
                ->where('user_id', $user->id)
                ->first();
            
            if ($account && $account->location_id) {
                $query->where('location_id', $account->location_id);
            }
        }

        $staff = $query->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'name' => $account->user?->name,
                    'email' => $account->user?->email,
                    'role' => $account->role,
                    'branch' => $account->branch?->branch_name ?? $account->location?->branch_name ?? $account->location?->name,
                    'location_id' => $account->location_id,
                    'permissions' => $account->permissions,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $staff
        ]);
    }

    /**
     * Get franchise settings
     */
    public function settings(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners, admins, and support team can see full settings
        $canViewFullSettings = in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) || 
                               in_array($user->role, ['admin', 'super_admin', 'support_team']);

        $settings = [
            'id' => $franchise->id,
            'name' => $franchise->name,
            'slug' => $franchise->slug,
            'description' => $franchise->description,
            'logo_url' => $franchise->logo_url,
            'primary_color' => $franchise->primary_color ?? '#10b981',
            'secondary_color' => $franchise->secondary_color ?? '#059669',
            'template_type' => $franchise->template_type ?? 'premium',
            'design_tokens' => $franchise->design_tokens ?? null,
        ];

        // Safely access settings JSON field
        $franchiseSettings = $franchise->settings ?? [];

        // Default fields — set empty defaults so frontend doesn't break when keys are absent
        $settings['email'] = '';
        $settings['phone'] = '';
        $settings['website'] = '';
        $settings['timezone'] = $franchiseSettings['timezone'] ?? 'UTC';
        $settings['currency'] = $franchiseSettings['currency'] ?? 'USD';
        $settings['address'] = '';
        $settings['city'] = '';
        $settings['state'] = '';
        $settings['country'] = '';
        $settings['postal_code'] = '';
        $settings['settings'] = $franchiseSettings;

        if ($canViewFullSettings) {
            // Populate with actual values for authorized users
            $settings['email'] = $franchise->support_email ?? $franchise->email ?? $settings['email'];
            $settings['phone'] = $franchise->support_phone ?? $franchise->phone ?? $settings['phone'];
            $settings['website'] = $franchise->website_url ?? $franchise->website ?? $settings['website'];
            $settings['timezone'] = $franchiseSettings['timezone'] ?? $settings['timezone'];
            $settings['currency'] = $franchiseSettings['currency'] ?? $settings['currency'];
            $settings['address'] = $franchiseSettings['address'] ?? $settings['address'];
            $settings['city'] = $franchiseSettings['city'] ?? $settings['city'];
            $settings['state'] = $franchiseSettings['state'] ?? $settings['state'];
            $settings['country'] = $franchiseSettings['country'] ?? $settings['country'];
            $settings['postal_code'] = $franchiseSettings['postal_code'] ?? $settings['postal_code'];
            $settings['settings'] = $franchiseSettings;
        }

        // Attach lightweight debug info when app debug is enabled to help diagnose missing fields
        if (config('app.debug')) {
            $settings['_debug'] = [
                'canViewFullSettings' => $canViewFullSettings,
                'user_role' => $user?->role ?? null,
                'franchise_role' => $role ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update franchise settings
     */
    public function updateSettings(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners, admins, and support team can update settings
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) && 
            !in_array($user->role, ['admin', 'super_admin', 'support_team'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update settings'
            ], 403);
        }

        // When using FormData, settings comes as JSON string, so decode it
        if ($request->has('settings') && is_string($request->settings)) {
            $request->merge(['settings' => json_decode($request->settings, true)]);
        }

        // Debug: log request payload to help diagnose silent failures
        if (config('app.debug')) {
            \Log::info('Franchise updateSettings payload', [
                'franchise_id' => $franchise->id,
                'user_id' => $user?->id,
                'role' => $role,
                'input' => $request->all(),
            ]);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'template_type' => 'nullable|string|in:premium,classic,minimal,barista,custom,isso',
            'design_tokens' => 'nullable|array',
            'settings' => 'nullable|array',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,svg,webp|max:2048',
        ], [], [
            'website' => 'website URL',
            'email' => 'contact email',
            'logo' => 'logo image'
        ]);

        $updateData = [];
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            try {
                $logo = $request->file('logo');
                
                // Validate the file
                if (!$logo->isValid()) {
                    \Log::error('Logo file validation failed', [
                        'franchise_id' => $franchise->id,
                        'error' => $logo->getErrorMessage(),
                        'error_code' => $logo->getError(),
                    ]);
                    throw new \Exception('Invalid file upload: ' . $logo->getErrorMessage());
                }
                
                // Check file size
                $maxSize = 2048 * 1024; // 2MB
                if ($logo->getSize() > $maxSize) {
                    throw new \Exception("File too large. Max size: 2MB");
                }
                
                $filename = 'franchise_' . $franchise->id . '_' . time() . '.' . $logo->getClientOriginalExtension();
                
                // Ensure logos directory exists with proper permissions
                $logosDir = storage_path('app/public/logos');
                if (!is_dir($logosDir)) {
                    if (!mkdir($logosDir, 0755, true)) {
                        throw new \Exception("Failed to create logos directory: $logosDir");
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($logosDir)) {
                    \Log::error('Logos directory not writable', [
                        'path' => $logosDir,
                        'is_writable' => is_writable($logosDir),
                        'permissions' => substr(sprintf('%o', fileperms($logosDir)), -4),
                    ]);
                    throw new \Exception("Logos directory is not writable");
                }
                
                // Store the file to public disk
                $path = $logo->storeAs('logos', $filename, 'public');
                
                if (!$path) {
                    throw new \Exception("storeAs returned false or null");
                }
                
                // Verify file was actually created
                $fullPath = storage_path('app/public/' . $path);
                if (!file_exists($fullPath)) {
                    \Log::error('Logo file not found after upload', [
                        'franchise_id' => $franchise->id,
                        'path' => $path,
                        'full_path' => $fullPath,
                        'file_exists' => file_exists($fullPath),
                    ]);
                    throw new \Exception("File exists check failed: $fullPath");
                }
                
                // Use API endpoint to serve logo so it works even without symlink
                $logoUrl = config('app.url') . '/api/logos/' . $filename;
                $updateData['logo_url'] = $logoUrl;
                
                \Log::info('Logo uploaded successfully', [
                    'franchise_id' => $franchise->id,
                    'filename' => $filename,
                    'path' => $path,
                    'api_url' => $logoUrl,
                    'storage_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                    'file_size' => filesize($fullPath),
                ]);
                
            } catch (\Exception $e) {
                \Log::error('Logo upload failed', [
                    'franchise_id' => $franchise->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                
                // Return error response immediately
                return response()->json([
                    'success' => false,
                    'message' => 'Logo upload failed: ' . $e->getMessage()
                ], 422);
            }
        }
        
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('website')) {
            $updateData['website_url'] = $request->website;
        }
        if ($request->has('email')) {
            $updateData['support_email'] = $request->email;
        }
        if ($request->has('phone')) {
            $updateData['support_phone'] = $request->phone;
        }
        if ($request->has('primary_color')) {
            $updateData['primary_color'] = $request->primary_color;
        }
        if ($request->has('secondary_color')) {
            $updateData['secondary_color'] = $request->secondary_color;
        }
        
        // Sync primary_color and secondary_color to design_tokens.colors
        if ($request->has('primary_color') || $request->has('secondary_color')) {
            $designTokens = $franchise->design_tokens ?? [];
            if (!isset($designTokens['colors'])) {
                $designTokens['colors'] = [];
            }
            
            if ($request->has('primary_color')) {
                $designTokens['colors']['primary'] = $request->primary_color;
            }
            if ($request->has('secondary_color')) {
                $designTokens['colors']['secondary'] = $request->secondary_color;
            }
            
            $updateData['design_tokens'] = $designTokens;
        }
        
        if ($request->has('template_type')) {
            $updateData['template_type'] = $request->template_type;
        }
        if ($request->has('design_tokens')) {
            $updateData['design_tokens'] = $request->design_tokens;
        }
        
        // Store address and other location info in settings JSON
        $existingSettings = $franchise->settings ?? [];
        $newSettings = $existingSettings;
        
        if ($request->has('address')) {
            $newSettings['address'] = $request->address;
        }
        if ($request->has('city')) {
            $newSettings['city'] = $request->city;
        }
        if ($request->has('state')) {
            $newSettings['state'] = $request->state;
        }
        if ($request->has('country')) {
            $newSettings['country'] = $request->country;
        }
        if ($request->has('postal_code')) {
            $newSettings['postal_code'] = $request->postal_code;
        }
        if ($request->has('timezone')) {
            $newSettings['timezone'] = $request->timezone;
        }
        if ($request->has('currency')) {
            $newSettings['currency'] = $request->currency;
        }
        if ($request->has('settings')) {
            $newSettings = array_merge($newSettings, $request->settings);
        }
        
        $updateData['settings'] = $newSettings;

        // Debug: log computed update data before applying
        if (config('app.debug')) {
            \Log::info('Franchise updateSettings computed updateData', [
                'franchise_id' => $franchise->id,
                'updateData' => $updateData,
            ]);
        }

        try {
            // Only update if we have data to update
            if (!empty($updateData)) {
                $franchise->update($updateData);
                if (config('app.debug')) {
                    \Log::info('Franchise updateSettings completed', [
                        'franchise_id' => $franchise->id,
                        'updated_fields' => array_keys($updateData),
                    ]);
                }
            } else {
                if (config('app.debug')) {
                    \Log::warning('Franchise updateSettings: no data to update', [
                        'franchise_id' => $franchise->id,
                    ]);
                }
            }
            
            // Refresh and return updated franchise
            $franchise = $franchise->fresh();
            
            $response = [
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $franchise
            ];
            
            // Add debug info if available
            if (config('app.debug')) {
                $response['_debug'] = [
                    'updated_fields' => array_keys($updateData),
                    'logo_url' => $franchise->logo_url,
                    'app_url' => config('app.url'),
                ];
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Franchise updateSettings error', [
                'franchise_id' => $franchise->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new branch (now creates a Location with branch fields)
     */
    public function createBranch(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners and admins can create branches
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create branches'
            ], 403);
        }

        $request->validate([
            'branch_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // Create a unified Location that serves as both branch and location
        // Get owner from pivot table (franchise_users)
        $ownerId = $franchise->owner?->id ?? $user->id;
        
        $branch = Location::create([
            'user_id' => $ownerId,
            'franchise_id' => $franchise->id,
            'name' => $request->branch_name,
            'branch_name' => $request->branch_name,
            'branch_code' => Location::generateBranchCode($franchise->id),
            'address_line_1' => $request->address ?? 'To be updated',
            'city' => $request->city ?? 'To be updated',
            'state' => 'To be updated',
            'postal_code' => 'To be updated',
            'country' => 'Sri Lanka',
            'phone' => $request->phone,
            'is_active' => $request->is_active ?? true,
            'is_default' => false,
            'added_by' => $user->id,
            'activated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            'data' => $branch
        ], 201);
    }

    /**
     * Update a branch
     */
    public function updateBranch(Request $request, string $franchiseSlug, int $branchId)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners and admins can update branches
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update branches'
            ], 403);
        }

        // Branches are now unified as Locations
        $branch = Location::where('franchise_id', $franchise->id)
            ->whereNotNull('branch_code')
            ->where('id', $branchId)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        $request->validate([
            'branch_name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [];
        if ($request->has('branch_name')) {
            $updateData['branch_name'] = $request->branch_name;
            $updateData['name'] = $request->branch_name; // Sync name with branch_name
        }
        if ($request->has('address')) {
            $updateData['address_line_1'] = $request->address;
        }
        if ($request->has('city')) {
            $updateData['city'] = $request->city;
        }
        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone;
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->is_active;
        }

        $branch->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch
        ]);
    }

    /**
     * Delete a branch
     */
    public function deleteBranch(Request $request, string $franchiseSlug, int $branchId)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners can delete branches
        if (!in_array($role, ['owner', 'franchise_owner']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only franchise owners can delete branches'
            ], 403);
        }

        // Branches are now unified as Locations
        $branch = Location::where('franchise_id', $franchise->id)
            ->whereNotNull('branch_code')
            ->where('id', $branchId)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        // Delete related records
        $branch->accounts()->delete();
        $branch->invitations()->delete();
        $branch->menus()->delete();
        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully'
        ]);
    }

    /**
     * Invite a staff member to the franchise
     * OPTIMIZED: Use cached franchise account from middleware
     */
    public function inviteStaff(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');
        $userAccount = $request->get('franchise_account');

        // Owners, admins can invite any role; branch managers can only invite staff to their branch
        $canInvite = false;
        $restrictToLocation = null;

        if (in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) || 
            in_array($user->role, ['admin', 'super_admin'])) {
            $canInvite = true;
        } elseif (in_array($role, ['branch_manager', 'manager'])) {
            // Branch managers can only invite staff to their own location
            $canInvite = true;
            $restrictToLocation = $userAccount?->location_id;
        }

        if (!$canInvite) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to invite staff'
            ], 403);
        }

        $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'role' => 'required|in:franchise_admin,branch_manager,staff,admin,manager',
            'branch_id' => 'nullable|integer', // Now refers to location_id
            'location_id' => 'nullable|integer',
        ]);

        // Map frontend role names to backend role names
        $roleMapping = [
            'admin' => 'franchise_admin',
            'manager' => 'branch_manager',
            'staff' => 'staff',
            'franchise_admin' => 'franchise_admin',
            'branch_manager' => 'branch_manager',
        ];
        $mappedRole = $roleMapping[$request->role] ?? $request->role;

        // Branch managers can only invite staff role, not admins or other managers
        if ($restrictToLocation !== null && $mappedRole !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Branch managers can only invite staff members'
            ], 403);
        }

        // Accept both branch_id and location_id (branch_id for backwards compatibility)
        $locationId = $request->location_id ?? $request->branch_id;

        // If branch manager, force the location to their own location
        if ($restrictToLocation !== null) {
            $locationId = $restrictToLocation;
        }

        // Check if location/branch belongs to franchise
        if ($locationId) {
            $location = Location::where('franchise_id', $franchise->id)
                ->where('id', $locationId)
                ->first();
            
            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch/Location not found'
                ], 404);
            }
        }

        // Find or create user
        $invitedUser = User::where('email', $request->email)->first();
        
        if (!$invitedUser) {
            // Create a new user account
            $invitedUser = User::create([
                'email' => $request->email,
                'name' => $request->name,
                'password' => bcrypt(Str::random(32)), // Temporary password
                'role' => 'user',
            ]);
        }

        // Check if user already has an account in this franchise
        $existingAccount = FranchiseAccount::where('franchise_id', $franchise->id)
            ->where('user_id', $invitedUser->id)
            ->first();

        if ($existingAccount) {
            return response()->json([
                'success' => false,
                'message' => 'This user is already a member of this franchise'
            ], 400);
        }

        // Create franchise account with location_id and invitation token
        $account = FranchiseAccount::create([
            'franchise_id' => $franchise->id,
            'user_id' => $invitedUser->id,
            'role' => $mappedRole,
            'location_id' => $locationId,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        // Generate invitation token
        $invitationToken = $account->generateInvitationToken(7); // 7 days expiry

        // Send invitation email
        try {
            $emailService = app(EmailService::class);
            $frontendUrl = config('app.frontend_url', 'https://staging.app.menuvire.com');
            $invitationLink = $frontendUrl . '/franchise/' . $franchise->slug . '/join?token=' . $invitationToken . '&email=' . urlencode($invitedUser->email);
            
            $emailService->sendFranchiseInvitation(
                $invitedUser->email,
                $invitedUser->name,
                $franchise->name,
                $mappedRole,
                $user->name,
                $invitationLink
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send franchise invitation email', [
                'error' => $e->getMessage(),
                'user_email' => $invitedUser->email,
                'franchise' => $franchise->name,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff member invited successfully',
            'data' => [
                'id' => $account->id,
                'user_id' => $invitedUser->id,
                'name' => $invitedUser->name,
                'email' => $invitedUser->email,
                'role' => $account->role,
                'branch' => $account->location?->branch_name,
            ]
        ], 201);
    }

    /**
     * Update a staff member's role or branch
     */
    public function updateStaff(Request $request, string $franchiseSlug, int $staffId)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners and admins can update staff
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update staff'
            ], 403);
        }

        $account = FranchiseAccount::where('franchise_id', $franchise->id)
            ->where('id', $staffId)
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        // Can't update franchise owner
        if (in_array($account->role, ['owner', 'franchise_owner'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify franchise owner'
            ], 403);
        }

        $request->validate([
            'role' => 'sometimes|in:franchise_admin,branch_manager,staff',
            'branch_id' => 'nullable|integer', // For backwards compatibility
            'location_id' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        // Accept both branch_id and location_id (branch_id for backwards compatibility)
        $locationId = $request->location_id ?? $request->branch_id;

        // Check if location/branch belongs to franchise
        if ($locationId) {
            $location = Location::where('franchise_id', $franchise->id)
                ->where('id', $locationId)
                ->first();
            
            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch/Location not found'
                ], 404);
            }
        }

        $updateData = $request->only(['role', 'is_active']);
        if ($locationId !== null) {
            $updateData['location_id'] = $locationId;
        }
        
        $account->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully',
            'data' => $account->load(['user:id,name,email', 'location:id,name,branch_name'])
        ]);
    }

    /**
     * Remove a staff member from the franchise
     */
    public function removeStaff(Request $request, string $franchiseSlug, int $staffId)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = $request->get('franchise_role');

        // Only owners and admins can remove staff
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to remove staff'
            ], 403);
        }

        $account = FranchiseAccount::where('franchise_id', $franchise->id)
            ->where('id', $staffId)
            ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        // Can't remove franchise owner
        if (in_array($account->role, ['owner', 'franchise_owner'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove franchise owner'
            ], 403);
        }

        // Can't remove yourself
        if ($account->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove yourself'
            ], 400);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff member removed successfully'
        ]);
    }

    /**
     * Get menu endpoints (tables, QR codes, etc.) for franchise
     */
    public function endpoints(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');
        $userAccount = $request->get('franchise_account');

        // Get location_id based on role
        $locationId = null;
        if (in_array($role, ['branch_manager', 'manager', 'staff']) && $userAccount?->location_id) {
            // Branch restricted users only see their location's endpoints
            $locationId = $userAccount->location_id;
        }

        // Get endpoints for THIS franchise only (filter by franchise_id directly)
        $query = \App\Models\MenuEndpoint::query()
            ->where('franchise_id', $franchise->id)
            ->with('template');

        // Filter by location if user is branch-restricted
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $type = $request->query('type');
        if ($type) {
            $query->where('type', $type);
        }

        $endpoints = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $endpoints
        ]);
    }

    /**
     * Create a new endpoint
     */
    public function createEndpoint(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');
        $userAccount = $request->get('franchise_account');

        // Only managers and above can create endpoints
        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin', 'manager', 'branch_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create endpoints'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:table,room,area,branch,kiosk,takeaway,event,delivery',
            'identifier' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_id' => 'nullable|integer|exists:menu_templates,id',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        // Get location: from request, user's location, or franchise default
        $locationId = $validated['location_id'] ?? $userAccount?->location_id;
        
        if (!$locationId) {
            $location = Location::where('franchise_id', $franchise->id)->first();
            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'No location found for franchise'
                ], 404);
            }
            $locationId = $location->id;
        }
        
        // Verify location belongs to this franchise
        $location = Location::where('id', $locationId)
            ->where('franchise_id', $franchise->id)
            ->first();
            
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid location for this franchise'
            ], 400);
        }

        // Get or create default template for franchise location
        $templateId = $validated['template_id'] ?? null;
        if (!$templateId) {
            $template = \App\Models\MenuTemplate::where('location_id', $locationId)
                ->where('franchise_id', $franchise->id)
                ->where('is_default', true)
                ->first();
            
            if (!$template) {
                $template = \App\Models\MenuTemplate::where('location_id', $locationId)
                    ->where('franchise_id', $franchise->id)
                    ->first();
            }
            
            // If still no template, create one
            if (!$template) {
                $template = \App\Models\MenuTemplate::create([
                    'franchise_id' => $franchise->id,
                    'location_id' => $locationId,
                    'user_id' => $request->user()->id,
                    'name' => 'Default Menu',
                    'slug' => 'default-menu-' . \Str::random(8),
                    'is_active' => true,
                    'is_default' => true,
                    'currency' => 'USD',
                ]);
            }
            
            $templateId = $template->id;
        }

        $endpoint = \App\Models\MenuEndpoint::create([
            'user_id' => $request->user()->id,
            'location_id' => $locationId,
            'franchise_id' => $franchise->id,
            'template_id' => $templateId,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'identifier' => $validated['identifier'],
            'description' => $validated['description'] ?? null,
            'short_code' => \Str::random(8),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $endpoint->load('template'),
            'message' => 'Endpoint created successfully'
        ]);
    }

    /**
     * Bulk create endpoints
     */
    public function bulkCreateEndpoints(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');
        $userAccount = $request->get('franchise_account');

        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin', 'manager', 'branch_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create endpoints'
            ], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:table,room,area,branch,kiosk,takeaway,event,delivery',
            'prefix' => 'required|string|max:255',
            'start' => 'required|integer|min:1',
            'count' => 'required|integer|min:1|max:100',
            'template_id' => 'nullable|integer|exists:menu_templates,id',
            'location_id' => 'nullable|integer|exists:locations,id',
        ]);

        // Get location: from request, user's location, or franchise default
        $locationId = $validated['location_id'] ?? $userAccount?->location_id;
        
        if (!$locationId) {
            $location = Location::where('franchise_id', $franchise->id)->first();
            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'No location found for franchise'
                ], 404);
            }
            $locationId = $location->id;
        }

        $templateId = $validated['template_id'] ?? null;
        if (!$templateId) {
            $template = \App\Models\MenuTemplate::where('location_id', $locationId)
                ->where('franchise_id', $franchise->id)
                ->where('is_default', true)
                ->first();
            
            if (!$template) {
                $template = \App\Models\MenuTemplate::where('location_id', $locationId)
                    ->where('franchise_id', $franchise->id)
                    ->first();
            }
            
            // If still no template, create one
            if (!$template) {
                $template = \App\Models\MenuTemplate::create([
                    'franchise_id' => $franchise->id,
                    'location_id' => $locationId,
                    'user_id' => $request->user()->id,
                    'name' => 'Default Menu',
                    'slug' => 'default-menu-' . \Str::random(8),
                    'is_active' => true,
                    'is_default' => true,
                    'currency' => 'USD',
                ]);
            }
            
            $templateId = $template->id;
        }

        $endpoints = [];
        for ($i = 0; $i < $validated['count']; $i++) {
            $number = $validated['start'] + $i;
            $name = $validated['prefix'] . ' ' . $number;
            $identifier = strtoupper($validated['prefix']) . '-' . $number;

            $endpoints[] = \App\Models\MenuEndpoint::create([
                'user_id' => $request->user()->id,
                'location_id' => $locationId,
                'franchise_id' => $franchise->id,
                'template_id' => $templateId,
                'type' => $validated['type'],
                'name' => $name,
                'identifier' => $identifier,
                'short_code' => \Str::random(8),
                'is_active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $endpoints,
            'message' => count($endpoints) . ' endpoints created successfully'
        ]);
    }

    /**
     * Get single endpoint
     */
    public function getEndpoint(Request $request, string $franchiseSlug, int $endpointId)
    {
        $franchise = $request->get('franchise');
        
        $endpoint = \App\Models\MenuEndpoint::with('template')
            ->where('franchise_id', $franchise->id)
            ->find($endpointId);

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $endpoint
        ]);
    }

    /**
     * Update endpoint
     */
    public function updateEndpoint(Request $request, string $franchiseSlug, int $endpointId)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin', 'manager', 'branch_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update endpoints'
            ], 403);
        }

        $endpoint = \App\Models\MenuEndpoint::where('franchise_id', $franchise->id)
            ->find($endpointId);

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:table,room,area,branch,kiosk,takeaway,event,delivery',
            'identifier' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'template_id' => 'sometimes|integer|exists:menu_templates,id',
            'location_id' => 'sometimes|integer|exists:locations,id',
        ]);
        
        // If location_id is being updated, verify it belongs to this franchise
        if (isset($validated['location_id'])) {
            $location = Location::where('id', $validated['location_id'])
                ->where('franchise_id', $franchise->id)
                ->first();
                
            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid location for this franchise'
                ], 400);
            }
        }

        $endpoint->update($validated);

        return response()->json([
            'success' => true,
            'data' => $endpoint->load('template'),
            'message' => 'Endpoint updated successfully'
        ]);
    }

    /**
     * Delete endpoint
     */
    public function deleteEndpoint(Request $request, string $franchiseSlug, int $endpointId)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin', 'manager', 'branch_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete endpoints'
            ], 403);
        }

        $endpoint = \App\Models\MenuEndpoint::where('franchise_id', $franchise->id)
            ->find($endpointId);

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $endpoint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Endpoint deleted successfully'
        ]);
    }

    /**
     * Get QR code for endpoint
     */
    public function getEndpointQR(Request $request, string $franchiseSlug, int $endpointId)
    {
        $franchise = $request->get('franchise');
        
        $endpoint = \App\Models\MenuEndpoint::withoutGlobalScopes()
            ->where('franchise_id', $franchise->id)
            ->find($endpointId);

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        // Generate QR code if not exists
        if (!$endpoint->qr_code_url) {
            // Generate unique short code if not exists
            if (!$endpoint->short_code) {
                $endpoint->short_code = \Str::random(8);
                $endpoint->save();
            }
            
            // Use /m/{code} format - short_code uniquely identifies the endpoint
            $menuUrl = config('app.frontend_url') . '/m/' . $endpoint->short_code;
            
            // Generate QR code using API
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode($menuUrl);
            
            $endpoint->update([
                'qr_code_url' => $qrCodeUrl,
                'short_url' => $menuUrl,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $endpoint->short_url,
                'short_url' => $endpoint->short_url,
                'qr_code_url' => $endpoint->qr_code_url,
            ]
        ]);
    }

    /**
     * Regenerate QR code
     */
    public function regenerateEndpointQR(Request $request, string $franchiseSlug, int $endpointId)
    {
        $franchise = $request->get('franchise');
        $role = $request->get('franchise_role');

        if (!in_array($role, ['owner', 'franchise_owner', 'franchise_admin', 'admin', 'manager', 'branch_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to regenerate QR codes'
            ], 403);
        }

        $endpoint = \App\Models\MenuEndpoint::withoutGlobalScopes()
            ->where('franchise_id', $franchise->id)
            ->find($endpointId);

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        // Generate new short code
        $newShortCode = \Str::random(8);
        
        // Use /m/{code} format - short_code uniquely identifies the endpoint
        $menuUrl = config('app.frontend_url') . '/m/' . $newShortCode;
        
        // Generate new QR code using API
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode($menuUrl);
        
        $endpoint->update([
            'qr_code_url' => $qrCodeUrl,
            'short_url' => $menuUrl,
            'short_code' => $newShortCode,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $endpoint->short_url,
                'short_url' => $endpoint->short_url,
                'qr_code_url' => $endpoint->qr_code_url,
            ],
            'message' => 'QR code regenerated successfully'
        ]);
    }
}
