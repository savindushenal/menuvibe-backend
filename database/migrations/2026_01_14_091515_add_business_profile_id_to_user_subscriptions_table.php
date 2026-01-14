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
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('business_profile_id')->nullable()->after('user_id');
            $table->foreign('business_profile_id')->references('id')->on('business_profiles')->onDelete('set null');
            $table->index('business_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['business_profile_id']);
            $table->dropIndex(['business_profile_id']);
            $table->dropColumn('business_profile_id');
        });
    }
};
