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
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Payment gateway fields
            $table->string('payment_gateway_transaction_id')->nullable()->after('payment_method');
            $table->integer('saved_card_id')->nullable()->after('payment_gateway_transaction_id');
            $table->json('payment_metadata')->nullable()->after('saved_card_id');
            
            // Index for looking up by transaction ID
            $table->index('payment_gateway_transaction_id', 'user_subscriptions_transaction_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex('user_subscriptions_transaction_id_index');
            $table->dropColumn([
                'payment_gateway_transaction_id',
                'saved_card_id',
                'payment_metadata',
            ]);
        });
    }
};
