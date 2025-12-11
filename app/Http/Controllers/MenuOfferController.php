<?php

namespace App\Http\Controllers;

use App\Models\MenuOffer;
use App\Models\MenuTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MenuOfferController extends Controller
{
    /**
     * Get all offers for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->query('type');
        $templateId = $request->query('template_id');
        $status = $request->query('status'); // active, expired, upcoming

        $query = MenuOffer::where('user_id', $user->id)
            ->with(['template:id,name']);

        if ($type) {
            $query->where('offer_type', $type);
        }
        if ($templateId) {
            $query->where('template_id', $templateId);
        }

        switch ($status) {
            case 'active':
                $query->valid();
                break;
            case 'expired':
                $query->where(function ($q) {
                    $q->where('ends_at', '<', now())
                      ->orWhere(function ($q2) {
                          $q2->whereNotNull('usage_limit')
                             ->whereRaw('usage_count >= usage_limit');
                      });
                });
                break;
            case 'upcoming':
                $query->where('starts_at', '>', now());
                break;
        }

        $offers = $query->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        // Add computed properties
        $offers->each(function ($offer) {
            $offer->is_valid = $offer->is_valid;
            $offer->is_expired = $offer->is_expired;
            $offer->is_upcoming = $offer->is_upcoming;
            $offer->remaining_time = $offer->remaining_time;
        });

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Create a new offer
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'template_id' => 'nullable|exists:menu_templates,id',
            'location_id' => 'nullable|exists:locations,id',
            'offer_type' => 'required|in:special,instant,seasonal,combo,happy_hour,loyalty,first_order',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
            'discount_type' => 'required|in:percentage,fixed_amount,bogo,bundle_price,free_item',
            'discount_value' => 'nullable|numeric|min:0',
            'bundle_price' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'applicable_items' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'applicable_endpoints' => 'nullable|array',
            'apply_to_all' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'available_days' => 'nullable|array',
            'available_time_start' => 'nullable|string|max:10',
            'available_time_end' => 'nullable|string|max:10',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'terms_conditions' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If template_id provided, verify it belongs to user
        if ($request->template_id) {
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

        $data = $validator->validated();
        $data['user_id'] = $user->id;
        $data['slug'] = Str::slug($data['title']) . '-' . Str::random(6);
        $data['created_by'] = $user->id;

        // Set default badge based on offer type if not provided
        if (empty($data['badge_text'])) {
            $badges = MenuOffer::OFFER_TYPES;
            $data['badge_text'] = $badges[$data['offer_type']] ?? 'Offer';
        }

        $offer = MenuOffer::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Offer created successfully',
            'data' => $offer
        ], 201);
    }

    /**
     * Get a specific offer
     */
    public function show(Request $request, int $offerId)
    {
        $user = $request->user();

        $offer = MenuOffer::where('user_id', $user->id)
            ->where('id', $offerId)
            ->with(['template:id,name'])
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
    public function update(Request $request, int $offerId)
    {
        $user = $request->user();

        $offer = MenuOffer::where('user_id', $user->id)
            ->where('id', $offerId)
            ->first();

        if (!$offer) {
            return response()->json([
                'success' => false,
                'message' => 'Offer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'template_id' => 'nullable|exists:menu_templates,id',
            'offer_type' => 'sometimes|in:special,instant,seasonal,combo,happy_hour,loyalty,first_order',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
            'discount_type' => 'sometimes|in:percentage,fixed_amount,bogo,bundle_price,free_item',
            'discount_value' => 'nullable|numeric|min:0',
            'bundle_price' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'applicable_items' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'applicable_endpoints' => 'nullable|array',
            'apply_to_all' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'available_days' => 'nullable|array',
            'available_time_start' => 'nullable|string|max:10',
            'available_time_end' => 'nullable|string|max:10',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
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
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(6);
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
    public function destroy(Request $request, int $offerId)
    {
        $user = $request->user();

        $offer = MenuOffer::where('user_id', $user->id)
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
    public function toggleActive(Request $request, int $offerId)
    {
        $user = $request->user();

        $offer = MenuOffer::where('user_id', $user->id)
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
    public function duplicate(Request $request, int $offerId)
    {
        $user = $request->user();

        $offer = MenuOffer::where('user_id', $user->id)
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
        $newOffer->slug = Str::slug($newOffer->title) . '-' . Str::random(6);
        $newOffer->is_active = false;
        $newOffer->usage_count = 0;
        $newOffer->created_by = $user->id;
        $newOffer->save();

        return response()->json([
            'success' => true,
            'message' => 'Offer duplicated successfully',
            'data' => $newOffer
        ], 201);
    }

    /**
     * Get offers by type
     */
    public function byType(Request $request, string $type)
    {
        $user = $request->user();

        $validTypes = array_keys(MenuOffer::OFFER_TYPES);
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid offer type'
            ], 400);
        }

        $offers = MenuOffer::where('user_id', $user->id)
            ->where('offer_type', $type)
            ->valid()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }
}
