<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class FranchiseScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        
        // Super admins see everything - no filtering
        if ($user && $user->role === 'super_admin') {
            return;
        }
        
        // Franchise users - filter by their franchise
        if ($user && $user->franchise_id) {
            $builder->where($model->getTable() . '.franchise_id', $user->franchise_id);
            return;
        }
        
        // Small business owners - filter by NULL franchise_id AND their user_id
        if ($user && is_null($user->franchise_id)) {
            $builder->whereNull($model->getTable() . '.franchise_id');
            
            // If model has user_id column, filter by that too
            if (in_array('user_id', $model->getFillable())) {
                $builder->where($model->getTable() . '.user_id', $user->id);
            }
        }
    }
}
