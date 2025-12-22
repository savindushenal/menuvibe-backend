# Menu Version Control & Sync System

## Overview

This system enables franchise owners to maintain a master menu template that can be synced to multiple branch locations while respecting local pricing and availability overrides.

## Key Features

### 1. Version Control
- Every change to a master menu creates a new version
- Full change history with rollback capability
- Snapshot storage for each version

### 2. Sync Modes
| Mode | Description |
|------|-------------|
| `auto` | Changes automatically applied to branches |
| `manual` | Branch manager must approve changes |
| `disabled` | No sync - branch operates independently |

### 3. Local Overrides
Branches can lock specific aspects of menu items:
- **Price Locked**: Master price changes are ignored
- **Availability Locked**: Master availability changes ignored
- **Fully Locked**: Item is completely independent from master

## Database Tables

### `master_menu_versions`
Stores version history for each master menu.

| Column | Description |
|--------|-------------|
| version_number | Sequential version number |
| change_type | Type of change (item_added, price_change, etc.) |
| change_summary | Human-readable description |
| changes_data | JSON diff of what changed |
| snapshot | Full menu state for rollback |

### `branch_menu_sync`
Tracks sync status for each branch.

| Column | Description |
|--------|-------------|
| synced_version | Last synced master version |
| sync_mode | auto/manual/disabled |
| has_pending_updates | Whether updates are waiting |
| pending_changes | JSON of pending changes |

### `branch_menu_overrides`
Stores local overrides for branch-specific pricing.

| Column | Description |
|--------|-------------|
| price_override | Custom branch price |
| price_locked | Whether to ignore master prices |
| fully_locked | Item is completely independent |

### `menu_sync_logs`
Audit trail of all sync operations.

## API Endpoints

### Branch Sync Status
```
GET /api/menu-sync/status/{locationId}/{masterMenuId}
```
Returns sync status including pending versions.

### Preview Pending Changes
```
GET /api/menu-sync/{branchSyncId}/pending
```
Shows what changes will be applied during sync.

### Manual Sync
```
POST /api/menu-sync/{branchSyncId}/sync
Body: { "target_version": 5 }  // Optional
```
Triggers sync to specific or latest version.

### Update Sync Mode
```
PUT /api/menu-sync/{branchSyncId}/mode
Body: { "sync_mode": "auto" | "manual" | "disabled" }
```

### Set Item Override
```
POST /api/menu-sync/{branchSyncId}/override/{masterItemId}
Body: {
  "price_override": 450.00,
  "price_locked": true,
  "notes": "Higher rent area"
}
```

### Remove Override
```
DELETE /api/menu-sync/{branchSyncId}/override/{masterItemId}
```

### Get All Overrides
```
GET /api/menu-sync/{branchSyncId}/overrides
```

### Sync History
```
GET /api/menu-sync/{branchSyncId}/history
```

### Version History
```
GET /api/menu-sync/versions/{masterMenuId}
```

### Initialize Branch Sync
```
POST /api/menu-sync/initialize
Body: {
  "location_id": 1,
  "menu_id": 1,
  "master_menu_id": 1,
  "sync_mode": "auto"
}
```

### Franchise Dashboard
```
GET /api/menu-sync/dashboard/{franchiseId}
```
Summary of all master menus and branch sync status.

### Bulk Sync All Branches
```
POST /api/menu-sync/bulk/{masterMenuId}
```
Syncs all branches to latest version.

## Conflict Resolution

When syncing, the system handles conflicts as follows:

1. **Price Locked Items**: Master price changes are skipped, logged as conflict
2. **Availability Locked**: Availability changes skipped
3. **Fully Locked**: All changes skipped for this item
4. **Local Price Override**: If branch has custom price, master changes skipped

### Conflict Details
Every sync operation logs:
- Items added
- Items updated
- Items removed
- Conflicts skipped
- Detailed conflict reasons

## Usage Examples

### Pizza Hut Scenario
1. HQ creates master menu with 50 items
2. 40 branches linked with `auto` sync mode
3. Some locations set `price_override` for premium areas
4. When HQ adds new item → auto-synced to all branches
5. When HQ changes price → branches with `price_locked` keep their prices

### Branch Manager Workflow
1. Check sync status: `GET /api/menu-sync/status/5/1`
2. Preview changes: `GET /api/menu-sync/3/pending`
3. Lock special pricing: `POST /api/menu-sync/3/override/10` with `price_locked: true`
4. Trigger sync: `POST /api/menu-sync/3/sync`

### Franchise Owner Dashboard
```javascript
// Get overview of all branches
const dashboard = await fetch('/api/menu-sync/dashboard/1');
// Returns:
{
  "data": [{
    "master_menu": { "id": 1, "name": "Main Menu", "current_version": 5 },
    "branches": {
      "total": 40,
      "synced": 35,
      "pending": 5,
      "auto_sync_enabled": 30
    }
  }]
}
```

## Sync Policy Configuration

Master menus can define sync policies:

```json
{
  "auto_sync": ["new_items", "removed_items"],
  "manual_sync": ["prices", "descriptions"],
  "never_sync": ["local_specials"]
}
```

This allows:
- New items auto-added to all branches
- Removed items auto-removed
- Price changes require manual approval
- Local specials are branch-only

## Best Practices

1. **Use Auto Sync for Structure**: New items, removed items
2. **Use Manual Sync for Prices**: Allows review before applying
3. **Lock High-Traffic Locations**: Premium pricing areas
4. **Regular Dashboard Checks**: Monitor pending updates
5. **Document Overrides**: Use `notes` field to explain why
