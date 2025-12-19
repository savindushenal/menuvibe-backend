<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\FranchisePricing;
use App\Models\FranchisePayment;
use App\Models\FranchiseBranch;
use App\Models\FranchiseInvitation;
use App\Models\FranchiseAccount;
use App\Models\Location;
use App\Models\User;
use App\Mail\FranchiseInvitationMail;
use App\Mail\FranchiseCredentialsMail;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminFranchiseOnboardingController extends Controller
{
    /**
     * Onboard a new franchise (alias for createFranchise)
     */
    public function onboard(Request $request)
    {
        return $this->createFranchise($request);
    }

    /**
     * Create a new franchise with pricing (Admin/Super Admin only)
     */
    public function createFranchise(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:franchises,name',
            'description' => 'nullable|string',
            'owner_email' => 'required|email',
            'owner_name' => 'required|string|max:255',
            'owner_phone' => 'nullable|string|max:20',
            
            // Pricing
            'pricing_type' => 'required|in:fixed_yearly,pay_as_you_go,custom',
            'yearly_price' => 'required_if:pricing_type,fixed_yearly|nullable|numeric|min:0',
            'per_branch_price' => 'required_if:pricing_type,pay_as_you_go|nullable|numeric|min:0',
            'initial_branches' => 'nullable|integer|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'billing_cycle' => 'nullable|in:monthly,quarterly,yearly',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'custom_terms' => 'nullable|string',
            
            // Options
            'send_credentials' => 'boolean',
            'create_owner_account' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            // Check if owner account exists or create new
            $owner = User::where('email', $request->owner_email)->first();
            $tempPassword = null;
            $isNewUser = false;

            if (!$owner && $request->create_owner_account !== false) {
                $tempPassword = Str::random(12);
                $isNewUser = true;
                
                $owner = User::create([
                    'name' => $request->owner_name,
                    'email' => $request->owner_email,
                    'phone' => $request->owner_phone,
                    'password' => Hash::make($tempPassword),
                    'role' => 'user',
                    'is_active' => true,
                ]);
            }

            // Create franchise
            $franchise = Franchise::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'is_active' => true,
            ]);

            // Attach owner to franchise through pivot table
            if ($owner) {
                $franchise->users()->attach($owner->id, [
                    'role' => 'owner',
                    'is_active' => true,
                    'accepted_at' => now(),
                ]);
            }

            // Create pricing
            $pricing = FranchisePricing::create([
                'franchise_id' => $franchise->id,
                'pricing_type' => $request->pricing_type,
                'yearly_price' => $request->yearly_price,
                'per_branch_price' => $request->per_branch_price,
                'initial_branches' => $request->initial_branches ?? 0,
                'setup_fee' => $request->setup_fee ?? 0,
                'billing_cycle' => $request->billing_cycle ?? 'monthly',
                'contract_start_date' => $request->contract_start_date,
                'contract_end_date' => $request->contract_end_date,
                'custom_terms' => $request->custom_terms,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            // Create setup fee payment if applicable
            if ($request->setup_fee > 0) {
                FranchisePayment::create([
                    'franchise_id' => $franchise->id,
                    'franchise_pricing_id' => $pricing->id,
                    'amount' => $request->setup_fee,
                    'payment_type' => 'setup',
                    'status' => 'pending',
                    'due_date' => now()->addDays(7),
                    'notes' => 'One-time setup fee',
                    'recorded_by' => $request->user()->id,
                ]);
            }

            // Create franchise account for owner
            if ($owner) {
                FranchiseAccount::create([
                    'franchise_id' => $franchise->id,
                    'user_id' => $owner->id,
                    'role' => FranchiseAccount::ROLE_FRANCHISE_OWNER,
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ]);
            }

            // Log activity
            if (method_exists($request->user(), 'logAdminActivity')) {
                $request->user()->logAdminActivity(
                    'franchise.created',
                    Franchise::class,
                    $franchise->id,
                    null,
                    $franchise->toArray()
                );
            }

            DB::commit();

            // Send credentials email if requested
            if ($isNewUser && $request->send_credentials && $tempPassword) {
                try {
                    $emailService = new EmailService();
                    $emailService->sendFranchiseCredentials(
                        $owner->email,
                        $owner->name,
                        $franchise->name,
                        $owner->email,
                        $tempPassword,
                        config('app.frontend_url') . '/login'
                    );
                } catch (\Exception $e) {
                    // Log but don't fail
                    \Log::error('Failed to send credentials email: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Franchise created successfully',
                'data' => [
                    'franchise' => $franchise->load(['owners', 'pricing']),
                    'owner_created' => $isNewUser,
                    'temp_password' => $isNewUser ? $tempPassword : null,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create franchise: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update franchise pricing
     */
    public function updatePricing(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'pricing_type' => 'required|in:fixed_yearly,pay_as_you_go,custom',
            'yearly_price' => 'nullable|numeric|min:0',
            'per_branch_price' => 'nullable|numeric|min:0',
            'initial_branches' => 'nullable|integer|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'billing_cycle' => 'nullable|in:monthly,quarterly,yearly',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'custom_terms' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Deactivate old pricing
        FranchisePricing::where('franchise_id', $franchiseId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Create new pricing
        $pricing = FranchisePricing::create([
            'franchise_id' => $franchiseId,
            'pricing_type' => $request->pricing_type,
            'yearly_price' => $request->yearly_price,
            'per_branch_price' => $request->per_branch_price,
            'initial_branches' => $request->initial_branches ?? 0,
            'setup_fee' => $request->setup_fee ?? 0,
            'billing_cycle' => $request->billing_cycle ?? 'monthly',
            'contract_start_date' => $request->contract_start_date,
            'contract_end_date' => $request->contract_end_date,
            'custom_terms' => $request->custom_terms,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pricing updated successfully',
            'data' => $pricing,
        ]);
    }

    /**
     * Add a branch to franchise
     */
    public function addBranch(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'branch_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create a unified Location that serves as both branch and location
        // Get owner from pivot table (franchise_users)
        $ownerId = $franchise->owner?->id ?? $request->user()->id;
        
        $branch = Location::create([
            'user_id' => $ownerId,
            'franchise_id' => $franchiseId,
            'name' => $request->branch_name,
            'branch_name' => $request->branch_name,
            'branch_code' => Location::generateBranchCode($franchiseId),
            'address_line_1' => $request->address ?? 'To be updated',
            'city' => $request->city ?? 'To be updated',
            'state' => 'To be updated',
            'postal_code' => 'To be updated',
            'country' => 'Sri Lanka',
            'phone' => $request->phone,
            'is_active' => true,
            'is_paid' => false,
            'is_default' => false,
            'activated_at' => now(),
            'added_by' => $request->user()->id,
        ]);

        // Create payment record for new branch
        $pricing = $franchise->activePricing;
        if ($pricing && $pricing->pricing_type === 'pay_as_you_go') {
            FranchisePayment::create([
                'franchise_id' => $franchiseId,
                'franchise_pricing_id' => $pricing->id,
                'amount' => $pricing->per_branch_price,
                'payment_type' => 'branch_addition',
                'status' => 'pending',
                'due_date' => now()->addDays(7),
                'branches_count' => 1,
                'notes' => "Branch addition: {$branch->branch_name} ({$branch->branch_code})",
                'recorded_by' => $request->user()->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Branch added successfully',
            'data' => $branch,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get franchise branches (now unified as Locations)
     */
    public function getBranches($franchiseId)
    {
        $branches = Location::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->with(['addedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    /**
     * Update branch (now unified as Location)
     */
    public function updateBranch(Request $request, $franchiseId, $branchId)
    {
        $branch = Location::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->findOrFail($branchId);

        $validator = Validator::make($request->all(), [
            'branch_name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'is_paid' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updateData = [];
        if ($request->has('branch_name')) {
            $updateData['branch_name'] = $request->branch_name;
            $updateData['name'] = $request->branch_name;
        }
        if ($request->has('address')) {
            $updateData['address_line_1'] = $request->address;
        }
        if ($request->has('city')) {
            $updateData['city'] = $request->city;
        }
        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone;
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->is_active;
        }
        if ($request->has('is_paid')) {
            $updateData['is_paid'] = $request->is_paid;
        }

        $branch->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch,
        ]);
    }

    /**
     * Delete branch (now unified as Location)
     */
    public function deleteBranch(Request $request, $franchiseId, $branchId)
    {
        $branch = Location::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->findOrFail($branchId);

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully',
        ]);
    }

    /**
     * Record a payment
     */
    public function recordPayment(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'payment_type' => 'required|in:setup,monthly,quarterly,yearly,branch_addition,custom',
            'status' => 'required|in:pending,paid,overdue,cancelled,refunded',
            'due_date' => 'required|date',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'branches_count' => 'nullable|integer|min:0',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payment = FranchisePayment::create([
            'franchise_id' => $franchiseId,
            'franchise_pricing_id' => $franchise->activePricing?->id,
            'amount' => $request->amount,
            'payment_type' => $request->payment_type,
            'status' => $request->status,
            'due_date' => $request->due_date,
            'paid_date' => $request->paid_date,
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'notes' => $request->notes,
            'branches_count' => $request->branches_count,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'recorded_by' => $request->user()->id,
        ]);

        // If paid, update branch (location) payment status
        if ($request->status === 'paid' && $request->branches_count) {
            Location::where('franchise_id', $franchiseId)
                ->whereNotNull('branch_code')
                ->where('is_paid', false)
                ->limit($request->branches_count)
                ->update(['is_paid' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => $payment,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get franchise payments
     */
    public function getPayments($franchiseId)
    {
        $payments = FranchisePayment::where('franchise_id', $franchiseId)
            ->with(['recorder:id,name', 'pricing'])
            ->orderBy('due_date', 'desc')
            ->get();

        // Calculate summary
        $summary = [
            'total_paid' => $payments->where('status', 'paid')->sum('amount'),
            'total_pending' => $payments->where('status', 'pending')->sum('amount'),
            'total_overdue' => $payments->where('status', 'overdue')->sum('amount'),
            'payments_count' => $payments->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $payments,
            'summary' => $summary,
        ]);
    }

    /**
     * Update payment status
     */
    public function updatePayment(Request $request, $franchiseId, $paymentId)
    {
        $payment = FranchisePayment::where('franchise_id', $franchiseId)
            ->findOrFail($paymentId);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,paid,overdue,cancelled,refunded',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldStatus = $payment->status;
        $payment->update($request->only([
            'status', 'paid_date', 'payment_method', 'transaction_reference', 'notes'
        ]));

        // If marking as paid, update branch (location) status
        if ($request->status === 'paid' && $oldStatus !== 'paid' && $payment->branches_count) {
            Location::where('franchise_id', $franchiseId)
                ->whereNotNull('branch_code')
                ->where('is_paid', false)
                ->limit($payment->branches_count)
                ->update(['is_paid' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment,
        ]);
    }

    /**
     * Create user account for franchise
     */
    public function createAccount(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:franchise_owner,franchise_manager,branch_manager,staff',
            'branch_id' => 'nullable|exists:franchise_branches,id',
            'send_credentials' => 'boolean',
            'custom_password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            // Generate or use custom password
            $password = $request->custom_password ?? Str::random(12);

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($password),
                'role' => 'user',
                'is_active' => true,
            ]);

            // Create franchise account
            $account = FranchiseAccount::create([
                'franchise_id' => $franchiseId,
                'user_id' => $user->id,
                'role' => $request->role,
                'branch_id' => $request->branch_id,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            // Create an invitation record for tracking
            $invitation = FranchiseInvitation::create([
                'franchise_id' => $franchiseId,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $request->role,
                'branch_id' => $request->branch_id,
                'token' => FranchiseInvitation::generateToken(),
                'status' => 'accepted', // Already accepted since account is created
                'expires_at' => now()->addDays(7),
                'accepted_at' => now(),
                'send_credentials' => $request->send_credentials ?? true,
                'invited_by' => $request->user()->id,
            ]);

            DB::commit();

            // Send credentials email
            if ($request->send_credentials !== false) {
                try {
                    $emailService = new EmailService();
                    $emailService->sendFranchiseCredentials(
                        $user->email,
                        $user->name,
                        $franchise->name,
                        $user->email,
                        $password,
                        config('app.frontend_url') . '/login'
                    );
                    \Log::info('Credentials email sent to: ' . $user->email);
                } catch (\Exception $e) {
                    \Log::error('Failed to send credentials email to ' . $user->email . ': ' . $e->getMessage());
                    \Log::error('Stack trace: ' . $e->getTraceAsString());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Account created and invitation sent successfully',
                'data' => [
                    'user' => $user,
                    'account' => $account,
                    'invitation' => $invitation,
                    'password' => $request->send_credentials === false ? $password : null,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get franchise accounts
     */
    public function getAccounts($franchiseId)
    {
        $accounts = FranchiseAccount::where('franchise_id', $franchiseId)
            ->with(['user:id,name,email,phone,is_active,last_login_at', 'branch:id,branch_name,branch_code'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Send invitation
     */
    public function sendInvitation(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'role' => 'required|in:franchise_owner,franchise_manager,branch_manager,staff',
            'branch_id' => 'nullable|exists:franchise_branches,id',
            'message' => 'nullable|string',
            'send_credentials' => 'boolean',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if user already exists
        $existingUser = User::where('email', $request->email)->first();
        
        // Check if already has account in this franchise
        if ($existingUser) {
            $existingAccount = FranchiseAccount::where('franchise_id', $franchiseId)
                ->where('user_id', $existingUser->id)
                ->exists();
            
            if ($existingAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has an account in this franchise',
                ], Response::HTTP_CONFLICT);
            }
        }

        // Check for pending invitation
        $pendingInvitation = FranchiseInvitation::where('franchise_id', $franchiseId)
            ->where('email', $request->email)
            ->pending()
            ->first();

        if ($pendingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'A pending invitation already exists for this email',
            ], Response::HTTP_CONFLICT);
        }

        $tempPassword = $request->send_credentials ? FranchiseInvitation::generateTempPassword() : null;

        $invitation = FranchiseInvitation::create([
            'franchise_id' => $franchiseId,
            'email' => $request->email,
            'name' => $request->name,
            'role' => $request->role,
            'branch_id' => $request->branch_id,
            'token' => FranchiseInvitation::generateToken(),
            'status' => 'pending',
            'expires_at' => now()->addDays($request->expires_in_days ?? 7),
            'message' => $request->message,
            'send_credentials' => $request->send_credentials ?? false,
            'temp_password' => $tempPassword ? Hash::make($tempPassword) : null,
            'invited_by' => $request->user()->id,
        ]);

        // Send invitation email
        try {
            $emailService = new EmailService();
            $acceptLink = config('app.frontend_url') . '/franchise/accept-invitation?token=' . $invitation->token;
            $emailService->sendFranchiseInvitation(
                $request->email,
                $request->name,
                $franchise->name,
                $request->role,
                $request->user()->name,
                $acceptLink
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send invitation email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully',
            'data' => $invitation,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get franchise invitations
     */
    public function getInvitations($franchiseId)
    {
        $invitations = FranchiseInvitation::where('franchise_id', $franchiseId)
            ->with(['branch:id,branch_name,branch_code', 'inviter:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    /**
     * Resend invitation
     */
    public function resendInvitation(Request $request, $franchiseId, $invitationId)
    {
        $invitation = FranchiseInvitation::where('franchise_id', $franchiseId)
            ->findOrFail($invitationId);

        $franchise = Franchise::find($franchiseId);

        // If invitation was already accepted, resend credentials with new password
        if ($invitation->status === 'accepted') {
            $user = User::where('email', $invitation->email)->first();
            if ($user) {
                $newPassword = Str::random(12);
                $user->update(['password' => Hash::make($newPassword)]);

                try {
                    $emailService = new EmailService();
                    $emailService->sendFranchiseCredentials(
                        $user->email,
                        $user->name,
                        $franchise->name,
                        $user->email,
                        $newPassword,
                        config('app.frontend_url') . '/login'
                    );
                } catch (\Exception $e) {
                    \Log::error('Failed to resend credentials email: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to send email',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Credentials resent successfully with new password',
                ]);
            }
        }

        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Can only resend pending invitations',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Generate new temp password if needed
        $tempPassword = $invitation->send_credentials ? FranchiseInvitation::generateTempPassword() : null;
        
        // Extend expiration and update temp password
        $invitation->update([
            'expires_at' => now()->addDays(7),
            'temp_password' => $tempPassword ? Hash::make($tempPassword) : $invitation->temp_password,
        ]);

        // Resend email
        try {
            $emailService = new EmailService();
            $acceptLink = config('app.frontend_url') . '/franchise/accept-invitation?token=' . $invitation->token;
            $emailService->sendFranchiseInvitation(
                $invitation->email,
                $invitation->name,
                $franchise->name,
                $invitation->role,
                $request->user()->name,
                $acceptLink
            );
        } catch (\Exception $e) {
            \Log::error('Failed to resend invitation email: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation resent successfully',
        ]);
    }

    /**
     * Cancel invitation
     */
    public function cancelInvitation(Request $request, $franchiseId, $invitationId)
    {
        $invitation = FranchiseInvitation::where('franchise_id', $franchiseId)
            ->findOrFail($invitationId);

        $invitation->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Invitation cancelled',
        ]);
    }

    /**
     * Get franchise full details for admin
     */
    public function getFranchiseDetails($franchiseId)
    {
        $franchise = Franchise::with([
            'owners:id,name,email',
            'pricing' => fn($q) => $q->where('is_active', true),
        ])->findOrFail($franchiseId);

        // Branches are now unified as Locations
        $branches = Location::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->get();
        $accounts = FranchiseAccount::where('franchise_id', $franchiseId)
            ->with('user:id,name,email')
            ->get();
        $payments = FranchisePayment::where('franchise_id', $franchiseId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Get all invitations (for display in Invitations tab)
        $allInvitations = FranchiseInvitation::where('franchise_id', $franchiseId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get pending invitations count for stats
        $pendingInvitations = $allInvitations->where('status', 'pending');

        $stats = [
            'total_branches' => $branches->count(),
            'active_branches' => $branches->where('is_active', true)->count(),
            'paid_branches' => $branches->where('is_paid', true)->count(),
            'total_accounts' => $accounts->count(),
            'pending_invitations' => $pendingInvitations->count(),
            'total_paid' => FranchisePayment::where('franchise_id', $franchiseId)
                ->where('status', 'paid')->sum('amount'),
            'total_pending' => FranchisePayment::where('franchise_id', $franchiseId)
                ->where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => $franchise,
                'branches' => $branches,
                'accounts' => $accounts,
                'recent_payments' => $payments,
                'pending_invitations' => $pendingInvitations->values(),
                'all_invitations' => $allInvitations,
                'stats' => $stats,
            ],
        ]);
    }
}
