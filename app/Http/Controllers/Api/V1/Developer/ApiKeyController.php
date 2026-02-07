<?php

namespace App\Http\Controllers\Api\V1\Developer;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * List all API keys for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->attributes->get('authenticated_user');
        
        $apiKeys = ApiKey::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key_prefix' => $key->key_prefix,
                    'key_preview' => $key->key_prefix . '...' . substr($key->key_hash, -4),
                    'key_type' => $key->key_type,
                    'environment' => $key->environment,
                    'rate_limit_per_hour' => $key->rate_limit_per_hour,
                    'scopes' => $key->scopes,
                    'is_active' => $key->is_active,
                    'last_used_at' => $key->last_used_at,
                    'expires_at' => $key->expires_at,
                    'created_at' => $key->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'api_keys' => $apiKeys,
            ],
            'meta' => [
                'total' => $apiKeys->count(),
            ]
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request)
    {
        $user = $request->attributes->get('authenticated_user');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'key_type' => 'required|in:public_read,standard,premium,enterprise',
            'environment' => 'required|in:production,sandbox',
            'whitelisted_ips' => 'nullable|array',
            'whitelisted_ips.*' => 'ip',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check user's subscription level to determine allowed key type
        $subscription = $user->subscription;
        $allowedKeyTypes = $this->getAllowedKeyTypes($subscription);

        if (!in_array($request->key_type, $allowedKeyTypes)) {
            return response()->json([
                'success' => false,
                'message' => "Your subscription plan does not allow '{$request->key_type}' API keys",
                'error' => [
                    'code' => 'SUBSCRIPTION_LIMIT',
                    'allowed_key_types' => $allowedKeyTypes,
                    'requested_key_type' => $request->key_type,
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        // Generate the API key
        $prefix = $request->environment === 'production' ? 'mvb_live_' : 'mvb_test_';
        $randomKey = Str::random(32);
        $fullKey = $prefix . $randomKey;
        
        $config = ApiKey::$keyTypes[$request->key_type];

        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $fullKey),
            'key_type' => $request->key_type,
            'scopes' => $config['permissions'],
            'rate_limit_per_hour' => $config['rate_limit'],
            'environment' => $request->environment,
            'whitelisted_ips' => $request->whitelisted_ips,
            'expires_at' => $request->expires_at,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key created successfully',
            'data' => [
                'api_key' => $fullKey, // ONLY time we return the full key!
                'key_id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_type' => $apiKey->key_type,
                'environment' => $apiKey->environment,
                'scopes' => $apiKey->scopes,
                'rate_limit_per_hour' => $apiKey->rate_limit_per_hour,
                'expires_at' => $apiKey->expires_at,
            ],
            'meta' => [
                'warning' => 'Store this API key securely. You will not be able to see it again!',
                'documentation' => 'https://docs.MenuVire.com/api/authentication',
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get a single API key details
     */
    public function show(Request $request, $id)
    {
        $user = $request->attributes->get('authenticated_user');
        
        $apiKey = ApiKey::where('user_id', $user->id)->find($id);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Get usage stats
        $usageStats = $apiKey->getUsageStats('24h');

        return response()->json([
            'success' => true,
            'data' => [
                'api_key' => [
                    'id' => $apiKey->id,
                    'name' => $apiKey->name,
                    'key_prefix' => $apiKey->key_prefix,
                    'key_preview' => $apiKey->key_prefix . '...' . substr($apiKey->key_hash, -4),
                    'key_type' => $apiKey->key_type,
                    'environment' => $apiKey->environment,
                    'scopes' => $apiKey->scopes,
                    'rate_limit_per_hour' => $apiKey->rate_limit_per_hour,
                    'whitelisted_ips' => $apiKey->whitelisted_ips,
                    'is_active' => $apiKey->is_active,
                    'last_used_at' => $apiKey->last_used_at,
                    'expires_at' => $apiKey->expires_at,
                    'created_at' => $apiKey->created_at,
                    'updated_at' => $apiKey->updated_at,
                ],
                'usage_stats' => [
                    'total_requests' => $usageStats->total_requests ?? 0,
                    'successful_requests' => $usageStats->successful_requests ?? 0,
                    'failed_requests' => $usageStats->failed_requests ?? 0,
                    'avg_response_time_ms' => round($usageStats->avg_response_time ?? 0, 2),
                ]
            ]
        ]);
    }

    /**
     * Update an API key
     */
    public function update(Request $request, $id)
    {
        $user = $request->attributes->get('authenticated_user');
        
        $apiKey = ApiKey::where('user_id', $user->id)->find($id);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'whitelisted_ips' => 'nullable|array',
            'whitelisted_ips.*' => 'ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $apiKey->update($request->only(['name', 'is_active', 'whitelisted_ips']));

        return response()->json([
            'success' => true,
            'message' => 'API key updated successfully',
            'data' => [
                'api_key' => [
                    'id' => $apiKey->id,
                    'name' => $apiKey->name,
                    'is_active' => $apiKey->is_active,
                    'whitelisted_ips' => $apiKey->whitelisted_ips,
                ]
            ]
        ]);
    }

    /**
     * Delete (revoke) an API key
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->attributes->get('authenticated_user');
        
        $apiKey = ApiKey::where('user_id', $user->id)->find($id);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $apiKey->delete();

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully'
        ]);
    }

    /**
     * Rotate an API key (generate new key, revoke old one)
     */
    public function rotate(Request $request, $id)
    {
        $user = $request->attributes->get('authenticated_user');
        
        $oldKey = ApiKey::where('user_id', $user->id)->find($id);

        if (!$oldKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Generate new key with same settings
        $prefix = $oldKey->environment === 'production' ? 'mvb_live_' : 'mvb_test_';
        $randomKey = Str::random(32);
        $fullKey = $prefix . $randomKey;

        $newKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => $oldKey->name . ' (Rotated)',
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $fullKey),
            'key_type' => $oldKey->key_type,
            'scopes' => $oldKey->scopes,
            'rate_limit_per_hour' => $oldKey->rate_limit_per_hour,
            'environment' => $oldKey->environment,
            'whitelisted_ips' => $oldKey->whitelisted_ips,
            'is_active' => true,
        ]);

        // Deactivate old key (don't delete for audit trail)
        $oldKey->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'API key rotated successfully',
            'data' => [
                'new_api_key' => $fullKey,
                'new_key_id' => $newKey->id,
                'old_key_id' => $oldKey->id,
            ],
            'meta' => [
                'warning' => 'Store this new API key securely. The old key has been deactivated.',
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get allowed key types based on subscription
     */
    private function getAllowedKeyTypes($subscription)
    {
        if (!$subscription) {
            return ['public_read'];
        }

        $planSlug = $subscription->plan_slug ?? 'free';

        return match($planSlug) {
            'enterprise' => ['public_read', 'standard', 'premium', 'enterprise'],
            'premium', 'pro' => ['public_read', 'standard', 'premium'],
            'basic', 'starter' => ['public_read', 'standard'],
            default => ['public_read'],
        };
    }
}
