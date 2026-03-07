<?php

namespace App\Http\Controllers;

use App\Models\MenuEndpoint;
use App\Models\MenuOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * RecommendationController
 *
 * Provides three recommendation signals for the customer menu experience:
 *
 * 1. trending      – items ordered most in the last 48 hours at this location
 * 2. pairings      – items that appear in the same order as the focused item (co-occurrence)
 * 3. cart_gaps     – items from categories that the customer has NOT yet added to their cart
 *                    (e.g., "you have a burger but no drink")
 * 4. upsells       – higher-priced or featured items in the same category as a given item
 *
 * All signals fall back to static, rules-based logic when there is insufficient
 * order history, so the engine is useful from Day 1 without any data.
 *
 * Feature flags:
 *   recommendation_guide_enabled  (platform_settings) – completely disable this endpoint
 *   mascot_assistant_enabled      (platform_settings) – returned in the response so the
 *                                                        frontend knows whether to show the
 *                                                        mascot UI layer
 */
class RecommendationController extends Controller
{
    // ------------------------------------------------------------------ //
    //  Public endpoint
    // ------------------------------------------------------------------ //

    /**
     * GET /api/menu/{shortCode}/recommendations
     *
     * Query params:
     *   item_id        (int)       – item currently open in the detail modal
     *   cart_item_ids  (int[])     – comma-separated list of item IDs already in cart
     *   limit          (int, 5)    – max results per signal
     */
    public function index(Request $request, string $shortCode): JsonResponse
    {
        // ── Resolve endpoint ──────────────────────────────────────────── //
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template', 'location'])
            ->first();

        if (!$endpoint) {
            return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
        }

        // ── Parse request ─────────────────────────────────────────────── //
        $focusedItemId = (int) $request->query('item_id', 0);
        $cartItemIds   = array_filter(
            array_map('intval', explode(',', $request->query('cart_item_ids', '')))
        );
        $limit = min((int) $request->query('limit', 5), 20);

        // ── Build flat item catalogue ─────────────────────────────────── //
        $menu          = $endpoint->getMenuWithOverrides();
        $allItems      = collect($menu['categories'] ?? [])
                            ->flatMap(fn($cat) => collect($cat['items'] ?? [])->map(fn($item) => array_merge($item, ['category_id' => $cat['id'], 'category_name' => $cat['name']])))
                            ->keyBy('id');

        if ($allItems->isEmpty()) {
            return $this->emptyResponse();
        }

        $locationId = $endpoint->location_id;

        // ── Signals ───────────────────────────────────────────────────── //
        $trending  = $this->getTrending($locationId, $allItems, $cartItemIds, $limit);
        $pairings  = $focusedItemId
                        ? $this->getPairings($locationId, $focusedItemId, $allItems, $cartItemIds, $limit)
                        : [];
        $cartGaps  = $this->getCartGaps($allItems, $cartItemIds, $limit);
        $upsells   = $focusedItemId
                        ? $this->getUpsells($focusedItemId, $allItems, $limit)
                        : [];

        return response()->json([
            'success' => true,
            'data' => [
                'trending'  => $trending,
                'pairings'  => $pairings,
                'cart_gaps' => $cartGaps,
                'upsells'   => $upsells,
            ],
        ]);
    }

    /**
     * GET /api/menu/{shortCode}/recommendations/guide
     *
     * Handles the "I don't know what to get" quick-filter flow.
     *
     * Query params:
     *   mood   (string)  – spicy | light | hearty | drink | dessert | surprise
     *   limit  (int, 6)
     */
    public function guide(Request $request, string $shortCode): JsonResponse
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template', 'location'])
            ->first();

        if (!$endpoint) {
            return response()->json(['success' => false, 'message' => 'Menu not found'], 404);
        }

        $mood  = strtolower($request->query('mood', 'surprise'));
        $limit = min((int) $request->query('limit', 6), 20);

        $menu     = $endpoint->getMenuWithOverrides();
        $allItems = collect($menu['categories'] ?? [])
                        ->flatMap(fn($cat) => collect($cat['items'] ?? [])->map(fn($item) => array_merge($item, ['category_id' => $cat['id'], 'category_name' => $cat['name']])))
                        ->values();

        $available = $allItems->filter(fn($item) => $item['is_available'] !== false);

        $results = match ($mood) {
            'spicy'   => $available->filter(fn($i) => ($i['is_spicy'] ?? false) || ($i['spice_level'] ?? 0) > 0)
                                    ->sortByDesc(fn($i) => $i['is_featured'] ?? false),
            'light'   => $available->filter(fn($i) => $this->itemMatchesMood($i, ['salad', 'soup', 'light', 'appetizer', 'starter', 'juice', 'smoothie'])),
            'hearty'  => $available->filter(fn($i) => $this->itemMatchesMood($i, ['main', 'grill', 'burger', 'rice', 'pasta', 'steak', 'platter', 'seafood'])),
            'drink'   => $available->filter(fn($i) => $this->itemMatchesMood($i, ['drink', 'beverage', 'juice', 'smoothie', 'coffee', 'tea', 'cocktail', 'mocktail', 'soda', 'water'])),
            'dessert' => $available->filter(fn($i) => $this->itemMatchesMood($i, ['dessert', 'sweet', 'cake', 'ice cream', 'pudding', 'pastry', 'waffle'])),
            default   => $available, // surprise — all items
        };

        // If no matches found, fall back to featured items
        if ($results->isEmpty()) {
            $results = $available->filter(fn($i) => $i['is_featured'] ?? false);
        }

        // If still empty, use all available
        if ($results->isEmpty()) {
            $results = $available;
        }

        // Sort: featured first, then by name
        $sorted = $results
            ->sortByDesc(fn($i) => ($i['is_featured'] ?? false) ? 1 : 0)
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'mood'  => $mood,
                'items' => $sorted,
            ],
        ]);
    }

    // ------------------------------------------------------------------ //
    //  Private signal builders
    // ------------------------------------------------------------------ //

    /**
     * Items ordered most in the last 48 hours at this location.
     * Falls back to featured items when there is little order history.
     */
    private function getTrending(
        ?int $locationId,
        \Illuminate\Support\Collection $allItems,
        array $excludeItemIds,
        int $limit
    ): array {
        if (!$locationId) {
            return $this->featuredFallback($allItems, $excludeItemIds, $limit);
        }

        $cacheKey = "trending_items_{$locationId}";

        $scores = Cache::remember($cacheKey, 1800, function () use ($locationId) {
            $scores = [];

            MenuOrder::where('location_id', $locationId)
                ->where('created_at', '>=', now()->subHours(48))
                ->whereNotIn('status', ['cancelled'])
                ->get(['items'])
                ->each(function ($order) use (&$scores) {
                    foreach ($order->items as $item) {
                        $id = (int) ($item['id'] ?? 0);
                        if ($id > 0) {
                            $scores[$id] = ($scores[$id] ?? 0) + ($item['quantity'] ?? 1);
                        }
                    }
                });

            return $scores;
        });

        if (empty($scores)) {
            return $this->featuredFallback($allItems, $excludeItemIds, $limit);
        }

        return $allItems
            ->filter(fn($item) => ($item['is_available'] ?? true) && !in_array($item['id'], $excludeItemIds))
            ->filter(fn($item) => isset($scores[$item['id']]))
            ->sortByDesc(fn($item) => $scores[$item['id']] ?? 0)
            ->take($limit)
            ->values()
            ->map(fn($item) => $this->formatItem($item, ['order_count' => $scores[$item['id']] ?? 0]))
            ->toArray();
    }

    /**
     * Items that appear in the same orders as $focusedItemId.
     * Uses a co-occurrence cache per location (refreshed every 6 hours).
     */
    private function getPairings(
        ?int $locationId,
        int $focusedItemId,
        \Illuminate\Support\Collection $allItems,
        array $excludeItemIds,
        int $limit
    ): array {
        if (!$locationId) {
            return $this->sameCategoryFallback($focusedItemId, $allItems, $excludeItemIds, $limit);
        }

        $cacheKey = "item_pairings_{$locationId}_{$focusedItemId}";

        $pairedIds = Cache::remember($cacheKey, 21600, function () use ($locationId, $focusedItemId) {
            $counts = [];

            MenuOrder::where('location_id', $locationId)
                ->whereNotIn('status', ['cancelled'])
                ->get(['items'])
                ->each(function ($order) use ($focusedItemId, &$counts) {
                    $ids = collect($order->items)->pluck('id')->map('intval')->toArray();

                    if (!in_array($focusedItemId, $ids)) {
                        return;
                    }

                    foreach ($ids as $id) {
                        if ($id !== $focusedItemId) {
                            $counts[$id] = ($counts[$id] ?? 0) + 1;
                        }
                    }
                });

            arsort($counts);
            return array_keys($counts);
        });

        if (empty($pairedIds)) {
            return $this->sameCategoryFallback($focusedItemId, $allItems, $excludeItemIds, $limit);
        }

        $allExclude = array_merge($excludeItemIds, [$focusedItemId]);

        return $allItems
            ->filter(fn($item) => ($item['is_available'] ?? true) && !in_array($item['id'], $allExclude) && in_array($item['id'], $pairedIds))
            ->sortBy(fn($item) => array_search($item['id'], $pairedIds))
            ->take($limit)
            ->values()
            ->map(fn($item) => $this->formatItem($item))
            ->toArray();
    }

    /**
     * Returns items from categories NOT currently represented in the cart.
     * e.g., cart has burgers & fries but no drink → suggest drinks.
     */
    private function getCartGaps(
        \Illuminate\Support\Collection $allItems,
        array $cartItemIds,
        int $limit
    ): array {
        if (empty($cartItemIds)) {
            return [];
        }

        // Find category IDs already in cart
        $cartCategoryIds = $allItems
            ->filter(fn($item) => in_array($item['id'], $cartItemIds))
            ->pluck('category_id')
            ->unique()
            ->toArray();

        return $allItems
            ->filter(fn($item) =>
                ($item['is_available'] ?? true) &&
                !in_array($item['id'], $cartItemIds) &&
                !in_array($item['category_id'], $cartCategoryIds)
            )
            ->sortByDesc(fn($item) => ($item['is_featured'] ?? false) ? 2 : (($item['compare_at_price'] ?? null) ? 1 : 0))
            ->take($limit)
            ->values()
            ->map(fn($item) => $this->formatItem($item))
            ->toArray();
    }

    /**
     * Items in the same category that are priced higher or marked featured —
     * "upgrade" nudges.
     */
    private function getUpsells(
        int $focusedItemId,
        \Illuminate\Support\Collection $allItems,
        int $limit
    ): array {
        $focusedItem = $allItems->get($focusedItemId);
        if (!$focusedItem) {
            return [];
        }

        $focusedPrice      = (float) ($focusedItem['price'] ?? 0);
        $focusedCategoryId = $focusedItem['category_id'] ?? null;

        return $allItems
            ->filter(fn($item) =>
                $item['id'] !== $focusedItemId &&
                ($item['is_available'] ?? true) &&
                $item['category_id'] === $focusedCategoryId &&
                // either higher price (upgrade) or has a variation (size upgrade)
                (
                    (float) ($item['price'] ?? 0) > $focusedPrice ||
                    ($item['is_featured'] ?? false)
                )
            )
            ->sortBy(fn($item) => abs((float) ($item['price'] ?? 0) - $focusedPrice))
            ->take($limit)
            ->values()
            ->map(fn($item) => $this->formatItem($item, [
                'price_diff' => round((float) ($item['price'] ?? 0) - $focusedPrice, 2),
            ]))
            ->toArray();
    }

    // ------------------------------------------------------------------ //
    //  Fallback helpers
    // ------------------------------------------------------------------ //

    private function featuredFallback(
        \Illuminate\Support\Collection $allItems,
        array $excludeItemIds,
        int $limit
    ): array {
        return $allItems
            ->filter(fn($item) => ($item['is_available'] ?? true) && !in_array($item['id'], $excludeItemIds) && ($item['is_featured'] ?? false))
            ->take($limit)
            ->values()
            ->map(fn($item) => $this->formatItem($item))
            ->toArray();
    }

    private function sameCategoryFallback(
        int $focusedItemId,
        \Illuminate\Support\Collection $allItems,
        array $excludeItemIds,
        int $limit
    ): array {
        $focusedItem = $allItems->get($focusedItemId);
        if (!$focusedItem) {
            return [];
        }

        $catId      = $focusedItem['category_id'] ?? null;
        $allExclude = array_merge($excludeItemIds, [$focusedItemId]);

        return $allItems
            ->filter(fn($item) =>
                ($item['is_available'] ?? true) &&
                !in_array($item['id'], $allExclude) &&
                $item['category_id'] === $catId
            )
            ->sortByDesc(fn($item) => $item['is_featured'] ?? false)
            ->take($limit)
            ->values()
            ->map(fn($item) => $this->formatItem($item))
            ->toArray();
    }

    // ------------------------------------------------------------------ //
    //  Utilities
    // ------------------------------------------------------------------ //

    private function formatItem(array $item, array $extra = []): array
    {
        return array_merge([
            'id'            => $item['id'],
            'name'          => $item['name'],
            'description'   => $item['description'] ?? null,
            'price'         => $item['price'],
            'image_url'     => $item['image_url'] ?? null,
            'icon'          => $item['icon'] ?? null,
            'is_featured'   => $item['is_featured'] ?? false,
            'is_spicy'      => $item['is_spicy'] ?? false,
            'category_id'   => $item['category_id'],
            'category_name' => $item['category_name'],
            'variations'    => $item['variations'] ?? [],
        ], $extra);
    }

    /**
     * Match an item against mood keywords by searching across
     * category name, item name, AND description — making the guide
     * smart enough to find e.g. "grilled salmon" tagged as Fish, not Seafood.
     */
    private function itemMatchesMood(array $item, array $keywords): bool
    {
        $haystack = strtolower(
            ($item['category_name'] ?? '') . ' ' .
            ($item['name'] ?? '') . ' ' .
            ($item['description'] ?? '')
        );
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function categoryMatchesKeywords(string $categoryName, array $keywords): bool
    {
        $lower = strtolower($categoryName);
        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function emptyResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'trending'  => [],
                'pairings'  => [],
                'cart_gaps' => [],
                'upsells'   => [],
            ],
        ]);
    }
}
