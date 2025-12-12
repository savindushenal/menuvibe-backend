<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use App\Models\FranchiseBranch;
use App\Models\MasterMenu;
use App\Models\MasterMenuCategory;
use App\Models\MasterMenuItem;
use App\Models\MasterMenuOffer;
use App\Models\BranchMenuOverride;
use App\Models\MenuSyncLog;
use App\Models\MenuImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MasterMenuController extends Controller
{
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

        $item->update($data);

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

        $item->delete();

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
     * Push master menu to all branches
     */
    public function syncToAllBranches(Request $request, int $franchiseId, int $menuId)
    {
        $menu = MasterMenu::where('franchise_id', $franchiseId)
            ->where('id', $menuId)
            ->with(['categories.items'])
            ->first();

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $branches = FranchiseBranch::where('franchise_id', $franchiseId)
            ->where('is_active', true)
            ->get();

        if ($branches->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active branches found'
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
            $itemsSynced = 0;
            $categoriesSynced = $menu->categories->count();

            // The "sync" here means making the master menu available to all branches
            // Branch overrides are preserved - we just update the last_synced_at timestamp
            foreach ($menu->items as $item) {
                $itemsSynced++;
            }

            // Update menu sync timestamp
            $menu->update(['last_synced_at' => now()]);

            $syncLog->update([
                'status' => 'completed',
                'items_synced' => $itemsSynced,
                'categories_synced' => $categoriesSynced,
                'completed_at' => now(),
                'changes' => [
                    'branches_count' => $branches->count(),
                    'items_count' => $itemsSynced,
                    'categories_count' => $categoriesSynced,
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => "Menu synced to {$branches->count()} branches successfully",
                'data' => [
                    'branches_synced' => $branches->count(),
                    'items_synced' => $itemsSynced,
                    'categories_synced' => $categoriesSynced,
                    'sync_log_id' => $syncLog->id,
                ]
            ]);
        } catch (\Exception $e) {
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

        // Get all branches for this franchise
        $branches = FranchiseBranch::where('franchise_id', $franchiseId)
            ->where('is_active', true)
            ->with('location:id,name')
            ->get();

        // Get sync logs for each branch
        $branchStatuses = $branches->map(function ($branch) use ($menuId) {
            // Get the most recent sync log for this branch and menu
            $lastSync = MenuSyncLog::where('master_menu_id', $menuId)
                ->where(function($q) use ($branch) {
                    $q->where('branch_id', $branch->id)
                      ->orWhereNull('branch_id'); // Global syncs count for all branches
                })
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();

            // Count overrides for this branch
            $overridesCount = BranchMenuOverride::where('branch_id', $branch->id)
                ->whereHas('masterItem', function ($q) use ($menuId) {
                    $q->where('master_menu_id', $menuId);
                })
                ->count();

            // Count items synced (items in master menu)
            $itemsCount = MasterMenuItem::where('master_menu_id', $menuId)->count();

            return [
                'id' => $branch->id,
                'name' => $branch->branch_name,
                'location_name' => $branch->location?->name ?? $branch->city ?? 'Unknown',
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
                'branches' => $branchStatuses,
                'total_branches' => $branches->count(),
                'synced_branches' => $branchStatuses->filter(fn($b) => $b['is_synced'])->count(),
            ]
        ]);
    }

    // ===========================================
    // BRANCH OVERRIDES
    // ===========================================

    /**
     * Get branch overrides for a menu
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

        $overrides = BranchMenuOverride::where('branch_id', $branchId)
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
     * Set a branch override for an item
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
        $data['branch_id'] = $branchId;

        $override = BranchMenuOverride::updateOrCreate(
            [
                'branch_id' => $branchId,
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
     * Remove a branch override
     */
    public function removeBranchOverride(Request $request, int $franchiseId, int $menuId, int $branchId, int $itemId)
    {
        BranchMenuOverride::where('branch_id', $branchId)
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
