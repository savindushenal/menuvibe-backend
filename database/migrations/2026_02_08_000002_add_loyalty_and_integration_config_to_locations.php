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
        Schema::table('locations', function (Blueprint $table) {
            // Loyalty configuration
            $table->enum('loyalty_provider', ['disabled', 'internal', 'external'])
                ->default('disabled')
                ->after('is_default');
            $table->boolean('loyalty_enabled')->default(false)->after('loyalty_provider');
            $table->json('loyalty_config')->nullable()->after('loyalty_enabled')
                ->comment('Configuration for external loyalty API: api_endpoint, api_key, auth_type, field_mappings');
            
            // Customer authentication configuration
            $table->enum('auth_mode', ['guest', 'internal', 'external', 'hybrid'])
                ->default('guest')
                ->after('loyalty_config');
            $table->json('external_auth_config')->nullable()->after('auth_mode')
                ->comment('Configuration for external authentication API');
            
            // Order sync configuration
            $table->boolean('order_sync_enabled')->default(false)->after('external_auth_config');
            $table->json('order_sync_config')->nullable()->after('order_sync_enabled')
                ->comment('Configuration for syncing orders to external franchise systems');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_provider',
                'loyalty_enabled',
                'loyalty_config',
                'auth_mode',
                'external_auth_config',
                'order_sync_enabled',
                'order_sync_config',
            ]);
        });
    }
};
