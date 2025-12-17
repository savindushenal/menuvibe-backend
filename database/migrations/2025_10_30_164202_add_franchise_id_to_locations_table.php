<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add franchise_id to locations table to link locations to franchises.
     * This is nullable to maintain backward compatibility with existing locations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Add franchise_id as nullable foreign key
            $table->foreignId('franchise_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained()
                  ->onDelete('set null');
            
            // Index for faster queries
            $table->index('franchise_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['franchise_id']);
            $table->dropColumn('franchise_id');
        });
    }
};
