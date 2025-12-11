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
            $request->merge(['franchise' => $franchise]);
            return $next($request);
        }

        // Check franchise_accounts table
        $hasAccountAccess = FranchiseAccount::where('user_id', $user->id)
            ->where('franchise_id', $franchise->id)
            ->where('is_active', true)
            ->exists();

        if ($hasAccountAccess) {
            $request->merge(['franchise' => $franchise]);
            return $next($request);
        }

        // Check franchise_users pivot table
        $hasPivotAccess = $user->franchises()
            ->where('franchises.id', $franchise->id)
            ->where('franchise_users.is_active', true)
            ->exists();

        if ($hasPivotAccess) {
            $request->merge(['franchise' => $franchise]);
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this franchise'
        ], 403);
    }

    /**
     * Get the user's role within the franchise
     */
    public static function getUserFranchiseRole($user, $franchise): ?string
    {
        // Check franchise_accounts first
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
}
