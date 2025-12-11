<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /**
     * Get all platform settings
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $settings = PlatformSetting::all()->groupBy('group');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Get settings by group
     */
    public function showGroup(Request $request, string $group): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $settings = PlatformSetting::where('group', $group)->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update a setting
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can modify platform settings',
            ], 403);
        }

        $validated = $request->validate([
            'value' => 'required',
        ]);

        $setting = PlatformSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        $oldValue = $setting->value;
        
        PlatformSetting::set($key, $validated['value'], $admin);

        AdminActivityLog::log(
            $admin,
            'setting.updated',
            $setting,
            ['value' => $oldValue],
            ['value' => $validated['value']],
            "Updated setting: {$key}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'data' => PlatformSetting::where('key', $key)->first(),
        ]);
    }

    /**
     * Update multiple settings at once
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can modify platform settings',
            ], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        $updated = [];
        foreach ($validated['settings'] as $item) {
            $setting = PlatformSetting::where('key', $item['key'])->first();
            if ($setting) {
                $oldValue = $setting->value;
                PlatformSetting::set($item['key'], $item['value'], $admin);
                $updated[] = $item['key'];

                AdminActivityLog::log(
                    $admin,
                    'setting.updated',
                    $setting,
                    ['value' => $oldValue],
                    ['value' => $item['value']],
                    "Updated setting: {$item['key']}"
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' settings updated successfully',
            'data' => ['updated_keys' => $updated],
        ]);
    }

    /**
     * Get public settings (no auth required)
     */
    public function publicSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PlatformSetting::getPublic(),
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
