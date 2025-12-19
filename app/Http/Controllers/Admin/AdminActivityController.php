<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    /**
     * List all activity logs
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = AdminActivityLog::with('user:id,name,email');

        // Filter by action
        if ($action = $request->get('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        // Filter by admin
        if ($adminId = $request->get('admin_id')) {
            $query->where('user_id', $adminId);
        }

        // Filter by target type
        if ($targetType = $request->get('target_type')) {
            $query->where('target_type', 'like', "%{$targetType}%");
        }

        // Filter by target id
        if ($targetId = $request->get('target_id')) {
            $query->where('target_id', $targetId);
        }

        // Date range filter
        if ($startDate = $request->get('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->get('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Search in notes
        if ($search = $request->get('search')) {
            $query->where('notes', 'like', "%{$search}%");
        }

        $query->latest();

        $perPage = min($request->get('per_page', 50), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get activity log details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $log = AdminActivityLog::with('user:id,name,email')->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity log not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * Get activity for a specific target
     */
    public function forTarget(Request $request, string $type, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $typeMap = [
            'user' => 'App\\Models\\User',
            'subscription' => 'App\\Models\\UserSubscription',
            'ticket' => 'App\\Models\\SupportTicket',
            'location' => 'App\\Models\\Location',
            'menu' => 'App\\Models\\Menu',
            'franchise' => 'App\\Models\\Franchise',
        ];

        $targetType = $typeMap[$type] ?? $type;

        $logs = AdminActivityLog::with('user:id,name,email')
            ->where('target_type', $targetType)
            ->where('target_id', $id)
            ->latest()
            ->take(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get list of unique actions
     */
    public function actions(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $actions = AdminActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }

    /**
     * Get list of admins who have performed actions
     */
    public function admins(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canAccessAdminPanel()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $adminIds = AdminActivityLog::select('user_id')
            ->distinct()
            ->pluck('user_id');

        $admins = User::whereIn('id', $adminIds)
            ->select('id', 'name', 'email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $admins,
        ]);
    }

    /**
     * Helper to get authenticated user
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        if (str_contains($token, '|')) {
            [$id, $plainTextToken] = explode('|', $token, 2);
            $hashedToken = hash('sha256', $plainTextToken);
        } else {
            $hashedToken = hash('sha256', $token);
        }

        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::where('token', $hashedToken)->first();

        if (!$tokenRecord) {
            return null;
        }

        return User::find($tokenRecord->tokenable_id);
    }
}
