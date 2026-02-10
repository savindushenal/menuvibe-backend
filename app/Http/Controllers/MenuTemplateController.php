<?php

namespace App\Http\Controllers;

use App\Models\MenuTemplate;
use App\Models\MenuTemplateCategory;
use App\Models\MenuTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MenuTemplateController extends Controller
{
    // ===========================================
    // TEMPLATES
    // ===========================================

    /**
     * Get all templates for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $locationId = $request->query('location_id');

        $query = MenuTemplate::where('user_id', $user->id)
            ->with(['categories.items'])
            ->withCount(['categories', 'items', 'endpoints']);

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Create a new template
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'currency' => 'nullable|string|max:10',
            'location_id' => 'nullable|exists:locations,id',
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
        $data['user_id'] = $user->id;
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(6);
        $data['created_by'] = $user->id;

        // If this is set as default, unset other defaults
        if (!empty($data['is_default'])) {
            MenuTemplate::where('user_id', $user->id)
                ->when($data['location_id'] ?? null, fn($q, $locId) => $q->where('location_id', $locId))
                ->update(['is_default' => false]);
        }

        $template = MenuTemplate::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template
        ], 201);
    }

    /**
     * Get a specific template with all data
     */
    public function show(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->with(['categories.items', 'offers', 'endpoints'])
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'template' => $template
            ]
        ]);
    }

    /**
     * Update a template
     */
    public function update(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
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
        $data['updated_by'] = $user->id;

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . Str::random(6);
        }

        // If this is set as default, unset other defaults
        if (!empty($data['is_default'])) {
            MenuTemplate::where('user_id', $user->id)
                ->where('id', '!=', $templateId)
                ->where('location_id', $template->location_id)
                ->update(['is_default' => false]);
        }

        $template->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template->fresh()->load('categories.items')
        ]);
    }

    /**
     * Delete a template
     */
    public function destroy(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        // Check if template has active endpoints
        if ($template->endpoints()->where('is_active', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete template with active endpoints. Deactivate or delete endpoints first.'
            ], 400);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    }

    /**
     * Duplicate a template
     */
    public function duplicate(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $newName = $request->input('name', $template->name . ' (Copy)');
        $newTemplate = $template->duplicate($newName);

        return response()->json([
            'success' => true,
            'message' => 'Template duplicated successfully',
            'data' => $newTemplate->load('categories.items')
        ], 201);
    }

    // ===========================================
    // CATEGORIES
    // ===========================================

    /**
     * Add a category to a template
     */
    public function addCategory(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
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
        $data['template_id'] = $templateId;
        $data['slug'] = Str::slug($data['name']);

        // Auto-assign sort order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = $template->categories()->max('sort_order') + 1;
        }

        $category = MenuTemplateCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, int $templateId, int $categoryId)
    {
        $user = $request->user();

        $category = MenuTemplateCategory::whereHas('template', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $categoryId)
          ->where('template_id', $templateId)
          ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
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
    public function deleteCategory(Request $request, int $templateId, int $categoryId)
    {
        $user = $request->user();

        $category = MenuTemplateCategory::whereHas('template', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $categoryId)
          ->where('template_id', $templateId)
          ->first();

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
     * Reorder categories
     */
    public function reorderCategories(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:menu_template_categories,id',
            'categories.*.sort_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->categories as $catData) {
            MenuTemplateCategory::where('id', $catData['id'])
                ->where('template_id', $templateId)
                ->update(['sort_order' => $catData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories reordered successfully'
        ]);
    }

    // ===========================================
    // ITEMS
    // ===========================================

    /**
     * Add an item to a category
     */
    public function addItem(Request $request, int $templateId, int $categoryId)
    {
        $user = $request->user();

        $category = MenuTemplateCategory::whereHas('template', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $categoryId)
          ->where('template_id', $templateId)
          ->first();

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
            'image_url' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
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
            'calories' => 'nullable|integer',
            'is_spicy' => 'nullable|boolean',
            'spice_level' => 'nullable|integer|min:1|max:5',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required_with:variations|string|max:100',
            'variations.*.price' => 'required_with:variations|numeric|min:0',
            'addons' => 'nullable|array',
            'sku' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['template_id'] = $templateId;
        $data['category_id'] = $categoryId;
        $data['slug'] = Str::slug($data['name']);

        // Auto-assign sort order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = $category->items()->max('sort_order') + 1;
        }

        $item = MenuTemplateItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Item added successfully',
            'data' => $item
        ], 201);
    }

    /**
     * Update an item
     */
    public function updateItem(Request $request, int $templateId, int $itemId)
    {
        $user = $request->user();

        $item = MenuTemplateItem::whereHas('template', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $itemId)
          ->where('template_id', $templateId)
          ->first();

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
            'image_url' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
            'gallery_images' => 'nullable|array',
            'category_id' => 'sometimes|exists:menu_template_categories,id',
            'card_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'heading_color' => 'nullable|string|max:20',
            'is_available' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'allergens' => 'nullable|array',
            'dietary_info' => 'nullable|array',
            'preparation_time' => 'nullable|integer',
            'calories' => 'nullable|integer',
            'is_spicy' => 'nullable|boolean',
            'spice_level' => 'nullable|integer|min:1|max:5',
            'variations' => 'nullable|array',
            'variations.*.name' => 'required_with:variations|string|max:100',
            'variations.*.price' => 'required_with:variations|numeric|min:0',
            'addons' => 'nullable|array',
            'sku' => 'nullable|string|max:100',
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
    public function deleteItem(Request $request, int $templateId, int $itemId)
    {
        $user = $request->user();

        $item = MenuTemplateItem::whereHas('template', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $itemId)
          ->where('template_id', $templateId)
          ->first();

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
    public function bulkUpdateItems(Request $request, int $templateId)
    {
        $user = $request->user();

        $template = MenuTemplate::where('user_id', $user->id)
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:menu_template_items,id',
            'items.*.sort_order' => 'nullable|integer',
            'items.*.is_available' => 'nullable|boolean',
            'items.*.category_id' => 'nullable|exists:menu_template_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->items as $itemData) {
            MenuTemplateItem::where('id', $itemData['id'])
                ->where('template_id', $templateId)
                ->update(array_filter([
                    'sort_order' => $itemData['sort_order'] ?? null,
                    'is_available' => $itemData['is_available'] ?? null,
                    'category_id' => $itemData['category_id'] ?? null,
                ], fn($v) => $v !== null));
        }

        return response()->json([
            'success' => true,
            'message' => 'Items updated successfully'
        ]);
    }
}
