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
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Basic Business Information
            $table->string('business_name');
            $table->string('business_type'); // restaurant, cafe, food_truck, etc.
            $table->text('description')->nullable();
            
            // Contact Information
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            
            // Address Information
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code');
            $table->string('country')->default('US');
            
            // Business Details
            $table->string('cuisine_type')->nullable(); // italian, chinese, mexican, etc.
            $table->integer('seating_capacity')->nullable();
            $table->json('operating_hours')->nullable(); // Store as JSON
            $table->json('services')->nullable(); // dine_in, takeout, delivery, etc.
            
            // Onboarding Status
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();
            
            // Additional Info
            $table->string('logo_url')->nullable();
            $table->json('social_media')->nullable(); // Store social media links as JSON
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id']);
            $table->index(['business_type']);
            $table->index(['onboarding_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
