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
            $table->foreignId('category_id')->nullable()->after('menu_id')->constrained('menu_categories')->onDelete('set null');
            $table->string('card_color', 7)->nullable()->after('currency'); // Individual item card background color
            $table->string('text_color', 7)->nullable()->after('card_color'); // Individual item text color
            $table->string('heading_color', 7)->nullable()->after('text_color'); // Individual item heading/name color
            
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'card_color', 'text_color', 'heading_color']);
        });
    }
};
