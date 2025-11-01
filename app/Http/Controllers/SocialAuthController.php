<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Debug logging
            \Log::info('Google OAuth callback received', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId()
            ]);
            
            // Check if user already exists
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if ($user) {
                // Update Google ID if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }
                \Log::info('Existing user found and updated', ['user_id' => $user->id]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);
                
                // Automatically assign free subscription plan to new user
                $this->assignFreeSubscription($user);
                
                \Log::info('New user created', ['user_id' => $user->id]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            \Log::info('Token created', ['token_length' => strlen($token)]);

            // Redirect to frontend with token
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $redirectUrl = $frontendUrl . '/auth/callback?token=' . $token;
            
            \Log::info('Redirecting to frontend', ['url' => $redirectUrl]);
            
            return redirect()->to($redirectUrl);

        } catch (\Exception $e) {
            \Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect()->to($frontendUrl . '/auth/login?error=google_auth_failed');
        }
    }

    /**
     * Handle Google OAuth for API (when called from frontend)
     */
    public function googleAuth(Request $request)
    {
        $validator = validator($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Get user info from Google using the access token
            $googleUser = Socialite::driver('google')->userFromToken($request->access_token);
            
            // Check if user already exists
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if ($user) {
                // Update Google ID if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);
                
                // Automatically assign free subscription plan to new user
                $this->assignFreeSubscription($user);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Google authentication successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed',
                'error' => $e->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }
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