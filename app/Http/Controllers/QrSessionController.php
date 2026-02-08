<?php

namespace App\Http\Controllers;

use App\Models\MenuEndpoint;
use App\Models\QrScanSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QrSessionController extends Controller
{
    /**
     * Create or update a QR scan session
     * 
     * POST /api/public/sessions/{shortCode}
     */
    public function createOrUpdate(Request $request, string $shortCode): JsonResponse
    {
        try {
            // Find endpoint by short code
            $endpoint = MenuEndpoint::where('short_code', $shortCode)
                ->where('is_active', true)
                ->with(['location', 'template'])
                ->first();
            
            if (!$endpoint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid QR code',
                ], 404);
            }
            
            // Get session token from request
            $existingToken = $request->input('session_token');
            $deviceFingerprint = $request->input('device_fingerprint');
            $loyaltyNumber = $request->input('loyalty_number');
            
            // Create or update session
            $session = QrScanSession::createOrUpdateSession(
                $endpoint,
                $existingToken,
                $deviceFingerprint,
                $loyaltyNumber
            );
            
            // Update endpoint scan stats
            $endpoint->increment('scan_count');
            $endpoint->update(['last_scanned_at' => now()]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session_token' => $session->session_token,
                    'session_id' => $session->id,
                    'endpoint' => [
                        'id' => $endpoint->id,
                        'name' => $endpoint->name,
                        'type' => $endpoint->type,
                        'identifier' => $endpoint->identifier,
                    ],
                    'location' => [
                        'id' => $endpoint->location->id,
                        'name' => $endpoint->location->name,
                    ],
                    'template' => [
                        'id' => $endpoint->template->id,
                        'name' => $endpoint->template->name,
                    ],
                    'scan_count' => $session->scan_count,
                    'is_new_session' => $session->scan_count === 1,
                    'has_ordered' => $session->has_ordered,
                    'loyalty_linked' => !is_null($session->loyalty_number),
                    'expires_at' => $session->expires_at->toIso8601String(),
                ],
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('QR Session creation failed', [
                'short_code' => $shortCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create session',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Link loyalty account to session
     * 
     * POST /api/public/sessions/link-loyalty
     */
    public function linkLoyalty(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string',
            'loyalty_number' => 'required|string',
            'provider' => 'required|in:internal,external',
            'loyalty_data' => 'nullable|array',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        try {
            $session = QrScanSession::where('session_token', $request->session_token)
                ->active()
                ->firstOrFail();
            
            $session->linkLoyalty(
                $request->loyalty_number,
                $request->provider,
                $request->loyalty_data
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Loyalty account linked successfully',
                'data' => [
                    'session_token' => $session->session_token,
                    'loyalty_number' => $session->loyalty_number,
                    'loyalty_provider' => $session->loyalty_provider,
                ],
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found or expired',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Loyalty linking failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to link loyalty account',
            ], 500);
        }
    }

    /**
     * Get session orders
     * 
     * GET /api/public/sessions/{sessionToken}/orders
     */
    public function getOrders(Request $request, string $sessionToken): JsonResponse
    {
        try {
            $session = QrScanSession::where('session_token', $sessionToken)
                ->with(['orders' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session_token' => $session->session_token,
                    'total_orders' => $session->order_count,
                    'total_spent' => (float) $session->total_spent,
                    'orders' => $session->orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'total' => (float) $order->total,
                            'status' => $order->status,
                            'items_count' => $order->items->count(),
                            'created_at' => $order->created_at->toIso8601String(),
                        ];
                    }),
                ],
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get session orders failed', [
                'session_token' => $sessionToken,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
            ], 500);
        }
    }

    /**
     * Get session history (table movements)
     * 
     * GET /api/public/sessions/{sessionToken}/history
     */
    public function getHistory(Request $request, string $sessionToken): JsonResponse
    {
        try {
            $session = QrScanSession::where('session_token', $sessionToken)
                ->with(['endpointChanges' => function ($query) {
                    $query->with(['fromEndpoint', 'toEndpoint'])
                        ->orderBy('created_at', 'desc');
                }])
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session_token' => $session->session_token,
                    'current_endpoint' => [
                        'id' => $session->endpoint->id,
                        'name' => $session->endpoint->name,
                        'type' => $session->endpoint->type,
                    ],
                    'total_changes' => $session->endpointChanges()->count(),
                    'changes' => $session->endpointChanges->map(function ($change) {
                        return [
                            'from' => $change->fromEndpoint ? [
                                'name' => $change->fromEndpoint->name,
                                'type' => $change->fromEndpoint->type,
                            ] : null,
                            'to' => [
                                'name' => $change->toEndpoint->name,
                                'type' => $change->toEndpoint->type,
                            ],
                            'change_type' => $change->change_type,
                            'moved_at' => $change->created_at->toIso8601String(),
                        ];
                    }),
                    'summary' => $session->getSummary(),
                ],
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get session history failed', [
                'session_token' => $sessionToken,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session history',
            ], 500);
        }
    }

    /**
     * Get session details
     * 
     * GET /api/public/sessions/{sessionToken}
     */
    public function show(Request $request, string $sessionToken): JsonResponse
    {
        try {
            $session = QrScanSession::where('session_token', $sessionToken)
                ->with(['endpoint', 'location'])
                ->firstOrFail();
            
            if (!$session->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session has expired',
                ], 410);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session' => [
                        'token' => $session->session_token,
                        'scan_count' => $session->scan_count,
                        'order_count' => $session->order_count,
                        'total_spent' => (float) $session->total_spent,
                        'has_ordered' => $session->has_ordered,
                        'loyalty_linked' => !is_null($session->loyalty_number),
                        'expires_at' => $session->expires_at->toIso8601String(),
                    ],
                    'endpoint' => [
                        'id' => $session->endpoint->id,
                        'name' => $session->endpoint->name,
                        'type' => $session->endpoint->type,
                    ],
                    'location' => [
                        'id' => $session->location->id,
                        'name' => $session->location->name,
                    ],
                ],
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }
    }
}
