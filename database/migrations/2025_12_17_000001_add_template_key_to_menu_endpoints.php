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
        Schema::table('menu_endpoints', function (Blueprint $table) {
            $table->string('template_key')->default('default')->after('template_id');
            $table->index('template_key');
        });
    }

    /**
     * Down the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_endpoints', function (Blueprint $table) {
            $table->dropIndex(['template_key']);
            $table->dropColumn('template_key');
        });
    }
};
