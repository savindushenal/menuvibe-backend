<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * DEMO ONLY: Loyalty OTP Controller
 * For testing and demonstration purposes
 * No real SMS sending, no real loyalty API
 */
class LoyaltyOtpController extends Controller
{
    /**
     * DEMO: Send OTP (actually just stores it, doesn't send SMS)
     * POST /api/{franchise}/loyalty/send-otp
     */
    public function sendOtp(Request $request, string $franchise)
    {
        // Only for Barista franchise
        if ($franchise !== 'barista') {
            return response()->json([
                'success' => false,
                'message' => 'OTP authentication not available for this franchise',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string|regex:/^[0-9]{10,15}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid mobile number format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mobileNumber = $request->mobile_number;
        
        // DEMO: Always use 123456 as OTP for testing
        $otpCode = '123456';

        // Store OTP in cache (10 minutes expiry)
        Cache::put("demo_otp:{$mobileNumber}", $otpCode, now()->addMinutes(10));

        Log::info("[DEMO] OTP generated", [
            'phone' => $mobileNumber,
            'otp' => $otpCode,
            'message' => 'In production, SMS would be sent here'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'demo_note' => 'DEMO MODE: OTP is always 123456',
            'demo_otp' => $otpCode, // In production, NEVER return OTP in response!
        ]);
    }

    /**
     * DEMO: Verify OTP and return loyalty info
     * POST /api/{franchise}/loyalty/verify-otp
     */
    public function verifyOtp(Request $request, string $franchise)
    {
        if ($franchise !== 'barista') {
            return response()->json([
                'success' => false,
                'message' => 'OTP authentication not available',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mobileNumber = $request->mobile_number;
        $otpCode = $request->otp_code;

        // DEMO: Verify OTP from cache
        $cachedOtp = Cache::get("demo_otp:{$mobileNumber}");

        if (!$cachedOtp || $cachedOtp !== $otpCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 401);
        }

        // Clear OTP after successful verification
        Cache::forget("demo_otp:{$mobileNumber}");

        // DEMO: Get loyalty info from hardcoded data
        $loyaltyInfo = $this->getDemoLoyaltyInfo($mobileNumber);

        // DEMO: Get saved cards from hardcoded data
        $savedCards = $this->getDemoSavedCards($mobileNumber);

        // Generate session token
        $sessionToken = base64_encode($mobileNumber . ':' . now()->timestamp);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'demo_mode' => true,
            'data' => [
                'mobile_number' => $mobileNumber,
                'loyalty_info' => $loyaltyInfo,
                'saved_cards' => $savedCards,
                'session_token' => $sessionToken,
            ],
        ]);
    }

    /**
     * DEMO: Hardcoded loyalty data for testing
     */
    protected function getDemoLoyaltyInfo(string $mobileNumber): ?array
    {
        // Demo loyalty members
        $demoMembers = [
            '0771234567' => [
                'member_number' => 'BAR-2024-001',
                'name' => 'Demo User 1',
                'mobile' => '0771234567',
                'email' => 'demo1@example.com',
                'points_balance' => 450,
                'tier' => 'Gold',
                'member_since' => '2024-01-15',
                'lifetime_points' => 2450,
            ],
            '0777654321' => [
                'member_number' => 'BAR-2024-002',
                'name' => 'Demo User 2',
                'mobile' => '0777654321',
                'email' => 'demo2@example.com',
                'points_balance' => 1250,
                'tier' => 'Platinum',
                'member_since' => '2023-11-20',
                'lifetime_points' => 5670,
            ],
            '0701112233' => [
                'member_number' => 'BAR-2024-003',
                'name' => 'Demo User 3',
                'mobile' => '0701112233',
                'email' => 'demo3@example.com',
                'points_balance' => 120,
                'tier' => 'Bronze',
                'member_since' => '2024-12-01',
                'lifetime_points' => 120,
            ],
        ];

        // If mobile number not in demo data, create new member
        if (!isset($demoMembers[$mobileNumber])) {
            return [
                'member_number' => 'BAR-2024-NEW',
                'name' => 'New Demo User',
                'mobile' => $mobileNumber,
                'email' => null,
                'points_balance' => 0,
                'tier' => 'Bronze',
                'member_since' => now()->format('Y-m-d'),
                'lifetime_points' => 0,
                'is_new_member' => true,
            ];
        }

        return $demoMembers[$mobileNumber];
    }

    /**
     * DEMO: Hardcoded saved cards for testing
     */
    protected function getDemoSavedCards(string $mobileNumber): array
    {
        // Demo saved cards (fake payment tokens)
        $demoCards = [
            '0771234567' => [
                [
                    'id' => 1,
                    'gateway_token' => 'pm_demo_visa_4242',
                    'last4' => '4242',
                    'brand' => 'Visa',
                    'exp_month' => 12,
                    'exp_year' => 2026,
                    'is_default' => true,
                    'loyalty_linked' => true,
                ],
                [
                    'id' => 2,
                    'gateway_token' => 'pm_demo_mastercard_5555',
                    'last4' => '5555',
                    'brand' => 'Mastercard',
                    'exp_month' => 8,
                    'exp_year' => 2025,
                    'is_default' => false,
                    'loyalty_linked' => false,
                ],
            ],
            '0777654321' => [
                [
                    'id' => 3,
                    'gateway_token' => 'pm_demo_amex_3782',
                    'last4' => '1005',
                    'brand' => 'American Express',
                    'exp_month' => 3,
                    'exp_year' => 2027,
                    'is_default' => true,
                    'loyalty_linked' => true,
                ],
            ],
        ];

        return $demoCards[$mobileNumber] ?? [];
    }

    /**
     * DEMO: Register new loyalty member (mock registration)
     * POST /api/{franchise}/loyalty/register
     */
    public function registerLoyalty(Request $request, string $franchise)
    {
        if ($franchise !== 'barista') {
            return response()->json([
                'success' => false,
                'message' => 'Loyalty program not available',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'name' => 'required|string',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // DEMO: Just return success with new member data
        return response()->json([
            'success' => true,
            'message' => 'Loyalty member registered successfully',
            'demo_mode' => true,
            'data' => [
                'member_number' => 'BAR-2024-' . strtoupper(substr(md5($request->mobile_number), 0, 6)),
                'name' => $request->name,
                'mobile' => $request->mobile_number,
                'email' => $request->email,
                'points_balance' => 0,
                'tier' => 'Bronze',
                'member_since' => now()->format('Y-m-d'),
                'welcome_bonus' => 50, // Demo welcome points
            ],
        ]);
    }

    /**
     * DEMO: Save payment card (mock save)
     * POST /api/{franchise}/loyalty/save-card
     */
    public function saveCard(Request $request, string $franchise)
    {
        if ($franchise !== 'barista') {
            return response()->json([
                'success' => false,
                'message' => 'Feature not available',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'mobile_number' => 'required|string',
            'card_number' => 'required|string', // In real app, this would be a payment token
            'expiry_month' => 'required|integer|min:1|max:12',
            'expiry_year' => 'required|integer|min:2025',
            'cvv' => 'required|string|size:3',
            'set_default' => 'boolean',
            'link_to_loyalty' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // DEMO: Mock card save
        $last4 = substr($request->card_number, -4);
        $brand = $this->detectCardBrand($request->card_number);

        return response()->json([
            'success' => true,
            'message' => 'Card saved successfully',
            'demo_mode' => true,
            'demo_note' => 'In production, card would be tokenized via Stripe/payment gateway',
            'data' => [
                'id' => rand(1000, 9999),
                'gateway_token' => 'pm_demo_' . strtolower($brand) . '_' . $last4,
                'last4' => $last4,
                'brand' => $brand,
                'exp_month' => $request->expiry_month,
                'exp_year' => $request->expiry_year,
                'is_default' => $request->set_default ?? false,
                'loyalty_linked' => $request->link_to_loyalty ?? false,
            ],
        ]);
    }

    /**
     * DEMO: Detect card brand from number
     */
    protected function detectCardBrand(string $cardNumber): string
    {
        $firstDigit = substr($cardNumber, 0, 1);
        $firstTwo = substr($cardNumber, 0, 2);

        if ($firstDigit === '4') return 'Visa';
        if (in_array($firstTwo, ['51', '52', '53', '54', '55'])) return 'Mastercard';
        if (in_array($firstTwo, ['34', '37'])) return 'American Express';
        if ($firstTwo === '60') return 'Discover';

        return 'Unknown';
    }
}
