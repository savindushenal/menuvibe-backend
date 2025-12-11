<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class AdminFranchiseController extends Controller
{
    /**
     * List all franchises with pagination
     */
    public function index(Request $request)
    {
        $query = Franchise::with(['owners:id,name,email', 'locations'])
            ->withCount(['locations', 'users']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhereHas('owners', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $franchises = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $franchises->items(),
            'meta' => [
                'current_page' => $franchises->currentPage(),
                'last_page' => $franchises->lastPage(),
                'per_page' => $franchises->perPage(),
                'total' => $franchises->total(),
            ],
        ]);
    }

    /**
     * Get franchise details
     */
    public function show($id)
    {
        $franchise = Franchise::with([
            'owners:id,name,email',
            'locations',
            'users.user:id,name,email',
        ])->withCount(['locations', 'users'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $franchise,
        ]);
    }

    /**
     * Update franchise details
     */
    public function update(Request $request, $id)
    {
        $franchise = Franchise::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'custom_domain' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $franchise->update($request->only([
            'name',
            'description',
            'is_active',
            'custom_domain',
            'settings',
        ]));

        // Log admin activity
        if (method_exists($request->user(), 'logAdminActivity')) {
            $request->user()->logAdminActivity(
                'franchise.updated',
                Franchise::class,
                $franchise->id,
                null,
                $request->all()
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Franchise updated successfully',
            'data' => $franchise->fresh(['owners', 'locations']),
        ]);
    }

    /**
     * Toggle franchise status
     */
    public function toggleStatus(Request $request, $id)
    {
        $franchise = Franchise::findOrFail($id);
        $franchise->is_active = !$franchise->is_active;
        $franchise->save();

        // Log admin activity
        if (method_exists($request->user(), 'logAdminActivity')) {
            $request->user()->logAdminActivity(
                $franchise->is_active ? 'franchise.activated' : 'franchise.deactivated',
                Franchise::class,
                $franchise->id
            );
        }

        return response()->json([
            'success' => true,
            'message' => $franchise->is_active ? 'Franchise activated' : 'Franchise deactivated',
            'data' => $franchise,
        ]);
    }

    /**
     * Delete a franchise
     */
    public function destroy(Request $request, $id)
    {
        $franchise = Franchise::findOrFail($id);
        
        // Log before deletion
        if (method_exists($request->user(), 'logAdminActivity')) {
            $request->user()->logAdminActivity(
                'franchise.deleted',
                Franchise::class,
                $franchise->id,
                $franchise->toArray()
            );
        }

        $franchise->delete();

        return response()->json([
            'success' => true,
            'message' => 'Franchise deleted successfully',
        ]);
    }

    /**
     * Get franchise statistics
     */
    public function statistics()
    {
        $stats = [
            'total' => Franchise::count(),
            'active' => Franchise::where('is_active', true)->count(),
            'inactive' => Franchise::where('is_active', false)->count(),
            'with_custom_domain' => Franchise::whereNotNull('custom_domain')->count(),
            'total_locations' => \App\Models\Location::whereNotNull('franchise_id')->count(),
        ];

        // Recent franchises
        $recentFranchises = Franchise::with('owners:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent' => $recentFranchises,
            ],
        ]);
    }

    /**
     * Transfer franchise ownership
     */
    public function transferOwnership(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_owner_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $franchise = Franchise::findOrFail($id);
        $oldOwner = $franchise->owners()->first();
        $oldOwnerId = $oldOwner ? $oldOwner->id : null;
        $newOwner = User::findOrFail($request->new_owner_id);

        // Remove old owner(s) and add new owner via pivot table
        $franchise->owners()->detach();
        $franchise->users()->attach($newOwner->id, ['role' => 'owner']);

        // Log admin activity
        if (method_exists($request->user(), 'logAdminActivity')) {
            $request->user()->logAdminActivity(
                'franchise.ownership_transferred',
                Franchise::class,
                $franchise->id,
                ['old_owner_id' => $oldOwnerId],
                ['new_owner_id' => $newOwner->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Ownership transferred to {$newOwner->name}",
            'data' => $franchise->fresh(['owners']),
        ]);
    }
}
