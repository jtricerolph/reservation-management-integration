# Backup - Hotel Admin Plugin v1.1.15

**Backup Date:** October 31, 2025
**Backup Reason:** Pre-restructuring backup before major code refactoring
**Plugin Version:** 1.1.15
**JavaScript Version:** 1.0.75

---

## What's in This Backup

This is a complete backup of the Hotel Admin plugin before a major restructuring effort.

### Main Files
- `hotel-admin.php` (215KB) - Main plugin file with all functionality
- `assets/staying-today.js` - JavaScript for UI and API interactions
- `assets/style.css` - All CSS styling
- `assets/SYSTEM_SUMMARY.md` - Comprehensive technical documentation

### Documentation
- `CHANGELOG.md` - Complete version history
- `summary.md` - Quick reference guide
- `API_PREVIEW_PARITY.md` - API development guidelines
- `RESOS_STATUS_VALUES.md` - Resos status reference
- `restructuring-plan.md` - Future refactoring plans
- `changelog.md` - Additional change notes

---

## Version 1.1.15 Features

### Major Features
1. **Dynamic Dietary Requirements**
   - Checkboxes auto-generated from Resos configuration
   - Uses choice IDs for reliable matching
   - Auto-syncs with Resos settings changes

2. **Booking Notes**
   - Restaurant notes via `/restaurantNote` endpoint
   - Collapsible UI section in booking form
   - Automatic addition after booking creation

3. **Version Management**
   - Version constants in class
   - Footer display showing plugin and JS versions
   - Synchronized asset cache busting

### Key Functions Added
- `ajax_get_dietary_choices()` - Fetches dietary options from Resos
- `fetchDietaryChoices()` - Loads choices on page load (JS)
- `populateDietaryCheckboxes()` - Dynamically creates checkboxes (JS)

### Bug Fixes
- Fixed booking ID extraction for string responses
- Fixed JavaScript syntax errors
- Corrected API key option name
- Fixed function hoisting issues

---

## Restoration Instructions

To restore this version:

1. **Backup Current State** (if needed)
   ```bash
   cp hotel-admin.php hotel-admin.php.backup
   cp -r assets assets.backup
   ```

2. **Restore Files**
   ```bash
   cp BACKUP/v1.1.15_2025-10-31/hotel-admin.php ./
   cp -r BACKUP/v1.1.15_2025-10-31/assets ./
   ```

3. **Clear Cache**
   - Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)
   - Clear WordPress cache if using caching plugin

4. **Verify Version**
   - Check footer on page shows "Plugin v1.1.15 | JS v1.0.75"
   - Check WordPress admin shows version 1.1.15

---

## Working State

### Tested & Working
✅ Auto-matching hotel guests with restaurant bookings
✅ Creating new Resos bookings from hotel data
✅ Updating existing Resos bookings
✅ Dynamic dietary requirements (synced with Resos)
✅ Booking notes functionality
✅ Testing and Sandbox modes
✅ API preview/execute parity
✅ Gantt chart visualization
✅ Time slot selection
✅ Version display in footer

### Configuration
- Newbook PMS API: Fully configured and working
- Resos API: Fully configured and working
- Custom fields: Hotel Guest, Booking #, DBB, Dietary Requirements
- API Mode: Production/Testing/Sandbox modes available

---

## Technical Details

### Architecture
- **Monolithic Structure**: All code in single `hotel-admin.php` file
- **WordPress Plugin**: Standard WordPress plugin architecture
- **AJAX Endpoints**: WordPress AJAX handlers for API calls
- **External APIs**: Newbook PMS (incoming), Resos (bidirectional)

### Database
- No custom database tables
- Uses WordPress transient cache (1-hour TTL for opening hours)
- All data fetched from external APIs

### Performance
- Lazy loading of dietary choices on page load
- Cached opening hours (1-hour transient)
- Asset versioning for cache busting

---

## Next Steps (Post-Backup)

This backup was created before attempting:
- Code restructuring to modular architecture
- Separation of concerns (MVC pattern)
- Breaking monolithic file into smaller components
- Improved code organization

**Important:** Test all functionality after restructuring and compare with this backup version if issues arise.

---

**Backup Status:** Complete and Verified
**File Count:** 10 markdown files + 1 PHP file + assets directory
**Total Size:** ~376KB
