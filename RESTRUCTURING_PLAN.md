# Hotel Admin Plugin - Restructuring Plan
## From Monolithic to Modular Architecture

**Date Created**: October 31, 2025
**Current Version**: v1.1.15
**Backup Location**: [BACKUP/v1.1.15_2025-10-31/](BACKUP/v1.1.15_2025-10-31/)
**Current Structure**: Single 4,435-line file ([hotel-admin.php](hotel-admin.php))
**Target Structure**: Modular architecture following WordPress standards

---

## Executive Summary

Convert the hotel-admin plugin from a monolithic single-file structure to a modular, maintainable architecture similar to the hotel-maintenance-management plugin. This restructuring will improve code organization, maintainability, and future development while maintaining 100% backward compatibility with existing functionality.

**Key Metrics**:
- Current: 1 file, 4,435 lines, 50 functions
- Target: 13+ files, ~200-400 lines each, organized by responsibility
- AJAX Endpoints: 7 handlers to move
- API Integrations: 2 external APIs (Newbook PMS, Resos)
- Settings: 8 WordPress options
- Shortcode: 1 shortcode `[hotel-table-bookings-by-date]`

---

## Current State Analysis

### File Structure (Before)
```
hotel-admin/
├── hotel-admin.php (4,435 lines - EVERYTHING)
├── assets/
│   ├── staying-today.js (v1.0.75)
│   └── style.css (v1.0.75)
└── [documentation files]
```

### Function Breakdown (50 total functions)

**Constructor & Hooks** (1):
- `__construct()` - Registers all hooks and shortcodes

**Admin Settings** (15):
- `add_admin_menu()`
- `register_settings()`
- `render_settings_page()`
- `settings_section_callback()`
- `resos_section_callback()`
- `testing_section_callback()`
- `username_field_callback()`
- `password_field_callback()`
- `api_key_field_callback()`
- `region_field_callback()`
- `hotel_id_field_callback()`
- `resos_api_key_field_callback()`
- `package_inventory_name_field_callback()`
- `mode_field_callback()`
- Field rendering callbacks (multiple)

**Asset Management** (2):
- `enqueue_styles()`
- `enqueue_scripts()`

**AJAX Handlers** (7):
- `ajax_get_available_times()` - Resos available time slots
- `ajax_preview_resos_match()` - Preview update request
- `ajax_confirm_resos_match()` - Execute update booking
- `ajax_create_resos_booking()` - Execute create booking
- `ajax_preview_resos_create()` - Preview create request
- `ajax_get_dietary_choices()` - Fetch dietary options from Resos
- `test_available_dates()` - Test endpoint for dates

**Newbook PMS API** (~8):
- `call_api()` - Base API call wrapper
- `get_bookings_data()` - Fetch hotel bookings
- `get_rooms_data()` - Fetch room information
- `get_note_types()` - Fetch note type definitions
- `get_group_details()` - Fetch booking group details
- Related helper functions

**Resos API** (~10):
- `get_restaurant_bookings_data()` - Fetch restaurant bookings
- `get_resos_available_times()` - Available time slots
- `get_resos_available_dates()` - Date availability
- `get_resos_opening_hours()` - Opening hours with caching
- `get_custom_field_definitions()` - Custom field schemas
- Create/update booking logic (within AJAX handlers)
- Add restaurant notes logic

**Matching Logic** (~5):
- `match_resos_to_hotel_booking()` - Core matching algorithm
- `prepare_comparison_data()` - Generate suggestions
- `format_phone_for_resos()` - Phone number formatting
- Confidence scoring logic

**Rendering** (~5):
- `render_booking_table()` - Main shortcode renderer
- `prevent_texturize()` - WordPress filter
- HTML generation functions

**Utilities** (3):
- `add_error()`
- `get_errors()`
- `get_hotel_id()`

---

## Target Structure (After)

### New Directory Structure
```
hotel-admin/
├── hotel-admin.php                    # Bootstrap (~60 lines)
│
├── includes/                          # Core functionality
│   ├── class-hbt-core.php            # Main orchestration (~100 lines)
│   ├── class-hbt-activator.php       # Plugin activation (~50 lines)
│   ├── class-hbt-deactivator.php     # Plugin deactivation (~30 lines)
│   ├── class-hbt-ajax.php            # AJAX handlers (~800 lines)
│   ├── class-hbt-newbook-api.php     # Newbook PMS integration (~400 lines)
│   ├── class-hbt-resos-api.php       # Resos API integration (~600 lines)
│   └── class-hbt-matcher.php         # Matching algorithm (~400 lines)
│
├── admin/                             # Admin functionality
│   ├── class-hbt-admin.php           # Admin menu, settings (~300 lines)
│   └── views/
│       └── admin-settings.php        # Settings page template (~200 lines)
│
├── public/                            # Front-end functionality
│   ├── class-hbt-public.php          # Shortcode, display logic (~600 lines)
│   └── views/
│       └── booking-table.php         # Table rendering template (~800 lines)
│
└── assets/                            # Assets (unchanged)
    ├── staying-today.js
    └── style.css
```

### Class Responsibilities

#### 1. **hotel-admin.php** (Bootstrap)
**Purpose**: Plugin entry point and initialization
**Lines**: ~60
**Responsibilities**:
- Define plugin constants (VERSION, JS_VERSION, paths)
- Register activation/deactivation hooks
- Require core class
- Initialize plugin

**Code Structure**:
```php
<?php
/**
 * Plugin Name: Hotel Booking Table by Date
 * Version: 1.1.15
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('HBT_VERSION', '1.1.15');
define('HBT_JS_VERSION', '1.0.75');
define('HBT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HBT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activation/deactivation
function activate_hotel_booking_table() {
    require_once HBT_PLUGIN_DIR . 'includes/class-hbt-activator.php';
    HBT_Activator::activate();
}

function deactivate_hotel_booking_table() {
    require_once HBT_PLUGIN_DIR . 'includes/class-hbt-deactivator.php';
    HBT_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hotel_booking_table');
register_deactivation_hook(__FILE__, 'deactivate_hotel_booking_table');

// Load core and run
require HBT_PLUGIN_DIR . 'includes/class-hbt-core.php';

function run_hotel_booking_table() {
    $plugin = new HBT_Core();
    $plugin->run();
}
run_hotel_booking_table();
```

---

#### 2. **includes/class-hbt-core.php** (Core Orchestration)
**Purpose**: Central orchestration class that loads dependencies and registers hooks
**Lines**: ~100
**Responsibilities**:
- Load all class dependencies
- Instantiate admin, public, AJAX classes
- Register WordPress hooks via other classes
- Manage plugin lifecycle

**Functions to implement**:
- `__construct()` - Initialize plugin
- `load_dependencies()` - Require all class files
- `define_admin_hooks()` - Register admin hooks
- `define_public_hooks()` - Register public hooks
- `run()` - Execute plugin
- `get_plugin_name()` - Return plugin name
- `get_version()` - Return version

**Dependencies to load**:
1. class-hbt-admin.php
2. class-hbt-public.php
3. class-hbt-ajax.php
4. class-hbt-newbook-api.php
5. class-hbt-resos-api.php
6. class-hbt-matcher.php

---

#### 3. **includes/class-hbt-activator.php** (Activation)
**Purpose**: Handle plugin activation tasks
**Lines**: ~50
**Responsibilities**:
- Set default options if not existing
- Flush rewrite rules
- Run any setup tasks

**Functions to implement**:
- `activate()` - Static method called on activation

**Notes**: Currently no database tables needed (uses transients and external APIs)

---

#### 4. **includes/class-hbt-deactivator.php** (Deactivation)
**Purpose**: Handle plugin deactivation cleanup
**Lines**: ~30
**Responsibilities**:
- Clear transient cache (opening hours)
- Cleanup if needed

**Functions to implement**:
- `deactivate()` - Static method called on deactivation

---

#### 5. **includes/class-hbt-ajax.php** (AJAX Handlers)
**Purpose**: Handle all AJAX requests from frontend
**Lines**: ~800
**Responsibilities**:
- Process AJAX requests
- Validate and sanitize input
- Call appropriate API methods
- Return JSON responses

**Functions to move** (from current hotel-admin.php):
1. `ajax_get_available_times()` (line 1082)
   - Gets available time slots from Resos
   - Uses HBT_Resos_API class

2. `ajax_preview_resos_match()` (line 1127)
   - Preview update booking request
   - Uses HBT_Resos_API for field definitions
   - Uses HBT_Matcher for data preparation

3. `ajax_confirm_resos_match()` (line 1488)
   - Execute update to existing Resos booking
   - Uses HBT_Resos_API for update
   - Processes custom fields and dietary requirements

4. `ajax_create_resos_booking()` (line 1936)
   - Execute create new Resos booking
   - Uses HBT_Resos_API for creation
   - Adds booking notes after creation

5. `ajax_preview_resos_create()` (line 2294)
   - Preview create booking request
   - Uses HBT_Resos_API for field definitions

6. `ajax_get_dietary_choices()` (line 2561)
   - Fetch dietary options from Resos custom fields
   - Uses HBT_Resos_API

7. `test_available_dates()` (line 847)
   - Test endpoint for date availability
   - Uses HBT_Resos_API

**Dependencies**:
- HBT_Resos_API - For all Resos operations
- HBT_Matcher - For data preparation and matching
- WordPress functions (wp_send_json_success/error)

**Notes on Implementation**:
- Each handler should instantiate needed API classes
- Validate nonces and user capabilities
- Sanitize all input
- Use wp_send_json_success/error for responses

---

#### 6. **includes/class-hbt-newbook-api.php** (Newbook PMS Integration)
**Purpose**: Handle all Newbook PMS API interactions
**Lines**: ~400
**Responsibilities**:
- Authenticate with Newbook API
- Fetch hotel bookings, rooms, guests
- Handle API errors
- Cache management if needed

**Functions to move**:
1. `call_api()` (line 502)
   - Base API wrapper with authentication
   - Error handling

2. `get_bookings_data()` (line 583)
   - Fetch bookings for specific date/hotel
   - Returns booking array with guest details

3. `get_rooms_data()` (line 614)
   - Fetch room information for hotel

4. `get_note_types()` (line 429)
   - Fetch note type definitions

5. `get_group_details()` (line 459)
   - Fetch booking group information

**Properties needed**:
- `$api_base_url` - 'https://api.newbook.cloud/rest/'
- Credentials from WordPress options

**API Configuration** (from WordPress options):
- `hotel_booking_api_username`
- `hotel_booking_api_password`
- `hotel_booking_api_key`
- `hotel_booking_api_region` (e.g., 'au', 'uk')
- `hotel_booking_default_hotel_id`

---

#### 7. **includes/class-hbt-resos-api.php** (Resos API Integration)
**Purpose**: Handle all Resos restaurant booking API interactions
**Lines**: ~600
**Responsibilities**:
- Authenticate with Resos API
- Fetch restaurant bookings
- Create and update bookings
- Get custom field definitions
- Get opening hours and available times
- Add restaurant notes

**Functions to move**:
1. `get_restaurant_bookings_data()` (line 634)
   - Fetch Resos bookings for date
   - Include custom fields

2. `get_resos_available_times()` (line 719)
   - Get available time slots
   - Filter by area if needed

3. `get_resos_available_dates()` (line 790)
   - Get date availability range

4. `get_resos_opening_hours()` (within restaurant bookings function)
   - Fetch opening hours with transient caching
   - Cache for 1 hour

5. `get_custom_field_definitions()` (embedded in AJAX handlers)
   - Fetch custom field schemas from Resos
   - Used for dynamic form building

6. `create_resos_booking()` (embedded in ajax_create_resos_booking)
   - POST to Resos to create booking
   - Handle custom fields and dietary requirements

7. `update_resos_booking()` (embedded in ajax_confirm_resos_match)
   - PUT to Resos to update booking
   - Handle partial updates

8. `add_restaurant_note()` (embedded in ajax_create_resos_booking)
   - POST to `/bookings/{id}/restaurantNote`
   - Add note after booking creation

**Properties needed**:
- `$api_base_url` - 'https://api.resos.com/v1/'
- API key from WordPress options

**API Configuration** (from WordPress options):
- `hotel_booking_resos_api_key`

**Transient Cache**:
- `resos_opening_hours` - 1 hour TTL

---

#### 8. **includes/class-hbt-matcher.php** (Matching Algorithm)
**Purpose**: Match hotel bookings with restaurant reservations
**Lines**: ~400
**Responsibilities**:
- Match bookings by ID, name, phone, email
- Generate confidence scores
- Prepare comparison data
- Format phone numbers
- Detect package/DBB status

**Functions to move**:
1. `match_resos_to_hotel_booking()` (line 2850+)
   - Core matching algorithm
   - Primary (booking ID match) vs Suggested matches
   - Confidence scoring (exact, fuzzy name/phone/email)

2. `prepare_comparison_data()` (line 2505)
   - Generate field-by-field comparison
   - Suggest updates based on hotel data
   - Handle custom fields

3. `format_phone_for_resos()` (line 2444)
   - Convert phone to international format
   - Handle various input formats

4. `detect_package_status()` (embedded in multiple places)
   - Check inventory items for DBB/package
   - Uses `hotel_booking_package_inventory_name` option

**Dependencies**:
- Needs both booking arrays (hotel and restaurant)
- Needs custom field definitions

---

#### 9. **admin/class-hbt-admin.php** (Admin Interface)
**Purpose**: Handle WordPress admin interface
**Lines**: ~300
**Responsibilities**:
- Register admin menu and settings page
- Register WordPress settings
- Enqueue admin assets
- Render settings fields

**Functions to move**:
1. `add_admin_menu()` (line 65)
   - Register settings page under Settings menu

2. `register_settings()` (line 78)
   - Register 8 WordPress options
   - Add settings sections and fields

3. `settings_section_callback()` (line 177)
4. `resos_section_callback()` (line 184)
5. `testing_section_callback()` (line 259)

6. **Field callbacks** (lines 191-274):
   - `username_field_callback()`
   - `password_field_callback()`
   - `api_key_field_callback()`
   - `region_field_callback()`
   - `hotel_id_field_callback()`
   - `resos_api_key_field_callback()`
   - `package_inventory_name_field_callback()`
   - `mode_field_callback()`

7. `render_settings_page()` (line 287)
   - Main settings page HTML
   - Move to view template

**Settings to register**:
1. `hotel_booking_api_username`
2. `hotel_booking_api_password`
3. `hotel_booking_api_key`
4. `hotel_booking_api_region`
5. `hotel_booking_default_hotel_id`
6. `hotel_booking_resos_api_key`
7. `hotel_booking_package_inventory_name`
8. `hotel_booking_mode` (production/testing/sandbox)

---

#### 10. **admin/views/admin-settings.php** (Settings Template)
**Purpose**: HTML template for settings page
**Lines**: ~200
**Responsibilities**:
- Display settings form
- Show testing mode banner if applicable
- Version display

**Content**: Extract HTML from `render_settings_page()` (line 287-350)

---

#### 11. **public/class-hbt-public.php** (Public Interface)
**Purpose**: Handle front-end display and functionality
**Lines**: ~600
**Responsibilities**:
- Register shortcode
- Enqueue public assets
- Process shortcode attributes
- Orchestrate data fetching
- Pass data to view template

**Functions to move**:
1. `render_booking_table()` (line 4100+)
   - Main shortcode handler
   - Parse attributes (date, hotel_id)
   - Fetch data from APIs
   - Call matcher
   - Pass to view template

2. `enqueue_styles()` (line 366)
   - Enqueue style.css with version

3. `enqueue_scripts()` (line 388)
   - Enqueue staying-today.js with version
   - Localize AJAX variables

4. `prevent_texturize()` (line 57)
   - WordPress filter to prevent HTML encoding

5. `add_error()`, `get_errors()` (lines 352, 359)
   - Error handling utilities

6. `get_hotel_id()` (line 490)
   - Parse hotel ID from shortcode attributes

**Shortcode**: `[hotel-table-bookings-by-date]`

**Attributes**:
- `date` - Display date (default: today)
- `hotel_id` - Override default hotel ID

---

#### 12. **public/views/booking-table.php** (Display Template)
**Purpose**: HTML template for booking table display
**Lines**: ~800
**Responsibilities**:
- Render booking table HTML
- Display matched/unmatched bookings
- Show comparison tables
- Display create booking forms
- Version footer

**Content**: Extract HTML generation from `render_booking_table()` function

**Data passed from class-hbt-public.php**:
- `$hotel_bookings` - Array of hotel bookings
- `$restaurant_bookings` - Array of Resos bookings
- `$matches` - Matched bookings array
- `$selected_date` - Display date
- `$mode` - API mode (production/testing/sandbox)

---

## Implementation Steps

### Phase 1: Setup Structure (Low Risk)
1. Create new directory structure
2. Create bootstrap file (hotel-admin.php)
3. Create class-hbt-core.php skeleton
4. Test that plugin still loads (empty core)

### Phase 2: Move Admin Functions (Low Risk)
1. Create class-hbt-admin.php
2. Move admin menu and settings functions
3. Create admin/views/admin-settings.php template
4. Test admin settings page

### Phase 3: Move API Classes (Medium Risk)
1. Create class-hbt-newbook-api.php
2. Move Newbook functions
3. Create class-hbt-resos-api.php
4. Move Resos functions
5. Test API connections independently

### Phase 4: Move Matcher (Medium Risk)
1. Create class-hbt-matcher.php
2. Move matching and comparison functions
3. Test matching algorithm

### Phase 5: Move AJAX Handlers (High Risk)
1. Create class-hbt-ajax.php
2. Move 7 AJAX functions
3. Update to use new API classes
4. Test each AJAX endpoint

### Phase 6: Move Public Display (High Risk)
1. Create class-hbt-public.php
2. Move shortcode and rendering functions
3. Create public/views/booking-table.php template
4. Test shortcode display

### Phase 7: Update Core (Medium Risk)
1. Complete class-hbt-core.php
2. Register all hooks
3. Load all dependencies

### Phase 8: Cleanup (Low Risk)
1. Remove old hotel-admin.php
2. Rename new bootstrap to hotel-admin.php
3. Test all functionality end-to-end

### Phase 9: Documentation (Low Risk)
1. Update CHANGELOG.md with v1.2.0 restructuring
2. Update summary.md with new structure
3. Create ARCHITECTURE.md documenting new structure

---

## Testing Checklist

After restructuring, verify ALL functionality:

### Admin Tests
- [ ] Settings page loads correctly
- [ ] All 8 settings save and load properly
- [ ] Mode banner displays in testing/sandbox modes

### Front-end Display
- [ ] Shortcode renders booking table
- [ ] Date selection works
- [ ] Hotel bookings display correctly
- [ ] Restaurant bookings display correctly

### Matching System
- [ ] Primary matches (booking ID) work
- [ ] Suggested matches (name/phone/email) work
- [ ] Confidence scoring displays correctly
- [ ] Comparison tables show correct suggestions

### Resos Integration
- [ ] Create new booking (production mode)
- [ ] Preview create booking (testing/sandbox modes)
- [ ] Update existing booking (production mode)
- [ ] Preview update booking (testing/sandbox modes)
- [ ] Dietary requirements save correctly (dynamic checkboxes)
- [ ] Booking notes save correctly
- [ ] Time slot selection works
- [ ] Gantt chart displays correctly
- [ ] Opening hours load correctly

### Newbook Integration
- [ ] Hotel bookings fetch correctly
- [ ] Guest data displays correctly
- [ ] Room information loads correctly
- [ ] Package/DBB detection works

### AJAX Endpoints
- [ ] get_available_times returns time slots
- [ ] preview_resos_match shows correct preview
- [ ] confirm_resos_match updates booking
- [ ] create_resos_booking creates booking
- [ ] preview_resos_create shows correct preview
- [ ] get_dietary_choices loads options
- [ ] test_available_dates returns dates

### Assets
- [ ] CSS loads and styles correctly
- [ ] JavaScript loads without errors
- [ ] Version numbers display correctly in footer

---

## Backward Compatibility Checklist

**CRITICAL**: Maintain 100% backward compatibility

- [ ] All WordPress option names unchanged
- [ ] Shortcode name unchanged: `[hotel-table-bookings-by-date]`
- [ ] AJAX action names unchanged (7 endpoints)
- [ ] CSS class names unchanged
- [ ] JavaScript function names unchanged
- [ ] Asset URLs unchanged (wp-content/plugins/hotel-admin/assets/)
- [ ] No database schema changes
- [ ] Version constants maintained (VERSION, JS_VERSION)

---

## Rollback Plan

If issues arise during restructuring:

1. **Deactivate restructured plugin** via WordPress admin
2. **Restore v1.1.15 backup**:
   ```bash
   cp BACKUP/v1.1.15_2025-10-31/hotel-admin.php ./
   cp -r BACKUP/v1.1.15_2025-10-31/assets ./
   ```
3. **Delete new directories**:
   ```bash
   rm -rf includes/ admin/ public/
   ```
4. **Reactivate plugin** via WordPress admin
5. **Hard refresh browser** (Ctrl+Shift+R)
6. **Verify version** shows v1.1.15 in footer

---

## Success Criteria

Restructuring is successful when:

1. ✅ All 50 functions moved to appropriate classes
2. ✅ All tests pass (see Testing Checklist)
3. ✅ No JavaScript errors in browser console
4. ✅ No PHP errors in wp-content/debug.log
5. ✅ Code is organized by responsibility
6. ✅ Each file is 200-600 lines (not 4,435)
7. ✅ Documentation updated
8. ✅ Version incremented to 1.2.0

---

## Reference: Comparison with Hotel Maintenance Management Plugin

The hotel-maintenance-management plugin successfully uses this structure:

```
hotel-maintenance-management/
├── hotel-maintenance-management.php (57 lines)
├── includes/
│   ├── class-hmm-core.php (77 lines)
│   ├── class-hmm-activator.php (2,220 lines - with DB)
│   ├── class-hmm-ajax.php (19,219 lines - large file)
│   └── class-hmm-deactivator.php (236 lines)
├── admin/
│   └── class-hmm-admin.php
└── public/
    └── class-hmm-public.php
```

**Note**: class-hmm-ajax.php is large (19K lines) - for hotel-admin, we can keep AJAX handler file smaller (~800 lines) by delegating to API classes.

---

## Estimated Effort

- **Phase 1-2** (Setup + Admin): 1-2 hours
- **Phase 3-4** (APIs + Matcher): 2-3 hours
- **Phase 5-6** (AJAX + Public): 3-4 hours
- **Phase 7-8** (Core + Cleanup): 1-2 hours
- **Phase 9** (Documentation): 1 hour
- **Testing**: 2-3 hours

**Total**: 10-15 hours

---

## Next Steps

1. Review this plan
2. Start new session with Opus model
3. Begin Phase 1: Setup Structure
4. Work through phases sequentially
5. Test thoroughly after each phase
6. Create new backup when complete (v1.2.0)

---

**Document Version**: 1.0
**Created**: October 31, 2025
**Last Updated**: October 31, 2025
