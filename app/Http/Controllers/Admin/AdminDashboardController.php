<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\BusinessProfile;
use App\Models\Location;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Get admin dashboard statistics
     * Optimized: Uses grouped queries and caching
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        
        if (!$user || !$user->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Cache stats for 5 minutes
        $stats = Cache::remember('admin_dashboard_stats', 300, function () {
            return $this->calculateDashboardStats();
        });

        // Get recent activity (not cached - needs to be fresh)
        $recentActivity = AdminActivityLog::with('user:id,name,email')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'admin' => $log->user?->name,
                    'admin_email' => $log->user?->email,
                    'target_type' => class_basename($log->target_type ?? ''),
                    'target_id' => $log->target_id,
                    'created_at' => $log->created_at->toIso8601String(),
                    'notes' => $log->notes,
                ];
            });

        // Get recent users
        $recentUsers = User::where('role', 'user')
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'email', 'created_at', 'is_active']);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'recent_users' => $recentUsers,
            ],
        ]);
    }

    /**
     * Calculate dashboard stats with optimized queries
     */
    private function calculateDashboardStats(): array
    {
        // Optimized: Single query for user stats using conditional aggregation
        $userStats = DB::table('users')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_this_week,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_this_month
            ", [now()->startOfWeek(), now()->startOfMonth()])
            ->first();

        // Optimized: Single query for business stats
        $businessStats = DB::table('business_profiles')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN onboarding_completed = 1 THEN 1 ELSE 0 END) as completed_onboarding
            ")
            ->first();

        // Optimized: Single query for location stats
        $locationStats = DB::table('locations')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            ")
            ->first();

        // Optimized: Single query for menu stats
        $menuStats = DB::table('menus')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            ")
            ->first();

        // Optimized: Single query for menu item stats
        $menuItemStats = DB::table('menu_items')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available
            ")
            ->first();

        // Optimized: Single query for subscription stats
        $subscriptionStats = DB::table('user_subscriptions')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 AND status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'trialing' THEN 1 ELSE 0 END) as trialing
            ")
            ->first();

        // Optimized: Single query for support ticket stats
        $openStatuses = ['open', 'in_progress', 'waiting_on_customer'];
        $supportStats = DB::table('support_tickets')
            ->selectRaw("
                SUM(CASE WHEN status IN ('" . implode("','", $openStatuses) . "') THEN 1 ELSE 0 END) as open_tickets,
                SUM(CASE WHEN status IN ('" . implode("','", $openStatuses) . "') AND assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_tickets,
                SUM(CASE WHEN status IN ('" . implode("','", $openStatuses) . "') AND priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets
            ")
            ->first();

        return [
            'users' => [
                'total' => (int) $userStats->total,
                'active' => (int) $userStats->active,
                'new_today' => (int) $userStats->new_today,
                'new_this_week' => (int) $userStats->new_this_week,
                'new_this_month' => (int) $userStats->new_this_month,
            ],
            'businesses' => [
                'total' => (int) $businessStats->total,
                'completed_onboarding' => (int) $businessStats->completed_onboarding,
            ],
            'locations' => [
                'total' => (int) $locationStats->total,
                'active' => (int) $locationStats->active,
            ],
            'menus' => [
                'total' => (int) $menuStats->total,
                'active' => (int) $menuStats->active,
            ],
            'menu_items' => [
                'total' => (int) $menuItemStats->total,
                'available' => (int) $menuItemStats->available,
            ],
            'subscriptions' => [
                'total' => (int) $subscriptionStats->total,
                'active' => (int) $subscriptionStats->active,
                'trialing' => (int) $subscriptionStats->trialing,
            ],
            'support' => [
                'open_tickets' => (int) $supportStats->open_tickets,
                'unassigned_tickets' => (int) $supportStats->unassigned_tickets,
                'urgent_tickets' => (int) $supportStats->urgent_tickets,
            ],
        ];
    }

    /**
     * Get platform analytics
     * Optimized: Uses efficient queries and caching
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        
        if (!$user || !$user->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        // Cache analytics for 10 minutes
        $cacheKey = 'admin_analytics_' . $days;
        $analytics = Cache::remember($cacheKey, 600, function () use ($startDate) {
            // User growth over time
            $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Optimized: Subscription breakdown with JOIN instead of loading all records
            $subscriptionBreakdown = DB::table('user_subscriptions')
                ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->where('user_subscriptions.is_active', true)
                ->selectRaw('subscription_plans.name as plan, COUNT(*) as count')
                ->groupBy('subscription_plans.id', 'subscription_plans.name')
                ->get();

            // Optimized: Location distribution with single query
            $locationDistribution = DB::table('users')
                ->join('locations', 'users.id', '=', 'locations.user_id')
                ->selectRaw("
                    CASE 
                        WHEN COUNT(locations.id) = 1 THEN '1 location'
                        WHEN COUNT(locations.id) BETWEEN 2 AND 3 THEN '2-3 locations'
                        WHEN COUNT(locations.id) BETWEEN 4 AND 5 THEN '4-5 locations'
                        ELSE '6+ locations'
                    END as location_range,
                    COUNT(DISTINCT users.id) as user_count
                ")
                ->groupBy('users.id')
                ->get()
                ->groupBy('location_range')
                ->map(function ($group) {
                    return $group->count();
                });

            return [
                'user_growth' => $userGrowth,
                'subscription_breakdown' => $subscriptionBreakdown,
                'location_distribution' => $locationDistribution,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Helper to get authenticated user with manual token check
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        // Parse the token (format: id|plainTextToken)
        if (str_contains($token, '|')) {
            [$id, $plainTextToken] = explode('|', $token, 2);
            $hashedToken = hash('sha256', $plainTextToken);
        } else {
            $hashedToken = hash('sha256', $token);
        }

        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::where('token', $hashedToken)->first();

        if (!$tokenRecord) {
            return null;
        }

        return User::find($tokenRecord->tokenable_id);
    }
}
