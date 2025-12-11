<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates Master Menu system for franchise-wide menu management
     */
    public function up(): void
    {
        // Master Menus - Central menu template for franchise
        Schema::create('master_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable(); // Menu cover image
            $table->string('currency', 10)->default('LKR');
            $table->json('availability_hours')->nullable(); // Operating hours
            $table->json('settings')->nullable(); // Additional settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default menu for franchise
            $table->integer('sort_order')->default(0);
            $table->timestamp('last_synced_at')->nullable(); // Last time pushed to branches
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['franchise_id', 'slug']);
        });

        // Master Menu Categories
        Schema::create('master_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_menu_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable(); // Category image
            $table->string('icon')->nullable(); // Icon name/class
            $table->string('background_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Master Menu Items
        Schema::create('master_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_menu_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('master_menu_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable(); // Original price for showing discounts
            $table->string('currency', 10)->default('LKR');
            $table->string('image_url')->nullable(); // Primary item image
            $table->json('gallery_images')->nullable(); // Additional images array
            $table->string('card_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->string('heading_color', 20)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('allergens')->nullable(); // ['gluten', 'dairy', 'nuts']
            $table->json('dietary_info')->nullable(); // ['vegan', 'vegetarian', 'halal']
            $table->integer('preparation_time')->nullable(); // in minutes
            $table->boolean('is_spicy')->default(false);
            $table->integer('spice_level')->nullable(); // 1-5
            $table->json('variations')->nullable(); // Size/flavor variations with prices
            $table->json('addons')->nullable(); // Add-on options
            $table->string('sku')->nullable(); // Stock keeping unit
            $table->integer('calories')->nullable();
            $table->timestamps();
        });

        // Master Menu Offers - Special/Instant/Seasonal promotions
        Schema::create('master_menu_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->onDelete('cascade');
            $table->foreignId('master_menu_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('offer_type', ['special', 'instant', 'seasonal', 'combo', 'happy_hour'])->default('special');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable(); // Offer banner image
            $table->string('badge_text')->nullable(); // e.g., "20% OFF", "NEW"
            $table->string('badge_color', 20)->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'bogo', 'bundle_price'])->default('percentage');
            $table->decimal('discount_value', 12, 2)->nullable(); // Discount amount or percentage
            $table->decimal('bundle_price', 12, 2)->nullable(); // Fixed price for bundle/combo
            $table->decimal('minimum_order', 12, 2)->nullable(); // Minimum order to apply
            $table->json('applicable_items')->nullable(); // Array of master_menu_item IDs
            $table->json('applicable_categories')->nullable(); // Array of category IDs
            $table->boolean('apply_to_all')->default(false); // Apply to entire menu
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('available_days')->nullable(); // ['monday', 'tuesday', ...] or null for all
            $table->string('available_time_start', 10)->nullable(); // e.g., '11:00'
            $table->string('available_time_end', 10)->nullable(); // e.g., '14:00'
            $table->integer('usage_limit')->nullable(); // Max total uses
            $table->integer('usage_count')->default(0); // Current usage count
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('terms_conditions')->nullable(); // Terms and conditions
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['franchise_id', 'offer_type', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });

        // Branch Menu Overrides - Per-branch price/availability customization
        Schema::create('branch_menu_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('franchise_branches')->onDelete('cascade');
            $table->foreignId('master_item_id')->constrained('master_menu_items')->onDelete('cascade');
            $table->decimal('price_override', 12, 2)->nullable(); // Custom price (null = use master)
            $table->boolean('is_available')->default(true); // Override availability
            $table->boolean('is_featured')->nullable(); // Override featured status
            $table->json('variation_prices')->nullable(); // Override variation prices
            $table->text('notes')->nullable(); // Internal notes
            $table->timestamps();
            
            $table->unique(['branch_id', 'master_item_id']);
        });

        // Branch Offer Overrides - Per-branch offer customization
        Schema::create('branch_offer_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('franchise_branches')->onDelete('cascade');
            $table->foreignId('master_offer_id')->constrained('master_menu_offers')->onDelete('cascade');
            $table->boolean('is_active')->default(true); // Enable/disable for this branch
            $table->decimal('discount_override', 12, 2)->nullable(); // Custom discount
            $table->timestamps();
            
            $table->unique(['branch_id', 'master_offer_id']);
        });

        // Menu Images Gallery - Centralized image storage
        Schema::create('menu_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('url');
            $table->string('thumbnail_url')->nullable();
            $table->string('mime_type', 50);
            $table->integer('file_size'); // in bytes
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->json('tags')->nullable(); // ['food', 'drink', 'dessert']
            $table->enum('type', ['item', 'category', 'menu', 'offer', 'gallery'])->default('item');
            $table->timestamps();
            
            $table->index(['franchise_id', 'type']);
        });

        // Menu Sync Log - Track when menus are pushed to branches
        Schema::create('menu_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_menu_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('franchise_branches')->onDelete('cascade');
            $table->enum('sync_type', ['full', 'partial', 'items_only', 'categories_only', 'offers_only']);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->integer('items_synced')->default(0);
            $table->integer('categories_synced')->default(0);
            $table->json('changes')->nullable(); // What was changed
            $table->text('error_message')->nullable();
            $table->foreignId('synced_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_sync_logs');
        Schema::dropIfExists('menu_images');
        Schema::dropIfExists('branch_offer_overrides');
        Schema::dropIfExists('branch_menu_overrides');
        Schema::dropIfExists('master_menu_offers');
        Schema::dropIfExists('master_menu_items');
        Schema::dropIfExists('master_menu_categories');
        Schema::dropIfExists('master_menus');
    }
};
