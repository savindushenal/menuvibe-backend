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
        Schema::table('menu_template_items', function (Blueprint $table) {
            // Add customizations field after variations
            $table->json('customizations')->nullable()->after('variations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_template_items', function (Blueprint $table) {
            $table->dropColumn('customizations');
        });
    }
};
