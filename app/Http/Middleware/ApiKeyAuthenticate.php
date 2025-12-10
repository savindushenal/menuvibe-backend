<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiUsage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return $this->unauthorized('API key is required. Provide it in Authorization header as "Bearer mvb_live_xxx" or "Bearer mvb_test_xxx"');
        }

        // Validate key format
        if (!$this->isValidKeyFormat($apiKey)) {
            return $this->unauthorized('Invalid API key format. Expected: mvb_live_xxx or mvb_test_xxx');
        }

        // Hash the key for lookup
        $keyHash = hash('sha256', $apiKey);

        // Check cache first (reduce DB queries)
        $apiKeyModel = Cache::remember(
            "api_key:{$keyHash}",
            now()->addMinutes(5),
            fn() => ApiKey::where('key_hash', $keyHash)->first()
        );

        if (!$apiKeyModel) {
            return $this->unauthorized('Invalid API key');
        }

        // Check if key is active
        if (!$apiKeyModel->is_active) {
            return $this->unauthorized('API key has been deactivated');
        }

        // Check expiration
        if ($apiKeyModel->expires_at && $apiKeyModel->expires_at->isPast()) {
            return $this->unauthorized('API key has expired');
        }

        // Check IP whitelist
        if (!$apiKeyModel->isIpAllowed($request->ip())) {
            return $this->unauthorized('API key not authorized from this IP address');
        }

        // Rate limiting (check before processing)
        if (!$this->checkRateLimit($apiKeyModel, $request)) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'limit' => $apiKeyModel->rate_limit_per_hour,
                    'period' => '1 hour',
                    'retry_after' => 3600,
                ]
            ], 429);
        }

        // Attach API key to request for later use
        $request->attributes->set('api_key', $apiKeyModel);
        $request->attributes->set('authenticated_user', $apiKeyModel->user);

        // Mark key as used (async to not slow down request)
        dispatch(function () use ($apiKeyModel) {
            $apiKeyModel->markAsUsed();
        })->afterResponse();

        $response = $next($request);

        // Log API usage (async)
        $this->logApiUsage($request, $response, $apiKeyModel);

        return $response;
    }

    /**
     * Extract API key from request
     */
    private function extractApiKey(Request $request): ?string
    {
        // Check Authorization header (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check X-API-Key header (alternative)
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

        // Check query parameter (not recommended for production)
        return $request->query('api_key');
    }

    /**
     * Validate API key format
     */
    private function isValidKeyFormat(string $key): bool
    {
        return preg_match('/^mvb_(live|test)_[a-zA-Z0-9]{32}$/', $key);
    }

    /**
     * Check rate limit
     */
    private function checkRateLimit(ApiKey $apiKey, Request $request): bool
    {
        // Unlimited for enterprise
        if ($apiKey->rate_limit_per_hour === -1) {
            return true;
        }

        $cacheKey = "rate_limit:{$apiKey->id}:" . now()->format('YmdH');
        $requestCount = Cache::get($cacheKey, 0);

        if ($requestCount >= $apiKey->rate_limit_per_hour) {
            return false;
        }

        // Increment counter
        Cache::put($cacheKey, $requestCount + 1, now()->addHour());

        return true;
    }

    /**
     * Log API usage (async)
     */
    private function logApiUsage(Request $request, Response $response, ApiKey $apiKey): void
    {
        dispatch(function () use ($request, $response, $apiKey) {
            ApiUsage::log([
                'api_key_id' => $apiKey->id,
                'user_id' => $apiKey->user_id,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'response_status' => $response->getStatusCode(),
                'response_time_ms' => (microtime(true) - LARAVEL_START) * 1000,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_params' => $request->method() === 'GET' ? $request->query() : null,
                'response_summary' => $response->getStatusCode() >= 400 ? [
                    'error' => true
                ] : null,
            ]);
        })->afterResponse();
    }

    /**
     * Return unauthorized response
     */
    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'type' => 'authentication_error',
            ]
        ], 401);
    }
}
