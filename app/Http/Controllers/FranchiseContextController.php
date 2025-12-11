<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerifyFranchiseAccess;
use App\Models\Franchise;
use App\Models\FranchiseBranch;
use App\Models\Location;
use App\Models\Menu;
use App\Models\FranchiseAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
     * Get franchise branches (locations grouped by branch)
     */
    public function branches(Request $request, string $franchiseSlug)
    {
        $franchise = $request->get('franchise');
        $user = $request->user();
        $role = VerifyFranchiseAccess::getUserFranchiseRole($user, $franchise);

        // For branch managers, only show their branch
        $query = FranchiseBranch::where('franchise_id', $franchise->id)
            ->with(['locations']);

        if ($role === 'branch_manager') {
            $account = FranchiseAccount::where('user_id', $user->id)
                ->where('franchise_id', $franchise->id)
                ->first();
            
            if ($account && $account->branch_id) {
                $query->where('id', $account->branch_id);
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

        // For branch managers, filter by their branch
        if ($role === 'branch_manager') {
            $account = FranchiseAccount::where('user_id', $user->id)
                ->where('franchise_id', $franchise->id)
                ->first();
            
            if ($account && $account->branch_id) {
                $query->where('branch_id', $account->branch_id);
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
        })->with(['location:id,name,branch_id', 'categories.items']);

        // For branch managers, filter by their branch
        if ($role === 'branch_manager') {
            $account = FranchiseAccount::where('user_id', $user->id)
                ->where('franchise_id', $franchise->id)
                ->first();
            
            if ($account && $account->branch_id) {
                $query->whereHas('location', function ($q) use ($account) {
                    $q->where('branch_id', $account->branch_id);
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
            'name' => $franchise->name,
            'slug' => $franchise->slug,
            'logo_url' => $franchise->logo_url,
            'brand_color' => $franchise->brand_color,
        ];

        if ($canViewFullSettings) {
            $settings['business_type'] = $franchise->business_type;
            $settings['contact_email'] = $franchise->contact_email;
            $settings['contact_phone'] = $franchise->contact_phone;
            $settings['website'] = $franchise->website;
            $settings['subscription_plan'] = $franchise->subscription_plan;
        }

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }
}
