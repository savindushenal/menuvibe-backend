<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetByAdminMail;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * List all users with pagination and filtering
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

        $query = User::with(['businessProfile:id,user_id,business_name,onboarding_completed', 'activeSubscription.subscriptionPlan:id,name,slug']);

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Subscription filter
        if ($subscription = $request->get('subscription')) {
            $query->whereHas('activeSubscription.subscriptionPlan', function ($q) use ($subscription) {
                $q->where('slug', $subscription);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 20), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get a specific user's details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::with([
            'businessProfile',
            'locations',
            'subscriptions.subscriptionPlan',
            'activeSubscription.subscriptionPlan',
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Get user statistics
        $stats = [
            'total_locations' => $user->locations()->count(),
            'total_menus' => $user->getTotalMenusCount(),
            'total_menu_items' => $user->getTotalMenuItemsCount(),
        ];

        // Get activity logs for this user (as target)
        $activityLogs = AdminActivityLog::where('target_type', User::class)
            ->where('target_id', $id)
            ->with('user:id,name,email')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'stats' => $stats,
                'activity_logs' => $activityLogs,
            ],
        ]);
    }

    /**
     * Update a user's details
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Check if admin can manage this user
        if (!$admin->canManageUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify this user',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'role' => ['sometimes', Rule::in(['user', 'admin', 'super_admin'])],
        ]);

        // Only super admin can change roles
        if (isset($validated['role']) && !$admin->isSuperAdmin()) {
            unset($validated['role']);
        }

        // Prevent super admin from demoting themselves
        if (isset($validated['role']) && $user->id === $admin->id) {
            unset($validated['role']);
        }

        $oldValues = $user->only(array_keys($validated));
        $user->update($validated);

        // Log the action
        AdminActivityLog::log(
            $admin,
            'user.updated',
            $user,
            $oldValues,
            $validated,
            "Updated user: {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->fresh(['businessProfile', 'activeSubscription.subscriptionPlan']),
        ]);
    }

    /**
     * Suspend/unsuspend a user
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Check if admin can manage this user
        if (!$admin->canManageUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify this user',
            ], 403);
        }

        // Prevent self-suspension
        if ($user->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot suspend yourself',
            ], 400);
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => !$oldStatus]);

        // Revoke all tokens if suspended
        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        $action = $user->is_active ? 'user.reactivated' : 'user.suspended';
        AdminActivityLog::log(
            $admin,
            $action,
            $user,
            ['is_active' => $oldStatus],
            ['is_active' => $user->is_active],
            $user->is_active ? "Reactivated user: {$user->email}" : "Suspended user: {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'User reactivated successfully' : 'User suspended successfully',
            'data' => [
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Delete a user (soft delete or hard delete based on config)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can delete users',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Prevent self-deletion
        if ($user->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete yourself',
            ], 400);
        }

        // Prevent deleting other super admins
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin accounts',
            ], 400);
        }

        $userEmail = $user->email;
        
        // Log before deletion
        AdminActivityLog::log(
            $admin,
            'user.deleted',
            null,
            ['user_id' => $user->id, 'email' => $userEmail],
            null,
            "Deleted user: {$userEmail}"
        );

        // Revoke tokens and delete
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Create a new admin user (super admin only)
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can create admin users',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'super_admin', 'support_officer'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'email_verified_at' => now(),
            'created_by' => $admin->id,
        ]);

        AdminActivityLog::log(
            $admin,
            'admin.created',
            $user,
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            "Created {$user->role}: {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Admin user created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Reset a user's password
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if (!$admin->canManageUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify this user',
            ], 403);
        }

        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all existing tokens
        $user->tokens()->delete();

        AdminActivityLog::log(
            $admin,
            'user.password_reset',
            $user,
            null,
            null,
            "Reset password for: {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * Generate a random password and send it to the user via email
     */
    public function generateAndSendPassword(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if (!$admin->canManageUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot modify this user',
            ], 403);
        }

        // Generate a secure random password
        $password = $this->generateSecurePassword();

        // Update the user's password
        $user->update([
            'password' => Hash::make($password),
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        // Send the password via email
        try {
            \Log::info('=== PASSWORD EMAIL DEBUG START ===');
            \Log::info('Mail configuration', [
                'to' => $user->email,
                'mail_driver' => config('mail.default'),
                'mail_host' => config('mail.mailers.smtp.host'),
                'mail_port' => config('mail.mailers.smtp.port'),
                'mail_encryption' => config('mail.mailers.smtp.encryption'),
                'mail_username' => config('mail.mailers.smtp.username'),
                'mail_from_address' => config('mail.from.address'),
                'mail_from_name' => config('mail.from.name'),
                'queue_connection' => config('queue.default'),
            ]);
            
            // Check if OpenSSL is loaded
            \Log::info('OpenSSL loaded: ' . (extension_loaded('openssl') ? 'YES' : 'NO'));
            
            // Send email via Email API service
            \Log::info('Sending email via Email API...');
            $emailService = new EmailService();
            $result = $emailService->sendPasswordReset(
                $user->email,
                $user->name,
                config('app.frontend_url') . '/login',
                'This is your new password'
            );
            
            // If template doesn't exist, send a basic email with the password
            if (!$result['success']) {
                // Fallback: send credentials email with password
                $result = $emailService->send($user->email, 'password-reset', [
                    'user_name' => $user->name,
                    'platform_name' => 'MenuVire',
                    'new_password' => $password,
                    'login_link' => config('app.frontend_url') . '/login',
                ]);
            }
            
            $emailSent = $result['success'];
            if ($emailSent) {
                \Log::info('=== PASSWORD EMAIL SENT SUCCESSFULLY ===', ['to' => $user->email]);
            } else {
                throw new \Exception($result['message'] ?? 'Email API failed');
            }
        } catch (\Throwable $e) {
            $emailSent = false;
            \Log::error('=== PASSWORD EMAIL FAILED ===', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Log the activity
        AdminActivityLog::log(
            $admin,
            'user.password_generated',
            $user,
            null,
            ['email_sent' => $emailSent],
            "Generated and sent new password for: {$user->email}"
        );

        return response()->json([
            'success' => true,
            'message' => $emailSent 
                ? 'New password generated and sent to user\'s email' 
                : 'Password reset but email failed to send. Password: ' . $password,
            'data' => [
                'email_sent' => $emailSent,
                'user_email' => $user->email,
                // Only include password in response if email failed
                'password' => !$emailSent ? $password : null,
            ],
        ]);
    }

    /**
     * Generate a secure random password
     */
    private function generateSecurePassword(int $length = 12): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $special = '!@#$%^&*';
        
        // Ensure at least one of each type
        $password = $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Fill the rest with random characters
        $allChars = $lowercase . $uppercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Helper to get authenticated user with manual token check
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
