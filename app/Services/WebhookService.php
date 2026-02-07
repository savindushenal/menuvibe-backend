<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch webhook event to all subscribed webhooks
     */
    public function dispatch(string $event, array $payload, ?int $userId = null): void
    {
        // Find all active webhooks subscribed to this event
        $webhooks = Webhook::where('is_active', true)
            ->when($userId, fn($query) => $query->where('user_id', $userId))
            ->get()
            ->filter(fn($webhook) => $webhook->isSubscribedTo($event));

        foreach ($webhooks as $webhook) {
            $this->deliverWebhook($webhook, $event, $payload);
        }
    }

    /**
     * Deliver webhook to endpoint
     */
    public function deliverWebhook(Webhook $webhook, string $event, array $payload, int $attemptNumber = 1): void
    {
        $startTime = microtime(true);
        
        // Prepare payload
        $fullPayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        // Generate signature if secret exists
        $headers = $webhook->headers ?? [];
        if ($webhook->secret) {
            $signature = $this->generateSignature($fullPayload, $webhook->secret);
            $headers['X-Webhook-Signature'] = $signature;
        }

        // Add standard headers
        $headers['Content-Type'] = 'application/json';
        $headers['User-Agent'] = 'MenuVire-Webhooks/1.0';
        $headers['X-Webhook-Event'] = $event;
        $headers['X-Webhook-Delivery-ID'] = uniqid('whd_');

        try {
            // Make HTTP request
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout_seconds)
                ->post($webhook->url, $fullPayload);

            $duration = (microtime(true) - $startTime) * 1000;

            // Log delivery
            WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event_type' => $event,
                'payload' => $fullPayload,
                'response_body' => $response->body(),
                'response_status' => $response->status(),
                'attempt_number' => $attemptNumber,
                'delivered_at' => $response->successful() ? now() : null,
                'failed_at' => $response->failed() ? now() : null,
                'error_message' => $response->failed() ? $response->body() : null,
                'duration_ms' => $duration,
            ]);

            // Retry if failed and attempts remaining
            if ($response->failed() && $attemptNumber < $webhook->max_retries) {
                $this->scheduleRetry($webhook, $event, $payload, $attemptNumber + 1);
            }

        } catch (\Exception $e) {
            Log::error('Webhook delivery failed', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            // Log failed delivery
            WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event_type' => $event,
                'payload' => $fullPayload,
                'attempt_number' => $attemptNumber,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            // Retry if attempts remaining
            if ($attemptNumber < $webhook->max_retries) {
                $this->scheduleRetry($webhook, $event, $payload, $attemptNumber + 1);
            }
        }
    }

    /**
     * Schedule retry with exponential backoff
     */
    private function scheduleRetry(Webhook $webhook, string $event, array $payload, int $attemptNumber): void
    {
        // Exponential backoff: 30s, 2m, 8m
        $delay = pow(2, $attemptNumber - 1) * 30;

        dispatch(function () use ($webhook, $event, $payload, $attemptNumber) {
            $this->deliverWebhook($webhook, $event, $payload, $attemptNumber);
        })->delay(now()->addSeconds($delay));
    }

    /**
     * Generate HMAC signature for webhook payload
     */
    private function generateSignature(array $payload, string $secret): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Verify webhook signature (for webhook receivers)
     */
    public static function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
