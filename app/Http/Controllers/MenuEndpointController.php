<?php

namespace App\Http\Controllers;

use App\Models\MenuEndpoint;
use App\Models\MenuTemplate;
use App\Models\EndpointOverride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuEndpointController extends Controller
{
    // ===========================================
    // ENDPOINTS
    // ===========================================

    /**
     * Get all endpoints for the authenticated user
     * IMPORTANT: Filters by context (business vs franchise) to prevent data leakage
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $locationId = $request->query('location_id');
        $type = $request->query('type');
        $templateId = $request->query('template_id');

        $query = MenuEndpoint::where('user_id', $user->id)
            ->with(['template:id,name,currency']);

        // CONTEXT-BASED FILTERING
        if ($locationId) {
            $location = \App\Models\Location::find($locationId);
            
            if ($location) {
                if ($location->franchise_id) {
                    // Franchise context: only show endpoints for THIS specific franchise and location
                    $query->where('location_id', $locationId);
                    $query->where('franchise_id', $location->franchise_id);
                } else {
                    // Business context: show ALL business endpoints across ALL business locations
                    // Don't filter by location_id - show all non-franchise endpoints
                    $query->whereNull('franchise_id');
                }
            }
        }
        // If no location_id: show ALL user's endpoints (both business and franchise)

        if ($type) {
            $query->where('type', $type);
        }
        if ($templateId) {
            $query->where('template_id', $templateId);
        }

        $endpoints = $query->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $endpoints
        ]);
    }

    /**
     * Create a new endpoint
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:menu_templates,id',
            'type' => 'required|in:table,room,area,branch,kiosk,takeaway,delivery,drive_thru,bar,patio,private,event',
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:100',
            'description' => 'nullable|string',
            'location_id' => 'nullable|exists:locations,id',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'capacity' => 'nullable|integer|min:1',
            'floor' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:100',
            'position' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify template belongs to user
        $template = MenuTemplate::where('id', $request->template_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $data = $validator->validated();
        $data['user_id'] = $user->id;
        $data['location_id'] = $data['location_id'] ?? $template->location_id;
        
        // Set franchise_id from location
        if ($data['location_id']) {
            $location = \App\Models\Location::find($data['location_id']);
            $data['franchise_id'] = $location ? $location->franchise_id : null;
        }

        $endpoint = MenuEndpoint::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Endpoint created successfully',
            'data' => $endpoint->load('template:id,name')
        ], 201);
    }

    /**
     * Bulk create endpoints (for tables)
     */
    public function bulkCreate(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'template_id' => 'required|exists:menu_templates,id',
            'type' => 'required|in:table,room,area,branch,kiosk,takeaway,delivery,drive_thru,bar,patio,private,event',
            'prefix' => 'required|string|max:50',
            'start_number' => 'required|integer|min:1',
            'count' => 'required|integer|min:1|max:100',
            'location_id' => 'nullable|exists:locations,id',
            'floor' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify template belongs to user
        $template = MenuTemplate::where('id', $request->template_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $endpoints = [];
        $typeName = MenuEndpoint::TYPES[$request->type] ?? ucfirst($request->type);

        // Get franchise_id from location
        $locationId = $request->location_id ?? $template->location_id;
        $franchiseId = null;
        if ($locationId) {
            $location = \App\Models\Location::find($locationId);
            $franchiseId = $location ? $location->franchise_id : null;
        }
        
        DB::transaction(function () use ($request, $user, $template, $typeName, $locationId, $franchiseId, &$endpoints) {
            for ($i = 0; $i < $request->count; $i++) {
                $number = $request->start_number + $i;
                $identifier = $request->prefix . $number;
                
                $endpoint = MenuEndpoint::create([
                    'user_id' => $user->id,
                    'template_id' => $request->template_id,
                    'location_id' => $locationId,
                    'franchise_id' => $franchiseId,
                    'type' => $request->type,
                    'name' => $typeName . ' ' . $number,
                    'identifier' => $identifier,
                    'floor' => $request->floor,
                    'section' => $request->section,
                    'is_active' => true,
                ]);
                
                $endpoints[] = $endpoint;
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($endpoints) . ' endpoints created successfully',
            'data' => $endpoints
        ], 201);
    }

    /**
     * Get a specific endpoint
     */
    public function show(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->with(['template', 'overrides.item:id,name,price'])
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $endpoint
        ]);
    }

    /**
     * Update an endpoint
     */
    public function update(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'template_id' => 'sometimes|exists:menu_templates,id',
            'type' => 'sometimes|in:table,room,area,branch,kiosk,takeaway,delivery,drive_thru,bar,patio,private,event',
            'name' => 'sometimes|string|max:255',
            'identifier' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'capacity' => 'nullable|integer|min:1',
            'floor' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:100',
            'position' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If changing template, verify it belongs to user
        if ($request->has('template_id')) {
            $template = MenuTemplate::where('id', $request->template_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found'
                ], 404);
            }
        }

        $endpoint->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Endpoint updated successfully',
            'data' => $endpoint->fresh()->load('template:id,name')
        ]);
    }

    /**
     * Delete an endpoint
     */
    public function destroy(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $endpoint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Endpoint deleted successfully'
        ]);
    }

    /**
     * Regenerate QR code for an endpoint
     */
    public function regenerateQr(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $endpoint->regenerateQrCode();

        return response()->json([
            'success' => true,
            'message' => 'QR code regenerated successfully',
            'data' => [
                'short_code' => $endpoint->short_code,
                'short_url' => $endpoint->short_url,
                'menu_url' => $endpoint->menu_url,
            ]
        ]);
    }

    /**
     * Get QR code data for an endpoint
     */
    public function getQrCode(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $endpoint->id,
                'name' => $endpoint->display_name,
                'type' => $endpoint->type,
                'short_code' => $endpoint->short_code,
                'short_url' => $endpoint->short_url,
                'menu_url' => $endpoint->menu_url,
                'qr_code_url' => $endpoint->qr_code_url,
            ]
        ]);
    }

    // ===========================================
    // OVERRIDES
    // ===========================================

    /**
     * Get all overrides for an endpoint
     */
    public function getOverrides(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $overrides = $endpoint->overrides()->with('item:id,name,price,category_id')->get();

        return response()->json([
            'success' => true,
            'data' => $overrides
        ]);
    }

    /**
     * Set an override for an item at this endpoint
     */
    public function setOverride(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:menu_template_items,id',
            'price_override' => 'nullable|numeric|min:0',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['endpoint_id'] = $endpointId;

        $override = EndpointOverride::updateOrCreate(
            [
                'endpoint_id' => $endpointId,
                'item_id' => $data['item_id'],
            ],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Override saved successfully',
            'data' => $override->load('item:id,name,price')
        ]);
    }

    /**
     * Remove an override
     */
    public function removeOverride(Request $request, int $endpointId, int $itemId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        EndpointOverride::where('endpoint_id', $endpointId)
            ->where('item_id', $itemId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Override removed successfully'
        ]);
    }

    /**
     * Bulk set overrides (for VIP pricing, etc.)
     */
    public function bulkSetOverrides(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->with('template.items')
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:percentage_increase,percentage_decrease,fixed_increase,fixed_decrease,set_unavailable,set_available,clear_all',
            'value' => 'required_unless:action,set_unavailable,set_available,clear_all|numeric',
            'apply_to' => 'nullable|in:all,category,items',
            'category_id' => 'required_if:apply_to,category|exists:menu_template_categories,id',
            'item_ids' => 'required_if:apply_to,items|array',
            'item_ids.*' => 'exists:menu_template_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $action = $request->action;
        $value = $request->value ?? 0;
        $applyTo = $request->apply_to ?? 'all';

        // Get items to apply to
        $items = $endpoint->template->items;
        
        if ($applyTo === 'category' && $request->category_id) {
            $items = $items->where('category_id', $request->category_id);
        } elseif ($applyTo === 'items' && $request->item_ids) {
            $items = $items->whereIn('id', $request->item_ids);
        }

        $count = 0;

        DB::transaction(function () use ($endpoint, $items, $action, $value, &$count) {
            foreach ($items as $item) {
                $overrideData = ['endpoint_id' => $endpoint->id, 'item_id' => $item->id];
                
                switch ($action) {
                    case 'percentage_increase':
                        $newPrice = $item->price * (1 + ($value / 100));
                        $overrideData['price_override'] = round($newPrice, 2);
                        break;
                    case 'percentage_decrease':
                        $newPrice = $item->price * (1 - ($value / 100));
                        $overrideData['price_override'] = max(0, round($newPrice, 2));
                        break;
                    case 'fixed_increase':
                        $overrideData['price_override'] = round($item->price + $value, 2);
                        break;
                    case 'fixed_decrease':
                        $overrideData['price_override'] = max(0, round($item->price - $value, 2));
                        break;
                    case 'set_unavailable':
                        $overrideData['is_available'] = false;
                        break;
                    case 'set_available':
                        $overrideData['is_available'] = true;
                        break;
                    case 'clear_all':
                        EndpointOverride::where('endpoint_id', $endpoint->id)
                            ->where('item_id', $item->id)
                            ->delete();
                        $count++;
                        continue 2;
                }

                EndpointOverride::updateOrCreate(
                    ['endpoint_id' => $endpoint->id, 'item_id' => $item->id],
                    $overrideData
                );
                $count++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "$count items updated successfully"
        ]);
    }

    // ===========================================
    // ANALYTICS
    // ===========================================

    /**
     * Get endpoint analytics
     */
    public function analytics(Request $request, int $endpointId)
    {
        $user = $request->user();

        $endpoint = MenuEndpoint::where('user_id', $user->id)
            ->where('id', $endpointId)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $endpoint->id,
                'name' => $endpoint->display_name,
                'type' => $endpoint->type,
                'scan_count' => $endpoint->scan_count,
                'last_scanned_at' => $endpoint->last_scanned_at,
                'created_at' => $endpoint->created_at,
                'is_active' => $endpoint->is_active,
            ]
        ]);
    }
}
