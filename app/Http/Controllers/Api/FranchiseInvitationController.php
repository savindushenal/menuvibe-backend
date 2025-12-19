<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchiseAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'slug' => 'required|string',
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

        // Find franchise by slug
        $franchise = Franchise::where('slug', $request->slug)->first();

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Find the invitation
        $query = FranchiseAccount::where('franchise_id', $franchise->id)
            ->whereNull('accepted_at');

        if ($request->token) {
            $query->where('invitation_token', $request->token);
        } elseif ($request->email) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', $request->email);
            });
        }

        $account = $query->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation. This invitation may have already been accepted.',
            ], 400);
        }

        // Check if invitation is expired
        if ($account->isInvitationExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired. Please contact the franchise owner for a new invitation.',
            ], 400);
        }

        $user = $account->user;
        $inviter = $account->creator;

        // Check if user has a real password (not a temporary one)
        // Users created via invitation have a random password hash
        $isExistingUser = $user->email_verified_at !== null || $user->google_id !== null;

        return response()->json([
            'success' => true,
            'message' => 'Invitation is valid',
            'data' => [
                'franchise_name' => $franchise->name,
                'role' => $account->role,
                'inviter_name' => $inviter ? $inviter->name : 'The franchise owner',
                'email' => $user->email,
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
            'slug' => 'required|string',
            'email' => 'required_without:token|email',
            'token' => 'nullable|string',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find franchise by slug
        $franchise = Franchise::where('slug', $request->slug)->first();

        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise not found',
            ], 404);
        }

        // Find the invitation
        $query = FranchiseAccount::where('franchise_id', $franchise->id)
            ->whereNull('accepted_at');

        if ($request->token) {
            $query->where('invitation_token', $request->token);
        } elseif ($request->email) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', $request->email);
            });
        }

        $account = $query->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation',
            ], 400);
        }

        // Check if invitation is expired
        if ($account->isInvitationExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired',
            ], 400);
        }

        $user = $account->user;

        // Check if this is a new user (needs password setup)
        $isExistingUser = $user->email_verified_at !== null || $user->google_id !== null;

        if (!$isExistingUser) {
            // New user - require password
            if (!$request->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is required for new users',
                ], 422);
            }

            // Update user with real password and mark as verified
            $user->update([
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);
        }

        // Accept the invitation
        $account->acceptInvitation();

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted successfully. You can now log in.',
            'data' => [
                'franchise_id' => $franchise->id,
                'franchise_name' => $franchise->name,
                'role' => $account->role,
            ],
        ]);
    }
}
