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
        // Add custom plan fields to subscription_plans table
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('is_active');
            $table->decimal('setup_fee', 8, 2)->nullable()->after('price');
            $table->string('billing_period')->default('monthly')->after('setup_fee'); // monthly, yearly, custom
            $table->integer('contract_months')->nullable()->after('billing_period');
            $table->text('custom_features')->nullable()->after('contract_months');
            $table->json('custom_limits')->nullable()->after('custom_features');
        });

        // Add custom subscription plan
        DB::table('subscription_plans')->insert([
            'name' => 'Custom Enterprise',
            'slug' => 'custom-enterprise',
            'description' => 'Tailored solution for large restaurant chains and franchises',
            'price' => 0.00, // Will be set based on negotiation
            'setup_fee' => 0.00,
            'billing_period' => 'custom',
            'contract_months' => 12,
            'features' => json_encode([
                'Unlimited locations',
                'Unlimited menus and items',
                'White-label branding',
                'Dedicated account manager',
                'Custom integrations',
                'API access',
                'Advanced analytics',
                'Priority support',
                'Custom training',
                'SLA guarantee'
            ]),
            'limits' => json_encode([
                'max_locations' => -1, // unlimited
                'max_menus_per_location' => -1,
                'max_menu_items_per_menu' => -1,
                'photo_uploads' => true,
                'custom_qr_codes' => true,
                'analytics' => true,
                'advanced_analytics' => true,
                'api_access' => true,
                'priority_support' => true,
                'dedicated_support' => true,
                'white_label' => true,
                'custom_integrations' => true,
                'sla_guarantee' => true
            ]),
            'custom_features' => 'Fully customizable solution with dedicated support team',
            'custom_limits' => json_encode([
                'custom_pricing' => true,
                'volume_discounts' => true,
                'flexible_billing' => true
            ]),
            'is_active' => true,
            'is_custom' => true,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove custom enterprise plan
        DB::table('subscription_plans')->where('slug', 'custom-enterprise')->delete();

        // Remove custom plan fields
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'is_custom',
                'setup_fee', 
                'billing_period',
                'contract_months',
                'custom_features',
                'custom_limits'
            ]);
        });
    }
};
