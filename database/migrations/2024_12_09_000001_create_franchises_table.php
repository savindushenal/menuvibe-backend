<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Franchise table for white-label support.
     * Each franchise represents a brand/organization that can have multiple locations.
     */
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table) {
            $table->id();
            
            // Basic franchise info
            $table->string('name');
            $table->string('slug')->unique(); // Used for subdomain routing (e.g., subway.menuvibe.com)
            $table->string('custom_domain')->nullable()->unique(); // Custom domain (e.g., menu.subway.com)
            $table->text('description')->nullable();
            
            // Branding - Visual Identity
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('primary_color', 7)->default('#000000'); // Hex color
            $table->string('secondary_color', 7)->default('#FFFFFF');
            $table->string('accent_color', 7)->nullable();
            $table->text('custom_css')->nullable(); // Advanced customization
            
            // Contact info
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('website_url')->nullable();
            
            // Settings (JSON for flexibility)
            $table->json('settings')->nullable();
            /*
             * settings JSON structure:
             * {
             *   "features": { "ordering": true, "reservations": false },
             *   "analytics": { "google_analytics_id": "UA-xxx" },
             *   "seo": { "meta_title": "...", "meta_description": "..." },
             *   "social": { "facebook": "...", "instagram": "...", "twitter": "..." },
             *   "defaults": { "timezone": "America/New_York", "currency": "USD" }
             * }
             */
            
            // Domain verification
            $table->string('domain_verification_token')->nullable();
            $table->boolean('domain_verified')->default(false);
            $table->timestamp('domain_verified_at')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for fast lookups
            $table->index('slug');
            $table->index('custom_domain');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchises');
    }
};
