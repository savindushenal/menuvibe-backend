<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Version Control System for Master Menu Sync
     */
    public function up(): void
    {
        // Master Menu versions tracking
        Schema::create('master_menu_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_menu_id')->constrained('master_menus')->onDelete('cascade');
            $table->integer('version_number');
            $table->string('change_type'); // 'item_added', 'item_removed', 'price_change', 'category_change', 'bulk_update'
            $table->string('change_summary', 500);
            $table->json('changes_data'); // Detailed diff of what changed
            $table->json('snapshot')->nullable(); // Full menu state at this version (for rollback)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['master_menu_id', 'version_number']);
            $table->index(['master_menu_id', 'created_at']);
        });

        // Add version tracking to master_menus
        Schema::table('master_menus', function (Blueprint $table) {
            $table->integer('current_version')->default(1)->after('is_active');
            $table->json('sync_policy')->nullable()->after('current_version');
            // sync_policy: { "auto_sync": ["new_items", "removed_items"], "manual_sync": ["prices", "descriptions"], "never_sync": [] }
        });

        // Branch menu sync status and local overrides
        Schema::create('branch_menu_sync', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('master_menu_id')->constrained('master_menus')->onDelete('cascade');
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->integer('synced_version')->default(0); // Last synced master version
            $table->enum('sync_mode', ['auto', 'manual', 'disabled'])->default('auto');
            $table->boolean('has_pending_updates')->default(false);
            $table->json('pending_changes')->nullable(); // Changes waiting to be applied
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            
            $table->unique(['location_id', 'master_menu_id']);
            $table->index('has_pending_updates');
        });

        // Local overrides for branch-specific pricing/availability
        Schema::create('branch_menu_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_menu_sync_id')->constrained('branch_menu_sync')->onDelete('cascade');
            $table->foreignId('master_menu_item_id')->constrained('master_menu_items')->onDelete('cascade');
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->onDelete('set null');
            
            // Override fields
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('is_available_override')->nullable();
            $table->string('name_override')->nullable();
            $table->text('description_override')->nullable();
            
            // Lock settings
            $table->boolean('price_locked')->default(false); // If true, master sync won't change price
            $table->boolean('availability_locked')->default(false);
            $table->boolean('fully_locked')->default(false); // Complete override, no sync
            
            // Tracking
            $table->string('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['branch_menu_sync_id', 'master_menu_item_id']);
        });

        // Sync history/audit log
        Schema::create('menu_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_menu_sync_id')->constrained('branch_menu_sync')->onDelete('cascade');
            $table->integer('from_version');
            $table->integer('to_version');
            $table->enum('sync_type', ['auto', 'manual', 'forced', 'rollback']);
            $table->enum('status', ['success', 'partial', 'failed', 'conflict']);
            $table->integer('items_added')->default(0);
            $table->integer('items_updated')->default(0);
            $table->integer('items_removed')->default(0);
            $table->integer('conflicts_skipped')->default(0);
            $table->json('conflict_details')->nullable();
            $table->json('changes_applied')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['branch_menu_sync_id', 'created_at']);
        });

        // Add sync tracking to menu_items
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('source_master_item_id')->nullable()->after('menu_category_id');
            $table->boolean('is_local_override')->default(false)->after('source_master_item_id');
            $table->integer('last_synced_version')->nullable()->after('is_local_override');
            
            $table->index('source_master_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['source_master_item_id']);
            $table->dropColumn(['source_master_item_id', 'is_local_override', 'last_synced_version']);
        });

        Schema::dropIfExists('menu_sync_logs');
        Schema::dropIfExists('branch_menu_overrides');
        Schema::dropIfExists('branch_menu_sync');
        
        Schema::table('master_menus', function (Blueprint $table) {
            $table->dropColumn(['current_version', 'sync_policy']);
        });
        
        Schema::dropIfExists('master_menu_versions');
    }
};
