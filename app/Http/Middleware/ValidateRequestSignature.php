<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRequestSignature
{
    /**
     * Handle an incoming request.
     * 
     * Validates HMAC-SHA256 signature for critical operations
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip signature validation for GET requests (read-only)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        if (!$signature || !$timestamp) {
            return $this->invalidSignature('Request signature and timestamp required for write operations');
        }

        // Check timestamp (prevent replay attacks - 5 minute window)
        if (abs(time() - $timestamp) > 300) {
            return $this->invalidSignature('Request timestamp expired. Requests must be made within 5 minutes');
        }

        $apiKey = $request->attributes->get('api_key');
        if (!$apiKey) {
            return $this->invalidSignature('API key required for signature validation');
        }

        // Get request body
        $body = $request->getContent();
        
        // Calculate expected signature
        $payload = $timestamp . '.' . $body;
        $expectedSignature = hash_hmac('sha256', $payload, config('app.api_secret_key'));

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            return $this->invalidSignature('Invalid request signature');
        }

        return $next($request);
    }

    /**
     * Return invalid signature response
     */
    private function invalidSignature(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'INVALID_SIGNATURE',
                'type' => 'security_error',
                'documentation' => 'https://docs.MenuVire.com/api/authentication#request-signing'
            ]
        ], 401);
    }
}
