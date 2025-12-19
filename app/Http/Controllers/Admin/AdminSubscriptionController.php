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

        $stats = [
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
                'active' => UserSubscription::where('status', 'active')->count(),
                'trialing' => UserSubscription::where('status', 'trialing')->count(),
                'cancelled' => UserSubscription::where('status', 'cancelled')->count(),
                'expired' => UserSubscription::where('status', 'expired')->count(),
            ],
            'expiring_soon' => UserSubscription::where('is_active', true)
                ->where('ends_at', '<=', now()->addDays(7))
                ->where('ends_at', '>', now())
                ->count(),
            'mrr' => $this->calculateMRR(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Calculate Monthly Recurring Revenue
     */
    private function calculateMRR(): float
    {
        $activeSubscriptions = UserSubscription::with('subscriptionPlan')
            ->where('is_active', true)
            ->where('status', 'active')
            ->get();

        $mrr = 0;
        foreach ($activeSubscriptions as $sub) {
            if ($sub->subscriptionPlan) {
                $mrr += $sub->subscriptionPlan->price_monthly ?? 0;
            }
        }

        return round($mrr, 2);
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
