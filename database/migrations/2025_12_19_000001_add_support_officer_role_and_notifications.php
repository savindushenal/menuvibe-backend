<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration:
     * 1. Adds 'support_officer' role to users
     * 2. Adds online status tracking fields
     * 3. Creates notifications table
     * 4. Creates user_online_status table for real-time tracking
     */
    public function up(): void
    {
        // 1. Update users role enum to include support_officer
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'super_admin', 'support_officer') DEFAULT 'user'");
        
        // 2. Add online status tracking to users
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('is_active');
            $table->timestamp('last_seen_at')->nullable()->after('is_online');
            $table->integer('active_tickets_count')->default(0)->after('last_seen_at');
        });
        
        // 3. Create notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // ticket_assigned, ticket_new, ticket_reply, etc.
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable(); // URL to navigate to
            $table->json('data')->nullable(); // Additional data
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
        
        // 4. Create ticket assignment history table
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('assignment_type', ['manual', 'auto', 'self'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            
            $table->index(['ticket_id', 'assigned_at']);
            $table->index(['assigned_to', 'assigned_at']);
        });
        
        // 5. Add viewed tracking to support tickets for "who looked at this"
        Schema::create('ticket_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            
            $table->unique(['ticket_id', 'user_id']);
            $table->index(['ticket_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_views');
        Schema::dropIfExists('ticket_assignments');
        Schema::dropIfExists('notifications');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'last_seen_at', 'active_tickets_count']);
        });
        
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'super_admin') DEFAULT 'user'");
    }
};
