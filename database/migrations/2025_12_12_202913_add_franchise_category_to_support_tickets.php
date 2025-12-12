<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, modify the enum to add 'franchise' category
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN category ENUM('billing', 'technical', 'feature_request', 'account', 'franchise', 'other') DEFAULT 'other'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE support_tickets MODIFY COLUMN category ENUM('billing', 'technical', 'feature_request', 'account', 'other') DEFAULT 'other'");
    }
};
