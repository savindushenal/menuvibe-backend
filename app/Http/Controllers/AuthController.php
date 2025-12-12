<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Location;
use App\Models\Franchise;
use App\Models\FranchiseAccount;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Automatically assign free subscription plan to new user
        $this->assignFreeSubscription($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Get user contexts (personal + franchise memberships)
        $contexts = $this->getUserContexts($user);

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'contexts' => $contexts,
                'default_redirect' => $this->getDefaultRedirect($contexts),
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Get user contexts (personal business + franchise memberships)
     */
    public function getContexts(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        // Return empty contexts if user is not authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'data' => [
                    'contexts' => [],
                    'default_redirect' => '/auth/login',
                ]
            ], 401);
        }
        
        $contexts = $this->getUserContexts($user);

        return response()->json([
            'success' => true,
            'data' => [
                'contexts' => $contexts,
                'default_redirect' => $this->getDefaultRedirect($contexts),
            ]
        ]);
    }

    /**
     * Build user contexts array
     */
    private function getUserContexts(User $user): array
    {
        $contexts = [];

        // Check for personal business (locations owned directly)
        $personalLocations = Location::where('user_id', $user->id)
            ->whereNull('franchise_id')
            ->count();
        
        if ($personalLocations > 0 || $user->role === 'user') {
            $contexts[] = [
                'type' => 'personal',
                'id' => null,
                'slug' => null,
                'name' => 'My Business',
                'role' => 'owner',
                'locations_count' => $personalLocations,
                'redirect' => '/dashboard',
            ];
        }

        // Check for franchise memberships
        $franchiseAccounts = FranchiseAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['franchise:id,name,slug,logo_url', 'branch:id,branch_name'])
            ->get();

        foreach ($franchiseAccounts as $account) {
            if ($account->franchise) {
                $contexts[] = [
                    'type' => 'franchise',
                    'id' => $account->franchise->id,
                    'slug' => $account->franchise->slug,
                    'name' => $account->franchise->name,
                    'logo_url' => $account->franchise->logo_url,
                    'role' => $account->role,
                    'branch' => $account->branch?->branch_name,
                    'redirect' => '/' . $account->franchise->slug . '/dashboard',
                ];
            }
        }

        // Also check franchise_users pivot table
        $franchiseUsers = $user->franchises()
            ->where('franchise_users.is_active', true)
            ->get();

        foreach ($franchiseUsers as $franchise) {
            // Avoid duplicates
            $exists = collect($contexts)->firstWhere('id', $franchise->id);
            if (!$exists) {
                $contexts[] = [
                    'type' => 'franchise',
                    'id' => $franchise->id,
                    'slug' => $franchise->slug,
                    'name' => $franchise->name,
                    'logo_url' => $franchise->logo_url,
                    'role' => $franchise->pivot->role,
                    'branch' => null,
                    'redirect' => '/' . $franchise->slug . '/dashboard',
                ];
            }
        }

        // Admin/Super Admin always has personal context
        if (in_array($user->role, ['admin', 'super_admin']) && empty($contexts)) {
            $contexts[] = [
                'type' => 'personal',
                'id' => null,
                'slug' => null,
                'name' => 'Admin Dashboard',
                'role' => $user->role,
                'locations_count' => 0,
                'redirect' => '/admin',
            ];
        }

        return $contexts;
    }

    /**
     * Determine default redirect based on contexts
     */
    private function getDefaultRedirect(array $contexts): string
    {
        if (empty($contexts)) {
            return '/dashboard';
        }

        // If only one context, redirect directly
        if (count($contexts) === 1) {
            return $contexts[0]['redirect'];
        }

        // If multiple contexts, go to context selector
        return '/auth/select-context';
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User logged out from all devices successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Manual auth methods (bypass CSRF middleware)
     */
    private function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return null;
        }

        return $personalAccessToken->tokenable;
    }

    public function logoutManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $request->bearerToken();
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        
        if ($personalAccessToken) {
            $personalAccessToken->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully'
        ], Response::HTTP_OK);
    }

    public function logoutAllManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User logged out from all devices successfully'
        ], Response::HTTP_OK);
    }

    public function profileManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Assign free subscription plan to new user
     */
    private function assignFreeSubscription(User $user)
    {
        // Get the free plan
        $freePlan = SubscriptionPlan::where('slug', 'free')->first();
        
        if ($freePlan) {
            UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $freePlan->id,
                'starts_at' => now(),
                'ends_at' => null, // Free plan doesn't expire
                'trial_ends_at' => null,
                'is_active' => true,
                'status' => 'active',
            ]);
        }
    }
}