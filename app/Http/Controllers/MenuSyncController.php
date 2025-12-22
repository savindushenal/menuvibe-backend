<?php

namespace App\Http\Controllers;

use App\Services\MenuSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuSyncController extends Controller
{
    private MenuSyncService $syncService;

    public function __construct(MenuSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Get sync status for a branch menu
     */
    public function getStatus(Request $request, int $locationId, int $masterMenuId)
    {
        $status = $this->syncService->getBranchSyncStatus($locationId, $masterMenuId);
        
        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Get pending changes preview
     */
    public function getPendingChanges(Request $request, int $branchSyncId)
    {
        $changes = $this->syncService->getPendingChangesPreview($branchSyncId);
        
        return response()->json([
            'success' => true,
            'data' => $changes,
        ]);
    }

    /**
     * Manually trigger sync for a branch
     */
    public function syncBranch(Request $request, int $branchSyncId)
    {
        $request->validate([
            'target_version' => 'nullable|integer',
        ]);

        $sync = DB::table('branch_menu_sync')->find($branchSyncId);
        
        if (!$sync) {
            return response()->json([
                'success' => false,
                'message' => 'Sync record not found',
            ], 404);
        }

        // Get target version (latest if not specified)
        $targetVersion = $request->target_version;
        
        if (!$targetVersion) {
            $masterMenu = DB::table('master_menus')->find($sync->master_menu_id);
            $targetVersion = $masterMenu->current_version ?? 1;
        }

        $result = $this->syncService->syncBranchToVersion(
            $branchSyncId,
            $targetVersion,
            'manual',
            $request->user()
        );

        return response()->json($result);
    }

    /**
     * Update sync mode for a branch
     */
    public function updateSyncMode(Request $request, int $branchSyncId)
    {
        $request->validate([
            'sync_mode' => 'required|in:auto,manual,disabled',
        ]);

        $updated = DB::table('branch_menu_sync')
            ->where('id', $branchSyncId)
            ->update([
                'sync_mode' => $request->sync_mode,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => $updated > 0,
            'message' => $updated > 0 ? 'Sync mode updated' : 'Record not found',
        ]);
    }

    /**
     * Set item override for a branch
     */
    public function setItemOverride(Request $request, int $branchSyncId, int $masterItemId)
    {
        $request->validate([
            'price_override' => 'nullable|numeric|min:0',
            'availability_override' => 'nullable|boolean',
            'price_locked' => 'nullable|boolean',
            'availability_locked' => 'nullable|boolean',
            'fully_locked' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->syncService->setLocalOverride(
            $branchSyncId,
            $masterItemId,
            $request->only([
                'price_override',
                'availability_override',
                'price_locked',
                'availability_locked',
                'fully_locked',
                'notes',
            ]),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Item override saved',
        ]);
    }

    /**
     * Remove item override
     */
    public function removeItemOverride(int $branchSyncId, int $masterItemId)
    {
        $deleted = DB::table('branch_menu_overrides')
            ->where('branch_menu_sync_id', $branchSyncId)
            ->where('master_menu_item_id', $masterItemId)
            ->delete();

        return response()->json([
            'success' => $deleted > 0,
            'message' => $deleted > 0 ? 'Override removed' : 'Override not found',
        ]);
    }

    /**
     * Get all overrides for a branch
     */
    public function getOverrides(int $branchSyncId)
    {
        $overrides = DB::table('branch_menu_overrides')
            ->where('branch_menu_sync_id', $branchSyncId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $overrides,
        ]);
    }

    /**
     * Get sync history/logs for a branch
     */
    public function getSyncHistory(int $branchSyncId)
    {
        $logs = DB::table('menu_sync_logs')
            ->where('branch_menu_sync_id', $branchSyncId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                $log->conflict_details = json_decode($log->conflict_details);
                $log->changes_applied = json_decode($log->changes_applied);
                return $log;
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get version history for a master menu
     */
    public function getVersionHistory(int $masterMenuId)
    {
        $versions = DB::table('master_menu_versions')
            ->where('master_menu_id', $masterMenuId)
            ->orderBy('version_number', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($v) {
                // Don't return full snapshot in list view
                unset($v->snapshot);
                $v->changes_data = json_decode($v->changes_data);
                return $v;
            });

        return response()->json([
            'success' => true,
            'data' => $versions,
        ]);
    }

    /**
     * Initialize sync link between branch and master menu
     */
    public function initializeBranchSync(Request $request)
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'menu_id' => 'required|exists:menus,id',
            'master_menu_id' => 'required|exists:master_menus,id',
            'sync_mode' => 'nullable|in:auto,manual,disabled',
        ]);

        // Check if already exists
        $existing = DB::table('branch_menu_sync')
            ->where('location_id', $request->location_id)
            ->where('master_menu_id', $request->master_menu_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Branch is already linked to this master menu',
                'data' => $existing,
            ], 400);
        }

        // Get current master version
        $masterMenu = DB::table('master_menus')->find($request->master_menu_id);

        $id = DB::table('branch_menu_sync')->insertGetId([
            'location_id' => $request->location_id,
            'menu_id' => $request->menu_id,
            'master_menu_id' => $request->master_menu_id,
            'synced_version' => 0, // Start at 0, will sync to current on first sync
            'sync_mode' => $request->sync_mode ?? 'manual',
            'has_pending_updates' => $masterMenu->current_version > 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch sync initialized',
            'data' => [
                'id' => $id,
                'pending_versions' => $masterMenu->current_version ?? 0,
            ],
        ]);
    }

    /**
     * Get dashboard summary for franchise owner
     */
    public function getSyncDashboard(Request $request, int $franchiseId)
    {
        // Get all master menus for this franchise
        $masterMenus = DB::table('master_menus')
            ->where('franchise_account_id', $franchiseId)
            ->get();

        $summary = [];

        foreach ($masterMenus as $masterMenu) {
            $branches = DB::table('branch_menu_sync')
                ->where('master_menu_id', $masterMenu->id)
                ->get();

            $synced = $branches->where('synced_version', $masterMenu->current_version)->count();
            $pending = $branches->where('synced_version', '<', $masterMenu->current_version)->count();
            $autoSync = $branches->where('sync_mode', 'auto')->count();

            $summary[] = [
                'master_menu' => [
                    'id' => $masterMenu->id,
                    'name' => $masterMenu->name,
                    'current_version' => $masterMenu->current_version,
                ],
                'branches' => [
                    'total' => $branches->count(),
                    'synced' => $synced,
                    'pending' => $pending,
                    'auto_sync_enabled' => $autoSync,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get a specific version's snapshot (view old menu)
     */
    public function getVersionSnapshot(int $masterMenuId, int $versionNumber)
    {
        $version = DB::table('master_menu_versions')
            ->where('master_menu_id', $masterMenuId)
            ->where('version_number', $versionNumber)
            ->first();

        if (!$version) {
            return response()->json([
                'success' => false,
                'message' => 'Version not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'version_number' => $version->version_number,
                'change_type' => $version->change_type,
                'change_summary' => $version->change_summary,
                'changes_data' => json_decode($version->changes_data),
                'snapshot' => json_decode($version->snapshot),
                'created_at' => $version->created_at,
            ],
        ]);
    }

    /**
     * Compare two versions
     */
    public function compareVersions(int $masterMenuId, int $fromVersion, int $toVersion)
    {
        $from = DB::table('master_menu_versions')
            ->where('master_menu_id', $masterMenuId)
            ->where('version_number', $fromVersion)
            ->first();

        $to = DB::table('master_menu_versions')
            ->where('master_menu_id', $masterMenuId)
            ->where('version_number', $toVersion)
            ->first();

        if (!$from || !$to) {
            return response()->json([
                'success' => false,
                'message' => 'One or both versions not found',
            ], 404);
        }

        $fromSnapshot = json_decode($from->snapshot, true);
        $toSnapshot = json_decode($to->snapshot, true);

        // Calculate differences
        $diff = $this->calculateSnapshotDiff($fromSnapshot, $toSnapshot);

        return response()->json([
            'success' => true,
            'data' => [
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'diff' => $diff,
                'from_snapshot' => $fromSnapshot,
                'to_snapshot' => $toSnapshot,
            ],
        ]);
    }

    /**
     * Calculate differences between two snapshots
     */
    private function calculateSnapshotDiff(?array $from, ?array $to): array
    {
        $diff = [
            'items_added' => [],
            'items_removed' => [],
            'items_modified' => [],
            'categories_added' => [],
            'categories_removed' => [],
        ];

        if (!$from || !$to) {
            return $diff;
        }

        // Extract items from snapshots
        $fromItems = $this->extractItemsFromSnapshot($from);
        $toItems = $this->extractItemsFromSnapshot($to);

        $fromItemIds = array_keys($fromItems);
        $toItemIds = array_keys($toItems);

        // Find added items
        $addedIds = array_diff($toItemIds, $fromItemIds);
        foreach ($addedIds as $id) {
            $diff['items_added'][] = $toItems[$id];
        }

        // Find removed items
        $removedIds = array_diff($fromItemIds, $toItemIds);
        foreach ($removedIds as $id) {
            $diff['items_removed'][] = $fromItems[$id];
        }

        // Find modified items
        $commonIds = array_intersect($fromItemIds, $toItemIds);
        foreach ($commonIds as $id) {
            $changes = [];
            foreach ($toItems[$id] as $key => $value) {
                if (isset($fromItems[$id][$key]) && $fromItems[$id][$key] !== $value) {
                    $changes[$key] = [
                        'from' => $fromItems[$id][$key],
                        'to' => $value,
                    ];
                }
            }
            if (!empty($changes)) {
                $diff['items_modified'][] = [
                    'item' => $toItems[$id],
                    'changes' => $changes,
                ];
            }
        }

        return $diff;
    }

    /**
     * Extract items from snapshot for comparison
     */
    private function extractItemsFromSnapshot(array $snapshot): array
    {
        $items = [];
        foreach ($snapshot['categories'] ?? [] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $items[$item['id']] = $item;
            }
        }
        return $items;
    }

    /**
     * Bulk sync all branches to latest version
     */
    public function bulkSyncAllBranches(Request $request, int $masterMenuId)
    {
        $masterMenu = DB::table('master_menus')->find($masterMenuId);
        
        if (!$masterMenu) {
            return response()->json([
                'success' => false,
                'message' => 'Master menu not found',
            ], 404);
        }

        $branches = DB::table('branch_menu_sync')
            ->where('master_menu_id', $masterMenuId)
            ->where('synced_version', '<', $masterMenu->current_version)
            ->where('sync_mode', '!=', 'disabled')
            ->get();

        $results = [
            'total' => $branches->count(),
            'successful' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($branches as $branch) {
            try {
                $result = $this->syncService->syncBranchToVersion(
                    $branch->id,
                    $masterMenu->current_version,
                    'bulk',
                    $request->user()
                );

                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }

                $results['details'][] = [
                    'branch_sync_id' => $branch->id,
                    'location_id' => $branch->location_id,
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'branch_sync_id' => $branch->id,
                    'location_id' => $branch->location_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$results['successful']}/{$results['total']} branches",
            'data' => $results,
        ]);
    }
}
