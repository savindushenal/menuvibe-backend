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
        // Update subscription plans with refined location limits
        
        // Update Free plan
        DB::table('subscription_plans')
            ->where('slug', 'free')
            ->update([
                'features' => json_encode([
                    'Up to 1 location',
                    'Up to 1 menu per location',
                    'Up to 10 menu items per menu',
                    'Basic QR codes',
                    'Mobile responsive design',
                    'Community support'
                ]),
                'limits' => json_encode([
                    'max_locations' => 1,
                    'max_menus_per_location' => 1,
                    'max_menu_items_per_menu' => 10,
                    'photo_uploads' => false,
                    'custom_qr_codes' => false,
                    'analytics' => false,
                    'priority_support' => false
                ]),
                'updated_at' => now(),
            ]);

        // Update Pro plan
        DB::table('subscription_plans')
            ->where('slug', 'pro')
            ->update([
                'features' => json_encode([
                    'Up to 3 locations',
                    'Up to 5 menus per location',
                    'Up to 50 menu items per menu',
                    'Photo uploads',
                    'Custom QR codes',
                    'Real-time analytics',
                    'Menu customization',
                    'Priority support'
                ]),
                'limits' => json_encode([
                    'max_locations' => 3,
                    'max_menus_per_location' => 5,
                    'max_menu_items_per_menu' => 50,
                    'photo_uploads' => true,
                    'custom_qr_codes' => true,
                    'analytics' => true,
                    'priority_support' => true
                ]),
                'updated_at' => now(),
            ]);

        // Update Enterprise plan
        DB::table('subscription_plans')
            ->where('slug', 'enterprise')
            ->update([
                'features' => json_encode([
                    'Up to 5 locations',
                    'Unlimited menus per location',
                    'Unlimited menu items',
                    'Everything in Pro',
                    'Advanced analytics',
                    'API access',
                    'Dedicated support',
                    'Custom integrations',
                    'White-label options'
                ]),
                'limits' => json_encode([
                    'max_locations' => 5,
                    'max_menus_per_location' => -1, // unlimited
                    'max_menu_items_per_menu' => -1, // unlimited
                    'photo_uploads' => true,
                    'custom_qr_codes' => true,
                    'analytics' => true,
                    'advanced_analytics' => true,
                    'api_access' => true,
                    'priority_support' => true,
                    'dedicated_support' => true,
                    'white_label' => true
                ]),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original subscription plan limits
        DB::table('subscription_plans')
            ->where('slug', 'free')
            ->update([
                'features' => json_encode([
                    'Up to 30 menu items',
                    '1 location',
                    'Basic QR codes',
                    'Mobile responsive',
                    'Community support'
                ]),
                'limits' => json_encode([
                    'max_menu_items' => 30,
                    'max_locations' => 1,
                    'max_menus' => 3,
                    'photo_uploads' => false,
                    'custom_qr_codes' => false,
                    'analytics' => false,
                    'priority_support' => false
                ]),
                'updated_at' => now(),
            ]);

        DB::table('subscription_plans')
            ->where('slug', 'pro')
            ->update([
                'features' => json_encode([
                    'Unlimited menu items',
                    '5 locations',
                    'Custom QR codes',
                    'Real-time analytics',
                    'Priority support',
                    'Menu customization',
                    'Photo uploads'
                ]),
                'limits' => json_encode([
                    'max_menu_items' => -1,
                    'max_locations' => 5,
                    'max_menus' => -1,
                    'photo_uploads' => true,
                    'custom_qr_codes' => true,
                    'analytics' => true,
                    'priority_support' => true
                ]),
                'updated_at' => now(),
            ]);

        DB::table('subscription_plans')
            ->where('slug', 'enterprise')
            ->update([
                'features' => json_encode([
                    'Everything in Pro',
                    'Unlimited locations',
                    'Advanced analytics',
                    'API access',
                    'Dedicated support',
                    'Custom integrations',
                    'White-label options'
                ]),
                'limits' => json_encode([
                    'max_menu_items' => -1,
                    'max_locations' => -1,
                    'max_menus' => -1,
                    'photo_uploads' => true,
                    'custom_qr_codes' => true,
                    'analytics' => true,
                    'advanced_analytics' => true,
                    'api_access' => true,
                    'priority_support' => true,
                    'dedicated_support' => true,
                    'white_label' => true
                ]),
                'updated_at' => now(),
            ]);
    }
};
