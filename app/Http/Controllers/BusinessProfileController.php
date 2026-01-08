<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
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

        if ($request->hasFile('logo')) {
            try {
                $data['logo_url'] = $this->storeLogo($request->file('logo'), $user->id);
            } catch (\Throwable $exception) {
                Log::error('Logo upload failed while creating business profile', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Logo upload failed. Please try again later.',
                ], Response::HTTP_BAD_GATEWAY);
            }
        } elseif ($request->filled('logo_url')) {
            $data['logo_url'] = $request->input('logo_url');
        } else {
            $data['logo_url'] = null;
        }

        unset($data['logo']);

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
            // Return empty structure instead of 404
            return response()->json([
                'success' => true,
                'data' => [
                    'business_profile' => null,
                    'needs_onboarding' => true
                ]
            ], Response::HTTP_OK);
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
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
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

        if ($request->hasFile('logo')) {
            try {
                $data['logo_url'] = $this->storeLogo($request->file('logo'), $user->id);
            } catch (\Throwable $exception) {
                Log::error('Logo upload failed while creating business profile (manual)', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Logo upload failed. Please try again later.',
                ], Response::HTTP_BAD_GATEWAY);
            }
        } elseif ($request->filled('logo_url')) {
            $data['logo_url'] = $request->input('logo_url');
        } else {
            $data['logo_url'] = null;
        }

        unset($data['logo']);

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

        Log::info('Incoming business profile update', [
            'user_id' => $user->id,
            'keys' => array_keys($request->all()),
            'has_logo' => $request->hasFile('logo'),
        ]);

        $normalized = $request->all();

        foreach ($normalized as $key => $value) {
            if ($key === 'business_name') {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                $normalized[$key] = null;
            }
        }

        $request->merge($normalized);

        $validator = Validator::make($request->all(), [
            'business_name' => 'sometimes|required|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'cuisine_type' => 'nullable|string|max:100',
            'seating_capacity' => 'nullable|integer|min:1|max:10000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
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
            try {
                $data['logo_url'] = $this->storeLogo($request->file('logo'), $user->id);
            } catch (\Throwable $exception) {
                Log::error('Logo upload failed while updating business profile (manual)', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Logo upload failed. Please try again later.',
                ], Response::HTTP_BAD_GATEWAY);
            }
        } elseif ($request->filled('logo_url')) {
            $data['logo_url'] = $request->input('logo_url');
        } else {
            $data['logo_url'] = $businessProfile->logo_url;
        }

        unset($data['logo']);

        if (array_key_exists('seating_capacity', $data) && $data['seating_capacity'] !== null) {
            $data['seating_capacity'] = (int) $data['seating_capacity'];
        }

        if (array_key_exists('country', $data) && $data['country']) {
            $data['country'] = strtoupper($data['country']);
        }

        Log::info('Business profile update payload', [
            'user_id' => $user->id,
            'data' => $data,
        ]);

        $businessProfile->fill($data);
        $changes = array_keys($businessProfile->getDirty());

        if (!empty($changes)) {
            $businessProfile->save();
        }

        Log::info('Business profile updated', [
            'user_id' => $user->id,
            'changes' => $changes,
            'logo_url' => $businessProfile->logo_url,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business profile updated successfully',
            'data' => [
                'business_profile' => $businessProfile->fresh(),
                'updated_fields' => $changes,
            ]
        ], Response::HTTP_OK);
    }

    private function storeLogo(UploadedFile $file, int $userId): string
    {
        $vercelToken = config('services.vercel_blob.token');

        if (!$vercelToken) {
            throw new \RuntimeException('Vercel Blob token is not configured');
        }

        $url = $this->uploadToVercelBlob($file, $userId, $vercelToken);

        Log::info('Logo uploaded to Vercel Blob', [
            'user_id' => $userId,
            'url' => $url,
        ]);

        return $url;
    }

    private function uploadToVercelBlob(UploadedFile $file, int $userId, string $token): string
    {
        $baseUrl = rtrim(config('services.vercel_blob.base_url', 'https://blob.vercel-storage.com'), '/');
        $prefix = trim(config('services.vercel_blob.prefix', 'logos'), '/');
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $fileName = $prefix . '/' . $userId . '/' . Str::uuid()->toString() . '.' . $extension;
        $uploadUrl = $baseUrl . '/' . ltrim($fileName, '/');

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                'x-vercel-metadata' => json_encode(['access' => 'public']),
            ])
            ->put($uploadUrl, $file->get());

        if ($response->failed()) {
            throw new \RuntimeException('Blob upload failed with status ' . $response->status());
        }

        $payload = $response->json();

        if (!is_array($payload) || empty($payload['url'])) {
            throw new \RuntimeException('Blob upload returned an unexpected response');
        }

        return $payload['url'];
    }
}
