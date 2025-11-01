<?php

namespace App\Observers;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Automatically assign free subscription plan to new user
        $this->assignFreeSubscription($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }

    /**
     * Assign free subscription plan to user
     */
    private function assignFreeSubscription(User $user): void
    {
        // Check if user already has a subscription
        if ($user->subscriptions()->exists()) {
            return;
        }

        // Get the free plan
        $freePlan = SubscriptionPlan::where('slug', 'free')->first();
        
        if ($freePlan) {
            UserSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $freePlan->id,
                'starts_at' => now(),
                'ends_at' => null, // Free plan doesn't expire
                'trial_ends_at' => null,
                'is_active' => true,
                'status' => 'active',
            ]);
        }
    }
}
