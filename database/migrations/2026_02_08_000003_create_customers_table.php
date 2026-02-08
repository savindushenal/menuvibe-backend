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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('franchise_id')->nullable()->constrained('franchises')->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->string('external_customer_id')->nullable()->index()->comment('ID in franchise system');
            $table->string('loyalty_number')->nullable()->index();
            $table->json('metadata')->nullable()->comment('Additional data from external system');
            $table->timestamps();
            
            // Composite unique index: one phone per location
            $table->unique(['phone', 'location_id']);
            
            // Indexes for performance
            $table->index(['location_id', 'created_at']);
            $table->index(['franchise_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
