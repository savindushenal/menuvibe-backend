<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Payment Gateway Health Check
Route::get('/payment-gateway-status', function () {
    try {
        // Check if class exists
        $serviceExists = class_exists(\App\Services\AbstercoPaymentService::class);
        $controllerExists = class_exists(\App\Http\Controllers\SubscriptionPaymentController::class);
        
        // Check configuration
        $config = [
            'api_key_set' => !empty(config('services.absterco.api_key')),
            'base_url' => config('services.absterco.base_url'),
            'organization_id' => config('services.absterco.organization_id'),
        ];
        
        // Check routes
        $routeExists = Route::has('api.subscriptions.change');
        
        return response()->json([
            'status' => 'ok',
            'service_class_exists' => $serviceExists,
            'controller_class_exists' => $controllerExists,
            'configuration' => $config,
            'route_registered' => $routeExists,
            'git_commit' => exec('git rev-parse --short HEAD'),
            'last_deployment' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});
