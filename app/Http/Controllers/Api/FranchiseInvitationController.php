<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseAccount;
use App\Models\FranchiseInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class FranchiseInvitationController extends Controller
{
    /**
     * Validate a franchise invitation.
     */
    public function validateInvitation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'nullable|string',
            'email' => 'required_without:token|email',
            'token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find the invitation by token or email
        $query = FranchiseInvitation::where('status', FranchiseInvitation::STATUS_PENDING);

        if ($request->token) {
            $query->where('token', $request->token);
        } elseif ($request->email) {
            $query->where('email', $request->email);
            // If slug is provided, also filter by franchise
            if ($request->slug) {
                $franchise = Franchise::where('slug', $request->slug)->first();
                if ($franchise) {
                    $query->where('franchise_id', $franchise->id);
                }
            }
        }

        $invitation = $query->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation. This invitation may have already been accepted.',
            ], 400);
        }

        // Check if invitation is expired
        if ($invitation->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired. Please contact the franchise owner for a new invitation.',
            ], 400);
        }

        // Get the franchise
        $franchise = $invitation->franchise;
        $inviter = $invitation->inviter;

        // Check if user already exists
        $existingUser = User::where('email', $invitation->email)->first();
        $isExistingUser = $existingUser && ($existingUser->email_verified_at !== null || $existingUser->google_id !== null);

        return response()->json([
            'success' => true,
            'message' => 'Invitation is valid',
            'data' => [
                'franchise_name' => $franchise->name,
                'franchise_slug' => $franchise->slug,
                'role' => $invitation->role,
                'inviter_name' => $inviter ? $inviter->name : 'The franchise owner',
                'email' => $invitation->email,
                'name' => $invitation->name,
                'is_existing_user' => $isExistingUser,
            ],
        ]);
    }

    /**
     * Accept a franchise invitation.
     */
    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'nullable|string',
            'email' => 'required_without:token|email',
            'token' => 'nullable|string',
            'password' => 'nullable|string|min:8',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find the invitation by token or email
        $query = FranchiseInvitation::where('status', FranchiseInvitation::STATUS_PENDING);

        if ($request->token) {
            $query->where('token', $request->token);
        } elseif ($request->email) {
            $query->where('email', $request->email);
            // If slug is provided, also filter by franchise
            if ($request->slug) {
                $franchise = Franchise::where('slug', $request->slug)->first();
                if ($franchise) {
                    $query->where('franchise_id', $franchise->id);
                }
            }
        }

        $invitation = $query->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation',
            ], 400);
        }

        // Check if invitation is expired
        if ($invitation->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired',
            ], 400);
        }

        $franchise = $invitation->franchise;

        try {
            DB::beginTransaction();

            // Check if user already exists
            $user = User::where('email', $invitation->email)->first();
            $isExistingUser = $user && ($user->email_verified_at !== null || $user->google_id !== null);

            if (!$user) {
                // New user - require password
                if (!$request->password) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password is required for new users',
                    ], 422);
                }

                // Create the user
                $user = User::create([
                    'name' => $request->name ?? $invitation->name ?? 'Team Member',
                    'email' => $invitation->email,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => now(),
                    'role' => 'user',
                    'is_active' => true,
                ]);
            } elseif (!$isExistingUser) {
                // Existing user but not verified - require password
                if (!$request->password) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password is required for new users',
                    ], 422);
                }

                // Update user with real password and mark as verified
                $user->update([
                    'name' => $request->name ?? $invitation->name ?? $user->name,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => now(),
                ]);
            }

            // Create franchise account for the user
            $account = FranchiseAccount::create([
                'franchise_id' => $franchise->id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'location_id' => $invitation->location_id,
                'is_active' => true,
                'accepted_at' => now(),
                'created_by' => $invitation->invited_by,
            ]);

            // Accept the invitation
            $invitation->accept();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted successfully. You can now log in.',
                'data' => [
                    'franchise_id' => $franchise->id,
                    'franchise_name' => $franchise->name,
                    'franchise_slug' => $franchise->slug,
                    'role' => $account->role,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
