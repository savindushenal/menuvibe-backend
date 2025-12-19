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
        Schema::table('franchise_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('franchise_accounts', 'invitation_token')) {
                $table->string('invitation_token', 64)->nullable()->unique()->after('is_active');
            }
            if (!Schema::hasColumn('franchise_accounts', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('invitation_token');
            }
            if (!Schema::hasColumn('franchise_accounts', 'invitation_expires_at')) {
                $table->timestamp('invitation_expires_at')->nullable()->after('accepted_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('franchise_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('franchise_accounts', 'invitation_token')) {
                $table->dropColumn('invitation_token');
            }
            if (Schema::hasColumn('franchise_accounts', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }
            if (Schema::hasColumn('franchise_accounts', 'invitation_expires_at')) {
                $table->dropColumn('invitation_expires_at');
            }
        });
    }
};
