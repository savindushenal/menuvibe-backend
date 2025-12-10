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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Organization/Company name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->string('domain')->unique()->nullable(); // Custom domain (white-label)
            $table->unsignedBigInteger('owner_user_id'); // Primary owner
            $table->string('subscription_tier')->default('free'); // free, pro, enterprise
            $table->boolean('is_active')->default(true);
            
            // White-label customization
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->default('#FF5733');
            $table->string('secondary_color')->nullable();
            $table->text('custom_css')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            
            // Limits and quotas
            $table->text('subscription_limits')->nullable(); // JSON
            $table->text('feature_flags')->nullable(); // JSON
            
            // Metadata
            $table->text('metadata')->nullable(); // JSON for custom fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('domain');
            $table->index('owner_user_id');
            $table->index('is_active');
            $table->index('subscription_tier');

            // Foreign key
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Junction table for tenant users (multi-tenant access)
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member'); // owner, admin, member, viewer
            $table->text('permissions')->nullable(); // JSON array of specific permissions
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // Unique constraint
            $table->unique(['tenant_id', 'user_id']);

            // Indexes
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('role');

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Add tenant_id to existing tables for multi-tenancy
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('user_id');
            $table->index('tenant_id');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('location_id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });

        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }
};
