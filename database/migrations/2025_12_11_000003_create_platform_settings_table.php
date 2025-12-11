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
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->string('group')->default('general'); // general, billing, email, security
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be accessed by non-admins
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('group');
        });
        
        // Insert default settings
        $settings = [
            ['key' => 'platform_name', 'value' => 'MenuVibe', 'type' => 'string', 'group' => 'general', 'description' => 'Platform display name', 'is_public' => true],
            ['key' => 'platform_email', 'value' => 'support@menuvibe.com', 'type' => 'string', 'group' => 'general', 'description' => 'Platform support email', 'is_public' => true],
            ['key' => 'allow_registrations', 'value' => 'true', 'type' => 'boolean', 'group' => 'security', 'description' => 'Allow new user registrations', 'is_public' => false],
            ['key' => 'require_email_verification', 'value' => 'false', 'type' => 'boolean', 'group' => 'security', 'description' => 'Require email verification for new users', 'is_public' => false],
            ['key' => 'max_free_menus', 'value' => '1', 'type' => 'integer', 'group' => 'billing', 'description' => 'Maximum menus for free plan', 'is_public' => false],
            ['key' => 'max_free_items', 'value' => '10', 'type' => 'integer', 'group' => 'billing', 'description' => 'Maximum items per menu for free plan', 'is_public' => false],
            ['key' => 'trial_days', 'value' => '14', 'type' => 'integer', 'group' => 'billing', 'description' => 'Trial period in days', 'is_public' => true],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'general', 'description' => 'Enable maintenance mode', 'is_public' => false],
            ['key' => 'maintenance_message', 'value' => 'We are currently performing scheduled maintenance. Please check back soon.', 'type' => 'string', 'group' => 'general', 'description' => 'Maintenance mode message', 'is_public' => true],
        ];
        
        foreach ($settings as $setting) {
            DB::table('platform_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
