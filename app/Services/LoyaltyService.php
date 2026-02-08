<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LoyaltyMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    /**
     * Verify loyalty member
     * Supports both internal and external loyalty systems
     */
    public function verifyMember(
        Location $location,
        string $loyaltyNumber,
        ?string $phone = null,
        ?string $otp = null
    ): array {
        $provider = $location->loyalty_provider ?? 'disabled';
        
        if ($provider === 'disabled') {
            return [
                'success' => false,
                'message' => 'Loyalty program is not enabled for this location',
            ];
        }
        
        if ($provider === 'internal') {
            return $this->verifyInternalMember($location, $loyaltyNumber, $phone, $otp);
        }
        
        if ($provider === 'external') {
            return $this->verifyExternalMember($location, $loyaltyNumber, $phone, $otp);
        }
        
        return [
            'success' => false,
            'message' => 'Unknown loyalty provider',
        ];
    }

    /**
     * Verify internal MenuVire loyalty member
     */
    protected function verifyInternalMember(
        Location $location,
        string $loyaltyNumber,
        ?string $phone = null,
        ?string $otp = null
    ): array {
        $query = LoyaltyMember::where('location_id', $location->id);
        
        // Try membership number first
        $member = $query->where('membership_number', $loyaltyNumber)->first();
        
        // If not found and phone provided, try phone
        if (!$member && $phone) {
            $member = LoyaltyMember::where('location_id', $location->id)
                ->where('phone', $phone)
                ->first();
        }
        
        if (!$member) {
            return [
                'success' => false,
                'message' => 'Loyalty member not found',
            ];
        }
        
        // Verify OTP if provided (for phone-based verification)
        if ($otp && $phone) {
            // OTP verification logic would go here
            // For now, assume OTP is valid
        }
        
        return [
            'success' => true,
            'provider' => 'internal',
            'member' => [
                'id' => $member->id,
                'membership_number' => $member->membership_number,
                'name' => $member->name,
                'phone' => $member->phone,
                'email' => $member->email,
                'points' => $member->points,
                'tier' => $member->tier,
                'status' => $member->status,
            ],
        ];
    }

    /**
     * Verify external franchise loyalty member via API
     */
    protected function verifyExternalMember(
        Location $location,
        string $loyaltyNumber,
        ?string $phone = null,
        ?string $otp = null
    ): array {
        $config = $location->loyalty_config;
        
        if (!$config || empty($config['api_endpoint'])) {
            return [
                'success' => false,
                'message' => 'External loyalty system not configured',
            ];
        }
        
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            $fieldMappings = $config['field_mappings'] ?? [];
            
            // Build request payload
            $payload = [
                $fieldMappings['loyalty_number'] ?? 'membership_number' => $loyaltyNumber,
            ];
            
            if ($phone) {
                $payload[$fieldMappings['phone'] ?? 'phone'] = $phone;
            }
            
            if ($otp) {
                $payload[$fieldMappings['otp'] ?? 'otp'] = $otp;
            }
            
            // Build HTTP request
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            // Make API call
            $response = $request->post($endpoint . '/verify-member', $payload);
            
            if (!$response->successful()) {
                Log::error('External loyalty verification failed', [
                    'location_id' => $location->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                
                return [
                    'success' => false,
                    'message' => 'External verification failed',
                ];
            }
            
            $data = $response->json();
            
            // Map response fields
            return [
                'success' => true,
                'provider' => 'external',
                'member' => [
                    'id' => $data[$fieldMappings['member_id'] ?? 'id'] ?? null,
                    'membership_number' => $data[$fieldMappings['membership_number'] ?? 'membership_number'] ?? $loyaltyNumber,
                    'name' => $data[$fieldMappings['name'] ?? 'name'] ?? null,
                    'phone' => $data[$fieldMappings['phone'] ?? 'phone'] ?? $phone,
                    'email' => $data[$fieldMappings['email'] ?? 'email'] ?? null,
                    'points' => $data[$fieldMappings['points'] ?? 'points'] ?? 0,
                    'tier' => $data[$fieldMappings['tier'] ?? 'tier'] ?? null,
                    'status' => $data[$fieldMappings['status'] ?? 'status'] ?? 'active',
                ],
                'raw_response' => $data, // Store for session
            ];
            
        } catch (\Exception $e) {
            Log::error('External loyalty API error', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Connection to external loyalty system failed',
            ];
        }
    }

    /**
     * Add points to member account
     */
    public function addPoints(
        Location $location,
        string $loyaltyNumber,
        int $points,
        string $reason = 'Purchase',
        ?array $metadata = null
    ): array {
        $provider = $location->loyalty_provider ?? 'disabled';
        
        if ($provider === 'internal') {
            return $this->addInternalPoints($location, $loyaltyNumber, $points, $reason, $metadata);
        }
        
        if ($provider === 'external') {
            return $this->addExternalPoints($location, $loyaltyNumber, $points, $reason, $metadata);
        }
        
        return ['success' => false, 'message' => 'Loyalty not enabled'];
    }

    /**
     * Add points to internal loyalty member
     */
    protected function addInternalPoints(
        Location $location,
        string $loyaltyNumber,
        int $points,
        string $reason,
        ?array $metadata
    ): array {
        $member = LoyaltyMember::where('location_id', $location->id)
            ->where('membership_number', $loyaltyNumber)
            ->first();
        
        if (!$member) {
            return ['success' => false, 'message' => 'Member not found'];
        }
        
        $member->increment('points', $points);
        
        // TODO: Create loyalty transaction record
        
        return [
            'success' => true,
            'points_added' => $points,
            'new_balance' => $member->points,
        ];
    }

    /**
     * Add points via external API
     */
    protected function addExternalPoints(
        Location $location,
        string $loyaltyNumber,
        int $points,
        string $reason,
        ?array $metadata
    ): array {
        $config = $location->loyalty_config;
        
        if (!$config || empty($config['api_endpoint'])) {
            return ['success' => false, 'message' => 'External loyalty not configured'];
        }
        
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            $fieldMappings = $config['field_mappings'] ?? [];
            
            $payload = [
                $fieldMappings['loyalty_number'] ?? 'membership_number' => $loyaltyNumber,
                $fieldMappings['points'] ?? 'points' => $points,
                $fieldMappings['reason'] ?? 'reason' => $reason,
            ];
            
            if ($metadata) {
                $payload['metadata'] = $metadata;
            }
            
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            $response = $request->post($endpoint . '/add-points', $payload);
            
            if (!$response->successful()) {
                return ['success' => false, 'message' => 'External API failed'];
            }
            
            $data = $response->json();
            
            return [
                'success' => true,
                'points_added' => $points,
                'new_balance' => $data[$fieldMappings['balance'] ?? 'balance'] ?? null,
            ];
            
        } catch (\Exception $e) {
            Log::error('External add points failed', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'message' => 'Connection failed'];
        }
    }

    /**
     * Test external API connection
     */
    public function testExternalConnection(Location $location): array
    {
        $config = $location->loyalty_config;
        
        if (!$config || empty($config['api_endpoint'])) {
            return [
                'success' => false,
                'message' => 'No API endpoint configured',
            ];
        }
        
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            $response = $request->get($endpoint . '/ping');
            
            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'message' => $response->successful() ? 'Connection successful' : 'Connection failed',
                'response_time' => $response->transferStats ? $response->transferStats->getTransferTime() : null,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}
