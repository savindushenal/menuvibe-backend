<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $role  Specific role requirement: 'super_admin', 'admin_only', or null (includes support_officer)
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        // Check for specific role requirement
        if ($role === 'super_admin') {
            // Super admin only
            if (!$user->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Super admin privileges required.',
                ], 403);
            }
        } elseif ($role === 'admin_only') {
            // Admin or super admin only (not support officer)
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.',
                ], 403);
            }
        } else {
            // Default: admin, super_admin, OR support_officer can access
            if (!$user->canAccessAdminPanel()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin or support officer privileges required.',
                ], 403);
            }
        }

        return $next($request);
    }
}
