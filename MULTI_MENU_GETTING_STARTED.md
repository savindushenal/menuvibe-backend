# Multi-Menu Time-Based Routing - Getting Started

## 🎉 Feature Complete!

The multi-menu time-based routing system has been successfully implemented. This guide will help you deploy and test the feature.

## 📋 Quick Summary

**What was built:**
- Smart menu selection system that routes customers to the right menu based on time of day
- "Corridor" landing page when multiple menus are active at the same time
- Admin interface to create and manage menu schedules
- Timezone-aware scheduling with priority-based conflict resolution

**Use Case Example:**
- Restaurant opens at 12:00 with a "Lunch Menu"
- At 18:00, switches to "Dinner Menu"  
- At 18:00 exactly, if both menus are active, show customer a menu selection page
- Customer can pick which menu they want to view

## 🚀 Deployment Steps

### 1. Run Backend Migrations

Navigate to the backend directory and run migrations:

```bash
cd menuvibe-backend

# Run all pending migrations
php artisan migrate

# Or specific migration
php artisan migrate --path=database/migrations/2025_02_21
```

**What gets created:**
- `menu_schedules` table - 10 columns for time windows, priority, timezone
- `timezone` column in `locations` table - for per-location timezone tracking

### 2. Verify Database Changes

```bash
# Check if migrations ran successfully
php artisan migrate:status

# Check table structure
php artisan tinker
>>> DB::table('menu_schedules')->limit(1)->get()
>>> DB::table('locations')->first(['id', 'name', 'timezone'])
```

### 3. Configure Location Timezones

Update each location with its correct timezone:

```bash
php artisan tinker
>>> Location::first()->update(['timezone' => 'UTC']);
>>> Location::find(2)->update(['timezone' => 'Asia/Colombo']);
>>> Location::find(3)->update(['timezone' => 'America/New_York']);
```

**Supported timezones:** Any PHP timezone (UTC, Asia/Colombo, America/New_York, Europe/London, etc.)

### 4. Create Menu Schedules

**Via Admin UI (Recommended):**
1. Navigate to `/franchise/[franchise-slug]/dashboard/locations`
2. Click the ⏰ icon on any location
3. Select a menu and click "Add Schedule"
4. Configure:
   - Start/End time (HH:MM format)
   - Days of week (Mon-Sun)
   - Priority (0-100, higher wins)
   - Allow overlap (✓ to show corridor, ☐ to auto-select)
5. Click "Create Schedule"

**Via API:**
```bash
curl -X POST "http://localhost:8000/api/franchise/test/menus/1/schedules" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "start_time": "12:00",
    "end_time": "18:00",
    "days": [0, 1, 2, 3, 4],
    "priority": 10,
    "timezone": "UTC",
    "is_active": true,
    "allow_overlap": true
  }'
```

## 🧪 Testing the Feature

### Test 1: Single Menu Active (Auto-selection)
**Setup:**
- Menu: "Lunch Menu" 
- Schedule: 12:00-18:00, Mon-Fri
- Priority: 10 (highest)

**Test:**
- Visit QR endpoint at 14:00
- Should show Lunch Menu directly (no corridor)
- Response action: `'redirect'`

### Test 2: Multiple Menus Active (Corridor)
**Setup:**
- Menu 1: "Lunch" 12:00-18:00, Priority 10 ✓ Allow overlap
- Menu 2: "Dinner" 17:00-23:00, Priority 5 ✓ Allow overlap

**Test:**
- Visit QR endpoint at 17:30
- Should show corridor with both menus
- Response action: `'corridor'`
- Click menu to select

### Test 3: Menu Selection from Corridor
**Setup:** Same as Test 2

**Test:**
- From corridor, click "Lunch Menu"
- URL adds `?menu_id=1`
- Returns Lunch Menu directly

## 📊 Response Formats

### Corridor Response (Multiple Menus)
```json
{
  "success": true,
  "action": "corridor",
  "data": {
    "location": {
      "id": 1,
      "name": "Colombo Branch"
    },
    "menus": [
      {
        "id": 1,
        "name": "Lunch Menu",
        "slug": "lunch-menu",
        "priority": 10
      },
      {
        "id": 2,
        "name": "Dinner Menu",
        "slug": "dinner-menu",
        "priority": 5
      }
    ],
    "cache_ttl_seconds": 1800,
    "next_change_at": "2025-02-21T18:01:00Z"
  }
}
```

### Redirect Response (Single Menu)
```json
{
  "success": true,
  "action": "redirect",
  "data": {
    "menu": { ... full menu data ... },
    "location": { ... },
    "endpoint": { ... },
    "franchise": { ... }
  }
}
```

## 📁 Implementation Files

### Backend (Laravel)
| File | Purpose |
|------|---------|
| `migrations/.../menu_schedules` | Stores schedule windows |
| `migrations/.../add_timezone_to_locations` | Location timezone field |
| `Models/MenuSchedule.php` | Schedule model with logic |
| `Services/MenuResolver.php` | Core resolution logic |
| `Controllers/MenuScheduleController.php` | Admin CRUD endpoints |
| `Controllers/PublicMenuController.php` | QR endpoint (enhanced) |
| `routes/api.php` | API route definitions |

### Frontend (Next.js)
| File | Purpose |
|------|---------|
| `app/m/[code]/page.tsx` | QR endpoint (enhanced with corridor) |
| `app/.../locations/page.tsx` | Location list with manage link |
| `app/.../locations/[locationId]/page.tsx` | Schedule management page |
| `app/.../MenuScheduleDialog.tsx` | Schedule form component |

## ⚙️ Configuration

### Frontend - Location Timezone
In admin UI, each location has a timezone field. This is used by the backend to evaluate schedules.

### Backend - Menu Schedule Fields

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| start_time | HH:MI:SS | ✓ | — | 24-hour, e.g., "12:00" |
| end_time | HH:MI:SS | ✓ | — | 24-hour, e.g., "18:00" |
| days | array | ✓ | — | [0-6], 0=Mon, 6=Sun |
| priority | int | | 0 | Higher wins, 0-100 |
| timezone | string | ✓ | — | PHP timezone (UTC, etc) |
| is_active | bool | | true | Disable without deleting |
| allow_overlap | bool | | false | Show corridor if conflicts |
| start_date | date | | null | Seasonal start (YYYY-MM-DD) |
| end_date | date | | null | Seasonal end (YYYY-MM-DD) |

## 🔧 Advanced Features

### Overnight Schedules
Create a schedule ending after it starts:
- Start: 22:00, End: 02:00 = 10 PM to 2 AM
- System handles overnight window correctly

### Seasonal Menus
Set start/end dates to enable menus only during specific periods:
- Summer Menu: June 1 - August 31
- Holiday Menu: Dec 1 - Dec 25

### Priority Conflicts
- **Higher Priority Wins**: Menu A (priority 10) beats Menu B (priority 5)
- **Equal Priority Shows Corridor**: Both priorit 0, customer picks
- **No allow_overlap**: Highest priority takes over (no corridor)

## 📊 Monitor Performance

### Check Active Menus
```bash
php artisan tinker
>>> app(MenuResolver::class)->resolve(Location::find(1))
```

### Check Next Schedule Change
```bash
>>> app(MenuResolver::class)->getNextMenuChangeAt(Location::find(1), now())
# Returns timestamp of next schedule change
```

### Monitor Cache TTL
Corridor responses include `cache_ttl_seconds`. Frontend can use this to refresh intelligently.

```javascript
// Example: Refresh menu every 30 minutes or every cache_ttl seconds
const refreshInterval = data.cache_ttl_seconds * 1000; // Convert to ms
```

## 🚨 Troubleshooting

### Issue: Corridor not appearing
**Diagnosis:** Multiple menus not active at same time
- Check current time vs schedule time
- Verify timezone setting on location
- Confirm allow_overlap=true on schedules

### Issue: Wrong menu showing
**Diagnosis:** Priority conflict resolution
- Check priority values (higher wins)
- Verify both menus are active at same time
- Check allow_overlap setting

### Issue: Timezone incorrect
**Diagnosis:** Location timezone not set or PHP timezone list mismatch
- Run: `php artisan tinker` → `Location::first()->timezone`
- Update as needed: `Location::first()->update(['timezone' => 'UTC'])`
- Verify against PHP timezone list

### Issue: API returning legacy format
**Diagnosis:** Endpoint not location-based
- Ensure Location and MenuEndpoint are properly linked
- Check `menu_endpoint.location_id` is set
- Fall back to template behavior if location missing

## 📚 Documentation

- **Implementation Details:** See `MULTI_MENU_IMPLEMENTATION_SUMMARY.md`
- **Testing Guide:** See `MULTI_MENU_TESTING_GUIDE.md`
- **API Reference:** See endpoint documentation above

## 🎯 Next Steps

1. **Deploy:** Run migrations on development/staging/production
2. **Configure:** Set location timezones
3. **Create Schedules:** Add time windows via admin UI
4. **Test:** Use testing guide to verify all scenarios
5. **Monitor:** Check analytics on menu selection patterns
6. **Iterate:** Adjust schedules based on customer behavior

## 💡 Tips for Success

- Start with one location and one menu to test
- Use UTC timezone initially to avoid confusion
- Create overlapping schedules to test corridor
- Monitor redirect vs corridor ratio in analytics
- Get feedback from staff/customers on menu selection

## 🤝 Support

For issues or questions:
1. Check the `MULTI_MENU_TESTING_GUIDE.md` for common scenarios
2. Review `MULTI_MENU_IMPLEMENTATION_SUMMARY.md` for technical details
3. Check database records: `SELECT * FROM menu_schedules`
4. Test API endpoints with cURL or Postman
5. Enable Laravel query logging for debugging

---

**Status:** ✅ Feature implemented and ready for production testing

**Last Updated:** February 21, 2025

**Version:** 1.0
