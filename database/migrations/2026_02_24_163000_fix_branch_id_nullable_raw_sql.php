<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw SQL ALTER to make branch_id nullable — no doctrine/dbal required.
     * Fixes: SQLSTATE[HY000]: Field 'branch_id' doesn't have a default value
     * when inserting into branch_menu_overrides using only location_id.
     */
    public function up(): void
    {
        if (Schema::hasTable('branch_menu_overrides') && Schema::hasColumn('branch_menu_overrides', 'branch_id')) {
            // Drop any FK on branch_id first
            try {
                Schema::table('branch_menu_overrides', function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                });
            } catch (\Exception $e) {
                // FK may already be gone
            }

            // Raw ALTER — works without doctrine/dbal
            DB::statement('ALTER TABLE branch_menu_overrides MODIFY COLUMN branch_id BIGINT UNSIGNED NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left empty — reverting to NOT NULL is unsafe
    }
};
