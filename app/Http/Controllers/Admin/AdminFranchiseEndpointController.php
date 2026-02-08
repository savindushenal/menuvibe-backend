<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\Location;
use App\Models\MenuEndpoint;
use App\Models\MenuTemplate;
use App\Models\Scopes\FranchiseScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminFranchiseEndpointController extends Controller
{
    /**
     * List all endpoints for a franchise
     */
    public function index(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $query = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->with(['location:id,name', 'menuTemplate:id,name']);

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_code', 'like', "%{$search}%")
                  ->orWhere('table_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $endpoints = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $endpoints->items(),
            'meta' => [
                'current_page' => $endpoints->currentPage(),
                'last_page' => $endpoints->lastPage(),
                'per_page' => $endpoints->perPage(),
                'total'=> $endpoints->total(),
            ],
            'franchise' => [
                'id' => $franchise->id,
                'name' => $franchise->name,
                'slug' => $franchise->slug,
            ],
        ]);
    }

    /**
     * Get endpoint details
     */
    public function show($franchiseId, $endpointId)
    {
        $endpoint = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->with(['location', 'menuTemplate', 'franchise'])
            ->findOrFail($endpointId);

        return response()->json([
            'success' => true,
            'data' => $endpoint,
        ]);
    }

    /**
     * Create new endpoint/QR code for franchise
     */
    public function store(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:locations,id',
            'menu_template_id' => 'nullable|exists:menu_templates,id',
            'name' => 'required|string|max:255',
            'table_number' => 'nullable|string|max:50',
            'template_key' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify location belongs to franchise
        $location = Location::withoutGlobalScope(FranchiseScope::class)
            ->where('id', $request->location_id)
            ->where('franchise_id', $franchiseId)
            ->firstOrFail();

        // Generate unique short code
        $shortCode = $this->generateUniqueShortCode();

        // Get franchise owner's user_id
        $owner = $franchise->owner;
        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise must have an owner to create endpoints',
            ], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = MenuEndpoint::create([
            'user_id' => $owner->id,
            'franchise_id' => $franchiseId,
            'location_id' => $request->location_id,
            'menu_template_id' => $request->menu_template_id,
            'name' => $request->name,
            'short_code' => $shortCode,
            'table_number' => $request->table_number,
            'template_key' => $request->template_key ?? $franchise->template_type ?? 'default',
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Endpoint created successfully',
            'data' => $endpoint->load(['location', 'menuTemplate']),
            'qr_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/menu/' . $shortCode,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update endpoint
     */
    public function update(Request $request, $franchiseId, $endpointId)
    {
        $endpoint = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($endpointId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'table_number' => 'nullable|string|max:50',
            'template_key' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'menu_template_id' => 'nullable|exists:menu_templates,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $endpoint->update($request->only([
            'name',
            'table_number',
            'template_key',
            'is_active',
            'menu_template_id',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Endpoint updated successfully',
            'data' => $endpoint->fresh(['location', 'menuTemplate']),
        ]);
    }

    /**
     * Delete endpoint
     */
    public function destroy($franchiseId, $endpointId)
    {
        $endpoint = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($endpointId);

        $endpoint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Endpoint deleted successfully',
        ]);
    }

    /**
     * Bulk create endpoints for tables
     */
    public function bulkCreate(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:locations,id',
            'menu_template_id' => 'nullable|exists:menu_templates,id',
            'table_prefix' => 'required|string|max:50',
            'table_start' => 'required|integer|min:1',
            'table_end' => 'required|integer|min:1|gt:table_start',
            'template_key' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify location belongs to franchise
        $location = Location::withoutGlobalScope(FranchiseScope::class)
            ->where('id', $request->location_id)
            ->where('franchise_id', $franchiseId)
            ->firstOrFail();

        // Get franchise owner's user_id
        $owner = $franchise->owner;
        if (!$owner) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise must have an owner to create endpoints',
            ], Response::HTTP_BAD_REQUEST);
        }

        $endpoints = [];
        for ($i = $request->table_start; $i <= $request->table_end; $i++) {
            $tableNumber = $request->table_prefix . $i;
            $shortCode = $this->generateUniqueShortCode();

            $endpoint = MenuEndpoint::create([
                'user_id' => $owner->id,
                'franchise_id' => $franchiseId,
                'location_id' => $request->location_id,
                'menu_template_id' => $request->menu_template_id,
                'name' => "Table {$tableNumber}",
                'short_code' => $shortCode,
                'table_number' => $tableNumber,
                'template_key' => $request->template_key ?? $franchise->template_type ?? 'default',
                'is_active' => true,
            ]);

            $endpoints[] = $endpoint;
        }

        return response()->json([
            'success' => true,
            'message' => count($endpoints) . ' endpoints created successfully',
            'data' => $endpoints,
        ], Response::HTTP_CREATED);
    }

    /**
     * Generate unique 6-character short code
     */
    private function generateUniqueShortCode(): string
    {
        do {
            $shortCode = strtoupper(Str::random(6));
        } while (MenuEndpoint::where('short_code', $shortCode)->exists());

        return $shortCode;
    }

    /**
     * Get QR code statistics for franchise
     */
    public function statistics($franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $totalEndpoints = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->count();

        $activeEndpoints = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('is_active', true)
            ->count();

        $byLocation = MenuEndpoint::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->select('location_id', \DB::raw('count(*) as count'))
            ->with('location:id,name')
            ->groupBy('location_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_endpoints' => $totalEndpoints,
                'active_endpoints' => $activeEndpoints,
                'inactive_endpoints' => $totalEndpoints - $activeEndpoints,
                'by_location' => $byLocation,
            ],
        ]);
    }
}
