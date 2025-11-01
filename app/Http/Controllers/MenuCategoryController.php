<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class MenuCategoryController extends Controller
{
    /**
     * Get authenticated user from bearer token
     */
    private function getAuthenticatedUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        return $accessToken ? $accessToken->tokenable : null;
    }

    /**
     * Get all categories for a menu
     */
    public function index(Request $request, $menuId)
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $menu = Menu::findOrFail($menuId);
            
            // Check authorization
            if ($menu->location->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $categories = $menu->categories()->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching menu categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Create a new category
     */
    public function store(Request $request, $menuId)
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $menu = Menu::findOrFail($menuId);
            
            // Check authorization
            if ($menu->location->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'background_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'text_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'heading_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'sort_order' => 'sometimes|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = MenuCategory::create([
                'menu_id' => $menuId,
                'name' => $request->name,
                'description' => $request->description,
                'background_color' => $request->background_color ?? '#10b981',
                'text_color' => $request->text_color ?? '#ffffff',
                'heading_color' => $request->heading_color ?? '#ffffff',
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => [
                    'category' => $category
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category'
            ], 500);
        }
    }

    /**
     * Update a category
     */
    public function update(Request $request, $menuId, $categoryId)
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $menu = Menu::findOrFail($menuId);
            
            // Check authorization
            if ($menu->location->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $category = MenuCategory::where('menu_id', $menuId)->findOrFail($categoryId);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'background_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'text_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'heading_color' => 'nullable|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'sort_order' => 'sometimes|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category->update($request->only([
                'name',
                'description',
                'background_color',
                'text_color',
                'heading_color',
                'sort_order',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => [
                    'category' => $category->fresh()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category'
            ], 500);
        }
    }

    /**
     * Delete a category
     */
    public function destroy(Request $request, $menuId, $categoryId)
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $menu = Menu::findOrFail($menuId);
            
            // Check authorization
            if ($menu->location->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $category = MenuCategory::where('menu_id', $menuId)->findOrFail($categoryId);
            
            // Note: Items will have category_id set to null due to onDelete('set null')
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting category: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category'
            ], 500);
        }
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request, $menuId)
    {
        try {
            $user = $this->getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $menu = Menu::findOrFail($menuId);
            
            // Check authorization
            if ($menu->location->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'category_ids' => 'required|array',
                'category_ids.*' => 'required|integer|exists:menu_categories,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->category_ids as $index => $categoryId) {
                MenuCategory::where('id', $categoryId)
                    ->where('menu_id', $menuId)
                    ->update(['sort_order' => $index]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Categories reordered successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error reordering categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder categories'
            ], 500);
        }
    }
}
