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
        Schema::create('api_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('endpoint'); // /api/v1/menus
            $table->string('method'); // GET, POST, PUT, DELETE
            $table->integer('response_status');
            $table->float('response_time_ms');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('request_params')->nullable(); // JSON
            $table->text('response_summary')->nullable(); // JSON (errors, etc.)
            $table->timestamp('created_at');

            // Indexes for analytics
            $table->index('api_key_id');
            $table->index('user_id');
            $table->index('endpoint');
            $table->index('method');
            $table->index('response_status');
            $table->index('created_at');
            $table->index(['api_key_id', 'created_at']);
            $table->index(['user_id', 'created_at']);

            // Foreign keys
            $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Daily aggregated stats for faster analytics
        Schema::create('api_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('date');
            $table->string('endpoint');
            $table->bigInteger('request_count')->default(0);
            $table->bigInteger('success_count')->default(0);
            $table->bigInteger('error_count')->default(0);
            $table->float('avg_response_time_ms')->nullable();
            $table->timestamps();

            // Unique constraint
            $table->unique(['api_key_id', 'user_id', 'date', 'endpoint'], 'usage_daily_unique');

            // Indexes
            $table->index('date');
            $table->index(['api_key_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage_daily');
        Schema::dropIfExists('api_usage');
    }
};
