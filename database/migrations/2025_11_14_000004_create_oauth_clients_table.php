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
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Client owner
            $table->string('name'); // Application name
            $table->string('client_id')->unique();
            $table->string('client_secret');
            $table->text('redirect_uris'); // JSON array
            $table->text('scopes')->nullable(); // JSON array of allowed scopes
            $table->string('grant_types')->default('authorization_code,refresh_token'); // Comma-separated
            $table->boolean('is_confidential')->default(true); // Public vs Confidential client
            $table->boolean('is_active')->default(true);
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('privacy_policy_url')->nullable();
            $table->string('terms_of_service_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('client_id');
            $table->index('is_active');

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Resource owner
            $table->string('token_hash')->unique();
            $table->text('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('client_id');
            $table->index('user_id');
            $table->index('token_hash');
            $table->index('expires_at');
            $table->index('revoked');

            // Foreign keys
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('access_token_id');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('access_token_id');
            $table->index('token_hash');
            $table->index('expires_at');

            // Foreign key
            $table->foreign('access_token_id')->references('id')->on('oauth_access_tokens')->onDelete('cascade');
        });

        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('user_id');
            $table->string('code_hash')->unique();
            $table->string('redirect_uri');
            $table->text('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('code_hash');
            $table->index('expires_at');
            $table->index('used');

            // Foreign keys
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_authorization_codes');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_clients');
    }
};
