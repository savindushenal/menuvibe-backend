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

class AdminDashboardController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Get platform statistics
        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
                'new_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
                'new_this_month' => User::whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
            ],
            'businesses' => [
                'total' => BusinessProfile::count(),
                'completed_onboarding' => BusinessProfile::where('onboarding_completed', true)->count(),
            ],
            'locations' => [
                'total' => Location::count(),
                'active' => Location::where('is_active', true)->count(),
            ],
            'menus' => [
                'total' => Menu::count(),
                'active' => Menu::where('is_active', true)->count(),
            ],
            'menu_items' => [
                'total' => MenuItem::count(),
                'available' => MenuItem::where('is_available', true)->count(),
            ],
            'subscriptions' => [
                'total' => UserSubscription::count(),
                'active' => UserSubscription::where('is_active', true)->where('status', 'active')->count(),
                'trialing' => UserSubscription::where('status', 'trialing')->count(),
            ],
            'support' => [
                'open_tickets' => SupportTicket::open()->count(),
                'unassigned_tickets' => SupportTicket::open()->unassigned()->count(),
                'urgent_tickets' => SupportTicket::open()->withPriority('urgent')->count(),
            ],
        ];

        // Get recent activity (last 10)
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
     * Get platform analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        // User growth over time
        $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Subscription breakdown
        $subscriptionBreakdown = UserSubscription::with('subscriptionPlan:id,name')
            ->where('is_active', true)
            ->get()
            ->groupBy('subscription_plan_id')
            ->map(function ($group) {
                $plan = $group->first()->subscriptionPlan;
                return [
                    'plan' => $plan?->name ?? 'Unknown',
                    'count' => $group->count(),
                ];
            })
            ->values();

        // Location distribution by business
        $locationDistribution = User::withCount('locations')
            ->having('locations_count', '>', 0)
            ->get()
            ->groupBy(function ($user) {
                $count = $user->locations_count;
                if ($count == 1) return '1 location';
                if ($count <= 3) return '2-3 locations';
                if ($count <= 5) return '4-5 locations';
                return '6+ locations';
            })
            ->map(fn($group) => $group->count());

        return response()->json([
            'success' => true,
            'data' => [
                'user_growth' => $userGrowth,
                'subscription_breakdown' => $subscriptionBreakdown,
                'location_distribution' => $locationDistribution,
            ],
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
