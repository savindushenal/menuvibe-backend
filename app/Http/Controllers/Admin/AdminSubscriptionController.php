<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    /**
     * List all subscription plans
     */
    public function plans(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $plans = SubscriptionPlan::withCount('subscriptions')->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Update a subscription plan
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can modify subscription plans',
            ], 403);
        }

        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'features' => 'sometimes|array',
            'limits' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $plan->only(array_keys($validated));
        $plan->update($validated);

        AdminActivityLog::log(
            $admin,
            'subscription_plan.updated',
            $plan,
            $oldValues,
            $validated,
            "Updated subscription plan: {$plan->name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully',
            'data' => $plan->fresh(),
        ]);
    }

    /**
     * List all user subscriptions
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = UserSubscription::with([
            'user:id,name,email',
            'subscriptionPlan:id,name,slug',
        ]);

        // Status filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Plan filter
        if ($planId = $request->get('plan_id')) {
            $query->where('subscription_plan_id', $planId);
        }

        // Active only
        if ($request->get('active_only')) {
            $query->where('is_active', true)->where('status', 'active');
        }

        // Expiring soon (within 7 days)
        if ($request->get('expiring_soon')) {
            $query->where('ends_at', '<=', now()->addDays(7))
                  ->where('ends_at', '>', now());
        }

        // Search by user
        if ($search = $request->get('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 20), 100);
        $subscriptions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions->items(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * Manually change a user's subscription
     */
    public function changeUserSubscription(Request $request, int $userId): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canManageSubscriptions()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'sometimes|in:active,trialing,cancelled,expired',
            'ends_at' => 'sometimes|nullable|date',
            'reason' => 'sometimes|string',
        ]);

        $plan = SubscriptionPlan::find($validated['plan_id']);

        // Deactivate current subscription
        $currentSub = $user->activeSubscription;
        if ($currentSub) {
            $currentSub->update(['is_active' => false, 'status' => 'cancelled']);
        }

        // Create new subscription
        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => $validated['status'] ?? 'active',
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        AdminActivityLog::log(
            $admin,
            'subscription.changed',
            $subscription,
            $currentSub ? ['plan_id' => $currentSub->subscription_plan_id] : null,
            ['plan_id' => $plan->id],
            "Changed {$user->email} subscription to {$plan->name}" . 
                ($validated['reason'] ?? '' ? " - Reason: {$validated['reason']}" : '')
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated successfully',
            'data' => $subscription->load(['user:id,name,email', 'subscriptionPlan']),
        ]);
    }

    /**
     * Cancel a user's subscription
     */
    public function cancelSubscription(Request $request, int $subscriptionId): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canManageSubscriptions()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $subscription = UserSubscription::with('user')->find($subscriptionId);

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found',
            ], 404);
        }

        $validated = $request->validate([
            'reason' => 'sometimes|string',
            'immediate' => 'sometimes|boolean',
        ]);

        $subscription->update([
            'status' => 'cancelled',
            'is_active' => !($validated['immediate'] ?? false),
            'ends_at' => ($validated['immediate'] ?? false) ? now() : $subscription->ends_at,
        ]);

        AdminActivityLog::log(
            $admin,
            'subscription.cancelled',
            $subscription,
            null,
            ['immediate' => $validated['immediate'] ?? false],
            "Cancelled subscription for {$subscription->user->email}" .
                ($validated['reason'] ?? '' ? " - Reason: {$validated['reason']}" : '')
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully',
            'data' => $subscription->fresh(),
        ]);
    }

    /**
     * Get subscription statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // OPTIMIZED: Cache statistics for 5 minutes and consolidate queries
        $stats = \Illuminate\Support\Facades\Cache::remember('admin_subscription_stats', 300, function () {
            // Single query for all status counts + expiring soon
            $statusCounts = DB::table('user_subscriptions')
                ->selectRaw("
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'trialing' THEN 1 ELSE 0 END) as trialing,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN is_active = 1 AND ends_at <= ? AND ends_at > ? THEN 1 ELSE 0 END) as expiring_soon
                ", [now()->addDays(7), now()])
                ->first();

            return [
                'by_plan' => SubscriptionPlan::withCount([
                    'subscriptions as active_count' => function ($q) {
                        $q->where('is_active', true)->where('status', 'active');
                    },
                ])->get()->map(fn($p) => [
                    'plan' => $p->name,
                    'slug' => $p->slug,
                    'active_count' => $p->active_count,
                ]),
                'by_status' => [
                    'active' => (int) ($statusCounts->active ?? 0),
                    'trialing' => (int) ($statusCounts->trialing ?? 0),
                    'cancelled' => (int) ($statusCounts->cancelled ?? 0),
                    'expired' => (int) ($statusCounts->expired ?? 0),
                ],
                'expiring_soon' => (int) ($statusCounts->expiring_soon ?? 0),
                'mrr' => $this->calculateMRR(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Calculate Monthly Recurring Revenue
     * OPTIMIZED: Single JOIN query instead of loading all subscriptions
     */
    private function calculateMRR(): float
    {
        $mrr = DB::table('user_subscriptions')
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('user_subscriptions.is_active', true)
            ->where('user_subscriptions.status', 'active')
            ->sum('subscription_plans.price_monthly');

        return round((float) $mrr, 2);
    }

    /**
     * Helper to get authenticated user
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

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
