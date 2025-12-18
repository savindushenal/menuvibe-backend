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
            $table->string('slug')->nullable()->after('name');
            $table->index(['menu_id', 'slug']);
        });
        
        // Generate slugs for existing items
        $items = \App\Models\MenuItem::all();
        foreach ($items as $item) {
            $item->slug = \Illuminate\Support\Str::slug($item->name);
            $item->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['menu_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
