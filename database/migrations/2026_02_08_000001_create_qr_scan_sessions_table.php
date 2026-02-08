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
        Schema::create('qr_scan_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 100)->unique()->index();
            $table->foreignId('endpoint_id')->constrained('menu_endpoints')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('franchise_id')->nullable()->constrained('franchises')->onDelete('cascade');
            
            // Loyalty integration
            $table->string('loyalty_number')->nullable()->index();
            $table->enum('loyalty_provider', ['disabled', 'internal', 'external'])->default('disabled');
            $table->json('loyalty_data')->nullable(); // Store external API response
            
            // Device tracking
            $table->string('device_fingerprint')->nullable();
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();
            
            // Session metadata
            $table->json('metadata')->nullable(); // Custom data, preferences, etc.
            
            // Activity tracking
            $table->integer('scan_count')->default(1);
            $table->integer('order_count')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->timestamp('first_scan_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            // Conversion tracking
            $table->boolean('has_ordered')->default(false);
            $table->timestamp('first_order_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['location_id', 'created_at']);
            $table->index(['franchise_id', 'created_at']);
            $table->index(['loyalty_number', 'location_id']);
            $table->index('expires_at');
        });

        Schema::create('endpoint_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('qr_scan_sessions')->onDelete('cascade');
            $table->foreignId('from_endpoint_id')->nullable()->constrained('menu_endpoints')->onDelete('set null');
            $table->foreignId('to_endpoint_id')->constrained('menu_endpoints')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('change_type')->default('moved'); // moved, relocated
            $table->text('reason')->nullable();
            $table->timestamps();
            
            $table->index(['session_id', 'created_at']);
        });

        // Add session reference to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('qr_session_id')->nullable()->after('id')->constrained('qr_scan_sessions')->onDelete('set null');
            $table->index('qr_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['qr_session_id']);
            $table->dropColumn('qr_session_id');
        });
        
        Schema::dropIfExists('endpoint_changes');
        Schema::dropIfExists('qr_scan_sessions');
    }
};
