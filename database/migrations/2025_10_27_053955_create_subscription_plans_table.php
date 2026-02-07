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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Free, Pro, Enterprise
            $table->string('slug')->unique(); // free, pro, enterprise
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2); // Monthly price
            $table->json('features'); // Array of features
            $table->json('limits'); // Limits like max_menu_items, max_locations
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Insert default plans
        DB::table('subscription_plans')->insert([
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for trying out MenuVire',
                'price' => 0.00,
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
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Best for single restaurants',
                'price' => 29.00,
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
                    'max_menu_items' => -1, // -1 means unlimited
                    'max_locations' => 5,
                    'max_menus' => -1,
                    'photo_uploads' => true,
                    'custom_qr_codes' => true,
                    'analytics' => true,
                    'priority_support' => true
                ]),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For restaurant chains',
                'price' => 99.00,
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
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
