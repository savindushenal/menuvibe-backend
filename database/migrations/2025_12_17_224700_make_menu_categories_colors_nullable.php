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
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('background_color')->nullable()->change();
            $table->string('text_color')->nullable()->change();
            $table->string('heading_color')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('background_color')->nullable(false)->change();
            $table->string('text_color')->nullable(false)->change();
            $table->string('heading_color')->nullable(false)->change();
        });
    }
};
