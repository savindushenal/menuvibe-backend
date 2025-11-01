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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('allergens')->nullable(); // Store allergen info as JSON
            $table->json('dietary_info')->nullable(); // vegan, vegetarian, gluten-free, etc.
            $table->integer('preparation_time')->nullable(); // in minutes
            $table->boolean('is_spicy')->default(false);
            $table->integer('spice_level')->nullable(); // 1-5 scale
            $table->json('variations')->nullable(); // size options, add-ons, etc.
            $table->timestamps();
            
            // Indexes
            $table->index(['menu_id', 'is_available']);
            $table->index(['menu_id', 'sort_order']);
            $table->index(['is_featured']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
