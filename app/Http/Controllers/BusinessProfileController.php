<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class BusinessProfileController extends Controller
{
    /**
     * Display the user's business profile.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $businessProfile = $user->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found',
                'needs_onboarding' => true
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business_profile' => $businessProfile,
                'needs_onboarding' => !$businessProfile->isOnboardingCompleted()
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created business profile.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Check if user already has a business profile
        if ($user->businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile already exists. Use update instead.'
            ], Response::HTTP_CONFLICT);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'nullable|string|max:2',
            'cuisine_type' => 'nullable|string|max:100',
            'seating_capacity' => 'nullable|integer|min:1|max:10000',
            'operating_hours' => 'nullable|array',
            'services' => 'nullable|array',
            'social_media' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        $data['user_id'] = $user->id;

        // Auto-complete onboarding if all required fields are provided
        $requiredFields = ['business_name', 'business_type', 'address_line_1', 'city', 'state', 'postal_code'];
        $hasAllRequired = collect($requiredFields)->every(fn($field) => !empty($data[$field]));
        
        if ($hasAllRequired) {
            $data['onboarding_completed'] = true;
            $data['onboarding_completed_at'] = now();
        }

        $businessProfile = BusinessProfile::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Business profile created successfully',
            'data' => [
                'business_profile' => $businessProfile->fresh(),
                'onboarding_completed' => $businessProfile->isOnboardingCompleted()
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Complete onboarding manually
     */
    public function completeOnboarding(Request $request)
    {
        $user = $request->user();
        $businessProfile = $user->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($businessProfile->isOnboardingCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding already completed'
            ], Response::HTTP_CONFLICT);
        }

        $businessProfile->completeOnboarding();

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed successfully',
            'data' => [
                'business_profile' => $businessProfile->fresh()
            ]
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

    public function indexManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $businessProfile = $user->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found',
                'needs_onboarding' => true
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business_profile' => $businessProfile,
                'needs_onboarding' => !$businessProfile->isOnboardingCompleted()
            ]
        ], Response::HTTP_OK);
    }

    public function storeManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user already has a business profile
        if ($user->businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile already exists. Use update instead.'
            ], Response::HTTP_CONFLICT);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'nullable|string|max:2',
            'cuisine_type' => 'nullable|string|max:100',
            'seating_capacity' => 'nullable|integer|min:1|max:10000',
            'operating_hours' => 'nullable|array',
            'services' => 'nullable|array',
            'social_media' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        $data['user_id'] = $user->id;

        // Auto-complete onboarding if all required fields are provided
        $requiredFields = ['business_name', 'business_type', 'address_line_1', 'city', 'state', 'postal_code'];
        $hasAllRequired = collect($requiredFields)->every(fn($field) => !empty($data[$field]));
        
        if ($hasAllRequired) {
            $data['onboarding_completed'] = true;
            $data['onboarding_completed_at'] = now();
        }

        $businessProfile = BusinessProfile::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Business profile created successfully',
            'data' => [
                'business_profile' => $businessProfile->fresh(),
                'onboarding_completed' => $businessProfile->isOnboardingCompleted()
            ]
        ], Response::HTTP_CREATED);
    }

    public function completeOnboardingManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $businessProfile = $user->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($businessProfile->isOnboardingCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding already completed'
            ], Response::HTTP_CONFLICT);
        }

        $businessProfile->completeOnboarding();

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed successfully',
            'data' => [
                'business_profile' => $businessProfile->fresh()
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Update business profile manually
     */
    public function updateManual(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $businessProfile = $user->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            'primary_color' => 'nullable|string|max:7', // Hex color
            'secondary_color' => 'nullable|string|max:7', // Hex color
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoFile = $request->file('logo');
            $logoPath = $logoFile->store('logos', 'public');
            $data['logo_url'] = '/storage/' . $logoPath;
        }

        // Update business profile
        $businessProfile->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Business profile updated successfully',
            'data' => [
                'business_profile' => $businessProfile->fresh()
            ]
        ], Response::HTTP_OK);
    }
}
