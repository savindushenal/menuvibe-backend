<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds timezone field to locations table to support per-location timezone settings
     * for menu schedules and other time-dependent features.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Add timezone field after the address columns
            $table->string('timezone')->nullable()->default(null)->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
