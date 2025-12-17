<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerifyFranchiseAccess;
use App\Models\Franchise;
use App\Models\FranchiseBranch;
use App\Models\Location;
use App\Models\Menu;
use App\Models\FranchiseAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class FranchiseContextController extends Controller
{
    /**
     * Get franchise dashboard data
     */
    public function dashboard(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Get basic stats
        $stats = [
            'branches' => FranchiseBranch::where('franchise_id', $franchise->id)->count(),
            'locations' => Location::where('franchise_id', $franchise->id)->count(),
            'menus' => Menu::whereHas('location', function ($q) use ($franchise) {
                $q->where('franchise_id', $franchise->id);
            })->count(),
            'staff' => FranchiseAccount::where('franchise_id', $franchise->id)
                ->where('is_active', true)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'slug' => $franchise->slug,
                    'logo_url' => $franchise->logo_url,
                    'brand_color' => $franchise->brand_color,
                ],
                'user_role' => $role,
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Get franchise branches (now unified with locations)
     */
    public function branches(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Branches are now locations with branch_code set
        $query = Location::where('franchise_id', $franchise->id)
            ->whereNotNull('branch_code')
            ->with(['accounts', 'menus']);

        if ($role === 'branch_manager') {
            $account = FranchiseAccount::where('user_id', $user->id)
                ->where('franchise_id', $franchise->id)
                ->first();
            
            if ($account && $account->location_id) {
                $query->where('id', $account->location_id);
            }
        }

        $branches = $query->get();

        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }

    /**
     * Get franchise locations
     */
    public function locations(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        $query = Location::where('franchise_id', $franchise->id)
            ->with(['menus:id,location_id,name']);

        // For branch managers, filter by their location
        if ($role === 'branch_manager') {
            $account = FranchiseAccount::where('user_id', $user->id)
                ->where('franchise_id', $franchise->id)
                ->first();
            
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
     */
    public function menus(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        $query = Menu::whereHas('location', function ($q) use ($franchise) {
            $q->where('franchise_id', $franchise->id);
        })->with(['location:id,name,branch_name,branch_code', 'categories.items']);

        // For branch managers, filter by their location
        if ($role === 'branch_manager') {
            $account = FranchiseAccount::where('user_id', $user->id)
                ->where('franchise_id', $franchise->id)
                ->first();
            
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
     * Get franchise staff/team members
     */
    public function staff(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only franchise_admin and above can see staff
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view staff'
            ], 403);
        }

        $staff = FranchiseAccount::where('franchise_id', $franchise->id)
            ->where('is_active', true)
            ->with(['user:id,name,email', 'branch:id,branch_name'])
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'name' => $account->user?->name,
                    'email' => $account->user?->email,
                    'role' => $account->role,
                    'branch' => $account->branch?->branch_name,
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
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can see full settings
        $canViewFullSettings = in_array($role, ['franchise_owner', 'franchise_admin']) || 
                               in_array($user->role, ['admin', 'super_admin']);

        $settings = [
            'id' => $franchise->id,
            'name' => $franchise->name,
            'slug' => $franchise->slug,
            'description' => $franchise->description,
            'logo_url' => $franchise->logo_url,
            'primary_color' => $franchise->primary_color ?? '#10b981',
            'secondary_color' => $franchise->secondary_color ?? '#059669',
        ];

        if ($canViewFullSettings) {
            $settings['email'] = $franchise->support_email;
            $settings['phone'] = $franchise->support_phone;
            $settings['website'] = $franchise->website_url;
            $settings['timezone'] = $franchise->settings['timezone'] ?? 'UTC';
            $settings['currency'] = $franchise->settings['currency'] ?? 'USD';
            $settings['address'] = $franchise->settings['address'] ?? '';
            $settings['city'] = $franchise->settings['city'] ?? '';
            $settings['state'] = $franchise->settings['state'] ?? '';
            $settings['country'] = $franchise->settings['country'] ?? '';
            $settings['postal_code'] = $franchise->settings['postal_code'] ?? '';
            $settings['settings'] = $franchise->settings;
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
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can update settings
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update settings'
            ], 403);
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
            'template_type' => 'nullable|string|in:premium,classic,minimal,barista,custom',
            'design_tokens' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $updateData = [];
        
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

        $franchise->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $franchise->fresh()
        ]);
    }

    /**
     * Create a new branch (now creates a Location with branch fields)
     */
    public function createBranch(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can create branches
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
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
        $branch = Location::create([
            'user_id' => $franchise->owner_id,
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
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can update branches
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
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
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners can delete branches
        if (!in_array($role, ['franchise_owner']) && 
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
     */
    public function inviteStaff(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can invite staff
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
            !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to invite staff'
            ], 403);
        }

        $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'role' => 'required|in:franchise_admin,branch_manager,staff',
            'branch_id' => 'nullable|integer', // Now refers to location_id
            'location_id' => 'nullable|integer',
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

        // Create franchise account with location_id
        $account = FranchiseAccount::create([
            'franchise_id' => $franchise->id,
            'user_id' => $invitedUser->id,
            'role' => $request->role,
            'location_id' => $locationId,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        // TODO: Send invitation email

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
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can update staff
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
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
        if ($account->role === 'franchise_owner') {
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
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // Only owners and admins can remove staff
        if (!in_array($role, ['franchise_owner', 'franchise_admin']) && 
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
        if ($account->role === 'franchise_owner') {
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
}
