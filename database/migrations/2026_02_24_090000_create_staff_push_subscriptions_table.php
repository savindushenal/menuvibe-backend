<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('franchise_id')->nullable();
            $table->string('endpoint')->unique();         // Push endpoint URL
            $table->string('p256dh_key');                // Subscriber public key
            $table->string('auth_key');                   // Auth secret
            $table->string('device_label')->nullable();   // e.g. "Kitchen Tablet"
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_push_subscriptions');
    }
};
