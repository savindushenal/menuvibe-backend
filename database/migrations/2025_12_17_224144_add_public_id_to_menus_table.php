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
        Schema::table('menus', function (Blueprint $table) {
            $table->string('public_id', 20)->nullable()->unique()->after('id');
        });
        
        // Generate public_ids for existing menus
        $menus = \App\Models\Menu::whereNull('public_id')->get();
        foreach ($menus as $menu) {
            $menu->public_id = \Illuminate\Support\Str::random(12);
            $menu->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};
