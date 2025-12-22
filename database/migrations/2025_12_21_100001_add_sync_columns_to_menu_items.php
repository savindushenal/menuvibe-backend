<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add missing sync columns to menu_items table
     */
    public function up(): void
    {
        // Add sync tracking to menu_items (missing from the original setup)
        if (!Schema::hasColumn('menu_items', 'source_master_item_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->unsignedBigInteger('source_master_item_id')->nullable()->after('category_id');
                $table->boolean('is_local_override')->default(false)->after('source_master_item_id');
                $table->integer('last_synced_version')->nullable()->after('is_local_override');
                
                $table->index('source_master_item_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'source_master_item_id')) {
                $table->dropIndex(['source_master_item_id']);
                $table->dropColumn(['source_master_item_id', 'is_local_override', 'last_synced_version']);
            }
        });
    }
};
