<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\MenuTemplate;
use App\Models\MenuTemplateCategory;
use App\Models\MenuTemplateItem;
use App\Models\Scopes\FranchiseScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminFranchiseMenuController extends Controller
{
    /**
     * List all menu templates for a franchise
     */
    public function indexTemplates(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $query = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->withCount(['categories', 'items']);

        // Search
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $templates = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
            'franchise' => [
                'id' => $franchise->id,
                'name' => $franchise->name,
            ],
        ]);
    }

    /**
     * Get menu template details with categories and items
     */
    public function showTemplate($franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->with(['categories.items', 'franchise'])
            ->findOrFail($templateId);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Create new menu template
     */
    public function storeTemplate(Request $request, $franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:standard,premium',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $template = MenuTemplate::create([
            'franchise_id' => $franchiseId,
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type ?? 'standard',
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu template created successfully',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update menu template
     */
    public function updateTemplate(Request $request, $franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($templateId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:standard,premium',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $template->update($request->only([
            'name',
            'description',
            'type',
            'is_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Menu template updated successfully',
            'data' => $template->fresh(),
        ]);
    }

    /**
     * Delete menu template
     */
    public function destroyTemplate($franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($templateId);

        // Check if template is being used by endpoints
        $endpointsCount = $template->endpoints()->count();
        if ($endpointsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete template. It is being used by {$endpointsCount} endpoint(s).",
            ], Response::HTTP_CONFLICT);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu template deleted successfully',
        ]);
    }

    /**
     * List categories for a menu template
     */
    public function indexCategories(Request $request, $franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($templateId);

        $categories = MenuTemplateCategory::withoutGlobalScope(FranchiseScope::class)
            ->where('menu_template_id', $templateId)
            ->withCount('items')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
            ],
        ]);
    }

    /**
     * Create menu category
     */
    public function storeCategory(Request $request, $franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($templateId);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = MenuTemplateCategory::create([
            'menu_template_id' => $templateId,
            'franchise_id' => $franchiseId,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'icon' => $request->icon,
            'sort_order' => $request->sort_order ?? 999,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update category
     */
    public function updateCategory(Request $request, $franchiseId, $templateId, $categoryId)
    {
        $category = MenuTemplateCategory::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('menu_template_id', $templateId)
            ->findOrFail($categoryId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updateData = $request->only([
            'name',
            'description',
            'icon',
            'sort_order',
            'is_active',
        ]);

        if (isset($updateData['name'])) {
            $updateData['slug'] = Str::slug($updateData['name']);
        }

        $category->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Delete category
     */
    public function destroyCategory($franchiseId, $templateId, $categoryId)
    {
        $category = MenuTemplateCategory::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('menu_template_id', $templateId)
            ->findOrFail($categoryId);

        // Check if category has items
        $itemsCount = $category->items()->count();
        if ($itemsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete category. It contains {$itemsCount} item(s).",
            ], Response::HTTP_CONFLICT);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * List menu items
     */
    public function indexItems(Request $request, $franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($templateId);

        $query = MenuTemplateItem::withoutGlobalScope(FranchiseScope::class)
            ->where('menu_template_id', $templateId)
            ->with('category:id,name');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        // Availability filter
        if ($request->has('is_available')) {
            $query->where('is_available', $request->is_available === 'true');
        }

        $items = $query->orderBy('sort_order')
            ->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Create menu item
     */
    public function storeItem(Request $request, $franchiseId, $templateId)
    {
        $template = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->findOrFail($templateId);

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:menu_template_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'image_url' => 'nullable|url',
            'icon' => 'nullable|string|max:50',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify category belongs to this template
        $category = MenuTemplateCategory::withoutGlobalScope(FranchiseScope::class)
            ->where('id', $request->category_id)
            ->where('menu_template_id', $templateId)
            ->firstOrFail();

        $item = MenuTemplateItem::create([
            'menu_template_id' => $templateId,
            'category_id' => $request->category_id,
            'franchise_id' => $franchiseId,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'currency' => $request->currency ?? 'LKR',
            'image_url' => $request->image_url,
            'icon' => $request->icon,
            'is_available' => $request->is_available ?? true,
            'is_featured' => $request->is_featured ?? false,
            'sort_order' => $request->sort_order ?? 999,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu item created successfully',
            'data' => $item->load('category'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update menu item
     */
    public function updateItem(Request $request, $franchiseId, $templateId, $itemId)
    {
        $item = MenuTemplateItem::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('menu_template_id', $templateId)
            ->findOrFail($itemId);

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:menu_template_categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'image_url' => 'nullable|url',
            'icon' => 'nullable|string|max:50',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updateData = $request->only([
            'category_id',
            'name',
            'description',
            'price',
            'currency',
            'image_url',
            'icon',
            'is_available',
            'is_featured',
            'sort_order',
        ]);

        if (isset($updateData['name'])) {
            $updateData['slug'] = Str::slug($updateData['name']);
        }

        $item->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Menu item updated successfully',
            'data' => $item->fresh(['category']),
        ]);
    }

    /**
     * Delete menu item
     */
    public function destroyItem($franchiseId, $templateId, $itemId)
    {
        $item = MenuTemplateItem::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('menu_template_id', $templateId)
            ->findOrFail($itemId);

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu item deleted successfully',
        ]);
    }

    /**
     * Bulk update item availability
     */
    public function bulkUpdateAvailability(Request $request, $franchiseId, $templateId)
    {
        $validator = Validator::make($request->all(), [
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:menu_template_items,id',
            'is_available' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updated = MenuTemplateItem::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('menu_template_id', $templateId)
            ->whereIn('id', $request->item_ids)
            ->update(['is_available' => $request->is_available]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} items updated successfully",
            'count' => $updated,
        ]);
    }

    /**
     * Get menu statistics
     */
    public function statistics($franchiseId)
    {
        $franchise = Franchise::findOrFail($franchiseId);

        $totalTemplates = MenuTemplate::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->count();

        $totalCategories = MenuTemplateCategory::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->count();

        $totalItems = MenuTemplateItem::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->count();

        $availableItems = MenuTemplateItem::withoutGlobalScope(FranchiseScope::class)
            ->where('franchise_id', $franchiseId)
            ->where('is_available', true)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_templates' => $totalTemplates,
                'total_categories' => $totalCategories,
                'total_items' => $totalItems,
                'available_items' => $availableItems,
                'unavailable_items' => $totalItems - $availableItems,
            ],
        ]);
    }
}
