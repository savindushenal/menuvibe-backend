<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Franchise users pivot table - connects users to franchises with roles.
     * Supports multiple users per franchise with different permission levels.
     */
    public function up(): void
    {
        Schema::create('franchise_users', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Role in the franchise
            $table->enum('role', ['owner', 'admin', 'manager', 'viewer'])->default('viewer');
            /*
             * Role definitions:
             * - owner: Full control, can delete franchise, manage billing
             * - admin: Full control except billing and deletion
             * - manager: Can manage locations, menus, staff within assigned scope
             * - viewer: Read-only access to analytics and reports
             */
            
            // Granular permissions (JSON for flexibility)
            $table->json('permissions')->nullable();
            /*
             * permissions JSON structure:
             * {
             *   "locations": ["view", "create", "edit", "delete"],
             *   "menus": ["view", "create", "edit", "delete"],
             *   "staff": ["view", "invite", "remove"],
             *   "analytics": ["view", "export"],
             *   "billing": ["view", "manage"],
             *   "branding": ["view", "edit"],
             *   "settings": ["view", "edit"]
             * }
             */
            
            // Optional scope restriction (limit to specific locations)
            $table->json('location_ids')->nullable(); // If set, user can only access these locations
            
            // Invitation tracking
            $table->string('invited_by')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('invitation_token')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Ensure unique user per franchise
            $table->unique(['franchise_id', 'user_id']);
            
            // Indexes
            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('franchise_users');
    }
};
