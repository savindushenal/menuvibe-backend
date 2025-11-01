<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete all existing menu items
        DB::table('menu_items')->truncate();

        // Drop the existing foreign key constraint
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        // Make category_id NOT NULL and recreate the foreign key with cascade
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->foreign('category_id')
                ->references('id')
                ->on('menu_categories')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Make category_id nullable again
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->change();
        });
    }
};
