<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiScope
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $scope  Required permission (e.g., "menus:write", "analytics:read")
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey) {
            return $this->forbidden('Authentication required');
        }

        // Check if API key has required permission
        if (!$apiKey->hasPermission($scope)) {
            return $this->forbidden(
                "This operation requires '{$scope}' permission. Your API key has: " . 
                implode(', ', $apiKey->scopes ?? [])
            );
        }

        return $next($request);
    }

    /**
     * Return forbidden response
     */
    private function forbidden(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'INSUFFICIENT_PERMISSIONS',
                'type' => 'authorization_error',
            ]
        ], 403);
    }
}
