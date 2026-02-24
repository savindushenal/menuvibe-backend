<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // branch_id may still exist as NOT NULL on production if the rename
        // migration added location_id without dropping branch_id.
        // Make it nullable so inserts using only location_id don't fail.
        if (Schema::hasTable('branch_menu_overrides') && Schema::hasColumn('branch_menu_overrides', 'branch_id')) {
            try {
                Schema::table('branch_menu_overrides', function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                });
            } catch (\Exception $e) {
                // FK may not exist — continue
            }

            Schema::table('branch_menu_overrides', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe rollback
    }
};
