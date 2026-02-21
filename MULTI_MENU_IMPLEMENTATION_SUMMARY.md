# Multi-Menu Time-Based Routing - Implementation Summary

## Completed Features

### ✅ Backend Infrastructure
- **Database Migrations**
  - `menu_schedules` table: Stores time-based availability windows with priority, timezone, seasonal date ranges
  - `timezone` column added to `locations` table: Enables per-location timezone support

- **Models & ORM**
  - `MenuSchedule` model with timezone-aware logic (isActiveAt, getNextChangeAt)
  - `Menu` model relationships: schedules(), activeSchedules()
  - Location relationships configured

- **Menu Resolver Service** (Core Logic)
  - `resolve(Location $location, ?Carbon $dateTime)`: Main entry point for menu resolution
  - Smart menu selection with priority-based conflict resolution
  - Returns action: 'corridor'|'redirect'|'error' with metadata
  - Timezone-aware: All comparisons in location's timezone
  - Cache TTL calculation: next_change_at timestamp for client-side optimization
  - Overnight window support: Handles schedules like 22:00-02:00

- **API Controllers & Routes**
  - MenuScheduleController: 6 endpoints for CRUD + admin operations
  - PublicMenuController: Enhanced QR endpoint with corridor support
  - menu_id parameter support: Force menu selection from corridor
  - API routes: 7 new endpoints under franchise group

### ✅ Frontend Implementation
- **Corridor Landing Page**
  - Menu selection cards with name, description, priority
  - Handles action='corridor' response from API
  - Clickable menu cards leading to ?menu_id=X parameter
  - Cache TTL display
  - Responsive design (mobile-first)

- **Admin UI for Menu Schedule Management**
  - Location-based schedule management page
  - MenuScheduleDialog: Create/edit/delete schedules
  - Form fields: Time (HH:MM), Days of week, Priority, Seasonal date range
  - Timezone display and info
  - Visual schedule conflict indicators
  - Toggle for allow_overlap behavior

- **Frontend Routes**
  - `/m/[code]`: QR endpoint with corridor support
  - `/[franchise]/dashboard/locations`: Updated with schedule management link
  - `/[franchise]/dashboard/locations/[locationId]`: New schedule management page

### 🔄 Data Flow

```
QR Scan (/m/{code})
    ↓
MenuResolver.resolve()
    ├─ Check location schedules
    ├─ Evaluate time/day/priority
    └─ Return action + data
    ├─ action='corridor' → Show menu selection
    ├─ action='redirect' → Load menu directly
    └─ action='error' → Show error

Menu Selection (with ?menu_id=X)
    ↓
PublicMenuController enforces menu_id
    ↓
Return selected menu directly
```

## Key Concepts

### Priority Resolution
- **Higher Priority Wins**: When one menu has higher priority, it's auto-selected
- **Conflict Resolution**: Equal priority triggers corridor mode
- **Cascade**: Admin UI shows visually which menus will be selected

### Timezone Support
- Per-location timezone setting
- All schedule evaluation happens in location's timezone
- Carbon library handles timezone conversion
- Supports overnight schedules (e.g., 22:00-02:00)

### Caching Strategy
- `next_change_at` timestamp: When next schedule change occurs
- `cache_ttl_seconds`: How long to cache the current resolution
- Ranges: 1 minute (to next second change) to 1 day (fully scheduled)
- Frontend can use this to optimize refresh intervals

### Backward Compatibility
- Menus without schedules fall back to legacy template behavior
- Existing QR endpoints continue to work
- No breaking changes to API responses (action field is additive)

## API Endpoints

### Public (Customer-facing)
- `GET /api/public/menu/endpoint/{shortCode}`
  - NEW: Returns action='corridor'|'redirect' with routing logic
  - PARAM: ?menu_id=123 to force menu selection

### Admin (Schedule Management)
- `GET /franchise/{slug}/location/{locationId}/menus-schedules`
  - List all menus with schedules for location
  
- `GET /franchise/{slug}/location/{locationId}/resolve-menus?datetime=...`
  - Resolve active menus at specific time
  
- `POST /franchise/{slug}/menus/{menuId}/schedules`
  - Create new schedule
  - Body: start_time, end_time, days[], priority, timezone, dates
  
- `GET /franchise/{slug}/menus/{menuId}/schedules`
  - List schedules for menu
  
- `GET /franchise/{slug}/menus/{menuId}/schedules/{scheduleId}`
  - Get single schedule details
  
- `PUT /franchise/{slug}/menus/{menuId}/schedules/{scheduleId}`
  - Update schedule
  
- `DELETE /franchise/{slug}/menus/{menuId}/schedules/{scheduleId}`
  - Delete schedule

## Configuration Options

### Menu Schedule Fields
| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| start_time | HH:MI:SS | Yes | - | 24-hour format |
| end_time | HH:MI:SS | Yes | - | 24-hour format |
| days | [0-6] array | Yes | - | 0=Monday, 6=Sunday |
| priority | integer | No | 0 | Higher = picked first |
| timezone | string | Yes | - | PHP timezone (UTC, Asia/Colombo, etc) |
| is_active | boolean | No | true | Disable without deleting |
| allow_overlap | boolean | No | false | Show corridor if conflicts |
| start_date | YYYY-MM-DD | No | null | Seasonal: menu starts on date |
| end_date | YYYY-MM-DD | No | null | Seasonal: menu ends on date |

## Files Created/Modified

### Backend
- `database/migrations/2025_02_21_000001_create_menu_schedules_table.php` ✨ NEW
- `database/migrations/2025_02_21_000002_add_timezone_to_locations_table.php` ✨ NEW
- `app/Models/MenuSchedule.php` ✨ NEW
- `app/Models/Menu.php` 🔧 UPDATED
- `app/Services/MenuResolver.php` ✨ NEW
- `app/Http/Controllers/MenuScheduleController.php` ✨ NEW
- `app/Http/Controllers/PublicMenuController.php` 🔧 UPDATED
- `routes/api.php` 🔧 UPDATED

### Frontend
- `app/m/[code]/page.tsx` 🔧 UPDATED
- `app/[franchise]/dashboard/locations/page.tsx` 🔧 UPDATED
- `app/[franchise]/dashboard/locations/[locationId]/page.tsx` ✨ NEW
- `app/[franchise]/dashboard/locations/[locationId]/MenuScheduleDialog.tsx` ✨ NEW

## Testing Requirements

### Database
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify tables exist: `menu_schedules`, location `timezone` column

### API Testing
- [ ] Create test location with timezone
- [ ] Create menus for location
- [ ] Create schedules with various times/priorities
- [ ] Test corridor response with multiple active menus
- [ ] Test redirect response with single menu
- [ ] Test menu_id parameter forcing selection

### Frontend Testing
- [ ] Corridor landing page displays correctly
- [ ] Menu cards clickable to select menu
- [ ] Admin schedule form validates inputs
- [ ] Schedule CRUD operations work
- [ ] Responsive design on mobile

### Edge Cases
- [ ] Overnight schedules (22:00-02:00)
- [ ] Seasonal date ranges
- [ ] Different timezones
- [ ] Priority conflicts
- [ ] Menus with no schedules (fallback)

## Next Steps

1. **Database Migration**
   ```bash
   php artisan migrate
   ```

2. **Test Schedule Creation**
   - Navigate to location settings
   - Create test schedules with various priorities
   - Verify times/days specified correctly

3. **Test QR Scanning**
   - Visit QR endpoint at different times
   - Verify corridor appears when expected
   - Verify menu selection works

4. **Performance Testing**
   - Check query performance with many schedules
   - Verify caching strategy effectiveness
   - Monitor timezone conversion overhead

## Rollback Plan

If issues encountered:

### Revert Changes
```bash
# Backend
git reset --hard <previous-commit>
php artisan migrate:rollback

# Frontend
git reset --hard <previous-commit>
npm run build
```

### Disable Feature
Without rolling back completely, disable by:
1. Remove menu_id parameter checking in PublicMenuController
2. Return legacy response format instead of corridor

## Performance Considerations

- **Queries**: MenuResolver caches menu relationships
- **Timezone Conversion**: Minimal overhead (Carbon built-in)
- **Schedule Evaluation**: O(n) where n = active menus
- **Indexes**: Created on location_id, franchise_id, menu_id, priority

## Security Notes

- Schedule times validated with PHP time parsing
- Timezone validated against PHP timezone list
- Menu_id parameter validated against user's location menus
- Date ranges validated for logical ordering
- All endpoints require franchise authentication

## Future Enhancements

1. **Advanced Scheduling**
   - Recurring patterns (bi-weekly, monthly)
   - Holiday exceptions
   - Special event menus

2. **Analytics**
   - Track how often corridor appears
   - Which menus customers select
   - Time-based menu popularity

3. **UI/UX**
   - Calendar view of schedule
   - Bulk schedule upload
   - Template schedules (copy from other locations)
   - Smart conflict resolution suggestions

4. **Mobile App**
   - Native corridor UI
   - Favorite menu persistence
   - Quick menu switching
