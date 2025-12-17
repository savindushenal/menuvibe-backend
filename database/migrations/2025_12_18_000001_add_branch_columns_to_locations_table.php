<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration unifies the FranchiseBranch and Location tables.
     * For franchises, locations now directly serve as branches, eliminating 
     * the redundant FranchiseBranch table.
     */
    public function up(): void
    {
        // Step 1: Add branch-related columns to locations table
        Schema::table('locations', function (Blueprint $table) {
            // Branch-specific fields (moved from franchise_branches)
            $table->string('branch_name')->nullable()->after('name');
            $table->string('branch_code')->nullable()->after('branch_name');
            $table->boolean('is_paid')->default(false)->after('is_active');
            $table->date('activated_at')->nullable()->after('is_paid');
            $table->date('deactivated_at')->nullable()->after('activated_at');
            $table->foreignId('added_by')->nullable()->after('deactivated_at')
                  ->constrained('users')->onDelete('set null');
            
            // Index for branch code lookup
            $table->index(['franchise_id', 'branch_code']);
        });

        // Step 2: Migrate data from franchise_branches to locations
        // First, update existing locations that are linked to branches
        $branches = DB::table('franchise_branches')->get();
        
        foreach ($branches as $branch) {
            if ($branch->location_id) {
                // Update existing location with branch data
                DB::table('locations')
                    ->where('id', $branch->location_id)
                    ->update([
                        'branch_name' => $branch->branch_name,
                        'branch_code' => $branch->branch_code,
                        'is_paid' => $branch->is_paid,
                        'activated_at' => $branch->activated_at,
                        'deactivated_at' => $branch->deactivated_at,
                        'added_by' => $branch->added_by,
                    ]);
            } else {
                // Create a new location for branches without one
                // Get the franchise owner from franchise_users pivot table
                $owner = DB::table('franchise_users')
                    ->where('franchise_id', $branch->franchise_id)
                    ->where('role', 'franchise_owner')
                    ->first();
                
                $userId = $owner ? $owner->user_id : ($branch->added_by ?? 1);
                
                DB::table('locations')->insert([
                    'user_id' => $userId,
                    'franchise_id' => $branch->franchise_id,
                    'name' => $branch->branch_name,
                    'branch_name' => $branch->branch_name,
                    'branch_code' => $branch->branch_code,
                    'address_line_1' => $branch->address ?? 'To be updated',
                    'city' => $branch->city ?? 'To be updated',
                    'state' => 'To be updated',
                    'postal_code' => 'To be updated',
                    'country' => 'Sri Lanka',
                    'phone' => $branch->phone,
                    'is_active' => $branch->is_active,
                    'is_paid' => $branch->is_paid,
                    'activated_at' => $branch->activated_at,
                    'deactivated_at' => $branch->deactivated_at,
                    'added_by' => $branch->added_by,
                    'is_default' => false,
                    'created_at' => $branch->created_at,
                    'updated_at' => now(),
                ]);
            }
        }

        // Step 3: Update franchise_accounts to reference locations directly
        // Add location_id column to franchise_accounts if it doesn't exist
        if (!Schema::hasColumn('franchise_accounts', 'location_id')) {
            Schema::table('franchise_accounts', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('branch_id')
                      ->constrained('locations')->onDelete('set null');
            });
        }

        // Migrate branch_id to location_id in franchise_accounts
        $accounts = DB::table('franchise_accounts')->whereNotNull('branch_id')->get();
        foreach ($accounts as $account) {
            $branch = DB::table('franchise_branches')->where('id', $account->branch_id)->first();
            if ($branch && $branch->location_id) {
                DB::table('franchise_accounts')
                    ->where('id', $account->id)
                    ->update(['location_id' => $branch->location_id]);
            }
        }

        // Step 4: Update franchise_invitations to reference locations directly
        if (!Schema::hasColumn('franchise_invitations', 'location_id')) {
            Schema::table('franchise_invitations', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('branch_id')
                      ->constrained('locations')->onDelete('cascade');
            });
        }

        // Migrate branch_id to location_id in franchise_invitations
        $invitations = DB::table('franchise_invitations')->whereNotNull('branch_id')->get();
        foreach ($invitations as $invitation) {
            $branch = DB::table('franchise_branches')->where('id', $invitation->branch_id)->first();
            if ($branch && $branch->location_id) {
                DB::table('franchise_invitations')
                    ->where('id', $invitation->id)
                    ->update(['location_id' => $branch->location_id]);
            }
        }

        // Step 5: Update branch_menu_overrides to reference locations directly
        if (!Schema::hasColumn('branch_menu_overrides', 'location_id')) {
            Schema::table('branch_menu_overrides', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('branch_id')
                      ->constrained('locations')->onDelete('cascade');
            });
        }

        // Migrate branch_id to location_id in branch_menu_overrides
        $overrides = DB::table('branch_menu_overrides')->whereNotNull('branch_id')->get();
        foreach ($overrides as $override) {
            $branch = DB::table('franchise_branches')->where('id', $override->branch_id)->first();
            if ($branch && $branch->location_id) {
                DB::table('branch_menu_overrides')
                    ->where('id', $override->id)
                    ->update(['location_id' => $branch->location_id]);
            }
        }

        // Step 6: Update menu_sync_logs to reference locations directly
        if (!Schema::hasColumn('menu_sync_logs', 'location_id')) {
            Schema::table('menu_sync_logs', function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('branch_id')
                      ->constrained('locations')->onDelete('cascade');
            });
        }

        // Migrate branch_id to location_id in menu_sync_logs
        $logs = DB::table('menu_sync_logs')->whereNotNull('branch_id')->get();
        foreach ($logs as $log) {
            $branch = DB::table('franchise_branches')->where('id', $log->branch_id)->first();
            if ($branch && $branch->location_id) {
                DB::table('menu_sync_logs')
                    ->where('id', $log->id)
                    ->update(['location_id' => $branch->location_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove location_id from related tables
        Schema::table('menu_sync_logs', function (Blueprint $table) {
            if (Schema::hasColumn('menu_sync_logs', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
        });

        Schema::table('branch_menu_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('branch_menu_overrides', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
        });

        Schema::table('franchise_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('franchise_invitations', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
        });

        Schema::table('franchise_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('franchise_accounts', 'location_id')) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            }
        });

        // Remove branch columns from locations
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['franchise_id', 'branch_code']);
            $table->dropForeign(['added_by']);
            $table->dropColumn([
                'branch_name',
                'branch_code',
                'is_paid',
                'activated_at',
                'deactivated_at',
                'added_by',
            ]);
        });
    }
};
