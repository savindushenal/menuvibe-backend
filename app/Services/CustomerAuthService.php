<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CustomerAuthService
{
    /**
     * Step 1: Check if customer exists and send OTP
     * 
     * @param Location $location
     * @param string $phone
     * @return array ['success' => bool, 'message' => string, 'session_id' => string]
     */
    public function sendOtp(Location $location, string $phone): array
    {
        $authMode = $location->auth_mode ?? 'guest';
        
        if ($authMode === 'guest') {
            return [
                'success' => false,
                'message' => 'Authentication not required for this location',
            ];
        }
        
        // Generate session ID for this OTP attempt
        $sessionId = Str::random(32);
        
        if ($authMode === 'internal') {
            return $this->sendInternalOtp($location, $phone, $sessionId);
        }
        
        if ($authMode === 'external') {
            return $this->sendExternalOtp($location, $phone, $sessionId);
        }
        
        if ($authMode === 'hybrid') {
            // Try external first, fallback to internal
            $external = $this->sendExternalOtp($location, $phone, $sessionId);
            if ($external['success']) {
                return $external;
            }
            return $this->sendInternalOtp($location, $phone, $sessionId);
        }
        
        return [
            'success' => false,
            'message' => 'Unknown authentication mode',
        ];
    }
    
    /**
     * Send OTP using MenuVire's internal system
     */
    protected function sendInternalOtp(Location $location, string $phone, string $sessionId): array
    {
        // Check if customer exists
        $customer = Customer::where('phone', $phone)
            ->where('location_id', $location->id)
            ->first();
        
        if (!$customer) {
            // New customer - create profile (or return error if registration required)
            return [
                'success' => false,
                'message' => 'Customer not found. Please register first.',
                'requires_registration' => true,
            ];
        }
        
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in cache for 5 minutes
        Cache::put("otp:{$sessionId}", [
            'otp' => $otp,
            'phone' => $phone,
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'provider' => 'internal',
        ], now()->addMinutes(5));
        
        // Send SMS (integrate with your SMS provider)
        $this->sendSms($phone, "Your MenuVire OTP is: {$otp}. Valid for 5 minutes.");
        
        return [
            'success' => true,
            'message' => 'OTP sent successfully',
            'session_id' => $sessionId,
            'otp_sent_by' => 'menuvire',
            'customer' => [
                'name' => $customer->name,
                'phone' => $phone,
                'exists' => true,
            ],
        ];
    }
    
    /**
     * Send OTP via external franchise system
     * Supports two modes: franchise sends OTP OR MenuVire sends OTP
     */
    protected function sendExternalOtp(Location $location, string $phone, string $sessionId): array
    {
        $config = $location->external_auth_config;
        
        if (!$config || empty($config['api_endpoint'])) {
            return [
                'success' => false,
                'message' => 'External authentication not configured',
            ];
        }
        
        $otpMode = $config['otp_mode'] ?? 'franchise_sends';
        
        if ($otpMode === 'menuvire_sends') {
            return $this->sendOtpViaMenuvire($location, $phone, $sessionId, $config);
        } else {
            return $this->sendOtpViaFranchise($location, $phone, $sessionId, $config);
        }
    }
    
    /**
     * Mode A: Franchise sends OTP (they handle SMS)
     */
    protected function sendOtpViaFranchise(Location $location, string $phone, string $sessionId, array $config): array
    {
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            $fieldMappings = $config['field_mappings'] ?? [];
            
            // Call franchise API to send OTP
            $payload = [
                $fieldMappings['phone'] ?? 'phone' => $phone,
            ];
            
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            $response = $request->post($endpoint . '/send-otp', $payload);
            
            if (!$response->successful()) {
                Log::error('External OTP send failed', [
                    'location_id' => $location->id,
                    'phone' => $phone,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Customer not found in franchise system',
                ];
            }
            
            $data = $response->json();
            
            // Store session data for verification later
            Cache::put("otp:{$sessionId}", [
                'phone' => $phone,
                'location_id' => $location->id,
                'provider' => 'external_franchise_otp',
                'external_session_id' => $data[$fieldMappings['session_id'] ?? 'session_id'] ?? null,
                'external_data' => $data,
            ], now()->addMinutes(5));
            
            return [
                'success' => true,
                'message' => $data['message'] ?? 'OTP sent successfully',
                'session_id' => $sessionId,
                'otp_sent_by' => 'franchise',
                'customer' => [
                    'name' => $data[$fieldMappings['name'] ?? 'name'] ?? null,
                    'phone' => $phone,
                    'exists' => true,
                    'loyalty_number' => $data[$fieldMappings['loyalty_number'] ?? 'loyalty_number'] ?? null,
                ],
            ];
            
        } catch (\Exception $e) {
            Log::error('External OTP API error', [
                'location_id' => $location->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to connect to franchise system',
            ];
        }
    }
    
    /**
     * Mode B: MenuVire sends OTP (franchise only validates customer exists)
     */
    protected function sendOtpViaMenuvire(Location $location, string $phone, string $sessionId, array $config): array
    {
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            $fieldMappings = $config['field_mappings'] ?? [];
            $checkEndpoint = $config['check_endpoint'] ?? '/customers/check';
            
            // Step 1: Check if customer exists in franchise system
            $payload = [
                $fieldMappings['phone'] ?? 'phone' => $phone,
            ];
            
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            $response = $request->post($endpoint . $checkEndpoint, $payload);
            
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Customer not found in franchise system',
                ];
            }
            
            $data = $response->json();
            
            if (empty($data['exists']) || !$data['exists']) {
                return [
                    'success' => false,
                    'message' => 'Customer not found',
                ];
            }
            
            // Step 2: Generate OTP ourselves
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Step 3: Cache OTP for verification
            Cache::put("otp:{$sessionId}", [
                'otp' => $otp,
                'phone' => $phone,
                'location_id' => $location->id,
                'provider' => 'external_menuvire_otp',
                'external_customer_id' => $data[$fieldMappings['customer_id'] ?? 'customer_id'] ?? null,
                'customer_data' => $data['customer'] ?? null,
            ], now()->addMinutes(5));
            
            // Step 4: Send SMS via MenuVire's SMS service
            $this->sendSms($phone, "Your {$location->name} OTP is: {$otp}. Valid for 5 minutes.");
            
            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'session_id' => $sessionId,
                'otp_sent_by' => 'menuvire',
                'customer' => [
                    'name' => $data['customer']['name'] ?? null,
                    'phone' => $phone,
                    'exists' => true,
                    'loyalty_number' => $data['customer']['loyalty_number'] ?? null,
                ],
            ];
            
        } catch (\Exception $e) {
            Log::error('External customer check failed', [
                'location_id' => $location->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify customer',
            ];
        }
    }
    
    /**
     * Step 2: Verify OTP and authenticate customer
     * 
     * @param string $sessionId
     * @param string $otp
     * @return array ['success' => bool, 'customer' => array, 'token' => string]
     */
    public function verifyOtp(string $sessionId, string $otp): array
    {
        $sessionData = Cache::get("otp:{$sessionId}");
        
        if (!$sessionData) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP session',
            ];
        }
        
        $provider = $sessionData['provider'];
        
        if ($provider === 'internal') {
            return $this->verifyInternalOtp($sessionData, $otp, $sessionId);
        }
        
        if ($provider === 'external_menuvire_otp') {
            return $this->verifyMenuvireOtp($sessionData, $otp, $sessionId);
        }
        
        if ($provider === 'external_franchise_otp') {
            return $this->verifyFranchiseOtp($sessionData, $otp, $sessionId);
        }
        
        return [
            'success' => false,
            'message' => 'Unknown authentication provider',
        ];
    }
    
    /**
     * Verify OTP for internal customers
     */
    protected function verifyInternalOtp(array $sessionData, string $otp, string $sessionId): array
    {
        // Check OTP
        if ($sessionData['otp'] !== $otp) {
            return [
                'success' => false,
                'message' => 'Invalid OTP',
            ];
        }
        
        // Get customer
        $customer = Customer::find($sessionData['customer_id']);
        
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Customer not found',
            ];
        }
        
        // Clear OTP from cache
        Cache::forget("otp:{$sessionId}");
        
        // Generate auth token
        $token = $customer->createToken('customer-auth')->plainTextToken;
        
        return [
            'success' => true,
            'message' => 'Authentication successful',
            'provider' => 'internal',
            'otp_verified_by' => 'menuvire',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'loyalty_number' => $customer->loyalty_number,
            ],
            'token' => $token,
        ];
    }
    
    /**
     * Verify OTP that MenuVire sent (external mode)
     */
    protected function verifyMenuvireOtp(array $sessionData, string $otp, string $sessionId): array
    {
        // Step 1: Verify OTP locally
        if ($sessionData['otp'] !== $otp) {
            return [
                'success' => false,
                'message' => 'Invalid OTP',
            ];
        }
        
        $location = Location::find($sessionData['location_id']);
        $config = $location->external_auth_config;
        
        // Step 2: Fetch full customer data from franchise API
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            $fieldMappings = $config['field_mappings'] ?? [];
            $getEndpoint = $config['get_customer_endpoint'] ?? '/customers/get';
            
            $payload = [];
            
            if (!empty($sessionData['external_customer_id'])) {
                $payload[$fieldMappings['customer_id'] ?? 'customer_id'] = $sessionData['external_customer_id'];
            } else {
                $payload[$fieldMappings['phone'] ?? 'phone'] = $sessionData['phone'];
            }
            
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            $response = $request->post($endpoint . $getEndpoint, $payload);
            
            if (!$response->successful()) {
                Log::error('Failed to fetch external customer data', [
                    'location_id' => $location->id,
                    'status' => $response->status(),
                ]);
                
                // Fallback to cached data if available
                if (!empty($sessionData['customer_data'])) {
                    $data = $sessionData['customer_data'];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Failed to fetch customer data',
                    ];
                }
            } else {
                $data = $response->json();
            }
            
            // Step 3: Create or update local customer record
            $customer = Customer::updateOrCreate(
                [
                    'phone' => $sessionData['phone'],
                    'location_id' => $location->id,
                ],
                [
                    'franchise_id' => $location->franchise_id,
                    'name' => $data[$fieldMappings['name'] ?? 'name'] ?? 'Customer',
                    'email' => $data[$fieldMappings['email'] ?? 'email'] ?? null,
                    'external_customer_id' => $data[$fieldMappings['customer_id'] ?? 'id'] ?? $sessionData['external_customer_id'],
                    'loyalty_number' => $data[$fieldMappings['loyalty_number'] ?? 'loyalty_number'] ?? null,
                    'metadata' => $data,
                ]
            );
            
            // Step 4: Clear OTP cache
            Cache::forget("otp:{$sessionId}");
            
            // Step 5: Generate auth token
            $token = $customer->createToken('customer-auth')->plainTextToken;
            
            return [
                'success' => true,
                'message' => 'Authentication successful',
                'provider' => 'external',
                'otp_verified_by' => 'menuvire',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $data[$fieldMappings['name'] ?? 'name'] ?? null,
                    'phone' => $sessionData['phone'],
                    'email' => $data[$fieldMappings['email'] ?? 'email'] ?? null,
                    'loyalty_number' => $data[$fieldMappings['loyalty_number'] ?? 'loyalty_number'] ?? null,
                    'points' => $data[$fieldMappings['points'] ?? 'points'] ?? 0,
                    'tier' => $data[$fieldMappings['tier'] ?? 'tier'] ?? null,
                ],
                'token' => $token,
                'external_data' => $data,
            ];
            
        } catch (\Exception $e) {
            Log::error('External customer data fetch error', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to fetch customer data',
            ];
        }
    }
    
    /**
     * Verify OTP with external franchise API (they sent OTP)
     */
    protected function verifyFranchiseOtp(array $sessionData, string $otp, string $sessionId): array
    {
        $location = Location::find($sessionData['location_id']);
        $config = $location->external_auth_config;
        
        try {
            $endpoint = $config['api_endpoint'];
            $apiKey = $config['api_key'] ?? null;
            $authType = $config['auth_type'] ?? 'bearer';
            $fieldMappings = $config['field_mappings'] ?? [];
            
            // Verify OTP with franchise API
            $payload = [
                $fieldMappings['phone'] ?? 'phone' => $sessionData['phone'],
                $fieldMappings['otp'] ?? 'otp' => $otp,
            ];
            
            // Include external session ID if provided
            if (!empty($sessionData['external_session_id'])) {
                $payload['session_id'] = $sessionData['external_session_id'];
            }
            
            $request = Http::timeout(10);
            
            if ($authType === 'bearer' && $apiKey) {
                $request->withToken($apiKey);
            } elseif ($authType === 'api_key' && $apiKey) {
                $request->withHeaders(['X-API-Key' => $apiKey]);
            }
            
            $response = $request->post($endpoint . '/verify-otp', $payload);
            
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Invalid OTP or verification failed',
                ];
            }
            
            $data = $response->json();
            
            // Clear OTP session
            Cache::forget("otp:{$sessionId}");
            
            // Create or update local customer record
            $customer = Customer::updateOrCreate(
                [
                    'phone' => $sessionData['phone'],
                    'location_id' => $location->id,
                ],
                [
                    'franchise_id' => $location->franchise_id,
                    'name' => $data[$fieldMappings['name'] ?? 'name'] ?? 'Customer',
                    'email' => $data[$fieldMappings['email'] ?? 'email'] ?? null,
                    'external_customer_id' => $data[$fieldMappings['customer_id'] ?? 'id'] ?? null,
                    'loyalty_number' => $data[$fieldMappings['loyalty_number'] ?? 'loyalty_number'] ?? null,
                    'metadata' => $data,
                ]
            );
            
            // Generate local auth token
            $token = $customer->createToken('customer-auth')->plainTextToken;
            
            return [
                'success' => true,
                'message' => 'Authentication successful',
                'provider' => 'external',
                'otp_verified_by' => 'franchise',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $data[$fieldMappings['name'] ?? 'name'] ?? null,
                    'phone' => $sessionData['phone'],
                    'email' => $data[$fieldMappings['email'] ?? 'email'] ?? null,
                    'loyalty_number' => $data[$fieldMappings['loyalty_number'] ?? 'loyalty_number'] ?? null,
                    'points' => $data[$fieldMappings['points'] ?? 'points'] ?? 0,
                    'tier' => $data[$fieldMappings['tier'] ?? 'tier'] ?? null,
                ],
                'token' => $token,
                'external_data' => $data,
            ];
            
        } catch (\Exception $e) {
            Log::error('External OTP verification error', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Verification failed',
            ];
        }
    }
    
    /**
     * Register new customer (for internal mode)
     */
    public function register(Location $location, array $data): array
    {
        if ($location->auth_mode !== 'internal') {
            return [
                'success' => false,
                'message' => 'Registration not available for this location',
            ];
        }
        
        // Check if customer already exists
        if (Customer::where('phone', $data['phone'])->where('location_id', $location->id)->exists()) {
            return [
                'success' => false,
                'message' => 'Customer already exists',
            ];
        }
        
        // Create customer
        $customer = Customer::create([
            'location_id' => $location->id,
            'franchise_id' => $location->franchise_id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ]);
        
        return [
            'success' => true,
            'message' => 'Registration successful. Please login.',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ],
        ];
    }
    
    /**
     * Send SMS (integrate with your SMS provider)
     */
    protected function sendSms(string $phone, string $message): void
    {
        // TODO: Integrate with SMS provider (Twilio, Dialog, Mobitel, etc.)
        Log::info('SMS sent', [
            'phone' => $phone,
            'message' => $message,
        ]);
        
        // Example integration (uncomment and configure):
        // Twilio:
        // $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $twilio->messages->create($phone, [
        //     'from' => config('services.twilio.from'),
        //     'body' => $message
        // ]);
    }
}
