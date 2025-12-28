<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuVersionController extends Controller
{
    /**
     * Get version history for a menu
     */
    public function index(Request $request, $menuId)
    {
        $menu = Menu::findOrFail($menuId);
        
        // Check authorization
        if ($menu->franchise_id !== $request->user()->franchise_id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        $versions = MenuVersion::where('menu_id', $menuId)
            ->with('creator:id,name,email')
            ->orderByDesc('version_number')
            ->get();
        
        return response()->json([
            'menu_id' => $menuId,
            'current_version' => $menu->version,
            'versions' => $versions,
        ]);
    }

    /**
     * Create a new version (snapshot current state)
     */
    public function store(Request $request, $menuId)
    {
        $request->validate([
            'description' => 'nullable|string|max:255',
        ]);
        
        $menu = Menu::findOrFail($menuId);
        
        // Check authorization
        if ($menu->franchise_id !== $request->user()->franchise_id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        DB::beginTransaction();
        try {
            $version = $menu->createVersion($request->description);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Version created successfully',
                'version' => $version,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to create version',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore menu to a specific version
     */
    public function restore(Request $request, $menuId, $versionNumber)
    {
        $menu = Menu::findOrFail($menuId);
        
        // Check authorization
        if ($menu->franchise_id !== $request->user()->franchise_id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        $version = MenuVersion::where('menu_id', $menuId)
            ->where('version_number', $versionNumber)
            ->firstOrFail();
        
        DB::beginTransaction();
        try {
            // Create a backup of current state before restoring
            $menu->createVersion("Before restore to v{$versionNumber}");
            
            // Restore the version
            $menu->restoreVersion($versionNumber);
            
            DB::commit();
            
            return response()->json([
                'message' => "Menu restored to version {$versionNumber}",
                'menu' => $menu->fresh()->load('items'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'error' => 'Failed to restore version',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a specific version
     */
    public function show(Request $request, $menuId, $versionNumber)
    {
        $menu = Menu::findOrFail($menuId);
        
        // Check authorization
        if ($menu->franchise_id !== $request->user()->franchise_id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        $version = MenuVersion::where('menu_id', $menuId)
            ->where('version_number', $versionNumber)
            ->with('creator:id,name,email')
            ->firstOrFail();
        
        return response()->json([
            'version' => $version,
        ]);
    }

    /**
     * Delete a version
     */
    public function destroy(Request $request, $menuId, $versionNumber)
    {
        $menu = Menu::findOrFail($menuId);
        
        // Check authorization
        if ($menu->franchise_id !== $request->user()->franchise_id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }
        
        $version = MenuVersion::where('menu_id', $menuId)
            ->where('version_number', $versionNumber)
            ->firstOrFail();
        
        // Prevent deleting the current version
        if ($version->version_number === $menu->version) {
            return response()->json([
                'error' => 'Cannot delete current version'
            ], 422);
        }
        
        $version->delete();
        
        return response()->json([
            'message' => "Version {$versionNumber} deleted successfully"
        ]);
    }
}
