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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Notification preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('weekly_reports')->default(false);
            $table->boolean('marketing_emails')->default(false);
            $table->boolean('order_notifications')->default(true);
            $table->boolean('inventory_alerts')->default(true);
            $table->boolean('team_updates')->default(true);
            
            // Security settings
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->boolean('login_notifications')->default(true);
            $table->boolean('suspicious_activity_alerts')->default(true);
            
            // Privacy settings
            $table->boolean('profile_public')->default(false);
            $table->boolean('analytics_tracking')->default(true);
            $table->boolean('data_collection')->default(true);
            
            // Display preferences
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i');
            $table->string('language')->default('en');
            $table->string('currency')->default('USD');
            
            // Business settings
            $table->json('working_hours')->nullable(); // Store as JSON
            $table->boolean('auto_accept_orders')->default(false);
            $table->integer('order_preparation_time')->default(30); // minutes
            $table->boolean('weekend_mode')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
