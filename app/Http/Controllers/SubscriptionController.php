<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class SubscriptionController extends Controller
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
     * Get all available subscription plans
     */
    public function getPlans()
    {
        $plans = SubscriptionPlan::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get user's current subscription
     */
    public function getCurrentSubscription(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $user->activeSubscription;
        $plan = $user->getCurrentSubscriptionPlan();

        // Get usage statistics using locations
        $usage = [];
        
        $locations = $user->locations()->with('menus.menuItems')->get();
        $totalMenus = 0;
        $totalMenuItems = 0;
        
        foreach ($locations as $location) {
            $totalMenus += $location->menus->count();
            foreach ($location->menus as $menu) {
                $totalMenuItems += $menu->menuItems->count();
            }
        }
        
        $usage = [
            'menus_count' => $totalMenus,
            'menu_items_count' => $totalMenuItems,
            'locations_count' => $locations->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'plan' => $plan,
                'usage' => $usage,
                'limits' => $plan ? $plan->limits : [],
                'can_upgrade' => true,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Start free trial for a plan
     */
    public function startTrial(Request $request, $planId)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $plan = SubscriptionPlan::active()->find($planId);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($plan->isFree()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot start trial for free plan'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if user already has an active subscription
        $activeSubscription = $user->activeSubscription;
        if ($activeSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'User already has an active subscription'
            ], Response::HTTP_CONFLICT);
        }

        // Create trial subscription
        $trialSubscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14), // 14-day trial
            'is_active' => true,
            'status' => 'trial',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trial started successfully',
            'data' => [
                'subscription' => $trialSubscription->load('subscriptionPlan'),
                'trial_ends_at' => $trialSubscription->trial_ends_at,
                'trial_days_remaining' => $trialSubscription->trial_days_remaining,
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get upgrade recommendations based on current usage
     */
    public function getUpgradeRecommendations(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $currentPlan = $user->getCurrentSubscriptionPlan();
        
        $recommendations = [];
        
        // Get usage statistics using locations
        $locations = $user->locations()->with('menus.menuItems')->get();
        $totalMenus = 0;
        $totalMenuItems = 0;
        
        foreach ($locations as $location) {
            $totalMenus += $location->menus->count();
            foreach ($location->menus as $menu) {
                $totalMenuItems += $menu->menuItems->count();
            }
        }
        
        // Check if current limits are being approached or exceeded
        if ($currentPlan) {
            $menuLimit = $currentPlan->getLimit('max_menus');
            $itemLimit = $currentPlan->getLimit('max_menu_items');
            
            if ($menuLimit !== -1 && $totalMenus >= $menuLimit * 0.8) {
                $recommendations[] = [
                    'type' => 'menu_limit',
                    'message' => 'You\'re approaching your menu limit',
                    'current' => $totalMenus,
                    'limit' => $menuLimit,
                    'suggested_plan' => 'pro'
                ];
            }
            
            if ($itemLimit !== -1 && $totalMenuItems >= $itemLimit * 0.8) {
                $recommendations[] = [
                    'type' => 'item_limit',
                    'message' => 'You\'re approaching your menu item limit',
                    'current' => $totalMenuItems,
                    'limit' => $itemLimit,
                    'suggested_plan' => 'pro'
                ];
            }
        }

        $availablePlans = SubscriptionPlan::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'recommendations' => $recommendations,
                'available_plans' => $availablePlans,
                'current_plan' => $currentPlan,
            ]
        ], Response::HTTP_OK);
    }
}
