<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FIX: Consolidate franchise_branches into locations
     * 
     * Current state:
     * - locations: Already has branch_code, is_paid, activated_at, deactivated_at ✓
     * - franchise_invitations: Already uses location_id ✓
     * - franchise_accounts: Already uses location_id ✓
     * - menu_sync_logs: Has BOTH branch_id and location_id (drop branch_id)
     * - branch_offer_overrides: Has branch_id (rename to location_id)
     * - branch_menu_overrides: Has branch_id (rename to location_id)
     * - franchise_branches: Still exists (DROP IT)
     */
    public function up(): void
    {
        // Fix menu_sync_logs - drop branch_id if both columns exist
        if (Schema::hasColumn('menu_sync_logs', 'branch_id') && Schema::hasColumn('menu_sync_logs', 'location_id')) {
            // Ensure location_id is populated from branch_id if needed
            DB::statement('
                UPDATE menu_sync_logs 
                SET location_id = branch_id 
                WHERE location_id IS NULL AND branch_id IS NOT NULL
            ');
            
            // Drop any foreign key on branch_id
            try {
                Schema::table('menu_sync_logs', function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            // Drop branch_id column
            Schema::table('menu_sync_logs', function (Blueprint $table) {
                $table->dropColumn('branch_id');
            });
        }

        // Fix branch_offer_overrides - rename branch_id to location_id
        if (Schema::hasTable('branch_offer_overrides') && Schema::hasColumn('branch_offer_overrides', 'branch_id')) {
            // Drop any foreign key on branch_id
            try {
                Schema::table('branch_offer_overrides', function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            // Rename if location_id doesn't exist
            if (!Schema::hasColumn('branch_offer_overrides', 'location_id')) {
                Schema::table('branch_offer_overrides', function (Blueprint $table) {
                    $table->renameColumn('branch_id', 'location_id');
                });
            } else {
                // Or drop if it already does
                Schema::table('branch_offer_overrides', function (Blueprint $table) {
                    $table->dropColumn('branch_id');
                });
            }
            
            // Add foreign key
            Schema::table('branch_offer_overrides', function (Blueprint $table) {
                if (!$this->foreignKeyExists('branch_offer_overrides', 'branch_offer_overrides_location_id_foreign')) {
                    $table->foreign('location_id')
                          ->references('id')
                          ->on('locations')
                          ->onDelete('cascade');
                }
            });
        }

        // Fix branch_menu_overrides - rename branch_id to location_id
        if (Schema::hasTable('branch_menu_overrides') && Schema::hasColumn('branch_menu_overrides', 'branch_id')) {
            // Drop any foreign key on branch_id
            try {
                Schema::table('branch_menu_overrides', function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            
            // Rename if location_id doesn't exist
            if (!Schema::hasColumn('branch_menu_overrides', 'location_id')) {
                Schema::table('branch_menu_overrides', function (Blueprint $table) {
                    $table->renameColumn('branch_id', 'location_id');
                });
            } else {
                // Or drop if it already does
                Schema::table('branch_menu_overrides', function (Blueprint $table) {
                    $table->dropColumn('branch_id');
                });
            }
            
            // Add foreign key
            Schema::table('branch_menu_overrides', function (Blueprint $table) {
                if (!$this->foreignKeyExists('branch_menu_overrides', 'branch_menu_overrides_location_id_foreign')) {
                    $table->foreign('location_id')
                          ->references('id')
                          ->on('locations')
                          ->onDelete('cascade');
                }
            });
        }

        // Finally, drop the franchise_branches table
        if (Schema::hasTable('franchise_branches')) {
            Schema::dropIfExists('franchise_branches');
        }
    }

    /**
     * Helper to check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $name): bool
    {
        $conn = Schema::getConnection()->getDoctrineSchemaManager();
        $foreignKeys = $conn->listTableForeignKeys($table);
        
        foreach ($foreignKeys as $key) {
            if ($key->getName() === $name) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Reverse the migration (not recommended - data loss!)
     */
    public function down(): void
    {
        // Cannot safely rollback - would need to recreate franchise_branches table
        // and split data back out from locations table
        throw new \Exception('Cannot rollback this migration - it removes duplicate data structures');
    }
};
