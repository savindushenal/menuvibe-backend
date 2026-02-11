# Franchise Tables Consolidation - COMPLETED ✅

## Summary

Successfully consolidated the `franchise_branches` table into the `locations` table, eliminating data duplication and sync issues.

## What Was Done

### 1. Database Changes ✅

#### Added Columns to `locations` Table:
- `branch_code` (varchar) - Unique identifier for franchisebranches
- `is_paid` (boolean) - Payment status
- `activated_at` (timestamp) - Activation date
- `deactivated_at` (timestamp) - Deactivation date

#### Migrated Data:
- Copied 1 branch record (Kollupitiya/BR001) from `franchise_branches` to `locations`
- All branch data preserved

#### Cleaned Up Foreign Keys:
- `menu_sync_logs`: Dropped `branch_id` (now uses `location_id`)
- `branch_offer_overrides`: Renamed `branch_id` → `location_id`
- `branch_menu_overrides`: Renamed `branch_id` → `location_id`
- `franchise_invitations`: Already using `location_id` ✅
- `franchise_accounts`: Already using `location_id` ✅

#### Dropped `franchise_branches` Table:
- Table completely removed from database
- No data loss - all data in `locations`

### 2. Code Changes ✅

#### Updated Controllers:

**FranchiseContextController.php:**
- Changed branch count query from `FranchiseBranch::where()` to `Location::whereNotNull('branch_code')`
- Removed `FranchiseBranch` import

**AdminFranchiseOnboardingController.php:**
- Removed `FranchiseBranch::create()` from `addBranch()`
- Removed `FranchiseBranch::update()` from `updateBranch()`
- Removed `FranchiseBranch::delete()` from `deleteBranch()`
- Removed `FranchiseBranch` import

#### Updated Test Scripts:

**check_isso_locations.php:**
- Changed to query `Location::whereNotNull('branch_code')` instead of `FranchiseBranch`
- Updated dashboard stats calculation

#### Deleted Files:
- `app/Models/FranchiseBranch.php` - No longer needed

### 3. Verification ✅

**ISSO Dashboard Stats (BEFORE):**
- Branches: 0 ❌
- Locations: 1

**ISSO Dashboard Stats (AFTER):**
- Branches: 1 ✅
- Locations: 1 ✅

## How It Works Now

### Identifying Branches

A `Location` is considered a franchise branch if `branch_code IS NOT NULL`:

```php
// Get all branches for a franchise
$branches = Location::where('franchise_id', $franchiseId)
    ->whereNotNull('branch_code')
    ->get();

// Count branches
$branchCount = Location::where('franchise_id', $franchiseId)
    ->whereNotNull('branch_code')
    ->count();
```

### Creating a Branch

Simply create a `Location` with a `branch_code`:

```php
$branch = Location::create([
    'franchise_id' => $franchiseId,
    'branch_code' => Location::generateBranchCode($franchiseId),
    'branch_name' => 'Branch Name',
    'is_paid' => false,
    'activated_at' => now(),
    // ... other location fields
]);
```

### Branch Status Management

Use the `Location` model methods:

```php
// Activate a branch
$location->activate();

// Deactivate a branch
$location->deactivate();

// Check if location is a branch
if ($location->isBranch()) {
    // It's a branch
}
```

## Benefits

### ✅ Single Source of Truth
- No more data duplication
- Location data always in sync

### ✅ Simplified Code
- Removed 50+ lines of duplicate creation/update/delete logic
- Fewer queries needed
- Cleaner controller methods

### ✅ Reduced Database Overhead
- One less table to maintain
- Fewer foreign keys
- Simpler schema

### ✅ Better Data Integrity
- Impossible to have location/branch mismatch
- No sync issues between tables

## Migration Files

1. **2026_02_12_000001_consolidate_franchise_branches_into_locations.php**
   - Adds columns to `locations`
   - Migrates data from `franchise_branches`
   - Status: Partially applied (columns added, data migrated)

2. **2026_02_12_000002_fix_duplicate_columns.php**
   - Cleans up duplicate `branch_id`/`location_id` columns
   - Drops `franchise_branches` table
   - Status: Applied successfully

## Database Schema (After Consolidation)

```sql
-- locations table
CREATE TABLE locations (
    id bigint PRIMARY KEY,
    user_id bigint,
    franchise_id bigint NULL,           -- NEW: Links to franchise
    name varchar(255),
    branch_name varchar(255) NULL,
    branch_code varchar(255) NULL,      -- NEW: Identifies franchise branches
    address_line_1 varchar(255),
    city varchar(255),
    phone varchar(255),
    is_active boolean DEFAULT 1,
    is_paid boolean DEFAULT 1,          -- NEW: Payment status
    activated_at timestamp NULL,        -- NEW: Activation date
    deactivated_at timestamp NULL,      -- NEW: Deactivation date
    created_at timestamp,
    updated_at timestamp,
    
    INDEX (franchise_id, branch_code)   -- NEW: For franchise queries
);

-- franchise_branches table - DROPPED ✅
```

## Related Tables Now Using location_id

All these tables now reference `locations.id` instead of `franchise_branches.id`:

- `franchise_invitations.location_id`
- `franchise_accounts.location_id`
- `menu_sync_logs.location_id`
- `branch_offer_overrides.location_id`
- `branch_menu_overrides.location_id`

## Future Development

When working with franchise branches:

1. **Always use `Location` model** - FranchiseBranch no longer exists
2. **Filter by `branch_code`** - Use `whereNotNull('branch_code')` to identify branches
3. **Use Location methods** - `activate()`, `deactivate()`, `isBranch()`, `generateBranchCode()`
4. **No syncing needed** - Everything is in one table

## Testing

✅ ISSO dashboard shows correct count (1 branch, 1 location)
✅ Admin can create branches (creates Location with branch_code)
✅ Admin can update branches (updates Location directly)
✅ Admin can delete branches (deletes Location directly)
✅ All foreign keys point to correct table
