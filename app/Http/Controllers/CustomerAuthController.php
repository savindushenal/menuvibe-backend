<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerAuthController extends Controller
{
    protected CustomerAuthService $authService;

    public function __construct(CustomerAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Send OTP to customer
     * 
     * POST /api/public/auth/send-otp
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:locations,id',
            'phone' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $location = Location::findOrFail($request->location_id);
            
            $result = $this->authService->sendOtp($location, $request->phone);

            return response()->json($result, $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            Log::error('Send OTP failed', [
                'location_id' => $request->location_id,
                'phone' => $request->phone,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verify OTP and authenticate customer
     * 
     * POST /api/public/auth/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->authService->verifyOtp(
                $request->session_id,
                $request->otp
            );

            return response()->json($result, $result['success'] ? 200 : 400);
            
        } catch (\Exception $e) {
            Log::error('Verify OTP failed', [
                'session_id' => $request->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Register new customer (internal mode only)
     * 
     * POST /api/public/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $location = Location::findOrFail($request->location_id);
            
            $result = $this->authService->register($location, $request->only([
                'name',
                'phone',
                'email',
            ]));

            return response()->json($result, $result['success'] ? 201 : 400);
            
        } catch (\Exception $e) {
            Log::error('Customer registration failed', [
                'location_id' => $request->location_id,
                'phone' => $request->phone,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get authenticated customer profile
     * 
     * GET /api/customer/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'loyalty_number' => $customer->loyalty_number,
                'location' => [
                    'id' => $customer->location->id,
                    'name' => $customer->location->name,
                ],
            ],
        ]);
    }

    /**
     * Logout customer (revoke token)
     * 
     * POST /api/customer/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
