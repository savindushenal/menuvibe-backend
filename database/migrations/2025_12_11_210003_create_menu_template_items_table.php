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
        Schema::create('menu_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('menu_templates')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('menu_template_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->json('gallery_images')->nullable();
            $table->string('card_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->string('heading_color', 20)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('allergens')->nullable();
            $table->json('dietary_info')->nullable();
            $table->integer('preparation_time')->nullable();
            $table->integer('calories')->nullable();
            $table->boolean('is_spicy')->default(false);
            $table->tinyInteger('spice_level')->nullable();
            $table->json('variations')->nullable();
            $table->json('addons')->nullable();
            $table->string('sku', 100)->nullable();
            $table->timestamps();
            
            $table->index(['template_id', 'is_available']);
            $table->index(['template_id', 'is_featured']);
            $table->index(['category_id', 'sort_order']);
            $table->unique(['template_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_template_items');
    }
};
