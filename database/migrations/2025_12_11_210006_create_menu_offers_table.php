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
        Schema::create('menu_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('menu_templates')->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('offer_type', ['special', 'instant', 'seasonal', 'combo', 'happy_hour', 'loyalty', 'first_order']);
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('badge_text', 50)->nullable();
            $table->string('badge_color', 20)->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'bogo', 'bundle_price', 'free_item']);
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('bundle_price', 10, 2)->nullable();
            $table->decimal('minimum_order', 10, 2)->nullable();
            $table->json('applicable_items')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->json('applicable_endpoints')->nullable();
            $table->boolean('apply_to_all')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('available_days')->nullable();
            $table->string('available_time_start', 10)->nullable();
            $table->string('available_time_end', 10)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('terms_conditions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index(['template_id', 'is_active']);
            $table->index(['offer_type', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_offers');
    }
};
