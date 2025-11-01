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
        // Get the free plan
        $freePlan = DB::table('subscription_plans')->where('slug', 'free')->first();
        
        if ($freePlan) {
            // Get all existing users without subscriptions
            $users = DB::table('users')
                ->leftJoin('user_subscriptions', 'users.id', '=', 'user_subscriptions.user_id')
                ->whereNull('user_subscriptions.user_id')
                ->select('users.id', 'users.created_at')
                ->get();

            // Create free subscriptions for all existing users
            foreach ($users as $user) {
                DB::table('user_subscriptions')->insert([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $freePlan->id,
                    'starts_at' => $user->created_at,
                    'ends_at' => null, // Free plan doesn't expire
                    'trial_ends_at' => null,
                    'is_active' => true,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all free subscriptions
        $freePlan = DB::table('subscription_plans')->where('slug', 'free')->first();
        
        if ($freePlan) {
            DB::table('user_subscriptions')
                ->where('subscription_plan_id', $freePlan->id)
                ->delete();
        }
    }
};
