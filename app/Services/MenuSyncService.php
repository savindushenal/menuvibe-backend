<?php

namespace App\Services;

use App\Models\MasterMenu;
use App\Models\MasterMenuItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuSyncService
{
    /**
     * Sync policies
     */
    const POLICY_AUTO_SYNC = 'auto';      // Sync automatically when master changes
    const POLICY_MANUAL_SYNC = 'manual';  // Require manual approval
    const POLICY_DISABLED = 'disabled';   // Never sync

    /**
     * Change types
     */
    const CHANGE_ITEM_ADDED = 'item_added';
    const CHANGE_ITEM_REMOVED = 'item_removed';
    const CHANGE_ITEM_UPDATED = 'item_updated';
    const CHANGE_PRICE_CHANGED = 'price_changed';
    const CHANGE_CATEGORY_CHANGED = 'category_changed';
    const CHANGE_BULK_UPDATE = 'bulk_update';

    /**
     * Create a new version when master menu changes
     */
    public function createVersion(MasterMenu $masterMenu, string $changeType, array $changesData, User $user): void
    {
        DB::transaction(function () use ($masterMenu, $changeType, $changesData, $user) {
            // Lock the master menu row to prevent race conditions
            $lockedMenu = MasterMenu::where('id', $masterMenu->id)->lockForUpdate()->first();
            
            $newVersion = $lockedMenu->current_version + 1;

            // Use updateOrInsert to avoid duplicate key errors
            // If the version already exists (from a failed previous attempt), update it
            // Otherwise, insert a new version
            DB::table('master_menu_versions')->updateOrInsert(
                [
                    'master_menu_id' => $lockedMenu->id,
                    'version_number' => $newVersion,
                ],
                [
                    'change_type' => $changeType,
                    'change_summary' => $this->generateChangeSummary($changeType, $changesData),
                    'changes_data' => json_encode($changesData),
                    'snapshot' => json_encode($this->createMenuSnapshot($lockedMenu)),
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $lockedMenu->update(['current_version' => $newVersion]);

            // Notify branches about pending updates
            $this->notifyBranchesOfUpdate($lockedMenu, $newVersion, $changeType);
        });
    }

    /**
     * Generate human-readable change summary
     */
    private function generateChangeSummary(string $changeType, array $changesData): string
    {
        return match ($changeType) {
            self::CHANGE_ITEM_ADDED => "Added " . count($changesData['items'] ?? [1]) . " new item(s)",
            self::CHANGE_ITEM_REMOVED => "Removed " . count($changesData['items'] ?? [1]) . " item(s)",
            self::CHANGE_PRICE_CHANGED => "Updated prices for " . count($changesData['items'] ?? [1]) . " item(s)",
            self::CHANGE_ITEM_UPDATED => "Updated " . count($changesData['items'] ?? [1]) . " item(s)",
            self::CHANGE_CATEGORY_CHANGED => "Modified categories",
            self::CHANGE_BULK_UPDATE => "Bulk update: " . ($changesData['description'] ?? 'Multiple changes'),
            default => "Menu updated",
        };
    }

    /**
     * Create a snapshot of the current menu state
     */
    private function createMenuSnapshot(MasterMenu $masterMenu): array
    {
        return [
            'version' => $masterMenu->current_version,
            'categories' => $masterMenu->categories()->with('items')->get()->toArray(),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Mark branches as having pending updates
     */
    private function notifyBranchesOfUpdate(MasterMenu $masterMenu, int $newVersion, string $changeType): void
    {
        // Get sync policy
        $syncPolicy = $masterMenu->sync_policy ?? ['auto_sync' => ['new_items', 'removed_items']];
        $autoSyncChanges = $syncPolicy['auto_sync'] ?? [];

        // Determine if this change type should auto-sync
        $shouldAutoSync = in_array($changeType, $autoSyncChanges) || 
                          in_array('all', $autoSyncChanges);

        // Update all branch sync records
        DB::table('branch_menu_sync')
            ->where('master_menu_id', $masterMenu->id)
            ->where('synced_version', '<', $newVersion)
            ->update([
                'has_pending_updates' => true,
                'pending_changes' => DB::raw("JSON_SET(COALESCE(pending_changes, '{}'), '$.version_{$newVersion}', JSON_OBJECT('type', '{$changeType}', 'auto_sync', " . ($shouldAutoSync ? 'true' : 'false') . "))"),
                'updated_at' => now(),
            ]);

        // Auto-sync branches that have auto mode enabled and change qualifies
        if ($shouldAutoSync) {
            $autoSyncBranches = DB::table('branch_menu_sync')
                ->where('master_menu_id', $masterMenu->id)
                ->where('sync_mode', self::POLICY_AUTO_SYNC)
                ->where('synced_version', '<', $newVersion)
                ->get();

            foreach ($autoSyncBranches as $branchSync) {
                // Queue the sync job (or do it synchronously for now)
                $this->syncBranchToVersion(
                    $branchSync->id,
                    $newVersion,
                    'auto',
                    null // System initiated
                );
            }
        }
    }

    /**
     * Sync a branch menu to a specific master version
     */
    public function syncBranchToVersion(
        int $branchSyncId,
        int $targetVersion,
        string $syncType = 'manual',
        ?User $initiatedBy = null
    ): array {
        $branchSync = DB::table('branch_menu_sync')->find($branchSyncId);
        
        if (!$branchSync) {
            return ['success' => false, 'message' => 'Branch sync record not found'];
        }

        $masterMenu = MasterMenu::with(['categories.items'])->find($branchSync->master_menu_id);
        $branchMenu = Menu::with(['categories.items'])->find($branchSync->menu_id);

        if (!$masterMenu || !$branchMenu) {
            return ['success' => false, 'message' => 'Menu not found'];
        }

        // Get changes between current synced version and target
        $changes = $this->getChangesBetweenVersions(
            $masterMenu->id,
            $branchSync->synced_version,
            $targetVersion
        );

        // Get branch overrides
        $overrides = DB::table('branch_menu_overrides')
            ->where('branch_menu_sync_id', $branchSyncId)
            ->get()
            ->keyBy('master_menu_item_id');

        // Apply changes with conflict resolution
        $result = $this->applyChanges($branchMenu, $masterMenu, $changes, $overrides, $branchSyncId);

        // Log the sync
        DB::table('menu_sync_logs')->insert([
            'branch_menu_sync_id' => $branchSyncId,
            'from_version' => $branchSync->synced_version,
            'to_version' => $targetVersion,
            'sync_type' => $syncType,
            'status' => $result['conflicts'] > 0 ? 'partial' : 'success',
            'items_added' => $result['added'],
            'items_updated' => $result['updated'],
            'items_removed' => $result['removed'],
            'conflicts_skipped' => $result['conflicts'],
            'conflict_details' => json_encode($result['conflict_details']),
            'changes_applied' => json_encode($result['applied_changes']),
            'initiated_by' => $initiatedBy?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update sync status
        DB::table('branch_menu_sync')
            ->where('id', $branchSyncId)
            ->update([
                'synced_version' => $targetVersion,
                'has_pending_updates' => false,
                'pending_changes' => null,
                'last_synced_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'success' => true,
            'message' => "Synced to version {$targetVersion}",
            'stats' => $result,
        ];
    }

    /**
     * Get all changes between two versions
     */
    private function getChangesBetweenVersions(int $masterMenuId, int $fromVersion, int $toVersion): array
    {
        $versions = DB::table('master_menu_versions')
            ->where('master_menu_id', $masterMenuId)
            ->where('version_number', '>', $fromVersion)
            ->where('version_number', '<=', $toVersion)
            ->orderBy('version_number')
            ->get();

        $aggregatedChanges = [
            'added_items' => [],
            'removed_items' => [],
            'updated_items' => [],
            'price_changes' => [],
        ];

        foreach ($versions as $version) {
            $data = json_decode($version->changes_data, true);
            
            // Aggregate changes
            if (isset($data['added_items'])) {
                $aggregatedChanges['added_items'] = array_merge(
                    $aggregatedChanges['added_items'],
                    $data['added_items']
                );
            }
            if (isset($data['removed_items'])) {
                $aggregatedChanges['removed_items'] = array_merge(
                    $aggregatedChanges['removed_items'],
                    $data['removed_items']
                );
            }
            if (isset($data['updated_items'])) {
                foreach ($data['updated_items'] as $itemId => $updates) {
                    $aggregatedChanges['updated_items'][$itemId] = array_merge(
                        $aggregatedChanges['updated_items'][$itemId] ?? [],
                        $updates
                    );
                }
            }
            if (isset($data['price_changes'])) {
                foreach ($data['price_changes'] as $itemId => $newPrice) {
                    $aggregatedChanges['price_changes'][$itemId] = $newPrice;
                }
            }
        }

        // Remove items that were added then removed
        $aggregatedChanges['added_items'] = array_diff(
            $aggregatedChanges['added_items'],
            $aggregatedChanges['removed_items']
        );

        return $aggregatedChanges;
    }

    /**
     * Apply changes to branch menu with conflict resolution
     */
    private function applyChanges(
        Menu $branchMenu,
        MasterMenu $masterMenu,
        array $changes,
        $overrides,
        int $branchSyncId
    ): array {
        $result = [
            'added' => 0,
            'updated' => 0,
            'removed' => 0,
            'conflicts' => 0,
            'conflict_details' => [],
            'applied_changes' => [],
        ];

        DB::beginTransaction();

        try {
            // 1. Handle added items
            foreach ($changes['added_items'] as $masterItemId) {
                $masterItem = MasterMenuItem::find($masterItemId);
                if (!$masterItem) continue;

                // Check if item already exists (maybe added locally)
                $existingItem = MenuItem::where('menu_id', $branchMenu->id)
                    ->where('source_master_item_id', $masterItemId)
                    ->first();

                if (!$existingItem) {
                    $this->createBranchItemFromMaster($branchMenu, $masterItem, $masterMenu->current_version);
                    $result['added']++;
                    $result['applied_changes'][] = ['type' => 'add', 'item_id' => $masterItemId];
                }
            }

            // 2. Handle removed items
            foreach ($changes['removed_items'] as $masterItemId) {
                $branchItem = MenuItem::where('menu_id', $branchMenu->id)
                    ->where('source_master_item_id', $masterItemId)
                    ->first();

                if ($branchItem) {
                    // Check if locked
                    $override = $overrides[$masterItemId] ?? null;
                    if ($override && $override->fully_locked) {
                        $result['conflicts']++;
                        $result['conflict_details'][] = [
                            'type' => 'remove_blocked',
                            'item_id' => $masterItemId,
                            'reason' => 'Item is locked at branch level',
                        ];
                    } else {
                        $branchItem->delete();
                        $result['removed']++;
                        $result['applied_changes'][] = ['type' => 'remove', 'item_id' => $masterItemId];
                    }
                }
            }

            // 3. Handle updated items (including price changes)
            foreach ($changes['updated_items'] as $masterItemId => $updates) {
                $this->applyItemUpdates(
                    $branchMenu,
                    $masterItemId,
                    $updates,
                    $overrides[$masterItemId] ?? null,
                    $result
                );
            }

            // 4. Handle price changes specifically
            foreach ($changes['price_changes'] as $masterItemId => $newPrice) {
                $this->applyPriceChange(
                    $branchMenu,
                    $masterItemId,
                    $newPrice,
                    $overrides[$masterItemId] ?? null,
                    $result
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Menu sync failed', [
                'branch_sync_id' => $branchSyncId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Create a branch menu item from master
     */
    private function createBranchItemFromMaster(Menu $branchMenu, MasterMenuItem $masterItem, int $version): MenuItem
    {
        // Find or create matching category in branch menu
        $branchCategory = $branchMenu->categories()
            ->where('name', $masterItem->category->name)
            ->first();

        if (!$branchCategory) {
            $branchCategory = $branchMenu->categories()->create([
                'name' => $masterItem->category->name,
                'description' => $masterItem->category->description,
                'sort_order' => $masterItem->category->sort_order,
            ]);
        }

        return MenuItem::create([
            'menu_id' => $branchMenu->id,
            'menu_category_id' => $branchCategory->id,
            'source_master_item_id' => $masterItem->id,
            'is_local_override' => false,
            'last_synced_version' => $version,
            'name' => $masterItem->name,
            'description' => $masterItem->description,
            'price' => $masterItem->price,
            'compare_at_price' => $masterItem->compare_at_price,
            'currency' => $masterItem->currency,
            'image_url' => $masterItem->image_url,
            'gallery_images' => $masterItem->gallery_images,
            'card_color' => $masterItem->card_color,
            'text_color' => $masterItem->text_color,
            'heading_color' => $masterItem->heading_color,
            'is_available' => $masterItem->is_available,
            'is_featured' => $masterItem->is_featured,
            'dietary_info' => $masterItem->dietary_info,
            'allergens' => $masterItem->allergens,
            'preparation_time' => $masterItem->preparation_time,
            'is_spicy' => $masterItem->is_spicy,
            'spice_level' => $masterItem->spice_level,
            'variations' => $masterItem->variations,
            'customizations' => $masterItem->customizations,
            'addons' => $masterItem->addons,
            'sku' => $masterItem->sku,
            'calories' => $masterItem->calories,
            'sort_order' => $masterItem->sort_order,
        ]);
    }

    /**
     * Apply updates to an item, respecting overrides
     */
    private function applyItemUpdates(
        Menu $branchMenu,
        int $masterItemId,
        array $updates,
        $override,
        array &$result
    ): void {
        $branchItem = MenuItem::where('menu_id', $branchMenu->id)
            ->where('source_master_item_id', $masterItemId)
            ->first();

        if (!$branchItem) return;

        // If fully locked, skip all updates
        if ($override && $override->fully_locked) {
            $result['conflicts']++;
            $result['conflict_details'][] = [
                'type' => 'update_blocked',
                'item_id' => $masterItemId,
                'reason' => 'Item is fully locked',
            ];
            return;
        }

        $appliedUpdates = [];

        foreach ($updates as $field => $value) {
            // Check field-specific locks
            if ($field === 'price' && $override && $override->price_locked) {
                $result['conflicts']++;
                $result['conflict_details'][] = [
                    'type' => 'price_locked',
                    'item_id' => $masterItemId,
                    'master_price' => $value,
                    'branch_price' => $branchItem->price,
                ];
                continue;
            }

            if ($field === 'is_available' && $override && $override->availability_locked) {
                continue;
            }

            // Apply the update
            $branchItem->$field = $value;
            $appliedUpdates[$field] = $value;
        }

        if (!empty($appliedUpdates)) {
            $branchItem->save();
            $result['updated']++;
            $result['applied_changes'][] = [
                'type' => 'update',
                'item_id' => $masterItemId,
                'fields' => array_keys($appliedUpdates),
            ];
        }
    }

    /**
     * Apply a price change, respecting overrides
     */
    private function applyPriceChange(
        Menu $branchMenu,
        int $masterItemId,
        float $newPrice,
        $override,
        array &$result
    ): void {
        $branchItem = MenuItem::where('menu_id', $branchMenu->id)
            ->where('source_master_item_id', $masterItemId)
            ->first();

        if (!$branchItem) return;

        // Check if price is locked
        if ($override && ($override->price_locked || $override->fully_locked)) {
            $result['conflicts']++;
            $result['conflict_details'][] = [
                'type' => 'price_locked',
                'item_id' => $masterItemId,
                'master_price' => $newPrice,
                'branch_price' => $override->price_override ?? $branchItem->price,
            ];
            return;
        }

        // Check if branch has a local price override
        if ($override && $override->price_override !== null) {
            // Branch has custom price - keep it but log the conflict
            $result['conflicts']++;
            $result['conflict_details'][] = [
                'type' => 'price_override_exists',
                'item_id' => $masterItemId,
                'master_price' => $newPrice,
                'branch_price' => $override->price_override,
            ];
            return;
        }

        // Apply the price change
        $branchItem->price = $newPrice;
        $branchItem->save();
        $result['updated']++;
        $result['applied_changes'][] = [
            'type' => 'price_update',
            'item_id' => $masterItemId,
            'new_price' => $newPrice,
        ];
    }

    /**
     * Set a local override for a branch item
     */
    public function setLocalOverride(
        int $branchSyncId,
        int $masterItemId,
        array $overrideData,
        User $user
    ): void {
        DB::table('branch_menu_overrides')->updateOrInsert(
            [
                'branch_menu_sync_id' => $branchSyncId,
                'master_menu_item_id' => $masterItemId,
            ],
            array_merge($overrideData, [
                'overridden_by' => $user->id,
                'updated_at' => now(),
            ])
        );
    }

    /**
     * Get sync status for a branch
     */
    public function getBranchSyncStatus(int $locationId, int $masterMenuId): array
    {
        $sync = DB::table('branch_menu_sync')
            ->where('location_id', $locationId)
            ->where('master_menu_id', $masterMenuId)
            ->first();

        if (!$sync) {
            return ['synced' => false, 'message' => 'Not linked to master menu'];
        }

        $masterMenu = MasterMenu::find($masterMenuId);
        $pendingVersions = $masterMenu->current_version - $sync->synced_version;

        return [
            'synced' => $pendingVersions === 0,
            'synced_version' => $sync->synced_version,
            'current_version' => $masterMenu->current_version,
            'pending_versions' => $pendingVersions,
            'sync_mode' => $sync->sync_mode,
            'has_pending_updates' => $sync->has_pending_updates,
            'last_synced_at' => $sync->last_synced_at,
        ];
    }

    /**
     * Get pending changes preview for a branch
     */
    public function getPendingChangesPreview(int $branchSyncId): array
    {
        $sync = DB::table('branch_menu_sync')->find($branchSyncId);
        if (!$sync) return [];

        $masterMenu = MasterMenu::find($sync->master_menu_id);
        
        return $this->getChangesBetweenVersions(
            $sync->master_menu_id,
            $sync->synced_version,
            $masterMenu->current_version
        );
    }

    /**
     * Rollback branch to a previous version
     */
    public function rollbackToVersion(int $branchSyncId, int $targetVersion, User $user): array
    {
        $sync = DB::table('branch_menu_sync')->find($branchSyncId);
        if (!$sync) {
            return ['success' => false, 'message' => 'Sync record not found'];
        }

        // Get the snapshot for that version
        $versionRecord = DB::table('master_menu_versions')
            ->where('master_menu_id', $sync->master_menu_id)
            ->where('version_number', $targetVersion)
            ->first();

        if (!$versionRecord || !$versionRecord->snapshot) {
            return ['success' => false, 'message' => 'Version snapshot not available'];
        }

        // This would restore the menu to that snapshot state
        // Implementation depends on your rollback strategy
        
        return [
            'success' => true,
            'message' => "Rolled back to version {$targetVersion}",
        ];
    }
}
