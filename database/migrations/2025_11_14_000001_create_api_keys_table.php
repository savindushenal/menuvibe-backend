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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name'); // User-friendly name
            $table->string('key_prefix', 20); // mvb_live_ or mvb_test_
            $table->string('key_hash'); // Hashed key for security
            $table->string('key_type')->default('standard'); // public_read, standard, premium, enterprise
            $table->text('scopes')->nullable(); // JSON array of permissions
            $table->integer('rate_limit_per_hour')->default(10000);
            $table->string('environment')->default('production'); // production, sandbox
            $table->ipAddress('whitelisted_ips')->nullable(); // Comma-separated IPs
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('metadata')->nullable(); // JSON for additional data
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('key_hash');
            $table->index('key_type');
            $table->index('is_active');
            $table->index('environment');

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
