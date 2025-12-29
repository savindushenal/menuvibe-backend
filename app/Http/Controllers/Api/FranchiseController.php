<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FranchiseController extends Controller
{
    /**
     * List all franchises the authenticated user belongs to.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $franchises = $user->franchises()
            ->withCount('locations')
            ->withCount('users')
            ->with(['franchiseUsers' => function($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->get()
            ->map(function($franchise) {
                return [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'slug' => $franchise->slug,
                    'logo_url' => $franchise->logo_url,
                    'primary_color' => $franchise->primary_color,
                    'secondary_color' => $franchise->secondary_color,
                    'is_active' => $franchise->is_active,
                    'locations_count' => $franchise->locations_count,
                    'users_count' => $franchise->users_count,
                    'my_role' => $franchise->franchiseUsers->first()?->role,
                    'created_at' => $franchise->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $franchises,
        ]);
    }

    /**
     * Create a new franchise.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check if user has enterprise plan with white-label feature
        $plan = $user->getCurrentSubscriptionPlan();
        if (!$plan || !$plan->getLimit('white_label')) {
            return response()->json([
                'success' => false,
                'message' => 'White-label feature requires an Enterprise subscription.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:franchises,slug|alpha_dash',
            'description' => 'nullable|string|max:1000',
            'logo_url' => 'nullable|url|max:500',
            'favicon_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create the franchise
            $franchise = Franchise::create([
                'name' => $request->name,
                'slug' => $request->slug ?: Str::slug($request->name),
                'description' => $request->description,
                'logo_url' => $request->logo_url,
                'favicon_url' => $request->favicon_url,
                'primary_color' => $request->primary_color ?? '#000000',
                'secondary_color' => $request->secondary_color ?? '#FFFFFF',
                'accent_color' => $request->accent_color,
                'support_email' => $request->support_email,
                'support_phone' => $request->support_phone,
                'website_url' => $request->website_url,
                'is_active' => true,
            ]);

            // Add creator as owner
            FranchiseUser::create([
                'franchise_id' => $franchise->id,
                'user_id' => $user->id,
                'role' => FranchiseUser::ROLE_OWNER,
                'accepted_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Franchise created successfully',
                'data' => $franchise->load('owners'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create franchise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific franchise.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::with(['locations', 'users'])
            ->withCount(['locations', 'users'])
            ->find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if user belongs to this franchise
        if (!$user->belongsToFranchise($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $userRole = $user->getRoleInFranchise($id);

        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => $franchise,
                'my_role' => $userRole,
                'branding' => $franchise->getBrandingData(),
                'statistics' => $franchise->getStatistics(),
            ],
        ]);
    }

    /**
     * Update a franchise.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if user is admin or owner of this franchise
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser || !$franchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins and owners can update franchise settings',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|alpha_dash|unique:franchises,slug,' . $id,
            'description' => 'nullable|string|max:1000',
            'logo_url' => 'nullable|url|max:500',
            'favicon_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'custom_css' => 'nullable|string|max:10000',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:500',
            'custom_domain' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If custom domain changed, reset verification
        if ($request->has('custom_domain') && $request->custom_domain !== $franchise->custom_domain) {
            $request->merge([
                'domain_verified' => false,
                'domain_verified_at' => null,
                'domain_verification_token' => 'menuvibe-verify-' . Str::random(32),
            ]);
        }

        $franchise->update($request->only([
            'name', 'slug', 'description', 'logo_url', 'favicon_url',
            'primary_color', 'secondary_color', 'accent_color', 'custom_css',
            'support_email', 'support_phone', 'website_url', 'custom_domain',
            'settings', 'is_active', 'domain_verified', 'domain_verified_at',
            'domain_verification_token',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Franchise updated successfully',
            'data' => $franchise->fresh(),
        ]);
    }

    /**
     * Delete a franchise.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Only owners can delete franchises
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser || !$franchiseUser->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Only owners can delete franchises',
            ], 403);
        }

        try {
            DB::beginTransaction();
            
            // Remove franchise_id from locations (don't delete locations)
            $franchise->locations()->update(['franchise_id' => null]);
            
            // Delete all franchise users
            $franchise->franchiseUsers()->delete();
            
            // Soft delete the franchise
            $franchise->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Franchise deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete franchise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get franchise branding by slug or custom domain.
     * This is a PUBLIC endpoint for loading branding on frontend.
     */
    public function getBranding(Request $request, string $identifier): JsonResponse
    {
        $franchise = Franchise::active()
            ->byDomainOrSlug($identifier)
            ->first();

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'branding' => $franchise->getBrandingData(),
                'css_variables' => $franchise->getCssVariables(),
            ],
        ]);
    }

    /**
     * Get domain verification records.
     */
    public function getDomainVerification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if user is admin or owner
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser || !$franchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'custom_domain' => $franchise->custom_domain,
                'domain_verified' => $franchise->domain_verified,
                'domain_verified_at' => $franchise->domain_verified_at,
                'verification_records' => $franchise->getDomainVerificationRecords(),
            ],
        ]);
    }

    /**
     * Verify domain ownership.
     */
    public function verifyDomain(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($id);

        if (!$franchise || !$franchise->custom_domain) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise or custom domain not found',
            ], 404);
        }

        // Check if user is admin or owner
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser || !$franchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // In production, this would check DNS TXT records
        // For now, we'll simulate verification
        try {
            // Check if TXT record exists with verification token
            // dns_get_record("_menuvibe-verify.{$franchise->custom_domain}", DNS_TXT);
            
            // For development, auto-verify
            $franchise->markDomainVerified();

            return response()->json([
                'success' => true,
                'message' => 'Domain verified successfully',
                'data' => [
                    'domain_verified' => true,
                    'domain_verified_at' => $franchise->domain_verified_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Domain verification failed. Please ensure DNS records are configured correctly.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get franchise locations.
     */
    public function getLocations(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check user access
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Get locations based on user scope
        $query = $franchise->locations();
        
        if ($franchiseUser->hasLocationScope()) {
            $query->whereIn('id', $franchiseUser->location_ids);
        }

        $locations = $query->with('menus')->get();

        return response()->json([
            'success' => true,
            'data' => $locations,
        ]);
    }

    /**
     * Attach a location to franchise.
     */
    public function attachLocation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $franchise = Franchise::find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if user is admin or owner
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser || !$franchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Verify user owns the location
        $location = $user->locations()->find($request->location_id);
        
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found or you do not own it',
            ], 404);
        }

        $location->update(['franchise_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Location attached to franchise',
            'data' => $location->fresh(),
        ]);
    }

    /**
     * Detach a location from franchise.
     */
    public function detachLocation(Request $request, int $id, int $locationId): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($id);

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if user is admin or owner
        $franchiseUser = FranchiseUser::where('franchise_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser || !$franchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $location = $franchise->locations()->find($locationId);
        
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found in this franchise',
            ], 404);
        }

        $location->update(['franchise_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Location removed from franchise',
        ]);
    }

    /**
     * Get public franchise data by slug.
     * This is a PUBLIC endpoint for the customer-facing menu view.
     */
    public function getPublicFranchise(Request $request, string $slug): JsonResponse
    {
        $franchise = Franchise::active()
            ->where('slug', $slug)
            ->first();

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $franchise->id,
                'name' => $franchise->name,
                'slug' => $franchise->slug,
                'description' => $franchise->description,
                'logo_url' => $franchise->logo_url,
                'favicon_url' => $franchise->favicon_url,
                'primary_color' => $franchise->primary_color,
                'secondary_color' => $franchise->secondary_color,
                'accent_color' => $franchise->accent_color,
                'design_tokens' => $franchise->design_tokens,
                'template_type' => $franchise->template_type,
                'support_email' => $franchise->support_email,
                'support_phone' => $franchise->support_phone,
                'website_url' => $franchise->website_url,
            ],
        ]);
    }

    /**
     * Get public menu for a franchise location.
     * This is a PUBLIC endpoint for the customer-facing menu view.
     */
    public function getPublicMenu(Request $request, string $franchiseSlug, string $locationSlug): JsonResponse
    {
        $franchise = Franchise::active()
            ->where('slug', $franchiseSlug)
            ->first();

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        $location = $franchise->locations()
            ->where(function($query) use ($locationSlug) {
                // Support both ID and slug for location lookup
                if (is_numeric($locationSlug)) {
                    $query->where('id', $locationSlug);
                } else {
                    $query->where('slug', $locationSlug);
                }
            })
            ->where('is_active', true)
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found',
            ], 404);
        }

        // Get the location's active menu with categories and items
        $menu = $location->menus()
            ->where('is_active', true)
            ->with(['categories' => function($query) {
                $query->orderBy('sort_order')
                    ->with(['items' => function($q) {
                        $q->where('is_available', true)
                            ->orderBy('sort_order');
                    }]);
            }])
            ->first();

        $menuItems = [];
        if ($menu) {
            foreach ($menu->categories as $category) {
                foreach ($category->items as $item) {
                    $menuItems[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'price' => $item->price,
                        'image_url' => $item->image_url,
                        'is_available' => $item->is_available,
                        'category' => [
                            'id' => $category->id,
                            'name' => $category->name,
                        ],
                        'customizations' => $item->customizations ?? [],
                    ];
                }
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
                    'design_tokens' => $franchise->design_tokens,
                    'template_type' => $franchise->template_type,
                ],
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'slug' => $location->slug,
                    'address' => $location->address,
                    'phone' => $location->phone,
                ],
                'menu_items' => $menuItems,
            ],
        ]);
    }

    /**
     * Get menu by endpoint short code (for QR codes)
     */
    public function getMenuByEndpointCode(Request $request, string $code): JsonResponse
    {
        $endpoint = \App\Models\MenuEndpoint::where('short_code', $code)->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid menu code',
            ], 404);
        }

        $template = $endpoint->template;
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Menu template not found',
            ], 404);
        }

        $location = $template->location;
        if (!$location || !$location->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found or inactive',
            ], 404);
        }

        $franchise = $location->franchise;
        if (!$franchise || $franchise->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found or inactive',
            ], 404);
        }

        // Get the location's active menu with categories and items
        $menu = $location->menus()
            ->where('is_active', true)
            ->with(['categories' => function($query) {
                $query->orderBy('sort_order')
                    ->with(['items' => function($q) {
                        $q->where('is_available', true)
                            ->orderBy('sort_order');
                    }]);
            }])
            ->first();

        $menuItems = [];
        if ($menu) {
            foreach ($menu->categories as $category) {
                foreach ($category->items as $item) {
                    $menuItems[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'price' => $item->price,
                        'image_url' => $item->image_url,
                        'is_available' => $item->is_available,
                        'category' => [
                            'id' => $category->id,
                            'name' => $category->name,
                        ],
                        'customizations' => $item->customizations ?? [],
                    ];
                }
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
                    'design_tokens' => $franchise->design_tokens,
                    'template_type' => $franchise->template_type,
                ],
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'slug' => $location->slug,
                    'address' => $location->address,
                    'phone' => $location->phone,
                ],
                'endpoint' => [
                    'id' => $endpoint->id,
                    'identifier' => $endpoint->identifier,
                    'table_number' => $endpoint->identifier, // Alias for compatibility
                ],
                'menu_items' => $menuItems,
            ],
        ]);
    }
}
