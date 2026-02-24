<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique(); // e.g. ORD-A1B2
            $table->foreignId('session_id')->constrained('qr_scan_sessions')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('franchise_id')->nullable()->constrained('franchises')->onDelete('set null');
            $table->json('items'); // cart items snapshot
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 10)->default('LKR');
            $table->enum('status', ['pending', 'preparing', 'ready', 'delivered', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable(); // customer notes
            $table->text('staff_notes')->nullable(); // kitchen/staff notes
            $table->string('table_identifier')->nullable(); // e.g. "Table 5"
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->index(['location_id', 'status']);
            $table->index(['franchise_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_orders');
    }
};
