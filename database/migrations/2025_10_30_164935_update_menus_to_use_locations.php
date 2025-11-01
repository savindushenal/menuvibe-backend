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
        // Add location_id column to menus table
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        });

        // Migrate existing menus to their corresponding locations
        $menus = DB::table('menus')->get();
        
        foreach ($menus as $menu) {
            // Find the location for this business profile
            $location = DB::table('locations')
                ->where('user_id', function($query) use ($menu) {
                    $query->select('user_id')
                        ->from('business_profiles')
                        ->where('id', $menu->business_profile_id);
                })
                ->where('is_default', true)
                ->first();

            if ($location) {
                DB::table('menus')
                    ->where('id', $menu->id)
                    ->update(['location_id' => $location->id]);
            }
        }

        // Make location_id required and remove business_profile_id
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable(false)->change();
            $table->dropForeign(['business_profile_id']);
            $table->dropColumn('business_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back business_profile_id
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('business_profile_id')->nullable()->after('id')->constrained()->onDelete('cascade');
        });

        // Migrate menus back to business profiles
        $menus = DB::table('menus')->get();
        
        foreach ($menus as $menu) {
            // Find the business profile for this location
            $businessProfile = DB::table('business_profiles')
                ->join('locations', 'locations.user_id', '=', 'business_profiles.user_id')
                ->where('locations.id', $menu->location_id)
                ->select('business_profiles.id')
                ->first();

            if ($businessProfile) {
                DB::table('menus')
                    ->where('id', $menu->id)
                    ->update(['business_profile_id' => $businessProfile->id]);
            }
        }

        // Make business_profile_id required and remove location_id
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('business_profile_id')->nullable(false)->change();
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
