<?php

namespace App\Http\Controllers;

use App\Models\MenuEndpoint;
use App\Models\MenuOffer;
use Illuminate\Http\Request;

class PublicMenuController extends Controller
{
    /**
     * Get menu by short code (for customer scanning QR)
     */
    public function getMenu(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template' => function ($q) {
                $q->where('is_active', true);
            }, 'location'])
            ->first();

        if (!$endpoint || !$endpoint->template) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found or inactive'
            ], 404);
        }

        // Record scan
        $endpoint->recordScan();

        // Get menu with overrides applied
        $menu = $endpoint->getMenuWithOverrides();

        // Get active offers
        $offers = $this->getActiveOffersForEndpoint($endpoint);

        // Build business info from location, user's default location, or template
        $business = null;
        $location = $endpoint->location;
        
        // If no direct location, try to get user's default location
        if (!$location && $endpoint->user_id) {
            $location = \App\Models\Location::where('user_id', $endpoint->user_id)
                ->where('is_default', true)
                ->first();
            
            // If no default, get first location
            if (!$location) {
                $location = \App\Models\Location::where('user_id', $endpoint->user_id)->first();
            }
        }
        
        if ($location) {
            $business = [
                'name' => $location->name,
                'description' => $location->description,
                'logo_url' => $location->logo_url,
                'phone' => $location->phone,
                'email' => $location->email,
                'website' => $location->website,
                'address' => array_filter([
                    $location->address_line_1,
                    $location->address_line_2,
                    $location->city,
                    $location->state,
                    $location->postal_code,
                    $location->country
                ]),
                'cuisine_type' => $location->cuisine_type,
                'operating_hours' => $location->operating_hours,
                'services' => $location->services,
                'social_media' => $location->social_media,
                'primary_color' => $location->primary_color,
                'secondary_color' => $location->secondary_color,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => $menu,
                'offers' => $offers,
                'business' => $business,
                'endpoint' => [
                    'id' => $endpoint->id,
                    'type' => $endpoint->type,
                    'name' => $endpoint->display_name,
                    'identifier' => $endpoint->identifier,
                ],
                'template' => [
                    'id' => $endpoint->template->id,
                    'name' => $endpoint->template->name,
                    'currency' => $endpoint->template->currency,
                    'settings' => $endpoint->template->settings,
                    'image_url' => $endpoint->template->image_url,
                ],
            ]
        ]);
    }

    /**
     * Get only menu data (lighter response)
     */
    public function getMenuOnly(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template' => function ($q) {
                $q->where('is_active', true);
            }])
            ->first();

        if (!$endpoint || !$endpoint->template) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found or inactive'
            ], 404);
        }

        // Get menu with overrides applied
        $menu = $endpoint->getMenuWithOverrides();

        return response()->json([
            'success' => true,
            'data' => $menu
        ]);
    }

    /**
     * Get active offers for an endpoint
     */
    public function getOffers(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $offers = $this->getActiveOffersForEndpoint($endpoint);

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Record a scan (for analytics)
     */
    public function recordScan(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $endpoint->recordScan();

        return response()->json([
            'success' => true,
            'message' => 'Scan recorded'
        ]);
    }

    /**
     * Get endpoint info (for preview)
     */
    public function getEndpointInfo(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->with('template:id,name,image_url,currency')
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
                'type' => $endpoint->type,
                'type_name' => $endpoint->type_name,
                'name' => $endpoint->display_name,
                'identifier' => $endpoint->identifier,
                'is_active' => $endpoint->is_active,
                'template' => $endpoint->template ? [
                    'id' => $endpoint->template->id,
                    'name' => $endpoint->template->name,
                    'image_url' => $endpoint->template->image_url,
                    'currency' => $endpoint->template->currency,
                ] : null,
            ]
        ]);
    }

    /**
     * Get active offers for an endpoint
     */
    private function getActiveOffersForEndpoint(MenuEndpoint $endpoint): array
    {
        $offers = MenuOffer::where('user_id', $endpoint->user_id)
            ->where(function ($q) use ($endpoint) {
                $q->whereNull('template_id')
                  ->orWhere('template_id', $endpoint->template_id);
            })
            ->valid()
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($offer) use ($endpoint) {
                return $offer->appliesToEndpoint($endpoint->id);
            })
            ->values()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'type' => $offer->offer_type,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'image_url' => $offer->image_url,
                    'badge_text' => $offer->badge_text ?? $offer->type_badge['text'],
                    'badge_color' => $offer->badge_color ?? $offer->type_badge['color'],
                    'discount_type' => $offer->discount_type,
                    'discount_value' => $offer->discount_value,
                    'bundle_price' => $offer->bundle_price,
                    'minimum_order' => $offer->minimum_order,
                    'remaining_time' => $offer->remaining_time,
                    'terms_conditions' => $offer->terms_conditions,
                    'is_featured' => $offer->is_featured,
                ];
            })
            ->toArray();

        return $offers;
    }
}
