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
        Schema::table('branch_menu_overrides', function (Blueprint $table) {
            if (!Schema::hasColumn('branch_menu_overrides', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_menu_overrides', function (Blueprint $table) {
            $table->dropColumn('updated_by');
        });
    }
};
