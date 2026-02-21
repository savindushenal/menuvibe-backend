# Multi-Menu Time-Based Routing - Testing Guide

## Overview
This guide demonstrates how to test the newly implemented multi-menu time-based routing system. The system allows restaurants/cafes to define multiple menus (lunch, dinner, ala carte) with different time windows and automatically routes customers to the correct menu or shows them a menu selection corridor.

## Prerequisites
- Backend running with Laravel (php artisan serve)
- Frontend running with Next.js (npm run dev)
- Database migrated with new tables

## Step 1: Run Database Migrations

```bash
cd menuvibe-backend
php artisan migrate
```

This will create:
- `menu_schedules` table - stores time-based menu availability windows
- `timezone` column in `locations` table - for per-location timezone support

## Step 2: Set Up Test Data

### Option A: Using Artisan Commands
```bash
# Create a test location with timezone
php artisan db:seed # if you have a seeder

# Or manually via SQL
INSERT INTO locations (name, timezone, address) VALUES ('Test Cafe', 'UTC', '123 Main St');
INSERT INTO menus (name, location_id) VALUES ('Lunch Menu', 1);
INSERT INTO menus (name, location_id) VALUES ('Dinner Menu', 1);
```

### Option B: Using Admin UI
1. Navigate to `/franchise/[slug]/dashboard/locations`
2. Click on a location's clock icon
3. Create menu schedules for your menus

## Step 3: Create Menu Schedules

### Example 1: Lunch Menu 12:00-18:00
**Admin UI Path:** Location Settings → Menu Schedules → Select "Lunch Menu" → Add Schedule

**Form Values:**
- Start Time: `12:00`
- End Time: `18:00`
- Days: Mon-Fri (0, 1, 2, 3, 4)
- Priority: `10`
- Timezone: `UTC` (or your location's timezone)
- Active: ✓ Checked
- Allow Overlap: ✓ Checked (enables corridor mode)

### Example 2: Dinner Menu 18:01-23:00
**Form Values:**
- Start Time: `18:01`
- End Time: `23:00`
- Days: Mon-Fri (0, 1, 2, 3, 4)
- Priority: `5`
- Allow Overlap: ✓ Checked

### Example 3: All-Day Ala Carte (Fallback)
**Form Values:**
- Start Time: `00:00`
- End Time: `23:59`
- Days: All days (0-6)
- Priority: `0` (lowest)
- Allow Overlap: ✓ Checked

## Step 4: Test Scenarios

### Scenario A: Single Menu Active (Redirect)
**Time:** 12:00-18:00 (Only Lunch Menu active)

**Test Flow:**
1. Visit QR endpoint: `/m/{shortCode}`
2. System should serve Lunch Menu directly
3. Response action should be `'redirect'`

**Expected Response:**
```json
{
  "success": true,
  "action": "redirect",
  "data": {
    "menu": { ... },
    "location": { ... },
    "endpoint": { ... }
  }
}
```

### Scenario B: Multiple Menus Active (Corridor)
**Time:** 17:00-19:00 (Both Lunch and Dinner overlap)

**Test Flow:**
1. Visit QR endpoint: `/m/{shortCode}`
2. Frontend should display corridor landing with menu options
3. User can click "Lunch Menu" or "Dinner Menu"
4. Response action should be `'corridor'`

**Expected Response:**
```json
{
  "success": true,
  "action": "corridor",
  "data": {
    "menus": [
      { "id": 1, "name": "Lunch Menu", "priority": 10 },
      { "id": 2, "name": "Dinner Menu", "priority": 5 }
    ],
    "location": { ... },
    "cache_ttl_seconds": 1800,
    "next_change_at": "2025-02-21T18:01:00Z"
  }
}
```

### Scenario C: Menu Selection from Corridor
**Test Flow:**
1. From corridor, user clicks "Lunch Menu"
2. Frontend adds `?menu_id=1` to URL
3. Backend should return Lunch Menu directly (bypassing corridor)
4. User sees Lunch Menu, not corridor

**URL:** `/m/{shortCode}?menu_id=1`

**Expected Response:**
```json
{
  "success": true,
  "action": "redirect",
  "data": {
    "menu": { "id": 1, "name": "Lunch Menu", ... },
    "location": { ... },
    "endpoint": { ... }
  }
}
```

### Scenario D: No Schedule (Legacy Behavior)
**Setup:** Menu with no schedules defined

**Test Flow:**
1. Visit QR endpoint: `/m/{shortCode}`
2. System should fall back to configured template menu
3. Response uses legacy format

## Step 5: Advanced Testing

### Test Timezone Handling
1. Create location with timezone: `Asia/Colombo` (UTC+5:30)
2. Create schedule: 12:00-18:00
3. Visit endpoint at 11:45 UTC (should be 17:15 local) → Should show menu
4. Visit endpoint at 18:05 UTC (should be 23:35 local) → Should not show menu

### Test Overnight Schedules
1. Create schedule: `22:00-02:00` (10 PM to 2 AM)
2. Create menu endpoint QR code
3. Test at 23:30 and 01:30 → Both should show menu
4. Test at 04:30 → Should not show (outside window)

### Test Date Range (Seasonal Menus)
1. Create schedule for summer menu: 
   - Dates: 2025-06-01 to 2025-08-31
   - Time: 12:00-18:00
2. Test on June 15 → Menu shows
3. Test on May 15 → Menu doesn't show (date outside range)

### Test Priority Conflicts
1. Create two menus both active 12:00-18:00
   - Menu A: Priority 10
   - Menu B: Priority 5
2. Before 12:00 → Corridor shows both
3. Pick Menu A → Should load Menu A directly

## Step 6: Frontend Corridor UI Testing

### Visual Checks
- [ ] Corridor page displays correctly
- [ ] Menu cards show name, description, priority
- [ ] Cards have hover effects
- [ ] Buttons are clickable and lead to menu selection
- [ ] Cache TTL displayed
- [ ] Next change time shown

### Mobile Testing
- [ ] Responsive layout on mobile
- [ ] Menu cards stack properly
- [ ] Buttons are touch-friendly
- [ ] No overflow issues

## Step 7: Edge Cases

### Empty Menus
**Setup:** Location with no menus

**Expected:** Error message or falls back to template

### Invalid Timezone
**Setup:** Create location with invalid timezone

**Expected:** System defaults to UTC

### Overlapping Schedules with Priority
**Setup:** 
- Menu A: 12:00-18:00, Priority 10, allow_overlap=false
- Menu B: 15:00-20:00, Priority 5, allow_overlap=false

**Expected:** At 15:00, Menu A serves (higher priority, no corridor)

## Step 8: API Testing with cURL

### Test Menu Resolution
```bash
# Single menu
curl "http://localhost:8000/api/public/menu/endpoint/abc123"

# With menu_id parameter
curl "http://localhost:8000/api/public/menu/endpoint/abc123?menu_id=1"

# Check schedule resolution
curl "http://localhost:8000/api/franchise/Test/location/1/resolve-menus?datetime=2025-02-21T15:30:00"
```

### Test Schedule Management
```bash
# Create schedule
curl -X POST "http://localhost:8000/api/franchise/Test/menus/1/schedules" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "start_time": "12:00",
    "end_time": "18:00",
    "days": [0,1,2,3,4],
    "priority": 10,
    "timezone": "UTC",
    "is_active": true,
    "allow_overlap": true
  }'

# List schedules
curl "http://localhost:8000/api/franchise/Test/menus/1/schedules" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update schedule
curl -X PUT "http://localhost:8000/api/franchise/Test/menus/1/schedules/1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{ ... }'

# Delete schedule
curl -X DELETE "http://localhost:8000/api/franchise/Test/menus/1/schedules/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Expected Issues & Solutions

### Issue: Corridor not showing
**Cause:** Schedules might not be active at current time
**Solution:** Check current time vs schedule, verify timezone

### Issue: Menu select from corridor not working
**Cause:** menu_id parameter might not be passed correctly
**Solution:** Check browser console, verify API response includes menu data

### Issue: Timezone offset incorrect
**Cause:** Location timezone might not be set
**Solution:** Update location record with correct timezone string

## Success Criteria

✓ Single menu shows when only one is active at current time
✓ Corridor displays when multiple menus are active
✓ Menu selection from corridor works
✓ Timezone handling is correct for different locations
✓ Priority conflicts resolved correctly
✓ Cache TTL helps frontend refresh appropriately
✓ Legacy template-only menus still work (backward compatible)

## Debugging Commands

```bash
# Check location timezone
php artisan tinker
>>> Location::find(1)->timezone

# Check menu schedules for location
>>> Location::find(1)->menus()->with('schedules')->get()

# Test menu resolution service
>>> app(MenuResolver::class)->resolve(Location::find(1))

# Check active schedules at specific time
>>> app(MenuResolver::class)->getActiveMenusAtTime(Location::find(1), now())
```
