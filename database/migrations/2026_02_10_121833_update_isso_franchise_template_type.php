<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update isso franchise to use custom isso template instead of premium
        DB::table('franchises')
            ->where('slug', 'isso')
            ->update([
                'template_type' => 'isso',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert isso franchise back to premium template
        DB::table('franchises')
            ->where('slug', 'isso')
            ->update([
                'template_type' => 'premium',
                'updated_at' => now(),
            ]);
    }
};
