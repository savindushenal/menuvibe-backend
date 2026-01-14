<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Absterco Payment Gateway Service
 * Handles payment link creation, card saving, and subscription payments
 * 
 * @follows MenuVibe Architecture: Service Layer (Shared)
 * @pattern Configuration Over Code
 */
class AbstercoPaymentService
{
    private string $apiKey;
    private string $baseUrl;
    private string $organizationId;
    private array $config;

    public function __construct()
    {
        // Load from centralized payment config
        $this->config = config('payment.gateways.absterco', []);
        
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->baseUrl = $this->config['base_url'] ?? '';
        $this->organizationId = $this->config['organization_id'] ?? '';

        if (!$this->apiKey || !$this->baseUrl) {
            throw new Exception('Absterco payment gateway credentials not configured');
        }
    }
    
    /**
     * Check if Absterco gateway is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }
    
    /**
     * Check if a feature is available
     */
    public function hasFeature(string $feature): bool
    {
        return $this->config['features'][$feature] ?? false;
    }
    
    /**
     * Get gateway settings
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->config['settings'][$key] ?? $default;
    }

    /**
     * Create payment link for subscription
     */
    public function createSubscriptionPayment(array $data): array
    {
        // Get settings from config
        $currency = $this->getSetting('currency', 'LKR');
        $allowSaveCard = $this->getSetting('allow_save_card', true);
        
        $payload = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $currency,
            'description' => $data['description'],
            'order_reference' => $data['order_reference'],
            
            // Customer details
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            
            // Card saving (use config default)
            'external_customer_id' => $data['external_customer_id'], // User ID
            'allow_save_card' => $data['allow_save_card'] ?? $allowSaveCard,
            
            // Callback URL (from payment config)
            'business_return_url' => $data['return_url'] ?? config('payment.subscription.return_urls.success'),
            
            // Metadata for tracking
            'metadata' => [
                'subscription_plan_id' => $data['subscription_plan_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'payment_type' => $data['payment_type'] ?? 'subscription',
            ],
        ];

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/payment-links", $payload);

            if ($response->failed()) {
                Log::error('Absterco payment link creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload' => $payload,
                ]);
                throw new Exception('Failed to create payment link: ' . $response->body());
            }

            $data = $response->json();

            return [
                'success' => true,
                'link_token' => $data['data']['link_token'] ?? $data['data']['id'] ?? null,
                'payment_url' => $data['data']['payment_url'] ?? $data['data']['url'] ?? null,
                'expires_at' => $data['data']['expires_at'] ?? $data['data']['expiry'] ?? null,
                'session_id' => $data['data']['session_id'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('Absterco payment service error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(string $linkToken): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/api/v1/payment-links/{$linkToken}/verify");

            if ($response->failed()) {
                throw new Exception('Failed to verify payment');
            }

            $data = $response->json();
            $paymentData = $data['data'];

            return [
                'success' => true,
                'status' => $paymentData['status'],
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'],
                'order_reference' => $paymentData['order_reference'],
                'card_saved' => $paymentData['card_saved'] ?? false,
                'saved_card_id' => $paymentData['saved_card_id'] ?? null,
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'metadata' => $paymentData['metadata'] ?? [],
                'paid_at' => $paymentData['completed_at'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error('Absterco payment verification error', [
                'link_token' => $linkToken,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get customer's saved cards
     */
    public function getSavedCards(string $externalCustomerId): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/api/v1/saved-cards", [
                'external_customer_id' => $externalCustomerId,
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to retrieve saved cards');
            }

            $data = $response->json();

            return [
                'success' => true,
                'cards' => collect($data['data'])->map(function ($card) {
                    return [
                        'id' => $card['id'],
                        'card_number_masked' => $card['card_number_masked'],
                        'card_brand' => $card['card_brand'],
                        'card_type' => $card['card_type'],
                        'expiry_month' => $card['expiry_month'],
                        'expiry_year' => $card['expiry_year'],
                        'is_default' => $card['is_default'],
                        'last_used_at' => $card['last_used_at'] ?? null,
                    ];
                })->toArray(),
            ];

        } catch (Exception $e) {
            Log::error('Absterco get saved cards error', [
                'customer_id' => $externalCustomerId,
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'cards' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pay with saved card
     */
    public function payWithSavedCard(array $data): array
    {
        $payload = [
            'saved_card_id' => $data['saved_card_id'],
            'external_customer_id' => $data['external_customer_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'LKR',
            'description' => $data['description'],
            'order_reference' => $data['order_reference'],
            'business_return_url' => $data['return_url'],
            'metadata' => $data['metadata'] ?? [],
        ];

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/saved-cards/pay", $payload);

            if ($response->failed()) {
                throw new Exception('Failed to process payment with saved card');
            }

            $data = $response->json();

            return [
                'success' => true,
                'session_id' => $data['data']['session_id'],
                'payment_url' => $data['data']['payment_url'], // 3DS verification
                'status' => $data['data']['status'],
            ];

        } catch (Exception $e) {
            Log::error('Absterco saved card payment error', [
                'saved_card_id' => $data['saved_card_id'],
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Set default card
     */
    public function setDefaultCard(string $externalCustomerId, int $savedCardId): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/saved-cards/{$savedCardId}/default", [
                'external_customer_id' => $externalCustomerId,
            ]);

            return $response->successful();

        } catch (Exception $e) {
            Log::error('Absterco set default card error', [
                'customer_id' => $externalCustomerId,
                'card_id' => $savedCardId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete saved card
     */
    public function deleteSavedCard(string $externalCustomerId, int $savedCardId): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->delete("{$this->baseUrl}/api/v1/saved-cards/{$savedCardId}", [
                'external_customer_id' => $externalCustomerId,
            ]);

            return $response->successful();

        } catch (Exception $e) {
            Log::error('Absterco delete card error', [
                'customer_id' => $externalCustomerId,
                'card_id' => $savedCardId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate subscription payment reference
     */
    public function generatePaymentReference(int $userId, int $planId): string
    {
        return 'SUB-' . $userId . '-' . $planId . '-' . time();
    }

    /**
     * Calculate subscription amount (including setup fee if applicable)
     */
    public function calculateSubscriptionAmount($plan, bool $includeSetupFee = false): float
    {
        $amount = (float) $plan->price;

        if ($includeSetupFee && $plan->setup_fee > 0) {
            $amount += (float) $plan->setup_fee;
        }

        return $amount;
    }
}
