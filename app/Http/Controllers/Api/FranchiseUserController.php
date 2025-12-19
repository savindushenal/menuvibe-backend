<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseUser;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FranchiseUserController extends Controller
{
    /**
     * List all users in a franchise.
     */
    public function index(Request $request, int $franchiseId): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($franchiseId);
        
        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if user belongs to franchise
        $currentFranchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $user->id)
            ->first();

        if (!$currentFranchiseUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $franchiseUsers = FranchiseUser::where('franchise_id', $franchiseId)
            ->with('user:id,name,email')
            ->get()
            ->map(function($fu) {
                return [
                    'id' => $fu->id,
                    'user_id' => $fu->user_id,
                    'name' => $fu->user->name,
                    'email' => $fu->user->email,
                    'role' => $fu->role,
                    'role_display' => $fu->getRoleDisplayName(),
                    'permissions' => $fu->permissions,
                    'location_ids' => $fu->location_ids,
                    'is_active' => $fu->is_active,
                    'is_pending' => $fu->isPending(),
                    'invited_at' => $fu->invited_at,
                    'accepted_at' => $fu->accepted_at,
                    'created_at' => $fu->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $franchiseUsers,
        ]);
    }

    /**
     * Invite a user to the franchise.
     */
    public function invite(Request $request, int $franchiseId): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'role' => 'required|in:admin,manager,viewer',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $franchise = Franchise::find($franchiseId);
        
        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if current user can invite (must be admin or owner)
        $currentFranchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $user->id)
            ->first();

        if (!$currentFranchiseUser || !$currentFranchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins and owners can invite users',
            ], 403);
        }

        // Find or create the user
        $invitedUser = User::where('email', $request->email)->first();
        
        if (!$invitedUser) {
            // Create a placeholder user account
            $invitedUser = User::create([
                'email' => $request->email,
                'name' => explode('@', $request->email)[0],
                'password' => bcrypt(Str::random(32)), // Temporary password
            ]);
        }

        // Check if user is already in franchise
        $existingMembership = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $invitedUser->id)
            ->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this franchise',
            ], 400);
        }

        // Validate location_ids belong to this franchise
        if ($request->location_ids) {
            $validLocations = $franchise->locations()
                ->whereIn('id', $request->location_ids)
                ->pluck('id')
                ->toArray();
            
            if (count($validLocations) !== count($request->location_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some locations do not belong to this franchise',
                ], 400);
            }
        }

        // Create franchise user with pending status
        $franchiseUser = FranchiseUser::create([
            'franchise_id' => $franchiseId,
            'user_id' => $invitedUser->id,
            'role' => $request->role,
            'location_ids' => $request->location_ids,
            'invited_by' => $user->id,
        ]);

        // Send invitation email
        try {
            $emailService = app(EmailService::class);
            $frontendUrl = config('app.frontend_url', 'https://staging.app.menuvire.com');
            $invitationLink = $frontendUrl . '/franchise/' . $franchise->slug . '/join?token=' . $franchiseUser->invitation_token;
            
            $emailService->sendFranchiseInvitation(
                $invitedUser->email,
                $invitedUser->name,
                $franchise->name,
                $request->role,
                $user->name,
                $invitationLink
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send franchise invitation email', [
                'error' => $e->getMessage(),
                'user_email' => $invitedUser->email,
                'franchise' => $franchise->name,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User invited successfully',
            'data' => [
                'id' => $franchiseUser->id,
                'email' => $invitedUser->email,
                'role' => $franchiseUser->role,
                'invitation_token' => $franchiseUser->invitation_token,
            ],
        ], 201);
    }

    /**
     * Accept a franchise invitation.
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $franchiseUser = FranchiseUser::where('invitation_token', $request->token)
            ->whereNull('accepted_at')
            ->first();

        if (!$franchiseUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation',
            ], 400);
        }

        $franchiseUser->acceptInvitation();

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted successfully',
            'data' => [
                'franchise_id' => $franchiseUser->franchise_id,
                'franchise_name' => $franchiseUser->franchise->name,
                'role' => $franchiseUser->role,
            ],
        ]);
    }

    /**
     * Update a user's role or permissions in the franchise.
     */
    public function update(Request $request, int $franchiseId, int $userId): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'role' => 'sometimes|in:owner,admin,manager,viewer',
            'permissions' => 'nullable|array',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $franchise = Franchise::find($franchiseId);
        
        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if current user can update (must be admin or owner)
        $currentFranchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $user->id)
            ->first();

        if (!$currentFranchiseUser || !$currentFranchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins and owners can update user roles',
            ], 403);
        }

        $targetFranchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $userId)
            ->first();

        if (!$targetFranchiseUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in franchise',
            ], 404);
        }

        // Prevent demoting yourself if you're the only owner
        if ($userId === $user->id && $request->role && $request->role !== 'owner') {
            $ownerCount = FranchiseUser::where('franchise_id', $franchiseId)
                ->where('role', 'owner')
                ->count();
            
            if ($ownerCount <= 1 && $currentFranchiseUser->isOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove the last owner. Transfer ownership first.',
                ], 400);
            }
        }

        // Only owners can promote to owner
        if ($request->role === 'owner' && !$currentFranchiseUser->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Only owners can promote users to owner',
            ], 403);
        }

        $targetFranchiseUser->update($request->only([
            'role', 'permissions', 'location_ids', 'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $targetFranchiseUser->fresh()->load('user:id,name,email'),
        ]);
    }

    /**
     * Remove a user from the franchise.
     */
    public function remove(Request $request, int $franchiseId, int $userId): JsonResponse
    {
        $user = $request->user();
        
        $franchise = Franchise::find($franchiseId);
        
        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Check if current user can remove (must be admin or owner)
        $currentFranchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $user->id)
            ->first();

        if (!$currentFranchiseUser || !$currentFranchiseUser->isAdminOrAbove()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins and owners can remove users',
            ], 403);
        }

        $targetFranchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $userId)
            ->first();

        if (!$targetFranchiseUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in franchise',
            ], 404);
        }

        // Prevent removing the last owner
        if ($targetFranchiseUser->isOwner()) {
            $ownerCount = FranchiseUser::where('franchise_id', $franchiseId)
                ->where('role', 'owner')
                ->count();
            
            if ($ownerCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove the last owner. Transfer ownership first.',
                ], 400);
            }

            // Only owners can remove other owners
            if (!$currentFranchiseUser->isOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only owners can remove other owners',
                ], 403);
            }
        }

        $targetFranchiseUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'User removed from franchise',
        ]);
    }

    /**
     * Leave a franchise (self-removal).
     */
    public function leave(Request $request, int $franchiseId): JsonResponse
    {
        $user = $request->user();
        
        $franchiseUser = FranchiseUser::where('franchise_id', $franchiseId)
            ->where('user_id', $user->id)
            ->first();

        if (!$franchiseUser) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this franchise',
            ], 400);
        }

        // Prevent leaving if you're the last owner
        if ($franchiseUser->isOwner()) {
            $ownerCount = FranchiseUser::where('franchise_id', $franchiseId)
                ->where('role', 'owner')
                ->count();
            
            if ($ownerCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot leave as the last owner. Transfer ownership or delete the franchise.',
                ], 400);
            }
        }

        $franchiseUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'You have left the franchise',
        ]);
    }
}
