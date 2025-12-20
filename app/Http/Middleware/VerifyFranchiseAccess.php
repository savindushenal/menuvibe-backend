<?php

namespace App\Http\Middleware;

use App\Models\Franchise;
use App\Models\FranchiseAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFranchiseAccess
{
    /**
     * Handle an incoming request.
     * Verify that the user has access to the franchise specified by slug
     * OPTIMIZED: Now attaches franchise account to request to avoid repeated queries
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('franchiseSlug');
        
        if (!$slug) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise slug is required'
            ], 400);
        }

        // Find the franchise by slug
        $franchise = Franchise::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found or inactive'
            ], 404);
        }

        $user = $request->user();

        // Super admins and admins have access to all franchises
        if (in_array($user->role, ['super_admin', 'admin'])) {
            $request->merge([
                'franchise' => $franchise,
                'franchise_account' => null,
                'franchise_role' => $user->role,
            ]);
            return $next($request);
        }

        // OPTIMIZED: Get the full account record (not just exists check)
        // This is reused by controllers instead of querying again
        $account = FranchiseAccount::where('user_id', $user->id)
            ->where('franchise_id', $franchise->id)
            ->where('is_active', true)
            ->first();

        if ($account) {
            $request->merge([
                'franchise' => $franchise,
                'franchise_account' => $account,
                'franchise_role' => $account->role,
            ]);
            return $next($request);
        }

        // Check franchise_users pivot table
        $pivotFranchise = $user->franchises()
            ->where('franchises.id', $franchise->id)
            ->where('franchise_users.is_active', true)
            ->first();

        if ($pivotFranchise) {
            $request->merge([
                'franchise' => $franchise,
                'franchise_account' => null,
                'franchise_role' => $pivotFranchise->pivot->role,
            ]);
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this franchise'
        ], 403);
    }

    /**
     * Get the user's role within the franchise
     * OPTIMIZED: Can use cached data from request if available
     */
    public static function getUserFranchiseRole($user, $franchise, ?Request $request = null): ?string
    {
        // Check if role is already cached in request (from middleware)
        if ($request && $request->has('franchise_role')) {
            return $request->get('franchise_role');
        }

        // Fallback: query database (for cases where called outside middleware context)
        $account = FranchiseAccount::where('user_id', $user->id)
            ->where('franchise_id', $franchise->id)
            ->where('is_active', true)
            ->first();

        if ($account) {
            return $account->role;
        }

        // Check franchise_users pivot
        $pivotFranchise = $user->franchises()
            ->where('franchises.id', $franchise->id)
            ->where('franchise_users.is_active', true)
            ->first();

        if ($pivotFranchise) {
            return $pivotFranchise->pivot->role;
        }

        return null;
    }

    /**
     * Get the user's franchise account from request (if available)
     */
    public static function getFranchiseAccount(Request $request): ?FranchiseAccount
    {
        return $request->get('franchise_account');
    }
}
