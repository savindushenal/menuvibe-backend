<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('table_number')->nullable(); // For table-specific QR codes
            $table->text('qr_url'); // The URL the QR code points to
            $table->longText('qr_image'); // Base64 or data URL of QR code image
            $table->integer('scan_count')->default(0); // Track how many times scanned
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['location_id', 'menu_id']);
            $table->index('table_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
