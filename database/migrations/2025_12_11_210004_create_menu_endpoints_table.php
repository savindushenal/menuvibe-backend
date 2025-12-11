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
        Schema::create('menu_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('franchise_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->constrained('menu_templates')->onDelete('cascade');
            $table->enum('type', ['table', 'room', 'area', 'branch', 'kiosk', 'takeaway', 'delivery', 'drive_thru', 'bar', 'patio', 'private']);
            $table->string('name');
            $table->string('identifier', 100);
            $table->text('description')->nullable();
            $table->string('short_code', 20)->unique();
            $table->string('qr_code_url', 500)->nullable();
            $table->string('short_url', 255)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->unsignedInteger('scan_count')->default(0);
            $table->integer('capacity')->nullable();
            $table->string('floor', 50)->nullable();
            $table->string('section', 100)->nullable();
            $table->json('position')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index(['location_id', 'type']);
            $table->index(['template_id']);
            $table->index(['short_code']);
            $table->unique(['user_id', 'location_id', 'identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_endpoints');
    }
};
