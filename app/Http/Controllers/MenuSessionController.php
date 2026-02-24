<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\MenuEndpoint;
use App\Models\MenuOrder;
use App\Models\QrScanSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MenuSessionController extends Controller
{
    /**
     * Init or restore a session when QR is scanned.
     * POST /api/menu-session/{code}/init
     */
    public function init(Request $request, string $code): JsonResponse
    {
        try {
            $endpoint = MenuEndpoint::where('short_code', $code)
                ->where('is_active', true)
                ->with(['location', 'franchise'])
                ->first();

            if (!$endpoint) {
                return response()->json(['success' => false, 'message' => 'Invalid QR code'], 404);
            }

            $existingToken = $request->input('session_token') ?? $request->input('token');
            $deviceId      = $request->input('device_id');
            $userAgent     = $request->userAgent();
            $ipAddress     = $request->ip();
            $session       = null;

            // 1. Try to restore by session token (fastest path)
            if ($existingToken) {
                $session = QrScanSession::where('session_token', $existingToken)
                    ->where('endpoint_id', $endpoint->id)
                    ->where('expires_at', '>', now())
                    ->first();
            }

            // 2. Fallback: restore by device fingerprint (works across browser clears)
            if (!$session && $deviceId) {
                $session = QrScanSession::where('device_fingerprint', $deviceId)
                    ->where('endpoint_id', $endpoint->id)
                    ->where('expires_at', '>', now())
                    ->orderByDesc('last_activity_at')
                    ->first();
            }

            if ($session) {
                // Refresh expiry + activity; update device_id if not yet recorded
                $updateData = ['last_activity_at' => now(), 'expires_at' => now()->addHours(24)];
                if ($deviceId && !$session->device_fingerprint) {
                    $updateData['device_fingerprint'] = $deviceId;
                }
                if ($userAgent && !$session->user_agent) {
                    $updateData['user_agent'] = $userAgent;
                }
                $session->increment('scan_count');
                $session->update($updateData);
            }

            // 3. Create new session if still none found
            if (!$session) {
                $session = QrScanSession::create([
                    'session_token'    => QrScanSession::generateToken($endpoint->location_id),
                    'endpoint_id'      => $endpoint->id,
                    'location_id'      => $endpoint->location_id,
                    'franchise_id'     => $endpoint->franchise_id,
                    'table_identifier' => $endpoint->identifier ?? $endpoint->display_name,
                    'device_fingerprint' => $deviceId,
                    'user_agent'       => $userAgent,
                    'ip_address'       => $ipAddress,
                    'expires_at'       => now()->addHours(24),
                    'last_activity_at' => now(),
                ]);
            }

            // Load active orders for this session
            $activeOrders = $session->menuOrders()
                ->whereIn('status', MenuOrder::ACTIVE_STATUSES)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($o) => $o->toSummary());

            // Load recently done orders (last 2 hours) so user can see completed
            $recentDoneOrders = $session->menuOrders()
                ->whereIn('status', ['delivered', 'completed'])
                ->where('updated_at', '>', now()->subHours(2))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($o) => $o->toSummary());

            return response()->json([
                'success' => true,
                'data' => [
                    'session_token'     => $session->session_token,
                    'session_id'        => $session->id,
                    'is_new_session'    => $session->scan_count <= 1,
                    'table_identifier'  => $session->table_identifier,
                    'cart_data'         => $session->cart_data ?? [],
                    'active_orders'     => $activeOrders,
                    'recent_orders'     => $recentDoneOrders,
                    'has_active_orders' => $activeOrders->isNotEmpty(),
                    'expires_at'        => $session->expires_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('MenuSession init failed', ['code' => $code, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Session error'], 500);
        }
    }

    /**
     * Persist cart to database (debounced from frontend).
     * PUT /api/menu-session/{token}/cart
     */
    public function saveCart(Request $request, string $token): JsonResponse
    {
        try {
            $session = $this->getValidSession($token);
            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Session expired'], 401);
            }

            $cartData = $request->input('cart', []);
            $session->update([
                'cart_data'         => $cartData,
                'last_activity_at'  => now(),
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('MenuSession saveCart failed', ['token' => $token, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to save cart'], 500);
        }
    }

    /**
     * Place a new order from the current cart.
     * POST /api/menu-session/{token}/orders
     */
    public function placeOrder(Request $request, string $token): JsonResponse
    {
        try {
            $session = $this->getValidSession($token);
            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Session expired'], 401);
            }

            $items = $request->input('items', []);
            if (empty($items)) {
                return response()->json(['success' => false, 'message' => 'Cart is empty'], 422);
            }

            $total    = collect($items)->sum(fn($i) => ($i['unit_price'] ?? $i['finalPrice'] ?? $i['price'] ?? 0) * ($i['quantity'] ?? 1));
            $notes    = $request->input('notes', '');
            $currency = $request->input('currency', 'LKR');

            $order = MenuOrder::create([
                'order_number'     => MenuOrder::generateOrderNumber(),
                'session_id'       => $session->id,
                'location_id'      => $session->location_id,
                'franchise_id'     => $session->franchise_id,
                'items'            => $items,
                'subtotal'         => $total,
                'total'            => $total,
                'currency'         => $currency,
                'status'           => 'pending',
                'notes'            => $notes,
                'table_identifier' => $session->table_identifier,
                'confirmed_at'     => now(),
            ]);

            // Broadcast to POS + send Web Push to staff
            try {
                broadcast(new OrderPlaced($order));
                (new OrderPlaced($order))->sendWebPush();
            } catch (\Exception $broadcastEx) {
                Log::warning('OrderPlaced broadcast failed', ['error' => $broadcastEx->getMessage()]);
            }

            // Clear cart after ordering, update session stats
            $session->update([
                'cart_data'         => [],
                'has_ordered'       => true,
                'order_count'       => $session->order_count + 1,
                'total_spent'       => $session->total_spent + $total,
                'last_activity_at'  => now(),
                'first_order_at'    => $session->first_order_at ?? now(),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $order->toSummary(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('MenuSession placeOrder failed', ['token' => $token, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to place order'], 500);
        }
    }

    /**
     * Get current session status with all orders.
     * GET /api/menu-session/{token}/status
     */
    public function status(string $token): JsonResponse
    {
        try {
            $session = $this->getValidSession($token);
            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Session expired'], 401);
            }

            $allOrders = $session->menuOrders()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($o) => $o->toSummary());

            $activeOrders = $allOrders->filter(fn($o) => $o['is_active']);
            $doneOrders   = $allOrders->reject(fn($o) => $o['is_active']);

            return response()->json([
                'success' => true,
                'data'    => [
                    'session_token'    => $session->session_token,
                    'table_identifier' => $session->table_identifier,
                    'cart_data'        => $session->cart_data ?? [],
                    'active_orders'    => $activeOrders->values(),
                    'done_orders'      => $doneOrders->values(),
                    'has_active_orders'=> $activeOrders->isNotEmpty(),
                    'expires_at'       => $session->expires_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('MenuSession status failed', ['token' => $token, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch status'], 500);
        }
    }

    /**
     * Cancel a specific order (only if still pending).
     * DELETE /api/menu-session/{token}/orders/{orderId}
     */
    public function cancelOrder(string $token, int $orderId): JsonResponse
    {
        try {
            $session = $this->getValidSession($token);
            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Session expired'], 401);
            }

            $order = $session->menuOrders()->find($orderId);
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            if ($order->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel — order is already ' . $order->status,
                ], 422);
            }

            $order->updateStatus('cancelled');

            return response()->json(['success' => true, 'data' => $order->toSummary()]);

        } catch (\Exception $e) {
            Log::error('MenuSession cancelOrder failed', ['token' => $token, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel order'], 500);
        }
    }

    // ---------- Private Helpers ----------

    private function getValidSession(string $token): ?QrScanSession
    {
        return QrScanSession::where('session_token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }
}
