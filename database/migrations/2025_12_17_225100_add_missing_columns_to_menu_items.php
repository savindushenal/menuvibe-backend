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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            $table->json('gallery_images')->nullable()->after('image_url');
            $table->json('addons')->nullable()->after('variations');
            $table->string('sku')->nullable()->after('addons');
            $table->integer('calories')->nullable()->after('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'gallery_images', 'addons', 'sku', 'calories']);
        });
    }
};
