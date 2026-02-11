<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use App\Models\MasterMenu;
use App\Models\MasterMenuCategory;
use App\Models\MasterMenuItem;
use App\Models\MasterMenuOffer;
use App\Models\BranchMenuOverride;
use App\Models\MenuSyncLog;
use App\Models\MenuImage;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Location;
use App\Services\MenuSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MasterMenuController extends Controller
{
    private MenuSyncService $syncService;

    public function __construct(MenuSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    // ===========================================
    // MASTER MENUS
    // ===========================================

    /**
     * Get all master menus for a franchise
     */
    public function index(Request $request, int $franchiseId)
    {
        $menus = MasterMenu::where('franchise_id', $franchiseId)
            ->with(['categories.items', 'activeOffers'])
            ->withCount(['categories', 'items'])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus
        ]);
    }

    /**
     * Create a new master menu
     */
    public function store(Request $request, int $franchiseId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|url',
            'currency' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'availability_hours' => 'nullable|array',
            'settings' => 'nullable|array',
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
        $data['created_by'] = $request->user()->id;
        $data['slug'] = Str::slug($data['name']);

        // If this is set as default, unset other defaults
        if (!empty($data['is_default'])) {
            MasterMenu::where('franchise_id', $franchiseId)
                ->update(['is_default' => false]);
        }

        $menu = MasterMenu::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Master menu created successfully',
            'data' => $menu->load('categories.items')
        ], 201);
    }

    /**
     * Get a specific master menu
     */
    public function show(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->with(['categories.items', 'offers', 'syncLogs' => function ($q) {
                $q->latest()->limit(10);
            }])
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $menu
        ]);
    }

    /**
     * Update a master menu
     */
    public function update(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'availability_hours' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = $request->user()->id;

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // If this is set as default, unset other defaults
        if (!empty($data['is_default'])) {
            MasterMenu::where('franchise_id', $franchiseId)
                ->where('id', '!=', $menuId)
                ->update(['is_default' => false]);
        }

        $menu->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Master menu updated successfully',
            'data' => $menu->fresh()->load('categories.items')
        ]);
    }

    /**
     * Delete a master menu
     */
    public function destroy(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master menu deleted successfully'
        ]);
    }

    // ===========================================
    // CATEGORIES
    // ===========================================

    /**
     * Add a category to a master menu
     */
    public function addCategory(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'background_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['master_menu_id'] = $menuId;
        $data['slug'] = Str::slug($data['name']);

        // Auto-assign sort order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = $menu->categories()->max('sort_order') + 1;
        }

        $category = MasterMenuCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Store a category (alias for addCategory)
     */
    public function storeCategory(Request $request, int $franchiseId, int $menuId)
    {
        return $this->addCategory($request, $franchiseId, $menuId);
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, int $franchiseId, int $menuId, int $categoryId)
    {
        $category = MasterMenuCategory::whereHas('masterMenu', function ($q) use ($franchiseId) {
            $q->where('franchise_id', $franchiseId);
        })->where('id', $categoryId)->where('master_menu_id', $menuId)->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'background_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category->fresh()
        ]);
    }

    /**
     * Delete a category
     */
    public function deleteCategory(Request $request, int $franchiseId, int $menuId, int $categoryId)
    {
        $category = MasterMenuCategory::whereHas('masterMenu', function ($q) use ($franchiseId) {
            $q->where('franchise_id', $franchiseId);
        })->where('id', $categoryId)->where('master_menu_id', $menuId)->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Destroy a category (alias for deleteCategory)
     */
    public function destroyCategory(Request $request, int $franchiseId, int $menuId, int $categoryId)
    {
        return $this->deleteCategory($request, $franchiseId, $menuId, $categoryId);
    }

    // ===========================================
    // ITEMS
    // ===========================================

    /**
     * Add an item to a category
     */
    public function addItem(Request $request, int $franchiseId, int $menuId, int $categoryId)
    {
        $category = MasterMenuCategory::whereHas('masterMenu', function ($q) use ($franchiseId) {
            $q->where('franchise_id', $franchiseId);
        })->where('id', $categoryId)->where('master_menu_id', $menuId)->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'image_url' => 'nullable|string',
            'gallery_images' => 'nullable|array',
            'card_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'heading_color' => 'nullable|string|max:20',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'allergens' => 'nullable|array',
            'dietary_info' => 'nullable|array',
            'preparation_time' => 'nullable|integer',
            'is_spicy' => 'nullable|boolean',
            'spice_level' => 'nullable|integer|min:1|max:5',
            'variations' => 'nullable|array',
            'addons' => 'nullable|array',
            'sku' => 'nullable|string|max:100',
            'calories' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['master_menu_id'] = $menuId;
        $data['category_id'] = $categoryId;
        $data['slug'] = Str::slug($data['name']);

        // Auto-assign sort order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = $category->items()->max('sort_order') + 1;
        }

        $item = MasterMenuItem::create($data);

        // Create version for this change
        $masterMenu = $category->masterMenu;
        $this->syncService->createVersion(
            $masterMenu,
            MenuSyncService::CHANGE_ITEM_ADDED,
            ['added_items' => [$item->id], 'item_name' => $item->name],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Item added successfully',
            'data' => $item
        ], 201);
    }

    /**
     * Alias for addItem - used by routes
     */
    public function storeItem(Request $request, int $franchiseId, int $menuId, int $categoryId)
    {
        return $this->addItem($request, $franchiseId, $menuId, $categoryId);
    }

    /**
     * Update an item
     */
    public function updateItem(Request $request, int $franchiseId, int $menuId, int $itemId)
    {
        $item = MasterMenuItem::whereHas('masterMenu', function ($q) use ($franchiseId) {
            $q->where('franchise_id', $franchiseId);
        })->where('id', $itemId)->where('master_menu_id', $menuId)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'image_url' => 'nullable|string',
            'gallery_images' => 'nullable|array',
            'category_id' => 'sometimes|exists:master_menu_categories,id',
            'card_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'heading_color' => 'nullable|string|max:20',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'allergens' => 'nullable|array',
            'dietary_info' => 'nullable|array',
            'preparation_time' => 'nullable|integer',
            'is_spicy' => 'nullable|boolean',
            'spice_level' => 'nullable|integer|min:1|max:5',
            'variations' => 'nullable|array',
            'addons' => 'nullable|array',
            'sku' => 'nullable|string|max:100',
            'calories' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Track what changed for version control
        $changedFields = [];
        $priceChanged = isset($data['price']) && $data['price'] != $item->price;
        foreach ($data as $key => $value) {
            if ($item->$key != $value) {
                $changedFields[$key] = ['old' => $item->$key, 'new' => $value];
            }
        }

        $item->update($data);

        // Create version for this change (only if something actually changed)
        if (!empty($changedFields)) {
            $changeType = $priceChanged 
                ? MenuSyncService::CHANGE_PRICE_CHANGED 
                : MenuSyncService::CHANGE_ITEM_UPDATED;
            
            $masterMenu = MasterMenu::find($menuId);
            $this->syncService->createVersion(
                $masterMenu,
                $changeType,
                [
                    'updated_items' => [$item->id => $changedFields],
                    'price_changes' => $priceChanged ? [$item->id => $data['price']] : [],
                    'item_name' => $item->name
                ],
                $request->user()
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'data' => $item->fresh()
        ]);
    }

    /**
     * Delete an item
     */
    public function deleteItem(Request $request, int $franchiseId, int $menuId, int $itemId)
    {
        $item = MasterMenuItem::whereHas('masterMenu', function ($q) use ($franchiseId) {
            $q->where('franchise_id', $franchiseId);
        })->where('id', $itemId)->where('master_menu_id', $menuId)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $itemId = $item->id;
        $itemName = $item->name;
        $masterMenu = MasterMenu::find($menuId);
        
        $item->delete();

        // Create version for this change
        $this->syncService->createVersion(
            $masterMenu,
            MenuSyncService::CHANGE_ITEM_REMOVED,
            ['removed_items' => [$itemId], 'item_name' => $itemName],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    }

    /**
     * Bulk update items (for sorting, availability, etc.)
     */
    public function bulkUpdateItems(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:master_menu_items,id',
            'items.*.sort_order' => 'nullable|integer',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.category_id' => 'nullable|exists:master_menu_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($request, $menuId) {
            foreach ($request->items as $itemData) {
                MasterMenuItem::where('id', $itemData['id'])
                    ->where('master_menu_id', $menuId)
                    ->update(array_filter([
                        'sort_order' => $itemData['sort_order'] ?? null,
                        'is_available' => $itemData['is_available'] ?? null,
                        'category_id' => $itemData['category_id'] ?? null,
                    ], fn($v) => $v !== null));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Items updated successfully'
        ]);
    }

    // ===========================================
    // SYNC TO BRANCHES
    // ===========================================

    /**
     * Push master menu to all branches - creates/updates actual Menu records
     * Now uses Location directly (unified with FranchiseBranch)
     */
    public function syncToAllBranches(Request $request, int $franchiseId, int $menuId)
    {
        $masterMenu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->with(['categories.items'])
            ->first();

        if (!$masterMenu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // Get all active franchise locations (branches) directly
        $locations = Location::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active branch locations found'
            ], 400);
        }

        // Create sync log
        $syncLog = MenuSyncLog::create([
            'master_menu_id' => $menuId,
            'sync_type' => 'full',
            'status' => 'in_progress',
            'synced_by' => $request->user()->id,
            'started_at' => now(),
        ]);

        try {
            DB::beginTransaction();
            
            $locationsSynced = 0;
            $itemsSynced = 0;
            $categoriesSynced = 0;

            foreach ($locations as $location) {
                // Find or create menu for this location
                $menu = Menu::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'slug' => Str::slug($masterMenu->name),
                    ],
                    [
                        'name' => $masterMenu->name,
                        'description' => $masterMenu->description,
                        'currency' => $masterMenu->currency ?? 'USD',
                        'is_active' => $masterMenu->is_active,
                        'image_url' => $masterMenu->image_url,
                        'availability_hours' => $masterMenu->availability_hours,
                        'settings' => $masterMenu->settings,
                    ]
                );

                // Map to track category IDs (master -> local)
                $categoryMap = [];

                // Sync categories
                foreach ($masterMenu->categories as $masterCategory) {
                    $localCategory = MenuCategory::updateOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'slug' => $masterCategory->slug,
                        ],
                        [
                            'name' => $masterCategory->name,
                            'description' => $masterCategory->description,
                            'image_url' => $masterCategory->image_url,
                            'icon' => $masterCategory->icon,
                            'background_color' => $masterCategory->background_color,
                            'text_color' => $masterCategory->text_color,
                            'sort_order' => $masterCategory->sort_order,
                            'is_active' => $masterCategory->is_active,
                        ]
                    );
                    
                    $categoryMap[$masterCategory->id] = $localCategory->id;
                    $categoriesSynced++;

                    // Sync items in this category
                    foreach ($masterCategory->items as $masterItem) {
                        // Check for branch-specific overrides
                        $override = BranchMenuOverride::where('location_id', $location->id)
                            ->where('master_item_id', $masterItem->id)
                            ->first();

                        $itemData = [
                            'name' => $masterItem->name,
                            'description' => $masterItem->description,
                            'price' => $override?->price_override ?? $masterItem->price,
                            'compare_at_price' => $masterItem->compare_at_price,
                            'currency' => $masterItem->currency ?? $masterMenu->currency ?? 'USD',
                            'image_url' => $masterItem->image_url,
                            'gallery_images' => $masterItem->gallery_images,
                            'card_color' => $masterItem->card_color,
                            'text_color' => $masterItem->text_color,
                            'heading_color' => $masterItem->heading_color,
                            'is_available' => $override?->is_available ?? $masterItem->is_available,
                            'is_featured' => $override?->is_featured ?? $masterItem->is_featured,
                            'sort_order' => $masterItem->sort_order,
                            'allergens' => $masterItem->allergens,
                            'dietary_info' => $masterItem->dietary_info,
                            'preparation_time' => $masterItem->preparation_time,
                            'is_spicy' => $masterItem->is_spicy,
                            'spice_level' => $masterItem->spice_level,
                            'variations' => $override?->variation_prices ?? $masterItem->variations,
                            'addons' => $masterItem->addons,
                            'sku' => $masterItem->sku,
                            'calories' => $masterItem->calories,
                            'category_id' => $localCategory->id,
                        ];

                        MenuItem::updateOrCreate(
                            [
                                'menu_id' => $menu->id,
                                'slug' => $masterItem->slug,
                            ],
                            $itemData
                        );

                        $itemsSynced++;
                    }
                }

                $locationsSynced++;
            }

            // Update master menu sync timestamp
            $masterMenu->update(['last_synced_at' => now()]);

            DB::commit();

            // Update sync log
            $syncLog->update([
                'status' => 'completed',
                'items_synced' => $itemsSynced,
                'categories_synced' => $categoriesSynced,
                'completed_at' => now(),
                'changes' => [
                    'locations_synced' => $locationsSynced,
                    'categories_synced' => $categoriesSynced,
                    'items_synced' => $itemsSynced,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Menu synced to {$locationsSynced} location(s) successfully",
                'data' => [
                    'locations_synced' => $locationsSynced,
                    'categories_synced' => $categoriesSynced,
                    'items_synced' => $itemsSynced,
                    'sync_log_id' => $syncLog->id,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $syncLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias for syncToAllBranches - used by routes
     */
    public function syncToBranches(Request $request, int $franchiseId, int $menuId)
    {
        return $this->syncToAllBranches($request, $franchiseId, $menuId);
    }

    /**
     * Sync master menu to a single branch
     */
    /**
     * Sync master menu to a single location (branch)
     * Now uses Location directly (unified with FranchiseBranch)
     * @param int $branchId - This is now actually location_id
     */
    public function syncToSingleBranch(Request $request, int $franchiseId, int $menuId, int $branchId)
    {
        $masterMenu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->with(['categories.items'])
            ->first();

        if (!$masterMenu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // branchId is now actually location_id in the unified model
        $location = Location::where('franchise_id', $franchiseId)
            ->where('id', $branchId)
            ->whereNotNull('branch_code')
            ->where('is_active', true)
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Branch location not found or inactive'
            ], 404);
        }

        // Create sync log
        $syncLog = MenuSyncLog::create([
            'master_menu_id' => $menuId,
            'location_id' => $location->id,
            'sync_type' => 'single',
            'status' => 'in_progress',
            'synced_by' => $request->user()->id,
            'started_at' => now(),
        ]);

        try {
            DB::beginTransaction();
            
            $itemsSynced = 0;
            $categoriesSynced = 0;

            // Find or create menu for this location
            $menu = Menu::updateOrCreate(
                [
                    'location_id' => $location->id,
                    'slug' => Str::slug($masterMenu->name),
                ],
                [
                    'name' => $masterMenu->name,
                    'description' => $masterMenu->description,
                    'currency' => $masterMenu->currency ?? 'USD',
                    'is_active' => $masterMenu->is_active,
                    'image_url' => $masterMenu->image_url,
                    'availability_hours' => $masterMenu->availability_hours,
                    'settings' => $masterMenu->settings,
                ]
            );

            // Map to track category IDs
            $categoryMap = [];

            // Sync categories
            foreach ($masterMenu->categories as $masterCategory) {
                $category = MenuCategory::updateOrCreate(
                    [
                        'menu_id' => $menu->id,
                        'slug' => Str::slug($masterCategory->name),
                    ],
                    [
                        'name' => $masterCategory->name,
                        'description' => $masterCategory->description,
                        'icon' => $masterCategory->icon,
                        'image_url' => $masterCategory->image_url,
                        'background_color' => $masterCategory->background_color,
                        'text_color' => $masterCategory->text_color,
                        'is_active' => $masterCategory->is_active ?? true,
                        'sort_order' => $masterCategory->sort_order,
                    ]
                );
                
                $categoryMap[$masterCategory->id] = $category->id;
                $categoriesSynced++;

                // Sync items
                foreach ($masterCategory->items as $masterItem) {
                    // Check for location-specific override
                    $override = BranchMenuOverride::where('location_id', $location->id)
                        ->where('master_item_id', $masterItem->id)
                        ->first();

                    $itemData = [
                        'name' => $masterItem->name,
                        'description' => $masterItem->description,
                        'price' => $override?->price_override ?? $masterItem->price,
                        'compare_at_price' => $masterItem->compare_at_price,
                        'currency' => $masterItem->currency ?? $masterMenu->currency ?? 'USD',
                        'image_url' => $masterItem->image_url,
                        'gallery_images' => $masterItem->gallery_images,
                        'card_color' => $masterItem->card_color,
                        'text_color' => $masterItem->text_color,
                        'heading_color' => $masterItem->heading_color,
                        'is_available' => $override?->is_available ?? $masterItem->is_available,
                        'is_featured' => $override?->is_featured ?? $masterItem->is_featured,
                        'sort_order' => $masterItem->sort_order,
                        'allergens' => $masterItem->allergens,
                        'dietary_info' => $masterItem->dietary_info,
                        'preparation_time' => $masterItem->preparation_time,
                        'is_spicy' => $masterItem->is_spicy,
                        'spice_level' => $masterItem->spice_level,
                        'variations' => $override?->variation_prices ?? $masterItem->variations,
                        'addons' => $masterItem->addons,
                        'sku' => $masterItem->sku,
                        'calories' => $masterItem->calories,
                        'category_id' => $category->id,
                    ];

                    MenuItem::updateOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'slug' => $masterItem->slug,
                        ],
                        $itemData
                    );
                    
                    $itemsSynced++;
                }
            }

            // Remove old categories and items
            $localCategoryIds = array_values($categoryMap);
            MenuItem::where('menu_id', $menu->id)
                ->whereNotIn('category_id', $localCategoryIds)
                ->delete();
            MenuCategory::where('menu_id', $menu->id)
                ->whereNotIn('id', $localCategoryIds)
                ->delete();

            DB::commit();

            $syncLog->update([
                'status' => 'completed',
                'items_synced' => $itemsSynced,
                'categories_synced' => $categoriesSynced,
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Menu synced to {$location->branch_name} successfully",
                'data' => [
                    'location_id' => $location->id,
                    'branch_id' => $location->id, // For backwards compatibility
                    'branch_name' => $branch->branch_name,
                    'items_synced' => $itemsSynced,
                    'categories_synced' => $categoriesSynced,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            $syncLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync history for a menu
     */
    public function getSyncHistory(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $logs = MenuSyncLog::where('master_menu_id', $menuId)
            ->with(['branch:id,branch_name', 'syncedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Get sync status for a menu with all branches
     */
    public function getSyncStatus(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // Get all branch locations for this franchise (unified model)
        $locations = Location::where('franchise_id', $franchiseId)
            ->whereNotNull('branch_code')
            ->where('is_active', true)
            ->get();

        // Get sync logs for each location
        $locationStatuses = $locations->map(function ($location) use ($menuId) {
            // Get the most recent sync log for this location and menu
            $lastSync = MenuSyncLog::where('master_menu_id', $menuId)
                ->where(function($q) use ($location) {
                    $q->where('location_id', $location->id)
                      ->orWhereNull('location_id'); // Global syncs count for all locations
                })
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();

            // Count overrides for this location
            $overridesCount = BranchMenuOverride::where('location_id', $location->id)
                ->whereHas('masterItem', function ($q) use ($menuId) {
                    $q->where('master_menu_id', $menuId);
                })
                ->count();

            // Count items synced (items in master menu)
            $itemsCount = MasterMenuItem::where('master_menu_id', $menuId)->count();

            return [
                'id' => $location->id,
                'name' => $location->branch_name ?? $location->name,
                'location_name' => $location->name,
                'city' => $location->city,
                'last_synced_at' => $lastSync?->completed_at,
                'is_synced' => $lastSync !== null,
                'items_synced' => $itemsCount,
                'has_overrides' => $overridesCount > 0,
                'overrides_count' => $overridesCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'menu' => [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'last_synced_at' => $menu->last_synced_at,
                ],
                'branches' => $locationStatuses, // Keep key as 'branches' for backwards compatibility
                'locations' => $locationStatuses,
                'total_branches' => $locations->count(),
                'synced_branches' => $locationStatuses->filter(fn($b) => $b['is_synced'])->count(),
            ]
        ]);
    }

    // ===========================================
    // BRANCH/LOCATION OVERRIDES
    // ===========================================

    /**
     * Get branch/location overrides for a menu
     * @param int $branchId - Now refers to location_id in unified model
     */
    public function getBranchOverrides(Request $request, int $franchiseId, int $menuId, int $branchId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // branchId is now location_id in the unified model
        $overrides = BranchMenuOverride::where('location_id', $branchId)
            ->whereHas('masterItem', function ($q) use ($menuId) {
                $q->where('master_menu_id', $menuId);
            })
            ->with('masterItem:id,name,price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $overrides
        ]);
    }

    /**
     * Set a branch/location override for an item
     * @param int $branchId - Now refers to location_id in unified model
     */
    public function setBranchOverride(Request $request, int $franchiseId, int $menuId, int $branchId)
    {
        $validator = Validator::make($request->all(), [
            'master_item_id' => 'required|exists:master_menu_items,id',
            'price_override' => 'nullable|numeric|min:0',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'variation_prices' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['location_id'] = $branchId; // Use location_id in unified model

        $override = BranchMenuOverride::updateOrCreate(
            [
                'location_id' => $branchId,
                'master_item_id' => $data['master_item_id'],
            ],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Override saved successfully',
            'data' => $override
        ]);
    }

    /**
     * Remove a branch/location override
     * @param int $branchId - Now refers to location_id in unified model
     */
    public function removeBranchOverride(Request $request, int $franchiseId, int $menuId, int $branchId, int $itemId)
    {
        BranchMenuOverride::where('location_id', $branchId)
            ->where('master_item_id', $itemId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Override removed successfully'
        ]);
    }

    // ===========================================
    // IMAGE UPLOAD
    // ===========================================

    /**
     * Upload an image
     */
    public function uploadImage(Request $request, int $franchiseId)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'type' => 'nullable|in:item,category,menu,offer,gallery',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('image');
            $originalFilename = $file->getClientOriginalName();
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // For now, store locally. In production, use Vercel Blob or S3
            $path = $file->storeAs('menu-images', $filename, 'public');
            $url = asset('storage/' . $path);

            // Get image dimensions
            $imageInfo = getimagesize($file->getPathname());
            $width = $imageInfo[0] ?? null;
            $height = $imageInfo[1] ?? null;

            $menuImage = MenuImage::create([
                'franchise_id' => $franchiseId,
                'user_id' => $request->user()->id,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'url' => $url,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'alt_text' => $request->alt_text,
                'type' => $request->type ?? 'item',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => $menuImage
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get image gallery for franchise
     */
    public function getImageGallery(Request $request, int $franchiseId)
    {
        $type = $request->query('type');

        $query = MenuImage::where('franchise_id', $franchiseId)
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        $images = $query->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }

    /**
     * Delete an image
     */
    public function deleteImage(Request $request, int $franchiseId, int $imageId)
    {
        $image = MenuImage::where('franchise_id', $franchiseId)
            ->where('id', $imageId)
            ->first();

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }

        // Delete file from storage
        $path = str_replace(asset('storage/'), '', $image->url);
        \Storage::disk('public')->delete($path);

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    }
}
