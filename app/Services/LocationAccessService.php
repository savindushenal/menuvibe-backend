<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LocationAccessService
{
    /**
     * Get accessible locations for a user based on their subscription plan
     */
    public function getAccessibleLocations(User $user)
    {
        $allLocations = $user->locations()->orderBy('created_at')->get();
        $plan = $user->getCurrentSubscriptionPlan();
        
        if (!$plan) {
            // No plan - allow default location only
            return $allLocations->take(1);
        }
        
        $maxLocations = $plan->limits['max_locations'] ?? 1;
        
        // If user is within limits, return all locations
        if ($allLocations->count() <= $maxLocations) {
            return $allLocations;
        }
        
        // User exceeded limits - return only allowed number, prioritizing default location
        $defaultLocation = $user->defaultLocation;
        $accessibleLocations = collect();
        
        if ($defaultLocation) {
            $accessibleLocations->push($defaultLocation);
            $maxLocations--;
        }
        
        // Add other locations up to the limit (oldest first)
        $otherLocations = $allLocations->reject(function($location) use ($defaultLocation) {
            return $defaultLocation && $location->id === $defaultLocation->id;
        })->take($maxLocations);
        
        return $accessibleLocations->merge($otherLocations);
    }
    
    /**
     * Get blocked locations for a user (locations they can't access due to plan limits)
     */
    public function getBlockedLocations(User $user)
    {
        $allLocations = $user->locations()->orderBy('created_at')->get();
        $accessibleLocations = $this->getAccessibleLocations($user);
        $accessibleIds = $accessibleLocations->pluck('id');
        
        return $allLocations->reject(function($location) use ($accessibleIds) {
            return $accessibleIds->contains($location->id);
        });
    }
    
    /**
     * Check if user can access a specific location
     */
    public function canAccessLocation(User $user, int $locationId): bool
    {
        $accessibleLocations = $this->getAccessibleLocations($user);
        return $accessibleLocations->contains('id', $locationId);
    }
    
    /**
     * Check if user can create more locations
     */
    public function canCreateLocation(User $user): bool
    {
        $plan = $user->getCurrentSubscriptionPlan();
        
        if (!$plan) {
            return $user->locations()->count() < 1;
        }
        
        $maxLocations = $plan->limits['max_locations'] ?? 1;
        return $user->locations()->count() < $maxLocations;
    }
    
    /**
     * Get location limit info for user
     */
    public function getLocationLimitInfo(User $user): array
    {
        $plan = $user->getCurrentSubscriptionPlan();
        $currentCount = $user->locations()->count();
        $maxLocations = $plan ? ($plan->limits['max_locations'] ?? 1) : 1;
        $accessibleCount = $this->getAccessibleLocations($user)->count();
        $blockedCount = $currentCount - $accessibleCount;
        
        return [
            'current_count' => $currentCount,
            'max_allowed' => $maxLocations,
            'accessible_count' => $accessibleCount,
            'blocked_count' => $blockedCount,
            'can_create_more' => $this->canCreateLocation($user),
            'is_over_limit' => $currentCount > $maxLocations,
        ];
    }
}
