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
            $table->string('slug')->nullable()->after('name');
            $table->index(['location_id', 'slug']);
        });
        
        // Generate slugs for existing menus
        $menus = \App\Models\Menu::all();
        foreach ($menus as $menu) {
            $menu->slug = \Illuminate\Support\Str::slug($menu->name);
            $menu->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
