<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates menu_schedules table to support time-based menu availability
     * and smart menu selection logic for restaurants with multiple menus
     * (e.g., lunch menu, dinner menu, ala carte menu).
     */
    public function up(): void
    {
        Schema::create('menu_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('franchise_id')->constrained('franchises')->onDelete('cascade');
            
            // Time window
            $table->time('start_time'); // e.g., "12:00:00"
            $table->time('end_time');   // e.g., "18:00:00"
            
            // Days of week (JSON array of 0-6, where 0=Sunday)
            // Example: [1, 2, 3, 4, 5] for Mon-Fri
            $table->json('days')->default(json_encode([0, 1, 2, 3, 4, 5, 6])); // All days by default
            
            // Optional date range for seasonal menus
            $table->date('start_date')->nullable(); // e.g., "2025-12-01" for holiday menu
            $table->date('end_date')->nullable();
            
            // Priority for conflict resolution when multiple menus are active at same time
            // Higher priority wins (if allowed), otherwise show corridor
            $table->integer('priority')->default(0);
            
            // Timezone for this schedule (e.g., "Asia/Colombo")
            $table->string('timezone')->default('UTC');
            
            // Whether to allow overlapping menus at the same time (if false, corridor shows options)
            $table->boolean('allow_overlap')->default(false);
            
            // Active flag
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes for fast lookups
            $table->index(['location_id', 'is_active']);
            $table->index(['franchise_id', 'is_active']);
            $table->index(['menu_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
            $table->index(['priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_schedules');
    }
};
