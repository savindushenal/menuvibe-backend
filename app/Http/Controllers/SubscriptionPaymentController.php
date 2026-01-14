<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\AbstercoPaymentService;
use App\Services\FeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Subscription Payment Controller
 * 
 * @follows MenuVibe Architecture: Controller Layer
 * @pattern Feature Flags for toggleable functionality
 */
class SubscriptionPaymentController extends Controller
{
    protected $paymentService;

    public function __construct(AbstercoPaymentService $paymentService)
    {
        $this->middleware('auth:sanctum')->except(['paymentCallback']);
        $this->paymentService = $paymentService;
    }

    /**
     * Initiate subscription upgrade/purchase
     * POST /api/subscriptions/upgrade
     */
    public function initiateUpgrade(Request $request)
    {
        // Check if subscription payments are enabled
        if (!$this->isPaymentFeatureEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription payments are not enabled for your account',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'saved_card_id' => 'nullable|integer',
            'payment_method' => 'nullable|in:new_card,saved_card',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Default to new_card if not specified
        $paymentMethod = $request->input('payment_method', 'new_card');

        $user = Auth::user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Check if plan is active
        if (!$plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription plan is not available',
            ], 400);
        }

        // Check if user already has this plan
        $currentSubscription = $user->subscriptions()->active()->first();
        if ($currentSubscription && $currentSubscription->subscription_plan_id === $plan->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are already subscribed to this plan',
            ], 400);
        }

        try {
            // Determine if we need to charge setup fee
            $includeSetupFee = !$currentSubscription || $currentSubscription->status !== 'active';
            $amount = $this->paymentService->calculateSubscriptionAmount($plan, $includeSetupFee);

            // Get current business profile ID if exists
            $businessProfileId = $user->businessProfile?->id;

            // Generate payment reference
            $paymentReference = $this->paymentService->generatePaymentReference($user->id, $plan->id);

            // Get return URLs from payment config (no query params - we'll use database lookup)
            $returnUrl = config('payment.subscription.return_urls.success');
            $cancelUrl = config('payment.subscription.return_urls.cancel');

            if ($paymentMethod === 'saved_card' && $request->saved_card_id) {
                // Check if saved cards feature is enabled
                if (!$this->isSavedCardsEnabled()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Saved cards feature is not available',
                    ], 403);
                }
                
                // Pay with saved card
                $paymentData = $this->paymentService->payWithSavedCard([
                    'saved_card_id' => $request->saved_card_id,
                    'external_customer_id' => (string) $user->id,
                    'amount' => $amount,
                    'currency' => 'LKR',
                    'description' => "Subscription: {$plan->name}",
                    'order_reference' => $paymentReference,
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'subscription_plan_id' => $plan->id,
                        'user_id' => $user->id,
                        'business_profile_id' => $businessProfileId,
                        'payment_type' => 'subscription_upgrade',
                        'include_setup_fee' => $includeSetupFee,
                    ],
                ]);
            } else {
                // Create new payment link
                $paymentData = $this->paymentService->createSubscriptionPayment([
                    'amount' => $amount,
                    'currency' => 'LKR',
                    'description' => "Subscription: {$plan->name}",
                    'order_reference' => $paymentReference,
                    'customer_name' => $user->name,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone,
                    'external_customer_id' => (string) $user->id,
                    'allow_save_card' => true,
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'subscription_plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'business_profile_id' => $businessProfileId,
                    'payment_type' => 'subscription_upgrade',
                ]);
            }

            // Store pending payment in database for secure callback lookup
            $pendingPayment = \App\Models\PendingPayment::create([
                'user_id' => $user->id,
                'link_token' => $paymentData['link_token'] ?? null,
                'session_id' => $paymentData['session_id'] ?? null,
                'order_reference' => $paymentReference,
                'subscription_plan_id' => $plan->id,
                'amount' => $amount,
                'currency' => 'LKR',
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'metadata' => json_encode([
                    'business_profile_id' => $businessProfileId,
                    'include_setup_fee' => $includeSetupFee,
                ]),
                'expires_at' => now()->addHours(2),
            ]);
            
            Log::info('Pending payment created', [
                'pending_payment_id' => $pendingPayment->id,
                'session_id' => $paymentData['session_id'] ?? null,
                'order_reference' => $paymentReference,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $paymentData['payment_url'],
                'amount' => $amount,
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription upgrade initiation failed', [
                'user_id' => $user->id ?? 'unknown',
                'plan_id' => $plan->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment. Please try again.',
                'error' => $e->getMessage(), // Always show error for debugging
                'debug' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                ],
            ], 500);
        }
    }

    /**
     * Handle payment callback
     * GET /api/subscriptions/payment-callback
     */
    public function paymentCallback(Request $request)
    {
        $status = $request->query('status');
        $sessionId = $request->query('session_id');
        $orderId = $request->query('order_id');
        $amount = $request->query('amount');
        $currency = $request->query('currency');
        $reference = $request->query('reference'); // Our order_reference

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment callback - missing session_id',
            ], 400);
        }

        try {
            // Lookup pending payment by session_id or order_reference
            $pendingPayment = \App\Models\PendingPayment::where(function($query) use ($sessionId, $reference) {
                $query->where('session_id', $sessionId);
                if ($reference) {
                    $query->orWhere('order_reference', $reference);
                }
            })
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

            // Fallback for old payments: parse reference parameter
            if (!$pendingPayment && $reference && preg_match('/USER_(\d+)_PLAN_(\d+)_/', $reference, $matches)) {
                $userId = $matches[1];
                $planId = $matches[2];
                $businessProfileId = null;
                
                Log::info('Using fallback reference parsing for old payment', [
                    'session_id' => $sessionId,
                    'reference' => $reference,
                    'parsed_user_id' => $userId,
                    'parsed_plan_id' => $planId,
                ]);
            } elseif (!$pendingPayment) {
                Log::error('Payment session not found in database', [
                    'session_id' => $sessionId,
                    'reference' => $reference,
                    'pending_payments_count' => \App\Models\PendingPayment::count(),
                    'recent_payments' => \App\Models\PendingPayment::latest()->take(5)->pluck('session_id', 'id'),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payment session not found or has expired',
                    'debug' => config('app.debug') ? [
                        'session_id' => $sessionId,
                        'reference' => $reference,
                        'total_pending' => \App\Models\PendingPayment::count(),
                    ] : null,
                ], 404);
            } else {
                $userId = $pendingPayment->user_id;
                $planId = $pendingPayment->subscription_plan_id;
                $businessProfileId = json_decode($pendingPayment->metadata, true)['business_profile_id'] ?? null;
            }

            // Step 1: Verify payment status with Absterco
            // Step 1: Verify payment status with Absterco
            $verification = $this->paymentService->verifyPayment($sessionId);
            
            \Log::info('Payment callback received', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'plan_id' => $planId,
                'verification_status' => $verification['status'],
                'amount' => $amount,
            ]);
            
            // Check if payment is completed
            $completedStatuses = ['completed', 'payment_done', 'success'];
            if (!$verification['success'] || !in_array($verification['status'], $completedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed',
                    'status' => $verification['status'] ?? 'unknown',
                ], 400);
            }
            
            // Step 2: Get user and plan
            $user = \App\Models\User::findOrFail($userId);
            $plan = \App\Models\SubscriptionPlan::findOrFail($planId);

            // Create or update subscription
            DB::beginTransaction();
            try {
                // Cancel existing active subscription for this business
                if ($businessProfileId) {
                    $user->subscriptions()
                        ->where('business_profile_id', $businessProfileId)
                        ->active()
                        ->update([
                            'is_active' => false,
                            'status' => 'cancelled',
                            'ends_at' => now(),
                        ]);
                } else {
                    $user->subscriptions()->active()->update([
                        'is_active' => false,
                        'status' => 'cancelled',
                        'ends_at' => now(),
                    ]);
                }

                // Create new subscription
                $subscription = UserSubscription::create([
                    'user_id' => $user->id,
                    'business_profile_id' => $businessProfileId,
                    'subscription_plan_id' => $plan->id,
                    'starts_at' => now(),
                    'ends_at' => $this->calculateSubscriptionEndDate($plan),
                    'is_active' => true,
                    'status' => 'active',
                    'payment_method' => 'absterco_gateway',
                    'payment_gateway_transaction_id' => $sessionId,
                    'last_payment_at' => now(),
                    'next_payment_at' => $this->calculateNextPaymentDate($plan),
                    'payment_metadata' => json_encode([
                        'session_id' => $sessionId,
                        'order_id' => $orderId,
                        'amount' => $amount,
                        'currency' => $currency,
                    ]),
                ]);

                // Update business profile subscription ONLY if business_profile_id was provided
                // This ensures we update the correct business when user has multiple businesses
                if ($businessProfileId) {
                    $businessProfile = \App\Models\BusinessProfile::find($businessProfileId);
                    
                    if ($businessProfile && $businessProfile->user_id === $user->id) {
                        $businessProfile->update([
                            'subscription_plan_id' => $plan->id,
                        ]);
                        
                        Log::info('Business profile subscription updated', [
                            'business_profile_id' => $businessProfile->id,
                            'plan_id' => $plan->id,
                            'user_id' => $user->id,
                        ]);
                    } else {
                        Log::warning('Business profile not found or does not belong to user', [
                            'business_profile_id' => $businessProfileId,
                            'user_id' => $user->id,
                        ]);
                    }
                } else {
                    Log::info('No business profile ID in payment metadata - user subscription only', [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                    ]);
                }

                // Mark pending payment as completed (if it exists)
                if ($pendingPayment) {
                    $pendingPayment->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }

                DB::commit();

                Log::info('Subscription activated successfully', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'amount_paid' => $amount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription activated successfully',
                    'subscription' => [
                        'id' => $subscription->id,
                        'plan' => $plan->name,
                        'status' => $subscription->status,
                        'ends_at' => $subscription->ends_at,
                    ],
                    'payment' => [
                        'amount' => $amount,
                        'currency' => $currency,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Subscription payment callback failed', [
                'session_id' => $sessionId ?? null,
                'order_id' => $orderId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate subscription',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get user's saved payment cards
     * GET /api/subscriptions/saved-cards
     */
    public function getSavedCards()
    {
        // Check if saved cards feature is enabled
        if (!$this->isSavedCardsEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Saved cards feature is not available',
                'cards' => [],
            ], 403);
        }

        $user = Auth::user();

        try {
            $result = $this->paymentService->getSavedCards((string) $user->id);

            return response()->json([
                'success' => true,
                'cards' => $result['cards'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('Get saved cards failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve saved cards',
                'cards' => [],
            ], 500);
        }
    }

    /**
     * Set default payment card
     * POST /api/subscriptions/saved-cards/{cardId}/default
     */
    public function setDefaultCard($cardId)
    {
        $user = Auth::user();

        try {
            $success = $this->paymentService->setDefaultCard((string) $user->id, (int) $cardId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Default card updated successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to set default card',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default card',
            ], 500);
        }
    }

    /**
     * Delete saved payment card
     * DELETE /api/subscriptions/saved-cards/{cardId}
     */
    public function deleteSavedCard($cardId)
    {
        $user = Auth::user();

        try {
            $success = $this->paymentService->deleteSavedCard((string) $user->id, (int) $cardId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Card deleted successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete card',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete card',
            ], 500);
        }
    }

    /**
     * Calculate subscription end date based on billing period
     */
    private function calculateSubscriptionEndDate($plan): Carbon
    {
        $billingPeriod = $plan->billing_period ?? 'monthly';

        return match($billingPeriod) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            default => now()->addMonth(),
        };
    }

    /**
     * Calculate next payment date
     */
    private function calculateNextPaymentDate($plan): Carbon
    {
        return $this->calculateSubscriptionEndDate($plan);
    }
    
    /**
     * Check if payment feature is enabled for the current user
     * 
     * @follows MenuVibe Pattern: Feature Flag checking
     */
    private function isPaymentFeatureEnabled(): bool
    {
        $user = Auth::user();
        
        // Check if user has franchise
        if (!$user || !$user->franchise_id) {
            // Default franchise settings
            $config = config('franchise.default.features', []);
            return $config['subscription_payments'] ?? true;
        }
        
        // Get franchise slug
        $franchise = $user->franchise;
        if (!$franchise) {
            return true; // Allow by default if franchise not found
        }
        
        // Check franchise feature flag
        $config = config("franchise.{$franchise->slug}.features", []);
        return $config['subscription_payments'] ?? true;
    }
    
    /**
     * Check if saved cards feature is enabled
     * 
     * @follows MenuVibe Pattern: Feature Flag checking
     */
    private function isSavedCardsEnabled(): bool
    {
        $user = Auth::user();
        
        // Check franchise feature flag
        if ($user && $user->franchise_id) {
            $franchise = $user->franchise;
            if ($franchise) {
                $config = config("franchise.{$franchise->slug}.features", []);
                return $config['saved_cards'] ?? true;
            }
        }
        
        // Check payment gateway feature
        return $this->paymentService->hasFeature('saved_cards');
    }
}
