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
        // Migrate existing business profiles to locations
        $businessProfiles = DB::table('business_profiles')->get();
        
        foreach ($businessProfiles as $profile) {
            // Create a location for each business profile
            DB::table('locations')->insert([
                'user_id' => $profile->user_id,
                'name' => $profile->business_name ?: 'Main Location',
                'description' => $profile->description,
                'is_active' => true,
                'sort_order' => 0,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'website' => $profile->website,
                'address_line_1' => $profile->address_line_1 ?: 'N/A',
                'address_line_2' => $profile->address_line_2,
                'city' => $profile->city ?: 'N/A',
                'state' => $profile->state ?: 'N/A',
                'postal_code' => $profile->postal_code ?: 'N/A',
                'country' => $profile->country ?: 'N/A',
                'cuisine_type' => $profile->cuisine_type,
                'seating_capacity' => $profile->seating_capacity,
                'operating_hours' => $profile->operating_hours,
                'services' => $profile->services,
                'logo_url' => $profile->logo_url,
                'primary_color' => $profile->primary_color,
                'secondary_color' => $profile->secondary_color,
                'social_media' => $profile->social_media,
                'is_default' => true, // First location is always default
                'created_at' => $profile->created_at ?: now(),
                'updated_at' => $profile->updated_at ?: now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all locations that were created from business profiles
        DB::table('locations')
            ->where('is_default', true)
            ->where('name', 'Main Location')
            ->delete();
    }
};
