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
        Schema::create('endpoint_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('menu_endpoints')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('menu_template_items')->onDelete('cascade');
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('is_available')->nullable();
            $table->boolean('is_featured')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['endpoint_id', 'item_id']);
            $table->index(['endpoint_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endpoint_overrides');
    }
};
