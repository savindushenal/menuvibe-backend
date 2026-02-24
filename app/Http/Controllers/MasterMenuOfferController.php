<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use App\Models\MasterMenuOffer;
use App\Models\BranchOfferOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MasterMenuOfferController extends Controller
{
    /**
     * Get all offers for a franchise
     */
    public function index(Request $request, int $franchiseId)
    {
        $type = $request->query('type'); // special, instant, seasonal, combo, happy_hour

        $query = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->with(['masterMenu:id,name', 'branchOverrides:id,master_offer_id,location_id,is_active'])
            ->orderBy('sort_order');

        if ($type) {
            $query->where('offer_type', $type);
        }

        // Filter by status
        $status = $request->query('status'); // active, upcoming, expired, all
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'upcoming') {
            $query->where('starts_at', '>', now());
        } elseif ($status === 'expired') {
            $query->where('ends_at', '<', now());
        }

        $offers = $query->get()->map(function ($offer) {
            $offer->is_valid = $offer->is_valid;
            $offer->is_expired = $offer->is_expired;
            $offer->is_upcoming = $offer->is_upcoming;
            $offer->remaining_time = $offer->remaining_time;
            $offer->type_badge = $offer->type_badge;
            return $offer;
        });

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Create a new offer
     */
    public function store(Request $request, int $franchiseId)
    {
        $validator = Validator::make($request->all(), [
            'master_menu_id' => 'nullable|exists:master_menus,id',
            'offer_type' => 'required|in:special,instant,seasonal,combo,happy_hour',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
            'discount_type' => 'required|in:percentage,fixed_amount,bogo,bundle_price',
            'discount_value' => 'nullable|numeric|min:0',
            'bundle_price' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'applicable_items' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'apply_to_all' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'available_days' => 'nullable|array',
            'available_time_start' => 'nullable|string|max:10',
            'available_time_end' => 'nullable|string|max:10',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'terms_conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['franchise_id'] = $franchiseId;
        $data['slug'] = Str::slug($data['title']);
        $data['created_by'] = $request->user()->id;

        // Set default badge based on offer type
        if (empty($data['badge_text'])) {
            switch ($data['offer_type']) {
                case 'special':
                    $data['badge_text'] = 'Special Offer';
                    $data['badge_color'] = '#ef4444';
                    break;
                case 'instant':
                    $data['badge_text'] = 'Instant Deal';
                    $data['badge_color'] = '#f59e0b';
                    break;
                case 'seasonal':
                    $data['badge_text'] = 'Seasonal';
                    $data['badge_color'] = '#10b981';
                    break;
                case 'combo':
                    $data['badge_text'] = 'Combo';
                    $data['badge_color'] = '#8b5cf6';
                    break;
                case 'happy_hour':
                    $data['badge_text'] = 'Happy Hour';
                    $data['badge_color'] = '#3b82f6';
                    break;
            }
        }

        $offer = MasterMenuOffer::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Offer created successfully',
            'data' => $offer
        ], 201);
    }

    /**
     * Get a specific offer
     */
    public function show(Request $request, int $franchiseId, int $offerId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->with(['masterMenu:id,name', 'branchOverrides.branch:id,branch_name'])
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        // Add computed properties
        $offer->is_valid = $offer->is_valid;
        $offer->is_expired = $offer->is_expired;
        $offer->is_upcoming = $offer->is_upcoming;
        $offer->remaining_time = $offer->remaining_time;
        $offer->type_badge = $offer->type_badge;

        return response()->json([
            'success' => true,
            'data' => $offer
        ]);
    }

    /**
     * Update an offer
     */
    public function update(Request $request, int $franchiseId, int $offerId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'master_menu_id' => 'nullable|exists:master_menus,id',
            'offer_type' => 'sometimes|in:special,instant,seasonal,combo,happy_hour',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
            'discount_type' => 'sometimes|in:percentage,fixed_amount,bogo,bundle_price',
            'discount_value' => 'nullable|numeric|min:0',
            'bundle_price' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'applicable_items' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'apply_to_all' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'available_days' => 'nullable|array',
            'available_time_start' => 'nullable|string|max:10',
            'available_time_end' => 'nullable|string|max:10',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'terms_conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $offer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Offer updated successfully',
            'data' => $offer->fresh()
        ]);
    }

    /**
     * Delete an offer
     */
    public function destroy(Request $request, int $franchiseId, int $offerId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        $offer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Offer deleted successfully'
        ]);
    }

    /**
     * Toggle offer active status
     */
    public function toggleActive(Request $request, int $franchiseId, int $offerId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        $offer->update(['is_active' => !$offer->is_active]);

        return response()->json([
            'success' => true,
            'message' => $offer->is_active ? 'Offer activated' : 'Offer deactivated',
            'data' => $offer
        ]);
    }

    /**
     * Duplicate an offer
     */
    public function duplicate(Request $request, int $franchiseId, int $offerId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        $newOffer = $offer->replicate();
        $newOffer->title = $offer->title . ' (Copy)';
        $newOffer->slug = Str::slug($newOffer->title);
        $newOffer->is_active = false;
        $newOffer->usage_count = 0;
        $newOffer->created_by = $request->user()->id;
        $newOffer->save();

        return response()->json([
            'success' => true,
            'message' => 'Offer duplicated successfully',
            'data' => $newOffer
        ], 201);
    }

    /**
     * Get offer analytics
     */
    public function analytics(Request $request, int $franchiseId, int $offerId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        // Calculate analytics (placeholder for now)
        $analytics = [
            'total_views' => 0, // Would come from analytics tracking
            'total_uses' => $offer->usage_count,
            'usage_rate' => $offer->usage_limit 
                ? round(($offer->usage_count / $offer->usage_limit) * 100, 2) 
                : null,
            'remaining_uses' => $offer->usage_limit 
                ? $offer->usage_limit - $offer->usage_count 
                : null,
            'status' => $offer->is_valid ? 'active' : ($offer->is_expired ? 'expired' : 'inactive'),
            'days_remaining' => $offer->ends_at 
                ? max(0, now()->diffInDays($offer->ends_at, false)) 
                : null,
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get offers by type for quick filtering
     */
    public function byType(Request $request, int $franchiseId, string $type)
    {
        $validTypes = ['special', 'instant', 'seasonal', 'combo', 'happy_hour'];
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid offer type'
            ], 400);
        }

        $offers = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('offer_type', $type)
            ->active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Set branch override for an offer
     */
    public function setBranchOverride(Request $request, int $franchiseId, int $offerId, int $branchId)
    {
        $offer = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'nullable|boolean',
            'discount_override' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $override = BranchOfferOverride::updateOrCreate(
            [
                'location_id' => $branchId,
                'master_offer_id' => $offerId,
            ],
            $validator->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Branch override saved successfully',
            'data' => $override
        ]);
    }

    /**
     * Remove branch override for an offer
     */
    public function removeBranchOverride(Request $request, int $franchiseId, int $menuId, int $offerId, int $branchId)
    {
        $deleted = BranchOfferOverride::where('master_offer_id', $offerId)
            ->where('location_id', $branchId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted ? 'Branch override removed' : 'Override not found'
        ]);
    }

    /**
     * Get active offers for public menu display
     */
    public function getActiveOffers(Request $request, int $franchiseId)
    {
        $branchId = $request->query('branch_id');

        $offers = MasterMenuOffer::where('franchise_id', $franchiseId)
            ->active()
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($offer) use ($branchId) {
                // Check branch override if branch specified
                if ($branchId) {
                    $override = $offer->branchOverrides()
                        ->where('location_id', $branchId)
                        ->first();
                    
                    if ($override && !$override->is_active) {
                        return false;
                    }
                }
                
                return $offer->is_valid;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }
}
