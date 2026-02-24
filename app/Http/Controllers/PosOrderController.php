<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\FranchiseUser;
use App\Models\Location;
use App\Models\MenuOrder;
use App\Models\StaffPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class PosOrderController extends Controller
{
    /**
     * Authenticate staff from bearer token and verify they have access
     * to the requested location via FranchiseUser role/location_ids.
     */
    private function resolveLocation(Request $request, int|string $locationId): ?Location
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $pat = PersonalAccessToken::findToken($token);
        if (!$pat) return null;

        $user = $pat->tokenable;
        $request->setUserResolver(fn() => $user);

        $location = Location::find($locationId);
        if (!$location) return null;

        // Platform admins always have access
        if (in_array($user->role ?? '', ['super_admin', 'platform_admin', 'admin'])) {
            return $location;
        }

        // Check FranchiseUser membership for this location's franchise
        $fu = FranchiseUser::where('user_id', $user->id)
            ->where('franchise_id', $location->franchise_id)
            ->where('is_active', true)
            ->first();

        if (!$fu) return null;

        // Owner / admin can access ALL locations in their franchise
        if (in_array($fu->role, [FranchiseUser::ROLE_OWNER, FranchiseUser::ROLE_ADMIN])) {
            return $location;
        }

        // Manager / viewer must have this specific location in their location_ids
        $ids = $fu->location_ids ?? [];
        if (in_array((int) $locationId, array_map('intval', $ids))) {
            return $location;
        }

        return null;
    }

    /**
     * Return the list of POS-accessible locations for the authenticated user.
     * GET /api/pos/me/locations
     */
    public function myLocations(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $pat = PersonalAccessToken::findToken($token);
        if (!$pat) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = $pat->tokenable;

        // Platform admins — return nothing specific (they pick manually)
        $memberships = FranchiseUser::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $locations = collect();

        foreach ($memberships as $fu) {
            if (in_array($fu->role, [FranchiseUser::ROLE_OWNER, FranchiseUser::ROLE_ADMIN])) {
                // All locations in their franchise
                $locs = Location::where('franchise_id', $fu->franchise_id)->get();
                $locations = $locations->merge($locs);
            } else {
                // Only their assigned locations
                $ids = array_map('intval', $fu->location_ids ?? []);
                if (!empty($ids)) {
                    $locs = Location::whereIn('id', $ids)->get();
                    $locations = $locations->merge($locs);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $locations->unique('id')->values()->map(fn($l) => [
                'id'           => $l->id,
                'name'         => $l->name,
                'franchise_id' => $l->franchise_id,
                'address'      => $l->address ?? null,
            ]),
        ]);
    }

    /**
     * List active + recent orders for a location.
     * GET /api/pos/{locationId}/orders
     */
    public function index(Request $request, int $locationId): JsonResponse
    {
        $location = $this->resolveLocation($request, $locationId);
        if (!$location) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $activeOrders = MenuOrder::where('location_id', $locationId)
            ->whereIn('status', MenuOrder::ACTIVE_STATUSES)
            ->with('session')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($o) => $o->toSummary());

        $recentDone = MenuOrder::where('location_id', $locationId)
            ->whereIn('status', ['delivered', 'completed', 'cancelled'])
            ->where('updated_at', '>', now()->subHours(4))
            ->orderBy('updated_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn($o) => $o->toSummary());

        return response()->json([
            'success' => true,
            'data'    => [
                'active' => $activeOrders,
                'done'   => $recentDone,
            ],
        ]);
    }

    /**
     * Update an order's status.
     * PATCH /api/pos/{locationId}/orders/{orderId}/status
     */
    public function updateStatus(Request $request, int $locationId, int $orderId): JsonResponse
    {
        $location = $this->resolveLocation($request, $locationId);
        if (!$location) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $order = MenuOrder::where('id', $orderId)
            ->where('location_id', $locationId)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $allowed   = MenuOrder::STATUS_TRANSITIONS;
        $newStatus = $request->input('status');

        if (!isset($allowed[$order->status]) || !in_array($newStatus, $allowed[$order->status])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot transition from {$order->status} to {$newStatus}.",
                'allowed' => $allowed[$order->status] ?? [],
            ], 422);
        }

        $oldStatus = $order->status;
        $order->updateStatus($newStatus);

        // Broadcast status change to POS channel + customer order channel
        broadcast(new OrderStatusChanged($order, $oldStatus));

        return response()->json([
            'success' => true,
            'data'    => $order->toSummary(),
        ]);
    }

    /**
     * Register a Web Push subscription for a staff device.
     * POST /api/pos/{locationId}/subscribe
     */
    public function subscribe(Request $request, int $locationId): JsonResponse
    {
        $location = $this->resolveLocation($request, $locationId);
        if (!$location) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'subscription.endpoint'      => 'required|string',
            'subscription.keys.p256dh'   => 'required|string',
            'subscription.keys.auth'     => 'required|string',
        ]);

        $sub = $request->input('subscription');

        StaffPushSubscription::register([
            'location_id'  => $locationId,
            'franchise_id' => $location->franchise_id ?? null,
            'endpoint'     => $sub['endpoint'],
            'p256dh_key'   => $sub['keys']['p256dh'],
            'auth_key'     => $sub['keys']['auth'],
            'device_label' => $request->input('device_label'),
        ]);

        return response()->json(['success' => true, 'message' => 'Subscribed to push notifications']);
    }

    /**
     * Unsubscribe a device.
     * DELETE /api/pos/{locationId}/subscribe
     */
    public function unsubscribe(Request $request, int $locationId): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);
        StaffPushSubscription::where('endpoint', $request->input('endpoint'))->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Pusher auth for private channels (not needed for public pos.{id} channels,
     * but here for future private upgrades).
     */
    public function pusherAuth(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $pusher = new \Pusher\Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            ['cluster' => config('broadcasting.connections.pusher.options.cluster')]
        );

        $auth = $pusher->authorizeChannel($request->channel_name, $request->socket_id);
        return response()->json(json_decode($auth, true));
    }
}
