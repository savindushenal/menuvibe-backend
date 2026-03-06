<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure branch_id is nullable in branch_menu_overrides.
     *
     * Previous migrations (2026-02-24) may not have run on production.
     * This idempotent migration uses raw SQL so it works without doctrine/dbal,
     * and is safe to run even if branch_id is already nullable.
     */
    public function up(): void
    {
        if (!Schema::hasTable('branch_menu_overrides')) {
            return;
        }

        if (!Schema::hasColumn('branch_menu_overrides', 'branch_id')) {
            return; // column doesn't exist, nothing to do
        }

        // Drop FK constraint if one exists (ignore errors)
        try {
            DB::statement('ALTER TABLE branch_menu_overrides DROP FOREIGN KEY branch_menu_overrides_branch_id_foreign');
        } catch (\Exception $e) {}

        // Make nullable — raw SQL, no doctrine/dbal required
        DB::statement('ALTER TABLE branch_menu_overrides MODIFY COLUMN branch_id BIGINT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Intentionally empty — reverting to NOT NULL is unsafe
    }
};
