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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('url'); // Webhook endpoint URL
            $table->string('secret')->nullable(); // For signature verification
            $table->text('events'); // JSON array of subscribed events
            $table->boolean('is_active')->default(true);
            $table->integer('max_retries')->default(3);
            $table->integer('timeout_seconds')->default(30);
            $table->string('description')->nullable();
            $table->text('headers')->nullable(); // JSON for custom headers
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('is_active');

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id');
            $table->string('event_type'); // menu.created, order.placed, etc.
            $table->text('payload'); // JSON payload sent
            $table->text('response_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->float('duration_ms')->nullable(); // Response time
            $table->timestamps();

            // Indexes
            $table->index('webhook_id');
            $table->index('event_type');
            $table->index('delivered_at');
            $table->index('failed_at');
            $table->index(['webhook_id', 'created_at']);

            // Foreign key
            $table->foreign('webhook_id')->references('id')->on('webhooks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
