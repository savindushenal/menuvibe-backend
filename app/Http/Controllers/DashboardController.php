<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class DashboardController extends Controller
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
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Get user's locations with menus
            $locations = $user->locations()->with(['menus.menuItems'])->get();
            
            // Calculate stats
            $totalLocations = $locations->count();
            $totalMenus = 0;
            $totalMenuItems = 0;
            $activeMenus = 0;
            
            foreach ($locations as $location) {
                $menus = $location->menus;
                $totalMenus += $menus->count();
                
                foreach ($menus as $menu) {
                    if ($menu->is_active ?? false) {
                        $activeMenus++;
                    }
                    $totalMenuItems += $menu->menuItems->count();
                }
            }

            // Get subscription info
            $subscription = $user->activeSubscription;
            $plan = $user->getCurrentSubscriptionPlan();

            // Calculate subscription usage percentage
            $limits = $plan ? $plan->limits : [];
            $usagePercentage = [];
            
            if (isset($limits['menus_limit']) && $limits['menus_limit'] > 0) {
                $usagePercentage['menus'] = min(100, ($totalMenus / $limits['menus_limit']) * 100);
            }
            
            if (isset($limits['menu_items_limit']) && $limits['menu_items_limit'] > 0) {
                $usagePercentage['items'] = min(100, ($totalMenuItems / $limits['menu_items_limit']) * 100);
            }
            
            if (isset($limits['locations_limit']) && $limits['locations_limit'] > 0) {
                $usagePercentage['locations'] = min(100, ($totalLocations / $limits['locations_limit']) * 100);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_locations' => $totalLocations,
                        'total_menus' => $totalMenus,
                        'active_menus' => $activeMenus,
                        'total_menu_items' => $totalMenuItems,
                    ],
                    'subscription' => [
                        'plan_name' => $plan ? $plan->name : 'Free',
                        'status' => $subscription ? $subscription->status : 'trial',
                        'expires_at' => $subscription ? $subscription->expires_at : null,
                    ],
                    'limits' => $limits,
                    'usage_percentage' => $usagePercentage,
                    'recent_activity' => $this->getRecentActivity($user),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error('Dashboard stats error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty stats on error
            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_locations' => 0,
                        'total_menus' => 0,
                        'active_menus' => 0,
                        'total_menu_items' => 0,
                    ],
                    'subscription' => [
                        'plan_name' => 'Free',
                        'status' => 'trial',
                        'expires_at' => null,
                    ],
                    'limits' => [],
                    'usage_percentage' => [],
                    'recent_activity' => [],
                ]
            ], Response::HTTP_OK);
        }
    }

    /**
     * Get recent activity for user
     */
    private function getRecentActivity($user)
    {
        // Get 5 most recently updated menus
        $recentMenus = $user->locations()
            ->with(['menus' => function($query) {
                $query->latest('updated_at')->limit(5);
            }])
            ->get()
            ->pluck('menus')
            ->flatten()
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        return $recentMenus->map(function($menu) {
            return [
                'id' => $menu->id,
                'name' => $menu->name,
                'location' => $menu->location->name ?? 'Unknown',
                'updated_at' => $menu->updated_at,
                'items_count' => $menu->menuItems()->count(),
            ];
        });
    }
}
