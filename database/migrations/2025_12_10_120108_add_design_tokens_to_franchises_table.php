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
        Schema::table('franchises', function (Blueprint $table) {
            // Design tokens for template customization
            $table->json('design_tokens')->nullable()->after('settings');
            
            // Template selection (e.g., 'premium', 'classic', 'minimal')
            $table->string('template_type')->default('premium')->after('design_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropColumn(['design_tokens', 'template_type']);
        });
    }
};
