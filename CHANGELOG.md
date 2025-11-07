# Reservation Management Integration - Changelog

All notable changes to this project will be documented in this file.

---

## [2.0.3] - 2025-11-07

### New Features

#### "NOT-#xxxxx" Pattern for Excluding Specific Incorrect Booking Matches
**Feature**: Staff can now exclude specific incorrect booking matches by adding restaurant notes with the pattern "NOT-#xxxxx" where xxxxx is the hotel booking ID that should not match.

**Problem Solved**:
- False suggested matches for common surnames (e.g., multiple "Smith" bookings)
- Family gatherings where multiple family members have similar names/contact details
- Booking sequence issues (restaurant booking created before hotel room assigned)
- Non-resident bookings that incorrectly match hotel guests with similar details

**How It Works**:
1. Staff identifies an incorrect suggested match (e.g., Booking #12345)
2. Adds restaurant note to the Resos booking: "NOT-#12345"
3. System excludes that specific booking from matching
4. Booking can still match OTHER hotel bookings (preserves future matching)

**Supported Formats** (all case-insensitive):
- `NOT-#12345` (recommended - most visible)
- `NOT-12345` (works without # symbol)
- `not-#12345` (lowercase accepted)
- Multiple exclusions: `NOT-#12345 NOT-#67890`
- Mixed with other notes: `Gluten-free. NOT-#12345. Window table.`

**Example Scenarios**:

**Family Gathering:**
- Hotel: Room 101 (John Smith #12345), Room 102 (Jane Smith #67890)
- Resos: "Smith party of 6"
- Initially matches both as "suggested"
- Add note: `NOT-#12345` (wrong room)
- Result: Only matches Room 102 ✓

**Common Surname:**
- Multiple "Jones" hotel bookings
- Resos walk-in: "Jones"
- Add note: `NOT-#11111 NOT-#22222 NOT-#33333`
- Result: Won't match any hotel Jones ✓

**Booking Sequence:**
- Restaurant booking created for "Brown" (no hotel booking yet)
- Later, hotel booking #99999 created for different "Brown"
- System incorrectly suggests match
- Add note: `NOT-#99999`
- Even later, correct hotel booking #88888 created
- Result: Now correctly matches #88888 ✓

**Technical Details**:
- Pattern matching: `/NOT-#?(\d+)/i` (regex, case-insensitive)
- Blocks: **ALL matching** for excluded booking IDs (note-based, surname, phone, email matches)
- Early return: Function returns immediately with "no match" when exclusion detected
- Prevents: Both PRIMARY (confirmed) and SECONDARY (suggested) matches
- Does NOT affect: Custom field "Booking #" explicit assignments (PRIORITY 1-2)
- Logging: Excluded matches logged to debug.log as "RMI: Booking #xxxxx explicitly excluded via NOT-# pattern"

**User Workflow**:
1. View incorrect suggested match in booking table
2. Open Resos booking dashboard
3. Add restaurant note: "NOT-#12345" (use exact format)
4. Save and refresh hotel booking page
5. Incorrect match no longer appears

**Files Modified**:
- `reservation-management-integration.php` (lines 3365-3378, 3381, 3394, 3407)

**Version Incremented**: 2.0.2 → 2.0.3

**Advantages Over Alternative Approaches**:
- Granular control (exclude specific bookings, not all)
- Preserves future matching potential
- Handles complex scenarios (family gatherings, booking sequences)
- Reversible (edit/delete note anytime)
- Multiple exclusions supported
- Audit trail via timestamped notes

---

## [2.0.2] - 2025-11-07

### New Features

#### Visual Alerts for Package Bookings Without Restaurant Reservations
**Feature**: Automatically highlights hotel bookings with dinner packages (DBB) that don't have a matched restaurant booking.

**Problem Solved**:
- Package/DBB bookings include dinner as part of the rate and require a table reservation
- Previously, these guests could be overlooked if no restaurant booking existed
- Staff had to manually scan for package bookings without matches

**Implementation**:

**Visual Indicators**:
- **Red warning notice** appears above Create Booking button: "⚠️ Package guest needs booking"
- **Red button styling** (instead of green) with pulsing animation to draw attention
- Applied to both individual rooms and grouped accommodations sections

**How It Works**:
1. Plugin checks if booking has dinner package via inventory items (existing logic)
2. If booking has package AND no matched restaurant booking → shows warning + red button
3. If booking has package AND has matched restaurant booking → normal display (no warning)
4. If booking has no package → green button as usual

**Technical Details**:
- Uses existing `has_package` detection from inventory items
- Checks for configured "Package Inventory Item Name" in settings (e.g., "Dinner Allocation")
- Date-specific detection (only flags packages for the viewing date)
- No JavaScript changes required - purely server-side rendering

**CSS Styling** ([style.css](assets/style.css)):
- `.package-no-match-warning` - Red bordered warning box with warning icon
- `.btn-create-booking.requires-booking` - Red button with pulse animation
- `@keyframes pulse-warning` - Subtle pulsing effect (2s loop)

**Button States**:
- Normal booking (no package): Green button
- Package booking (no match): Red button with warning + pulse effect
- Package booking (has match): No create button (shows match buttons instead)

**User Experience**:
- Clear visual hierarchy separates urgent (red) from routine (green) bookings
- Warning message provides context: "Package guest needs booking"
- Maintains all existing functionality - only adds visual emphasis

**Configuration**:
- No new settings required
- Uses existing "Package Inventory Item Name" from Settings > Reservation Management
- Works with any configured package text (e.g., "Dinner Allocation", "DBB", etc.)

**Files Modified**:
- `reservation-management-integration.php` (lines 4141-4161, 4464-4484)
- `assets/style.css` (lines 488-532 added)

**Version Incremented**: 2.0.1 → 2.0.2

**Testing Scenarios**:
1. ✅ Hotel booking WITH package + NO restaurant match → Red button + warning
2. ✅ Hotel booking WITHOUT package + NO restaurant match → Green button, no warning
3. ✅ Hotel booking WITH package + HAS restaurant match → No create button (shows match buttons)
4. ✅ Hotel booking WITHOUT package + HAS restaurant match → No create button (shows match buttons)
5. ✅ Grouped accommodations display warnings correctly
6. ✅ Responsive design on mobile/tablet devices

---

## [2.0.1] - 2025-11-06

### Bug Fixes

#### Fixed Critical Duplicate Booking Submission Bug
**Issue**: Create booking button could be clicked multiple times in rapid succession (within ~0.5-2 seconds), creating duplicate bookings in Resos.

**Root Cause**:
- No button disabling during API request (JavaScript)
- No in-flight request tracking flag (JavaScript)
- No server-side duplicate detection (PHP)
- No user feedback on errors

**Fix Implemented - Multi-Layer Protection**:

**Layer 1: JavaScript Client-Side Protection** ([staying-today.js](assets/staying-today.js))
- Added global in-flight request flag `window.createBookingInProgress`
- Early return if request already in progress (line 800)
- Button disabled immediately when clicked with visual feedback "Creating..." (lines 917-923)
- Button stays disabled on success (page reloads) (lines 982-985)
- Button re-enabled on errors with user-friendly alert dialogs (lines 989-1004, 1007-1020)
- Same protection applied to preview mode (testing/sandbox) (lines 1028-1033, 1105-1136)
- Network errors now show alerts instead of silent console logging

**Layer 2: PHP Server-Side Protection** ([reservation-management-integration.php](reservation-management-integration.php))
- WordPress transient-based duplicate detection (lines 2016-2036)
- Unique key generated from: `md5(guest_name|date|time|email)`
- Checks for duplicate submission within 5-second window
- Returns user-friendly error if duplicate detected
- Transient extended to 10 seconds after successful booking (line 2286)
- Detailed logging: "RMI: Duplicate booking submission blocked - [guest] on [date] at [time]"

**User Experience Improvements**:
- Button shows loading state: "Creating..." with clock icon
- Success state before reload: "Created!" with checkmark icon
- Clear error messages displayed to user (not just console)
- Network errors handled gracefully with retry option

**Testing Recommendations**:
1. Test rapid double-click (should only create one booking)
2. Test slow network conditions (button should stay disabled)
3. Test error scenarios (button should re-enable with alert)
4. Test all three API modes (production/testing/sandbox)
5. Verify transient cleanup after 10 seconds
6. Check debug.log for duplicate blocking messages

**Files Modified**:
- `assets/staying-today.js` (lines 798-1141 modified)
- `reservation-management-integration.php` (lines 2016-2036, 2285-2286 added)

**Version Incremented**: 2.0.0 → 2.0.1

---

## [2.0.0] - 2025-10-31

### Major Plugin Restructuring

#### Plugin Renamed
- **Old Name**: Hotel Booking Table by Date / Hotel Admin
- **New Name**: Reservation Management Integration for NewBook & ResOS
- **Text Domain**: Changed from `hotel-booking-table` to `rmi-newbook-resos`
- **Main File**: `reservation-management-integration.php` (was `hotel-admin.php`)

#### Breaking Changes
- Plugin directory renamed from `hotel-admin` to `reservation-management-integration`
- Version reset to 2.0.0 to indicate major restructuring
- JS and CSS versions synchronized to 2.0.0

#### New Features
- Added new shortcode `[rmi-bookings-table]` (recommended)
- Legacy shortcode `[hotel-table-bookings-by-date]` maintained for backward compatibility
- Comprehensive documentation suite created:
  - DEVELOPMENT_CHECKLIST.md - Mandatory session checklist
  - FUNCTION_REFERENCE.md - Complete function documentation
  - SESSION_CONTEXT.md - Development state tracking
  - API_DOCUMENTATION.md - Full API endpoint documentation
- Consolidated changelogs (recovered 18 missing versions from 1.0.68-1.0.88)

#### Improvements
- Updated all plugin references to new name
- Admin menu now shows "Reservation Management" instead of "Hotel Booking Table"
- Error logs now use "RMI:" prefix instead of "Hotel Booking Table:"
- File ownership fixed to www-data:www-data

#### Development Infrastructure
- Created timestamped backup system
- Cleaned up duplicate and empty files
- Prepared for Git initialization
- Ready for modular restructuring per RESTRUCTURING_PLAN.md

#### Files Modified
- `reservation-management-integration.php` (v2.0.0)
- All documentation files updated
- Directory structure reorganized

---

## [1.1.15] - 2025-10-31

### Major Features Added
- **Dynamic Dietary Requirements System**:
  - Dietary checkboxes now dynamically generated from Resos custom field configuration
  - New AJAX endpoint `ajax_get_dietary_choices()` fetches available choices from Resos API
  - Frontend uses choice IDs for reliable matching instead of names
  - Automatically syncs with any changes made in Resos settings
  - Eliminates hardcoded options and name mismatch issues
  - Supports multiselect checkbox format with object arrays `{_id, name, value: true}`

- **Booking Notes Functionality**:
  - Added support for restaurant notes via Resos `/restaurantNote` endpoint
  - Notes added automatically after booking creation
  - Fixed bug where Resos booking ID wasn't extracted correctly (handles both string and object responses)
  - Note field with toggle button in create booking interface
  - Works in all API modes (production, testing, sandbox)

### Improvements
- **Version Management**:
  - Added version constants to class: `VERSION` and `JS_VERSION`
  - Version numbers displayed in page footer: "Plugin v1.1.15 | JS v1.0.75"
  - Synchronized CSS and JavaScript enqueued versions using constants
  - Single source of truth for version numbers

- **UI Enhancements**:
  - Fixed action button alignment using `margin-left: auto` in flexbox
  - Blue button color for "Update Selected" on confirmed matches
  - Consistent button positioning across create and match comparison sections

- **Code Cleanup**:
  - Removed verbose debug logging from production code
  - Kept essential error logs and warnings for troubleshooting
  - Cleaner console output with only relevant information
  - Removed temporary debugging statements from dietary requirements development

### Bug Fixes
- Fixed JavaScript syntax error (comment in string concatenation)
- Fixed function hoisting issue with `fetchDietaryChoices()`
- Corrected Resos API key option name in `ajax_get_dietary_choices()`
- Fixed booking ID extraction to handle string responses from Resos API

### Technical Details
- **New Functions**:
  - `ajax_get_dietary_choices()`: Fetches dietary custom field choices from Resos
  - `fetchDietaryChoices()`: JavaScript function to load choices on page load
  - `populateDietaryCheckboxes()`: Dynamically creates checkboxes from Resos data

- **Modified Functions**:
  - `ajax_create_resos_booking()`: Enhanced to handle notes and dynamic dietary IDs
  - `ajax_preview_resos_create()`: Maintains parity with create function
  - `createBookingWithSelectedTime()`: Collects dietary choice IDs from dynamic checkboxes

### Files Modified
- `hotel-admin.php` (v1.1.15)
- `staying-today.js` (v1.0.75)
- `style.css` (v1.0.75)

---

## [1.1.0] - 2025-10-31

### Major Features Added
- **Outgoing API Requests - Create Restaurant Bookings**:
  - Full integration to create Resos bookings from hotel guest data
  - Creates bookings via POST to Resos API with guest information, time, party size, and custom fields
  - Auto-populates guest name, phone, email from Newbook booking data
  - Includes opening hour selection based on booking time
  - Custom fields: Hotel Guest, Booking #, Package/DBB
  - Supports dietary requirements/allergies submission
  - Phone number formatting to international format (UK +44 default)
  - Notification preferences (SMS/Email) with auto-check based on field values

- **Outgoing API Requests - Update Restaurant Bookings**:
  - PUT requests to update existing Resos bookings with suggested changes
  - Selective field updates via checkbox system
  - Preserves existing data for unchecked fields
  - CustomFields array handled as "all or nothing" to prevent data loss
  - Status updates with approval workflow

- **API Testing & Sandbox Modes**:
  - **Production Mode**: Direct API calls without confirmation
  - **Testing Mode**: Preview dialogue before execution with confirm/cancel options
  - **Sandbox Mode**: Preview-only mode, no actual API calls executed
  - Preview shows exact transformed request body before sending
  - API mode banner at top of page (orange gradient) for non-production modes
  - API Preview/Execute Parity system ensures preview matches actual request

- **Enhanced Create Booking UI**:
  - Dedicated "Booking Details" section with grey container
  - Gantt chart shows restaurant bookings for selected date
  - Form fields: Guest Name, Phone, Email, Booking #, Party Size
  - Checkbox groups: Hotel Guest, Package/DBB, Allow SMS, Allow Email
  - Collapsible allergies/dietary requirements section (light red background)
  - Time slot selection with visual gantt sight line
  - Selected time persists with red sight line on gantt chart
  - Time buttons filter by opening hours and booking duration
  - Create Booking button (disabled until time selected)
  - Reduced margins/padding for compact layout

### Changed
- **Time Slot Selection Behavior**:
  - Time buttons now select time instead of immediate booking creation
  - Selected time highlighted in purple
  - Create Booking button required for final submission
  - Gantt sight line remains visible on selected time
  - Hover over times temporarily moves sight line, returns to selected on mouse leave

- **Opening Hours Logic**:
  - Time filtering accounts for booking duration (default 120 minutes)
  - Ensures full booking fits within opening hours close time
  - Opening hour ID sent with booking requests for proper categorization

- **Notification Checkboxes**:
  - Allow SMS and Allow Email default to unchecked
  - Auto-check when user enters value in phone/email fields
  - Auto-uncheck when field cleared

- **Comparison Row Styling**:
  - Reduced outer margins from 20px to 5px horizontally
  - Reduced padding from 20px to 10px
  - Better use of screen real estate

- **Status Row Enhancement**:
  - Newbook column now shows booking status from Newbook PMS
  - Previously showed dash (-), now displays actual status

- **Gantt Chart Title**:
  - Changed from "Today's Restaurant Bookings" to "Restaurant Bookings for [DATE]"
  - Date formatted in British format (e.g., "30 Oct 2025")

- **Sandbox Banner Styling**:
  - Sandbox mode banner now matches testing mode orange gradient
  - Previously purple, now consistent orange theme for all non-production modes

### Added Files
- **API_PREVIEW_PARITY.md**: Documentation for maintaining preview/execute parity
  - Guidelines for keeping preview and execute functions synchronized
  - Affected flows: Create Booking, Update Booking
  - Checklist for updates to API requests

### Technical Implementation
- **JavaScript Functions** (staying-today.js):
  - `selectTimeSlot()`: Highlights time, shows gantt sight line, enables Create button
  - `createBookingWithSelectedTime()`: Executes booking creation with all validation
  - `setupNotificationAutoCheck()`: Event listeners for SMS/Email checkbox automation
  - `getOpeningHourForTime()`: Returns opening hour ID and name for selected time
  - `format_phone_for_resos()` (PHP): Formats phone to international E.164 format
  - `toggleAllergiesSection()`: Expands/collapses dietary requirements
  - `buildCreateBookingSection()`: Builds complete booking UI with gantt chart

- **PHP Functions** (hotel-admin.php):
  - `ajax_create_resos_booking()`: Handles POST to create new Resos bookings
  - `ajax_preview_resos_create()`: Returns preview of create request transformation
  - `ajax_confirm_resos_match()`: Handles PUT to update existing bookings
  - `ajax_preview_resos_match()`: Returns preview of update request transformation
  - `format_phone_for_resos()`: Phone formatting with country code validation
  - Added `status` field to comparison data preparation

- **CSS Enhancements** (style.css):
  - Time slot selection styling (`.time-selected` class)
  - Create Booking button styling (green with disabled state)
  - Gantt sight line selection state (`.sight-line-selected`)
  - Booking Details title styling
  - Allergies section with light red background
  - Compact form layout with reduced spacing
  - Fixed width containers to prevent layout shift
  - Notification checkbox vertical lists

### Fixed
- **API Parity Issues**:
  - Opening hour field name corrected from `openingHour` to `openingHourId`
  - Phone field added to both execute and preview functions
  - Email field added to both execute and preview functions
  - Notification fields added to both execute and preview functions
  - All FormData fields synchronized between preview and execute

- **Phone Formatting**:
  - Implemented international format validation with + prefix
  - UK country code (+44) as default
  - Strips leading 0 from local format
  - Handles empty phone gracefully (optional field)

- **Opening Hours Time Filtering**:
  - Fixed issue where 18:00 showed as "afternoon" instead of "evening"
  - Now calculates booking end time (start + duration)
  - Verifies both start and end fit within opening hours period

- **Layout Consistency**:
  - Time slots section maintains fixed width when expanded/collapsed
  - No layout shift when toggling time period sections

### Database Changes
- Added `status` field extraction from Newbook booking data
- Status included in comparison data hotel array

---

## Version Consolidation Note

**This changelog consolidates two previously separate files:**
- `/wp-content/plugins/hotel-admin/CHANGELOG.md` (uppercase)
- `/wp-content/plugins/hotel-admin/changelog.md` (lowercase)

The version numbering shows a jump from **v1.0.88 to v1.1.0**. This reflects the major feature additions in v1.1.0, particularly:
- Outgoing API functionality (creating and updating Resos bookings)
- API testing/sandbox modes with preview/execute parity
- Enhanced create booking UI with time slot selection

Versions 1.0.68 through 1.0.88 (documented below) represent iterative improvements to the UI, Gantt chart functionality, and special events integration that were developed in parallel but not captured in the original uppercase changelog.

Version 1.0.65 appears in both files with different content - both versions have been merged below to provide complete documentation.

**Consolidated by:** Claude Code on 2025-10-31
**Original files archived in:** `/BACKUP/changelog-originals/`

---

## [1.0.88] - 2025-10-31

### Fixed: Service Period Dropdown Sync with Section Expansion

**Summary:** Fixed the service period dropdown to update when manually expanding/collapsing time slot sections by clicking section headers.

#### Problem:
- When clicking a section header to expand it (e.g., "Lunch"), the dropdown at the top would stay on "Dinner" (or whatever was previously selected)
- Dropdown and expanded section were out of sync
- Made it confusing which service period was actually active

#### Solution:
Updated `toggleTimeSection()` function ([staying-today.js](assets/staying-today.js) lines 993-1019):

**Added dropdown sync on expand:**
```javascript
if (section.style.display === 'none') {
    // Expand section
    section.style.display = 'flex';
    header.classList.add('expanded');
    if (icon) icon.textContent = '▼';

    // Update the dropdown to match the expanded section
    var selector = document.getElementById('opening-time-selector');
    if (selector && sectionIndex !== null) {
        selector.value = sectionIndex;
    }
}
```

#### How it Works:
1. User clicks section header (e.g., "Lunch 12:00 - 14:00")
2. Section expands
3. **Dropdown automatically updates** to show "Lunch" selected
4. Dropdown and visible section stay in sync

#### Benefits:
- **Always in sync**: Dropdown always shows which section is expanded
- **Clear feedback**: User knows which service period they're working with
- **Two-way sync**: Works both ways (dropdown → section, section → dropdown)

#### Files Modified:
- `/assets/staying-today.js` lines 1008-1012: Added dropdown sync on section expand

---

## [1.0.87] - 2025-10-31

### Fixed: Reduced Cache Duration for Faster Resos Updates

**Summary:** Reduced opening hours cache from 24 hours to 1 hour so interval/duration changes in Resos appear much faster in the plugin.

#### Problem:
- Opening hours data was cached for 24 hours
- When you changed intervals in Resos (e.g., 15 min → 20 min), it wouldn't show in the plugin for up to 24 hours
- Made testing and adjustments very slow
- Interval extraction was already working correctly from API (`$hours['seating']['interval']`)

#### Solution:
Reduced cache duration ([hotel-admin.php](hotel-admin.php) lines 711, 770):

**Before:**
```php
set_transient('resos_opening_hours', $data, 24 * HOUR_IN_SECONDS); // 24 hours
```

**After:**
```php
set_transient('resos_opening_hours', $data, HOUR_IN_SECONDS); // 1 hour
```

#### Benefits:
- **Faster updates**: Changes in Resos appear within 1 hour instead of 24
- **Better for testing**: Can adjust intervals and see results quickly
- **Still cached**: Reduces API calls while providing reasonable freshness
- **Already API-driven**: Intervals were already being extracted correctly from `seating.interval`

#### How it Works:
When you change settings in Resos:
1. **Within 1 hour**: New settings will appear in the plugin
2. **No action needed**: Cache expires automatically
3. **Fresh data**: Next page load fetches updated opening hours from Resos API

#### Alternative Manual Cache Clear:
If you need immediate updates, you can manually clear the cache by adding this code temporarily:
```php
delete_transient('resos_opening_hours');
```

#### Files Modified:
- `/hotel-admin.php` line 711: Updated comment (24 hours → 1 hour)
- `/hotel-admin.php` line 770: Changed cache duration (24 * HOUR_IN_SECONDS → HOUR_IN_SECONDS)

---

## [1.0.86] - 2025-10-31

### Fixed: Proper Calculation of Service Times from API Data

**Summary:** Replaced duplicate tracking workaround with proper calculation of actual service times by subtracting booking duration from close time. Time slots now respect actual service periods and adapt to any interval/duration changes in Resos.

#### Problem with v1.0.85:
- Used duplicate tracking to prevent overlap, but didn't address root cause
- Still generated time slots using extended close times
- Wasn't truly API-driven - wouldn't adapt if Resos intervals/durations changed

#### Understanding the API Data:
Resos opening hours contain:
- `open`: First seating time (e.g., 1200 = 12:00)
- `close`: **Extended** close time (includes booking duration buffer)
- `interval`: Time between seating slots (e.g., 15 minutes)
- `duration`: Default booking length (e.g., 120 minutes = 2 hours)

**Example:**
```
Lunch service data from API:
{
  open: 1200,     // 12:00 - first seating
  close: 1600,    // 16:00 - EXTENDED close (14:00 + 2-hour buffer)
  interval: 15,   // 15-minute slots
  duration: 120   // 2-hour bookings
}

Actual last seating: close - duration = 16:00 - 2:00 = 14:00
```

#### Solution:
Calculate actual last seating time mathematically ([staying-today.js](assets/staying-today.js) lines 878-947):

**Calculation Logic:**
```javascript
var closeTime = period.close;    // 1600 (16:00)
var duration = period.duration;  // 120 minutes

// Convert to hours/minutes
var closeHour = 16, closeMin = 0;
var durationHours = 2, durationMins = 0;

// Subtract duration from close time
closeHour -= durationHours;  // 16 - 2 = 14
closeMin -= durationMins;     // 0 - 0 = 0

// Result: actualEndTime = 14:00
```

**Time Slot Generation:**
- Start: `period.open` (12:00)
- End: `close - duration` (14:00)
- Interval: `period.interval` (15 minutes)
- Result: 12:00, 12:15, 12:30, ..., 13:45, 14:00

**Section Headers:**
- Now show **actual** service times (12:00 - 14:00)
- Not extended times (12:00 - 16:00)
- Much clearer for staff

#### Benefits:
- **Truly API-driven**: Respects `interval` and `duration` from Resos
- **No overlap**: Each service period shows only its actual seating times
- **Adapts automatically**: If you change interval from 15 to 30 minutes in Resos, it updates
- **Clearer headers**: Section titles show real service times, not extended times
- **Simpler code**: No need for duplicate tracking Set

#### Example Result:
**Lunch (12:00 - 14:00):**
- Buttons: 12:00, 12:15, 12:30, ..., 13:45, 14:00

**Dinner (17:00 - 21:00):**
- Buttons: 17:00, 17:15, 17:30, ..., 20:45, 21:00

**No overlap!** Clean separation between service periods.

#### Files Modified:
- `/assets/staying-today.js` lines 878-947: Calculate actual service times from API data
- Removed duplicate tracking code (no longer needed)

---

## [1.0.85] - 2025-10-31

### Fixed: Duplicate Time Slots Across Service Periods

**Summary:** Fixed issue where time slots appeared in multiple service period sections due to overlapping extended opening times. Added tracking to prevent duplicate time slot buttons.

#### Problem:
- Opening times are extended to account for booking duration (e.g., lunch 12:00-14:00 becomes 12:00-16:00 to allow 14:00 booking with 2-hour duration)
- This caused overlap between service periods (e.g., lunch 12:00-16:00 overlaps with dinner 15:00-22:00)
- Time slots from 15:00-16:00 appeared in BOTH lunch and dinner sections
- Created confusing duplicate buttons for the same time

#### Understanding the Issue:

**Example Scenario:**
```
Lunch Service (actual): 12:00 - 14:00
  → Extended for bookings: 12:00 - 16:00 (allows 14:00 booking + 2-hour duration)
  → Generates buttons: 12:00, 12:15, 12:30, ..., 15:45

Dinner Service (actual): 17:00 - 21:00
  → Extended for bookings: 15:00 - 23:00 (allows 21:00 booking + 2-hour duration)
  → Generates buttons: 15:00, 15:15, 15:30, ..., 22:45

Overlap: 15:00, 15:15, 15:30, 15:45 appear in BOTH sections!
```

#### Solution:
Added duplicate tracking system ([staying-today.js](assets/staying-today.js) lines 878-879, 925-934):

```javascript
// Track which times have already been added to prevent duplicates
var addedTimes = new Set();

periods.forEach(function(period, index) {
    // ... for each time slot ...

    // Skip this time if it's already been added in a previous section
    if (addedTimes.has(timeStr)) {
        continue; // Skip, don't create duplicate button
    }

    // Mark this time as added
    addedTimes.add(timeStr);

    // Create the time slot button...
});
```

**How it works:**
1. Before generating buttons, create a Set to track added times
2. For each service period (in order: earliest to latest)
3. Check if each time has already been added
4. If yes: skip it (prevents duplicate)
5. If no: add it to the section and mark as added
6. Result: Each time appears only once, in the first/earliest section it belongs to

#### Additional Confirmation:
**Time slot intervals** are pulled from Resos API opening hours data (`period.interval`), **not hardcoded**. This was already implemented correctly.

#### Benefits:
- **No duplicate buttons**: Each time appears exactly once
- **First-come placement**: Times appear in the earliest service period they belong to
- **Cleaner UI**: No confusion from seeing the same time in multiple sections
- **API-driven intervals**: Respects Resos configuration for time intervals

#### Example Result:
**Before (with duplicates):**
```
Lunch Section:    12:00, 12:15, ..., 15:45
Dinner Section:   15:00, 15:15, 15:30, 15:45, 16:00, ...
                  ^^^^^ Duplicates! ^^^^^
```

**After (no duplicates):**
```
Lunch Section:    12:00, 12:15, ..., 15:45
Dinner Section:                              16:00, 16:15, ...
                  No overlap - clean sections!
```

#### Files Modified:
- `/assets/staying-today.js` lines 878-879: Added `addedTimes` Set tracker
- `/assets/staying-today.js` lines 925-934: Added duplicate check logic

---

## [1.0.84] - 2025-10-31

### Fixed: Final Overlap Fix and Adjusted Row Span Formula

**Summary:** Fixed remaining overlap issue caused by border height not being accounted for, and adjusted row span formula so 1-3 people use single row thickness, with 4-5, 6-7, etc. incrementing by 2s.

#### Problems (from v1.0.83):
1. **Still overlapping**: Border (2px) wasn't accounted for in bar height calculation
2. **Row span increment too early**: 3-person bookings were 2 rows thick (same as 4-person)

#### Root Cause - The Missing 2px:
```
v1.0.83 calculation (still broken):
- GRID_ROW_HEIGHT = 28px
- Bar height = (1 * 28) - 4 = 24px
- Padding = 2px top + 2px bottom = 4px
- Border = 1px top + 1px bottom = 2px ← FORGOT THIS!
- Total visual height = 24 + 4 + 2 = 30px
- Overlap = 30px - 28px = 2px overlap! ❌
```

#### Solution:

**Increased gap from -4 to -6** ([staying-today.js](assets/staying-today.js) line 746):
```javascript
// Before: var barHeight = (booking.rowSpan * GRID_ROW_HEIGHT) - 4;
// After:  var barHeight = (booking.rowSpan * GRID_ROW_HEIGHT) - 6;
```

**New calculation (finally correct!):**
```
- GRID_ROW_HEIGHT = 28px
- Bar height = (1 * 28) - 6 = 22px
- Padding = 4px
- Border = 2px
- Total = 22 + 4 + 2 = 28px ✓ PERFECT FIT!
```

**Adjusted row span formula** ([staying-today.js](assets/staying-today.js) line 686):
```javascript
// Before: Math.ceil(partySize / 2)
// After:  Math.max(1, Math.floor(partySize / 2))
```

**New Row Span Table:**
| Party Size | v1.0.83 | v1.0.84 | Change |
|------------|---------|---------|--------|
| 1 person   | 1 row   | 1 row   | Same   |
| 2 people   | 1 row   | 1 row   | Same   |
| 3 people   | 2 rows  | 1 row   | -1 row ✓ |
| 4 people   | 2 rows  | 2 rows  | Same   |
| 5 people   | 3 rows  | 2 rows  | -1 row ✓ |
| 6 people   | 3 rows  | 3 rows  | Same   |
| 7 people   | 4 rows  | 3 rows  | -1 row ✓ |

**Rationale:**
- 1-3 people typically use same table size in restaurants (single baseline)
- 4-5 people grouped together (width of "4 booking")
- 6-7 people grouped together (width of "6 booking")
- Even number grouping matches table sizing conventions better

#### Benefits:
- **Zero overlap**: Bars perfectly fit grid rows with exact math
- **Rounded corners preserved**: No more disappearing edges
- **Better grouping**: 3-person bookings now same thickness as 1-2 (makes sense)
- **Cleaner visual steps**: Only increments at even numbers (4, 6, 8, etc.)

#### Files Modified:
- `/assets/staying-today.js` line 686: Updated row span formula
- `/assets/staying-today.js` line 746: Increased gap from -4 to -6

---

## [1.0.83] - 2025-10-31

### Fixed: Bar Overlap Issue and Improved Hover Behavior

**Summary:** Fixed bars overlapping each other by increasing grid row height and reducing padding. Also increased base bar thickness for better readability and replaced lift animation with subtle color change on hover.

#### Problems (from v1.0.82):
1. **Bars overlapping**: Rounded corners were disappearing because bars were overlapping vertically
2. **Padding calculation issue**: Bar height (16px) + padding (8px) = 24px total, but grid rows were only 20px apart → 4px overlap!
3. **Too thin**: Base 2-person bars at 16px were very tight and hard to read
4. **Unnecessary animation**: Hover lift animation (`translateY`) was distracting

#### Root Cause Analysis:
```
Previous calculation (broken):
- GRID_ROW_HEIGHT = 20px
- Bar height = (1 * 20) - 4 = 16px
- Padding = 4px top + 4px bottom = 8px
- Total visual height = 16px + 8px = 24px
- Overlap = 24px - 20px = 4px overlap! ❌
```

#### Solution:
**Increased grid height and reduced padding** ([staying-today.js](assets/staying-today.js) line 672):
- `GRID_ROW_HEIGHT`: `20px` → `28px` (40% increase)

**Reduced vertical padding** ([style.css](assets/style.css) line 734):
- `padding`: `4px 8px` → `2px 8px` (50% reduction in vertical padding)

**New calculation (fixed):**
```
- GRID_ROW_HEIGHT = 28px
- Bar height = (1 * 28) - 4 = 24px
- Padding = 2px top + 2px bottom = 4px
- Total visual height = 24px + 4px = 28px
- Perfect fit! No overlap! ✅
```

**Replaced hover animation** ([style.css](assets/style.css) lines 741, 747-750):
- Before: `transform: translateY(-2px)` with shadow change
- After: `background-color: #7c8ef5` (subtle lighter shade)
- Transition: `transition: all 0.2s` → `transition: background-color 0.2s` (more performant)

#### Bar Height Comparison:
| Party Size | v1.0.82 | v1.0.83 | Change |
|------------|---------|---------|--------|
| 1-2 people | 16px    | 24px    | +50%   |
| 3-4 people | 36px    | 52px    | +44%   |
| 5-6 people | 56px    | 80px    | +43%   |

#### Benefits:
- **No overlap**: Bars fit perfectly in grid rows with proper spacing
- **Rounded corners visible**: No more disappearing corners
- **Better readability**: 50% thicker base bars are easier to read
- **Cleaner hover**: Subtle color change instead of distracting animation
- **Better performance**: Color transition is more performant than transform

#### Files Modified:
- `/assets/staying-today.js` line 672: Increased GRID_ROW_HEIGHT to 28px
- `/assets/style.css` line 734: Reduced vertical padding to 2px
- `/assets/style.css` lines 741, 747-750: Replaced hover animation with color change

---

## [1.0.82] - 2025-10-31

### Fixed: Enhanced Visual Separation for Gantt Chart Bars

**Summary:** Improved visual separation between Gantt chart bars to prevent them from appearing to merge together. Increased border radius, added borders, enhanced shadows, and doubled the vertical gap between bars.

#### Problem (from v1.0.81):
- Bars starting at the same time (on different grid rows) visually merged together
- Hard to distinguish individual bookings at a glance
- Corner rounding may have been lost or insufficient
- 2px gap was too subtle for clear visual separation

#### Solution:
Enhanced bar styling and spacing with CSS and JavaScript changes:

**CSS Updates** ([style.css](assets/style.css) lines 728-745):
- **Increased border-radius**: `4px` → `6px` for more prominent rounded corners
- **Added border**: `1px solid rgba(255, 255, 255, 0.2)` for clear edge definition
- **Enhanced box-shadow**:
  - Before: `0 2px 4px rgba(0,0,0,0.1)` (subtle)
  - After: `0 2px 5px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.1)` (stronger shadow + inset highlight)

**JavaScript Gap Update** ([staying-today.js](assets/staying-today.js) line 746):
- **Doubled vertical gap**: `-2px` → `-4px`
- Single-row bar: 20px grid → 16px bar height (4px gap)
- Two-row bar: 40px grid → 36px bar height (4px gap)

#### Visual Result:
```
Before (bars touching, merging visually):
[Booking 1 ─────────] ← 18px bar, 2px gap
[Booking 2 ─────────] ← Appears merged

After (clear separation):
[Booking 1 ─────────] ← 16px bar with rounded corners & border
                      ← 4px gap
[Booking 2 ─────────] ← Clearly separate bar
```

#### Benefits:
- **Clear separation**: 4px gap plus border creates obvious visual distinction
- **Prominent corners**: 6px radius makes rounded edges more visible
- **Better depth**: Enhanced shadows give bars more dimension
- **Edge definition**: Subtle white border separates bars from background
- **Glanceable**: Easy to count and distinguish individual bookings quickly

#### Files Modified:
- `/assets/style.css` lines 728-745: Enhanced bar styling
- `/assets/staying-today.js` line 746: Increased vertical gap

---

## [1.0.81] - 2025-10-31

### Refined: Grid Row Span Calculation for Optimal Bar Thickness

**Summary:** Adjusted grid row span calculation to use a more sensible baseline and increments. Now 1-2 people = 1 row (baseline), increasing by 1 row per 2 additional people, up to a maximum of 10 rows for 20 people.

#### Problem (from v1.0.80):
- Previous logic: `rowSpan = partySize` (1 person = 1 row, 2 people = 2 rows, etc.)
- Made ALL bars thicker than necessary
- Common 2-person tables were 2 rows thick (40px), which was excessive
- Single person bookings were unnecessarily thin (1 row = 20px)
- Inconsistent with typical restaurant table sizing patterns

#### Solution:
Updated row span calculation ([staying-today.js](assets/staying-today.js) lines 684-686):

**New Formula:**
```javascript
var rowSpan = Math.ceil(partySize / 2);
```

**Row Span Table:**
| Party Size | Grid Rows | Bar Height |
|------------|-----------|------------|
| 1-2 people | 1 row     | 20px       |
| 3-4 people | 2 rows    | 40px       |
| 5-6 people | 3 rows    | 60px       |
| 7-8 people | 4 rows    | 80px       |
| 9-10 people| 5 rows    | 100px      |
| 11-12 people| 6 rows   | 120px      |
| 13-14 people| 7 rows   | 140px      |
| 15-16 people| 8 rows   | 160px      |
| 17-18 people| 9 rows   | 180px      |
| 19-20 people| 10 rows  | 200px (max)|

**Max Party Size:** Increased from 16 to 20 (10 grid rows max)

#### Benefits:
- **Sensible baseline**: 1-2 people (most common) = 1 row, compact but readable
- **Table-size increments**: Increases by 2 people per row (matches typical table sizing)
- **Reduced thickness**: 2-person tables now 20px instead of 40px (50% reduction)
- **Better visual scaling**: Larger parties stand out more proportionally
- **Higher capacity**: Can display parties up to 20 people (vs 16 previously)

#### Rationale:
- Most restaurant bookings are 2-person tables - should be baseline thickness
- 3-person bookings typically use same table size as 4-person (round up to 2 rows)
- Incrementing by 2s matches restaurant table sizing (2-top, 4-top, 6-top, etc.)
- Max of 20 people covers virtually all bookings while capping at reasonable height

#### Files Modified:
- `/assets/staying-today.js` lines 673, 684-686: Updated row span calculation

---

## [1.0.80] - 2025-10-31

### Fixed: Grid-Based Layout for Consistent Gantt Chart Spacing

**Summary:** Replaced variable-height row system with fixed-height grid layout where larger bookings span multiple grid rows. This eliminates inconsistent spacing issues while maintaining visual prominence for larger party sizes.

#### Problem (from v1.0.79):
- Variable bar heights caused inconsistent vertical spacing
- When a large party (tall bar) and small party shared a row, the entire row height increased
- This created odd gaps and misalignment for subsequent bookings
- Spacing was unpredictable and visually jarring

**Example of the issue:**
```
Row with 2-person (small) and 12-person (tall) booking:
  [2 people - 24px] ←─┐
  [12 people - 44px]   ├─ Row height = 44px (max height)
                      ─┘

Next row starts 44px down, even though first booking only needs 24px
```

#### Solution:
Implemented grid-based layout ([staying-today.js](assets/staying-today.js) lines 670-783):

**Grid System:**
- **Fixed grid row height**: 20px per grid row
- **Row spanning**: Bookings span multiple grid rows based on party size
  - 1 person = 1 grid row (20px)
  - 4 people = 4 grid rows (80px)
  - 16 people = 16 grid rows (320px, max)
- **Consistent spacing**: Every grid row is exactly 20px, creating perfect alignment

**Algorithm:**
1. Calculate row span needed: `rowSpan = min(partySize, 16)` (1-16 grid rows)
2. Find first available position where `rowSpan` consecutive grid rows are free
3. Check all spanned rows for time conflicts with 5-minute buffer
4. Mark all spanned grid rows as occupied for the booking's time range
5. Position bar at fixed grid position: `y = 10 + (gridRow × 20px)`

**Visual Result:**
```
Grid Row 0: [2 people] ────────────── [4 people at 19:00]
Grid Row 1: [2 people] ────────────── [4 people at 19:00]
Grid Row 2:                           [4 people at 19:00]
Grid Row 3:                           [4 people at 19:00]
Grid Row 4: [12 people] ──────────────────────────────────
Grid Row 5: [12 people] ──────────────────────────────────
...
Grid Row 15: [12 people] ─────────────────────────────────
```

#### Benefits:
- **Perfectly consistent spacing**: Every row is exactly 20px apart
- **Visual hierarchy preserved**: Larger parties still visually prominent (span more rows)
- **No alignment issues**: Grid ensures everything lines up perfectly
- **Predictable layout**: Easy to visually scan and understand capacity
- **Scalable**: Works with any party size from 1-16+

#### Technical Details:
- `GRID_ROW_HEIGHT = 20px` (constant)
- `MAX_PARTY_SIZE = 16` (max row span)
- Bar height = `(rowSpan × 20px) - 2px` (2px gap for visual separation)
- Total chart height = `10px + (gridRows × 20px) + 10px`
- Each grid row tracks occupied time segments independently

#### Files Modified:
- `/assets/staying-today.js` lines 670-783: Grid-based layout system

---

## [1.0.79] - 2025-10-31

### Performance: Smart Row-Packing for Gantt Chart

**Summary:** Implemented intelligent row-packing algorithm to significantly reduce vertical space usage in Gantt chart by allowing non-overlapping bookings to share the same row. Lunch and dinner bookings can now coexist on the same row since they don't overlap temporally.

#### Problem:
- Every booking occupied its own horizontal row in the Gantt chart
- Wasted significant vertical space, especially with mixed lunch/dinner service
- A lunch booking at 12:00 and dinner booking at 19:00 were on separate rows despite never overlapping
- Required excessive scrolling to view all bookings

#### Solution:
Implemented row-packing algorithm ([staying-today.js](assets/staying-today.js) lines 670-779):

**Algorithm Logic:**
1. For each booking, calculate its time range (start + duration)
2. Try to place booking on an existing row where it doesn't overlap with any other booking
3. Check includes 5-minute buffer between bookings for visual clarity
4. If no suitable row exists, create a new row
5. Track maximum bar height per row for proper vertical spacing

**Example Packing:**
```
Row 1: [12:00 Lunch] ----gap---- [19:00 Dinner] ----gap---- [21:00 Late]
Row 2: [12:30 Lunch] ------------[19:30 Dinner]
Row 3: [18:00 Early Dinner] ----[20:00 Dinner]
```

Before: 6 bookings = 6 rows
After: 6 bookings = 3 rows (50% reduction!)

#### Benefits:
- **Space efficiency**: Typically 40-60% reduction in Gantt chart height
- **Better overview**: More bookings visible without scrolling
- **Smart packing**: Respects service periods naturally (lunch/dinner separation)
- **Visual clarity**: 5-minute buffer prevents bookings from touching
- **Scalability**: More bookings = more savings

#### Technical Details:
- Bookings sorted by start time before packing (earliest first)
- Each row tracks all bookings and their time ranges
- Overlap detection: `!(end1 <= start2 || start1 >= end2)`
- Variable bar height supported (based on party size)
- Row height = maximum bar height in that row

#### Files Modified:
- `/assets/staying-today.js` lines 670-779: Row-packing algorithm

---

## [1.0.78] - 2025-10-31

### UI Improvement: Compact Special Events Alert Banner

**Summary:** Reduced vertical space usage of special event alert banners by making them more compact and arranging content inline with format: icon, time range (bold), event name.

#### Changes:

**CSS Updates** ([style.css](assets/style.css) lines 976-1018):
- Reduced padding from `12px 15px` to `6px 12px` (50% vertical reduction)
- Reduced margin-bottom from `10px` to `6px` between alerts
- Changed icon size from `24px` to `20px`
- Changed `.special-event-content` from `flex-direction: column` to `row` for inline layout
- Reduced font size to `13px` for more compact appearance
- Made time range bold with `font-weight: 700`

**HTML Structure Updates** ([staying-today.js](assets/staying-today.js) lines 527-548):
- Rearranged content order: time range first (bold), then event name
- Format: **18:00 - 22:00:** limited kitchen staff
- Or: **All Day:** Service unavailable

#### Benefits:
- **Space saving**: ~50% reduction in vertical space per alert
- **Better readability**: Inline format is easier to scan
- **Visual hierarchy**: Bold time range draws attention first
- **Cleaner look**: More professional, compact appearance

#### Files Modified:
- `/assets/style.css` lines 976-1018: Compact styling
- `/assets/staying-today.js` lines 527-548: Inline HTML structure

---

## [1.0.77] - 2025-10-31

### Fixed: Time Slot Availability Fallback Behavior

**Summary:** Changed time slot availability logic to always mark times as unavailable when not returned by the API, removing the misleading fallback that showed all times as available.

#### Problem:
- When no available times returned from API, all time slots appeared available (not greyed out)
- This conflicted with special event restrictions which correctly greyed out times
- Created confusion: some times greyed due to special events, others appeared available despite no API data
- "No available times" message contradicted the visual appearance of available buttons

#### Solution:
Changed availability check from:
```javascript
var isAvailable = availableSet.size === 0 || availableSet.has(timeStr);
```

To:
```javascript
var isAvailable = availableSet.size > 0 && availableSet.has(timeStr);
```

#### Benefits:
- **Consistent behavior**: All unavailable times now greyed out, whether from missing API data or special events
- **Accurate visual feedback**: Time slots now accurately reflect actual availability
- **Staff override still works**: Greyed-out times remain clickable for manual overrides
- **Better UX**: Visual state matches the "No available times" message

#### Files Modified:
- `/assets/staying-today.js` line 856: Updated availability logic

---

## [1.0.76] - 2025-10-31

### Performance: Removed Excessive Debug Logging

**Summary:** Cleaned up debug.log file bloat by removing all unnecessary debug logging statements that were running on every page load. Reduced logging overhead significantly while keeping essential error logging for API failures.

#### Problem:
- Debug log grew to 83,650+ lines
- Extensive debug logging on EVERY page load
- Full API responses being logged (large data dumps)
- Booking matching debug output for every restaurant booking
- Notes structure debugging on every booking

#### Debug Logs Removed:

**Available Times API (lines 698-712)**:
- Full API response dump: `print_r($data, true)`
- Available times count logging
- Sample times array logging
- **Impact:** Ran on every "Create Booking" click

**Restaurant Booking Matching (lines 1697-1779)**:
- Full booking structure dumps
- Matched bookings array dumps
- ID match success logging
- Name+time match success logging
- First comparison attempt logging
- "NO MATCH FOUND" logging for every non-resident
- **Impact:** Ran on EVERY page load for ALL bookings

**Notes Extraction Debug (lines 1784-1791)**:
- Full notes structure dumps
- restaurantNotes field checking
- comments field checking
- Notes array print_r output
- **Impact:** Ran on EVERY page load

#### Logs Retained (Essential Error Logging):
- API connection failures
- HTTP error responses (401, 404, 500, etc.)
- JSON parsing errors
- Date calculation exceptions
- All legitimate error conditions

#### Performance Impact:

**Before v1.0.76:**
- 83,650 lines in debug.log
- Hundreds of log entries per page load
- Large print_r() dumps on every request
- File I/O overhead on every booking display

**After v1.0.76:**
- Only errors logged
- Minimal file I/O
- Faster page loads
- Cleaner log file for actual debugging

#### Files Modified:
- `hotel-admin.php` - Removed 15+ debug logging statements

#### Recommendation:
Consider truncating your debug.log file to start fresh:
```bash
echo "" > /var/www/html/wp-content/debug.log
```

Or disable debug logging entirely in wp-config.php if not needed:
```php
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
```

---

## [1.0.75] - 2025-10-31

### Special Events Integration - Alert Banners & Restricted Time Slots

**Summary:** Integrated special events from Resos API to show service restrictions/closures. Added alert banners above the Gantt chart to display special events (closures, limited service, etc.), greyed out restricted time slots with hover tooltips explaining the restriction, while still allowing staff override functionality.

#### Key Features:

1. **Special Events Alert Banner**
   - Displays above Gantt chart when special events affect the selected date
   - Shows event name (e.g., "limited kitchen staff", "Closed for maintenance")
   - Shows time range if event affects specific hours, or "All Day" for full closures
   - Yellow/amber styling with warning icon for visibility
   - Multiple alerts can display if multiple events affect the day

2. **Restricted Time Slot Indicators**
   - Time slots falling within special event periods are greyed out (like unavailable slots)
   - Remain fully clickable for staff override (same as existing unavailable override system)
   - Existing confirmation popup still appears when clicking restricted slots
   - Visual distinction between normal unavailable and special event restricted

3. **Hover Tooltips on Restricted Slots**
   - Hover over greyed-out time slots to see restriction reason
   - Tooltip displays warning icon + restriction message
   - Examples: "limited kitchen staff", "No availability", "Service unavailable"
   - Dark themed tooltip for easy readability
   - Instant feedback before clicking to override

#### Technical Implementation:

**PHP Changes ([hotel-admin.php](hotel-admin.php))**:

New function `get_special_events_for_date($date)` (lines 863-897):
```php
private function get_special_events_for_date($date) {
    $opening_hours = $this->get_resos_opening_hours();
    $special_events = array();
    $target_date = date('Y-m-d', strtotime($date));

    foreach ($opening_hours as $hours) {
        if (!isset($hours['special']) || $hours['special'] !== true) {
            continue; // Skip non-special entries
        }

        if (isset($hours['date'])) {
            $event_date = date('Y-m-d', strtotime($hours['date']));

            if ($event_date === $target_date) {
                $special_events[] = array(
                    'name' => isset($hours['name']) ? $hours['name'] : 'Service unavailable',
                    'isOpen' => isset($hours['isOpen']) && !empty($hours['isOpen']),
                    'open' => isset($hours['open']) ? intval($hours['open']) : null,
                    'close' => isset($hours['close']) ? intval($hours['close']) : null,
                    'range' => isset($hours['range']) ? $hours['range'] : 'single'
                );
            }
        }
    }

    return $special_events;
}
```

Updated `enqueue_scripts()` to pass special events (lines 345-347):
```php
$special_events = $this->get_special_events_for_date($input_date);

wp_localize_script(
    'hotel-booking-table-scripts',
    'hotelBookingAjax',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hotel-booking-nonce'),
        'currentDate' => $input_date,
        'openingHours' => $opening_hours,
        'specialEvents' => $special_events  // NEW
    )
);
```

Updated AJAX handler (line 925, 930):
```php
$special_events = $this->get_special_events_for_date($date);
$result['specialEvents'] = $special_events;
```

**JavaScript Changes ([staying-today.js](assets/staying-today.js))**:

New function `buildSpecialEventsAlert(specialEvents)` (lines 470-501):
- Builds HTML for alert banner
- Formats time ranges
- Handles multiple events

Updated `buildCreateBookingSection()` (lines 512, 516):
- Retrieves special events from localized data
- Renders alert banner above Gantt chart

Updated `buildTimeSlots()` signature (line 701):
- Accepts `specialEvents` parameter
- Helper function `isTimeRestricted()` checks if time falls within special event period
- Adds `data-restriction` attribute with reason to greyed-out slots

New function `setupTimeSlotTooltips()` (lines 358-399):
- Attaches hover listeners to restricted time slots
- Shows tooltip with restriction reason
- Positions tooltip below button

Updated `fetchAvailableTimes()` (lines 206, 215-217, 221):
- Extracts special events from AJAX response
- Passes to buildTimeSlots
- Calls setupTimeSlotTooltips after building

**CSS Changes ([style.css](assets/style.css))**:

Alert banner styles (lines 976-1017):
```css
.special-events-banner { margin-bottom: 15px; }

.special-event-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    border-left: 4px solid #ff9800;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
```

Tooltip styles (lines 1019-1041):
```css
.timeslot-tooltip {
    background-color: #2d3748;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2);
    max-width: 300px;
    z-index: 10000;
}
```

#### User Experience:

**Before v1.0.75:**
- No indication of special events/closures
- Staff unaware of service restrictions when creating bookings
- No context for why certain times might be problematic

**After v1.0.75:**
- Alert banner clearly shows special events affecting the day
- Restricted time slots visually identified (greyed out)
- Hover reveals exact reason for restriction
- Staff can still override if needed (existing confirmation flow)
- Better informed booking decisions
- Reduced confusion about service availability

#### Example Scenarios:

**Scenario 1: Limited Kitchen Staff**
- Alert banner: "⚠️ limited kitchen staff • 18:00 - 22:00"
- Evening time slots greyed out
- Hover shows: "⚠️ limited kitchen staff"
- Staff can still override and book if necessary

**Scenario 2: Full Day Closure**
- Alert banner: "⚠️ Closed for maintenance • All Day"
- All time slots greyed out
- Hover shows: "⚠️ Closed for maintenance"
- Staff can override for special circumstances

**Scenario 3: Partial Service**
- Alert banner: "⚠️ Private event • 12:00 - 15:00"
- Only lunch slots greyed out
- Dinner slots remain normal
- Clear visual separation

#### Files Modified:
- `hotel-admin.php` - Added special events fetching and passing to JavaScript
- `assets/staying-today.js` - Alert banner, time slot restriction, and tooltips
- `assets/style.css` - Alert banner and tooltip styling

---

## [1.0.73] - 2025-10-31

### Preload Service Period Dropdown on Page Load

**Summary:** Fixed the service period dropdown to populate automatically when the page loads, rather than waiting until a booking is created. The dropdown now shows the available service periods for the selected date immediately, with the latest period (dinner) pre-selected.

#### Problem:
- Service period dropdown showed "Loading..." until user clicked "Create Booking"
- Not useful as a navigation tool since it wasn't populated
- Had to create a booking just to see available service periods

#### Solution:
- Opening hours for the current date are now fetched on the server side during page load
- Data is passed to JavaScript via `wp_localize_script`
- Dropdown is populated immediately on `DOMContentLoaded`
- Latest service period (dinner) is pre-selected by default

#### Technical Changes:

**PHP ([hotel-admin.php](hotel-admin.php) - `enqueue_scripts()` function, lines 339-355)**:
```php
// Get current date from query parameter or use today
$input_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

// Get opening hours for the current date
$opening_hours = $this->get_opening_hours_for_date($input_date);

// Pass opening hours to JavaScript
wp_localize_script(
    'hotel-booking-table-scripts',
    'hotelBookingAjax',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hotel-booking-nonce'),
        'currentDate' => $input_date,
        'openingHours' => $opening_hours  // NEW
    )
);
```

**JavaScript ([staying-today.js](assets/staying-today.js) - DOMContentLoaded, lines 1021-1032)**:
```javascript
// Populate opening hours dropdown on page load
if (typeof hotelBookingAjax !== 'undefined' && hotelBookingAjax.openingHours) {
    var openingHours = hotelBookingAjax.openingHours;

    if (openingHours && Array.isArray(openingHours) && openingHours.length > 0) {
        window.populateOpeningTimeSelector(openingHours);
        console.log('Opening hours dropdown populated on page load');
    }
}
```

#### Result:
- Service period dropdown is immediately populated when page loads
- Shows available periods for the selected date (e.g., "Week Day Lunch Service", "Evening Service", "Afternoon Menu")
- Latest period (dinner) is pre-selected
- Users can navigate between service periods before creating any bookings
- Better UX - dropdown is always ready to use

---

## [1.0.72] - 2025-10-31

### Fixed: Opening Hours Filtering for Special Events

**Summary:** Fixed an issue where the opening hours dropdown and time sections were displaying ALL opening hours entries including special events (Christmas, closures, private bookings, etc.) instead of only showing regular recurring hours for the selected day.

#### Problem:
- Dropdown showed 163+ periods for a single day (Monday)
- Included one-off special events like "Christmas Day Lunch", "no power", "Private Group", etc.
- These are date-specific entries marked with `special: true` in the API
- Made the interface cluttered and confusing

#### Solution:
- Added filter to exclude entries where `special === true`
- Now only shows regular recurring hours (e.g., "Week Day Lunch Service", "Evening Service", "Afternoon Menu")
- Typical days now show 2-3 periods instead of 100+

#### Technical Changes:
**[hotel-admin.php](hotel-admin.php) - `get_opening_hours_for_date()` function (lines 798-815)**:
```php
// Skip special events - we only want regular recurring hours
$is_special = isset($hours['special']) && $hours['special'] === true;

if ($is_special) {
    continue; // Skip special events (Christmas, closures, private bookings, etc.)
}
```

#### Result:
- Monday now shows: "Week Day Lunch Service", "Evening Service", "Afternoon Menu" (3 periods)
- Instead of: 163 periods including all special events
- Cleaner dropdown and collapsible sections
- Only relevant service periods for booking creation

---

## [1.0.69] - 2025-10-31

### Collapsible Time Sections with Service Period Selector

**Summary:** Made time slot sections collapsible to save vertical screen space. Added a service period dropdown selector in the header that allows quick navigation between different opening times (lunch, dinner, etc.). The latest service period (dinner) is expanded and selected by default.

#### Key Features:

1. **Collapsible Time Sections**
   - Each opening time section (lunch, dinner, etc.) can be collapsed/expanded
   - Click section header to toggle visibility
   - Visual collapse/expand icons (▶ collapsed, ▼ expanded)
   - Smooth transition animations
   - Saves significant vertical screen space when multiple periods exist

2. **Service Period Dropdown Selector**
   - Added to date selector area at top of page
   - Lists all available service periods for the selected date
   - Displays period name or time range (e.g., "Dinner" or "18:00 - 22:00")
   - Automatically populated when opening hours load from API
   - Changes selection to expand/collapse corresponding section

3. **Smart Defaults**
   - Latest opening time (dinner service) is expanded by default
   - Latest period is pre-selected in dropdown
   - Other sections start collapsed to minimize clutter
   - Single-period days show expanded section (no collapse needed)

4. **Visual Enhancements**
   - Hover effects on collapsible headers (darker background)
   - Expanded sections have blue tint background
   - Collapse icons rotate smoothly
   - Dropdown styling matches existing date selector inputs

#### Technical Implementation:

**PHP Changes** ([hotel-admin.php](hotel-admin.php)):
- Added service period dropdown to date selector (lines 1820-1823):
```php
<label for="opening-time-selector">Service Period:</label>
<select id="opening-time-selector" onchange="switchServicePeriod()">
    <option value="">Loading...</option>
</select>
```

**JavaScript Changes** ([staying-today.js](assets/staying-today.js)):
- Modified `buildTimeSlots()` to create collapsible sections:
  - Added collapse/expand icons to headers
  - Added data attributes for section identification
  - Last period defaults to expanded state
  - Sections have unique IDs for targeting
- Added `toggleTimeSection(sectionId)` function:
  - Toggles display between flex and none
  - Updates icon direction
  - Adds/removes expanded class
- Added `switchServicePeriod()` function:
  - Reads dropdown selection
  - Collapses all sections
  - Expands selected section
  - Syncs icons and classes
- Added `populateOpeningTimeSelector(openingHours)` function:
  - Clears loading message
  - Creates option for each period
  - Sets last period as selected
- Called `populateOpeningTimeSelector()` in `fetchAvailableTimes()` (line 221)

**CSS Changes** ([style.css](assets/style.css)):
- Added collapsible header styles (lines 847-868):
```css
.time-slots-section-header.collapsible {
    cursor: pointer;
    user-select: none;
    transition: all 0.2s;
}

.time-slots-section-header.collapsible:hover {
    background-color: #e9ecef;
}

.time-slots-section-header.collapsible.expanded {
    background-color: #e7f0ff;
}
```
- Added dropdown styling to match date selector (lines 54-68):
```css
.date-selector select {
    padding: 8px 12px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    min-width: 180px;
}
```

#### User Experience:

**Before v1.0.69:**
- All time sections always visible
- Significant vertical scrolling needed for multi-period days
- No quick way to jump between lunch and dinner times

**After v1.0.69:**
- Dinner section expanded by default (most common use case)
- Other sections collapsed to save space
- Dropdown provides quick navigation between periods
- Click headers to manually expand/collapse
- Cleaner, more organized interface

#### Files Modified:
- `hotel-admin.php` - Version bump to 1.0.69, added service period dropdown
- `assets/style.css` - Added collapsible header and dropdown styles
- `assets/staying-today.js` - Added collapse/expand and dropdown logic

---

## [1.0.68] - 2025-10-31

### Gantt Chart Full-Day View with Interactive Scroll

**Summary:** Enhanced the Gantt chart to display the complete restaurant day while maintaining a 4-hour viewport with horizontal scrolling. Added interactive hover functionality where hovering over time slot buttons automatically scrolls the Gantt and displays a red sight line for easy time reference.

#### Key Features:

1. **Full-Day Gantt Display**
   - Gantt now spans entire restaurant operating hours (all opening periods)
   - Dynamically calculates range from opening hours API data
   - Maintains ~800px viewport width (approximately 4-hour view)
   - Horizontal scrollbar automatically appears for longer days

2. **Interactive Time Button Hover**
   - Hover over any time slot button to trigger Gantt interaction
   - Gantt smoothly auto-scrolls to center on the hovered time
   - Red dashed "sight line" appears vertically at the time position
   - Provides instant visual reference across all bookings
   - Sight line disappears when mouse leaves button

3. **Visual Enhancements**
   - Red sight line with dashed styling for clear visibility
   - Smooth scroll animation (CSS `scroll-behavior: smooth`)
   - Position calculated as percentage of total timeline
   - Sight line height matches booking area height

#### Technical Implementation:

**CSS Changes** ([style.css](assets/style.css)):
```css
.gantt-timeline {
    overflow-x: auto;
    overflow-y: hidden;
}

.gantt-time-axis,
.gantt-bookings {
    min-width: 800px; /* Ensures scrolling for 4-hour+ days */
}

.gantt-sight-line {
    position: absolute;
    width: 2px;
    background-color: rgba(220, 38, 38, 0.8);
    border-left: 2px dashed rgba(220, 38, 38, 0.9);
    z-index: 100;
    pointer-events: none;
    transition: left 0.3s ease-out;
}
```

**JavaScript Changes** ([staying-today.js](assets/staying-today.js)):
- Updated `buildGanttChart()` to accept `openingHours` parameter
- Added sight line element to Gantt HTML structure
- Implemented `setupTimeButtonHoverListeners()` function:
  - Calculates time position within full day range
  - Positions sight line at correct percentage
  - Scrolls Gantt to center time in viewport
  - Handles mouse enter/leave events

#### User Experience:

**Before v1.0.68:**
- Fixed 18:00-22:00 time range regardless of actual hours
- No way to preview time positions in Gantt

**After v1.0.68:**
- Dynamic range matching restaurant opening hours
- Hover any time button to see exact position in booking timeline
- Easy navigation through long service days
- Visual confirmation of time slot availability vs booking density

#### Files Modified:
- `hotel-admin.php` - Version bump to 1.0.68, updated asset versions
- `assets/style.css` - Added scrolling and sight line styles
- `assets/staying-today.js` - Added sight line and hover scroll logic

---

## [1.0.67] - 2025-10-26

### Changed
- **Gantt Chart Room Display Format**:
  - Hotel guests: Bar shows just room number (e.g., "Name - 202") instead of "Name - Room 202"
  - Non-residents: Bar shows only guest name, no room identifier
  - Tooltip display: Room number only for hotel guests, "Non-Resident" for walk-ins
- **Gantt Chart Time Display**:
  - Removed 22:00 time header from Gantt axis (timeline now shows 18:00 through 21:30)
  - Gantt area ends precisely at 22:00 but header doesn't display that boundary marker

### Fixed
- **Gantt Bar Overflow**: Fixed booking bars extending beyond Gantt container
  - Added `overflow: hidden` to `.gantt-timeline` container (style.css:682)
  - Bars now properly clip at container boundaries
- **Capped Bar Styling**: Bars that extend to 22:00 closing time now have sharp right corners instead of rounded
  - Added `.gantt-bar-capped` class with `border-top-right-radius: 0` and `border-bottom-right-radius: 0`
  - Visual indicator that booking extends to closing time

### Technical Details
- **Room Identifier Changes** (hotel-admin.php):
  - Line 1424: Default identifier changed from `'Non-Hotel Guest'` to `'Non-Resident'`
  - Line 1459: Room match now stores just room number (e.g., `$room`) instead of `'Room ' . $room`
- **Bar Display Logic** (staying-today.js:466-468):
  - Conditional formatting: Non-residents show `booking.name` only
  - Hotel guests show `booking.name + ' - ' + booking.room`
  - Automatically handles different display based on room identifier
- **Time Header Loop** (staying-today.js:505):
  - Changed loop condition from `h <= endHour` to `h < endHour`
  - Prevents 22:00 from appearing in header while maintaining 22:00 as endpoint for calculations

---

## [1.0.66] - 2025-10-26

### Added
- **Gantt Chart Enhancements**:
  - Bookings now sorted vertically by start time (earliest at top)
  - Interactive tooltips on hover showing guest name, room number, time, and party size
  - Dynamic height based on content - no more fixed height or scrolling
  - **Bar display format**: Circular party size badge + "guest name - room number" (e.g., "(4) John Smith - Room 101")
  - Party size badge styled as circle with dark semi-transparent background (24px diameter)
  - **Shows ALL restaurant bookings** (not just matched ones) - includes non-hotel guests
  - **15-minute interval lines**: Vertical dashed gridlines at 15-minute intervals (including hour marks) for easier time visualization
  - **Half-hourly time headers**: Time axis shows 18:00, 18:30, 19:00, 19:30, etc. (left-aligned to their respective positions)
  - Room identifier shown for both hotel guests (e.g., "Room 101") and "Non-Hotel Guest"
  - **Robust name field detection**: Checks multiple possible field names from Resos API (name, guestName, customerName, firstName+lastName, customer.name)
  - **Resos notes in tooltips**: Booking notes from Resos API now displayed in color-coded boxes within the Gantt chart tooltip
    - **Guest messages** (green boxes): Messages/comments from the guest when booking
    - **Internal notes** (blue boxes): Staff notes and internal comments
  - **Table information in tooltips**: Displays assigned table(s) from Resos with area names
    - Format: "Table 9 (Top Section), Table 10 (Top Section)"
    - Shows all tables assigned to the booking
- **Expanded Comparison Visual Enhancements**:
  - Comparison rows now appear to "slide out" from parent row with animation
  - Darker borders (2px solid #495057) and shadow effect for depth
  - Grey background (#f8f9fa) behind comparison section
  - 20px margins on sides to create inset appearance
  - Rounded bottom corners (8px) for polished look
- **Parent Row Highlighting**: When comparison is expanded, parent row gets:
  - Light blue background (#e7f3ff)
  - Left border accent (3px solid #667eea)
  - Bold text to emphasize active state
- **Slide-Down Animation**: 0.3s smooth animation when opening comparison
  - Fades in from 0 to full opacity
  - Slides down 10px with slight scale effect
  - Creates smooth, professional transition
- **Smart Actions Column**: Actions column now displays context-aware buttons based on match status
  - "Create Booking" (green) for rooms with no restaurant match
  - "Check Match" (amber) for suggested matches requiring review
  - "Check Updates" (blue) for confirmed matches with available updates
- **Expandable Comparisons for Confirmed Matches**: Confirmed matches can now show comparison details and suggested updates, not just suggested matches
- **Toggle functionality**: New `toggleComparisonRow()` JavaScript function to open/close comparison rows from action buttons
- **Button Icons**: All action and comparison buttons now include Material Symbols Outlined icons
  - Create Booking: `add_circle` icon
  - Check Match: `search` icon
  - Check Updates: `update` icon
  - Update Selected: `check_circle` icon
  - Close: `close` icon
- **Material Symbols Font**: Added Google Material Symbols Outlined font for clean, professional icons

### Changed
- **Comparison Table Headers**: Removed "(HOTEL)" and "(RESTAURANT)" labels - headers now show clean "Newbook" and "Resos" labels
- **Button Sizing**: All action buttons now have consistent width (140px min-width) regardless of label length
- **Comparison Button Styling**:
  - Buttons now aligned to the right with proper spacing
  - Close button: Grey (#6c757d)
  - Confirm/Update button: Amber (#f59e0b)
  - Added hover effects and smooth transitions
  - Icons aligned with flexbox for clean display
- **Dynamic Button Labels**: Comparison action button label changes based on match type:
  - Confirmed matches: "Update Selected"
  - Suggested matches: "Update Selected & Match"
- **Match Indicator Display**: Removed arrow icons (▶) from inline "Matched Fields" text
- **Comparison Data Preparation**: Now prepares comparison data for both confirmed AND suggested matches (previously only suggested)

### Fixed
- **Guest name extraction**: Fixed field priority order to check `guest.name` first (primary Resos field) before fallback options
  - Resolves issue where some bookings were showing "Guest" instead of actual guest name
- **Gantt chart overflow**: Extended Gantt timeline to 22:00 (10pm) and added logic to cap booking bars that extend beyond
  - Booking bars now intelligently truncate at 22:00 if the 2-hour duration would extend past closing
  - Time slots now show up to 20:00 (latest booking time for 2-hour slots ending at 22:00)
- **Critical JavaScript Syntax Error**: Resolved WordPress HTML entity encoding issue that was breaking all expansion functionality
  - WordPress was encoding `&&` operators to `&#038;&#038;` in inline JavaScript, causing syntax errors
  - Solution: Moved all JavaScript to separate `/assets/staying-today.js` file that WordPress doesn't process
  - Added `enqueue_scripts()` function to properly load JavaScript via WordPress's enqueue system
- **Gantt Chart Height**: Fixed issue where last booking bar would extend past bottom of container
  - Now calculates total height dynamically and applies it directly to container
- **Vacant Room Actions**: Removed "Create Booking" button from vacant rooms
  - Button now only appears for rooms with hotel bookings that have no restaurant match
- Missing CSS for comparison action buttons (`.btn-confirm-match`, `.btn-close-comparison`)
- Comparison buttons lost formatting after previous revision - now properly styled
- Inconsistent button widths in actions column

### Technical Details
- **New JavaScript Architecture**:
  - Created `/assets/staying-today.js` (19KB) containing all expansion and interaction logic
  - Properly enqueued via `wp_enqueue_script()` with version 1.0.66
  - Loads in footer for better page load performance
  - Global functions (toggleComparisonRow, toggleCreateBookingRow, buildGanttChart, buildTimeSlots) remain available for onclick handlers
  - Restaurant bookings data loaded via JSON script tag to avoid PHP variable encoding issues
- **Gantt Chart Data Structure**:
  - Created new `$all_restaurant_bookings` array containing ALL Resos bookings (not just matched)
  - Loops through raw `$restaurant_bookings` from API and formats each booking
  - Determines room identifier by checking if booking exists in `$matched_restaurant_bookings`
  - **Improved room matching logic**: Checks multiple ID field names (id, bookingId, reservationId) and falls back to name+time matching if ID not available
  - Uses "Non-Hotel Guest" identifier for non-matched bookings
  - Data structured as `['all' => [array of bookings]]` for JavaScript consumption
  - **Name field fallback logic**: Checks 5 possible field names in order (name, guestName, customerName, firstName+lastName, customer.name)
  - **Notes extraction**: Extracts notes from Resos API array structures and categorizes by type:
    - Internal notes: `restaurantNotes` array → loops through and extracts `restaurantNote` field → tagged as 'internal' type
    - Guest messages: `comments` array → filters out system comments by checking `role !== 'system'` → tagged as 'guest' type
    - Each note stored as object with `type` and `content` properties
  - **Table extraction**: Extracts table information from `tables` array in Resos booking:
    - Loops through each table object and extracts `name` field
    - Appends `area.name` in parentheses if available (e.g., "Table 9 (Top Section)")
    - Stores as array of formatted strings for JavaScript consumption
  - **Name field priority**: Reordered field checking to prioritize `guest.name` (primary Resos field) first
  - Added debug logging to output first booking structure to PHP error log for troubleshooting (accessible via `docker logs`)
  - **Fixed ID matching**: Now checks `_id` field first (Resos uses MongoDB-style `_id` not `id`)
  - **Fixed name matching**: Now correctly reads nested `guest.name` field from Resos bookings
- **Dynamic Height Calculation**:
  - `buildGanttChart()` now applies height directly to `.gantt-bookings` container via inline style
  - Calculates `totalHeight = yOffset + 5` after all bars are positioned
  - Removed fixed 300px height from `.gantt-timeline` CSS, replaced with `min-height: 80px`
- **Tooltip System for Gantt Bars**:
  - Added `setupGanttTooltips()` function called after expansion
  - Attaches mouseenter/mouseleave events to all `.gantt-booking-bar` elements
  - Reads data attributes (data-name, data-people, data-time, data-room, data-notes, data-tables) for tooltip content
  - Fixed to use `booking.resos_booking.room` instead of loop variable for correct room display
  - **Tables display**: Parses JSON-encoded tables array and displays as comma-separated list
    - Format: "Table(s): Table 9 (Top Section), Table 10 (Top Section)"
    - Positioned between party size and notes in tooltip
  - **Notes display**: Parses JSON-encoded notes array and displays each note in color-coded box based on type
  - Guest messages styled with `.tooltip-note-box-guest` (green: #d4edda background, #c3e6cb border, #155724 text)
  - Internal notes styled with `.tooltip-note-box-internal` (blue: #d1ecf1 background, #bee5eb border, #0c5460 text)
  - JavaScript checks note structure and handles both string and object formats
- **Gantt Visual Improvements**:
  - Changed loop variable from `room` to `key` to avoid confusion with actual room data
  - Bar uses flexbox layout with `.gantt-party-size` badge (circular, 24px) and `.gantt-bar-text` with format: "name - room"
  - Party size badge styled as circle with `border-radius: 50%`, dark semi-transparent background
  - **Dashed interval lines**: Changed from solid to dashed (`border-left: 1px dashed`) for better visual clarity
  - Interval lines at every 15 minutes starting from 18:00 (includes :00, :15, :30, :45 of each hour) - 15 lines total for 4-hour span
  - Lines loop from m=0 to m<240 in steps of 15, skipping m=0 to avoid line at left edge
  - **Half-hourly time headers**: Now shows 9 headers (18:00, 18:30, 19:00, 19:30, 20:00, 20:30, 21:00, 21:30, 22:00)
  - Time labels positioned absolutely with inline `left: X%` style for precise placement
  - Time axis uses `position: relative` and labels use `position: absolute` for left-alignment
  - Booking bars use `display: flex` and `align-items: center` for proper badge/text alignment
- **Gantt Booking Bar Width Calculation**:
  - Added `bookingDuration` constant (120 minutes / 2 hours)
  - Calculates `bookingEndMinutes = minutesFromStart + bookingDuration`
  - If booking extends beyond `totalMinutes` (22:00), caps it: `bookingEndMinutes = totalMinutes`
  - Width calculated as: `(actualBookingWidth / totalMinutes) * 100%`
  - Ensures no bars overflow the Gantt container even for late bookings
- **Time Slot Buttons**: Updated to show slots from 18:00 to 20:00 (9 slots in 15-min increments)
  - Previous limit was 20:30, now 20:00 to accommodate 2-hour bookings ending at 22:00
- **Action Button Logic**:
  - Both regular and grouped room sections now check `$has_booking` before showing "Create Booking" button
  - Changed from `<?php else: ?>` to `<?php elseif ($has_booking): ?>` on lines 1641 and 1881
  - Ensures vacant rooms have empty action cells
- Modified actions column logic in both regular and grouped room sections
- Added button classes: `.btn-check-match`, `.btn-check-updates`
- Updated CSS with new button styles, consistent sizing, and comparison actions layout
- Refactored comparison data preparation to support all match types
- All buttons now use flexbox (`display: inline-flex`) for proper icon alignment
- Icon spacing controlled with `gap: 6px` property

---

## [1.0.65] - 2025-10-25

### Changed
- Enhanced suggestion column styling to better distinguish suggested updates from matched data
- Added interactive checkboxes to allow users to selectively apply updates
- Version number display added to footer

### Technical Details
- Green highlighting now only applies to first 3 columns (Field, Newbook, Resos), not Suggested Updates column
- Suggestion cells with content receive amber background (#fff3cd)
- Checkboxes default to checked, store field name and value in data attributes
- `confirmMatch()` function collects checked suggestions for future API integration

### Summary
Enhanced the suggestion column styling to better distinguish suggested updates from matched data, and added interactive checkboxes to allow users to selectively apply updates. Also added version number display to help track deployments.

### Major Changes

**1. Improved Suggestion Column Visual Hierarchy**

The green "match" highlighting now only applies to the first three columns (Field, Newbook, Resos), **not** the Suggested Updates column. This creates a clearer visual distinction between:
- **Green**: Data that already matches between systems
- **Amber/Yellow**: Data that needs updating

**Previous Behavior:**
```
All four columns turned green when a match was detected
```

**New Behavior:**
```
Only the data columns (1-3) turn green for matches
Suggestion column maintains amber background when it has content
```

**2. Amber Highlighting for Active Suggestions**

Suggestion cells that contain actual recommendations now receive an amber/yellow background (`#fff3cd`), matching the column header color. Empty suggestion cells (showing just "-") remain with a transparent background.

**Visual Result:**
- Suggestions with values: **Amber background** (stands out)
- Suggestions without values: Transparent background (blends in)

**3. Interactive Update Checkboxes**

Each suggestion row with content now includes a checkbox on the right side, allowing users to selectively choose which updates to apply.

**Checkbox Features:**
- Checked by default for all suggestions
- Aligned to the right of the suggestion cell
- Stores field name and value in `data-` attributes
- Can be unchecked to exclude specific updates
- Hover effect for better UX

**Data Attributes:**
```html
<input type="checkbox"
       class="suggestion-checkbox"
       data-field="phone"
       data-value="01234567891"
       checked>
```

**4. Enhanced Confirm Match Function**

The `confirmMatch()` function now collects all checked suggestions and prepares them for the API update:

```javascript
window.confirmMatch = function(uniqueId, bookingId, roomId) {
    // Collects all checked checkboxes
    var checkedBoxes = comparisonRow.querySelectorAll('.suggestion-checkbox:checked');
    var updates = {};

    // Builds updates object: { field: value }
    checkedBoxes.forEach(function(checkbox) {
        updates[checkbox.getAttribute('data-field')] = checkbox.getAttribute('data-value');
    });

    // Shows what will be updated in alert
    // TODO: Pass 'updates' object to Resos API
}
```

**Alert Preview:**
```
Confirm Match

This will update Resos with Booking # 12345
for the restaurant reservation for Room 101.

Updates to apply:

• name: John Smith
• phone: 01234567891
• hotel_guest: Yes

API integration coming in next version.
```

**5. Version Number Display**

Added a small, centered version number at the bottom of the content area (after the last group section).

**Display:**
```
v1.0.65
```

**Styling:**
- Small text (11px)
- Light gray color (#adb5bd)
- Centered alignment
- Top border to separate from content
- 20px padding for spacing

**Location:** Inside `.hotel-booking-content` div, after all room tables

### CSS Changes (v1.0.63)

#### Updated Styles

**1. Match Row Highlighting (Fixed)**
```css
/* OLD - Applied green to all columns */
.comparison-table tbody tr.match-row {
    background-color: #d4edda;
}

.comparison-table tbody tr.match-row td {
    color: #155724;
}

/* NEW - Only applies to first 3 columns */
.comparison-table tbody tr.match-row td:nth-child(1),
.comparison-table tbody tr.match-row td:nth-child(2),
.comparison-table tbody tr.match-row td:nth-child(3) {
    background-color: #d4edda;
    color: #155724;
}

.comparison-table tbody tr.match-row td:nth-child(1) {
    color: #0c4128;
    font-weight: 700;
}
```

**2. Suggestion Column Base Styling**
```css
.comparison-table tbody td.suggestion-cell {
    background-color: transparent;
    color: #856404;
    font-weight: 500;
}

.comparison-table tbody td.suggestion-cell.has-suggestion {
    background-color: #fff3cd;
}
```

**3. Checkbox Container and Styling**
```css
.suggestion-cell-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.suggestion-text {
    flex: 1;
}

.suggestion-checkbox {
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #667eea;
}

.suggestion-checkbox:hover {
    transform: scale(1.1);
}
```

**4. Version Number Styling**
```css
.plugin-version {
    text-align: center;
    color: #adb5bd;
    font-size: 11px;
    padding: 20px 0;
    margin-top: 30px;
    border-top: 1px solid #e9ecef;
}
```

### PHP Changes (v1.0.65)

#### Modified Functions

**1. `buildComparisonRow()` - JavaScript Function**
**Already Implemented** - This was already in place in the "v1_0_64" file:

```javascript
// Name row with checkbox
var nameSuggestionCell = suggestions.name ?
    '<td class="suggestion-cell has-suggestion">' +
        '<div class="suggestion-cell-content">' +
            '<span class="suggestion-text">' + nameSuggestion + '</span>' +
            '<input type="checkbox" class="suggestion-checkbox" ' +
                   'data-field="name" ' +
                   'data-value="' + suggestions.name.replace(/"/g, '&quot;') + '" ' +
                   'checked>' +
        '</div>' +
    '</td>' :
    '<td class="suggestion-cell">' + nameSuggestion + '</td>';
```

Applied to all suggestion fields:
- Guest Name
- Phone
- Email
- Hotel Guest
- Booking #
- Tariff/Package (DBB)

**2. Version Number Display - HTML**
**Added at line 1755-1756:**
```php
<!-- Plugin Version -->
<div class="plugin-version">v1.0.65</div>
```

**3. `enqueue_styles()` - Updated Version Reference**
```php
wp_enqueue_style(
    'hotel-booking-table-styles',
    plugin_dir_url(__FILE__) . 'assets/style.css',
    array(),
    '1.0.63'  // Updated from 1.0.61
);
```

### User Experience Improvements

**Before v1.0.65:**
1. Green highlighting spanned all columns including suggestions (confusing)
2. All suggestion cells had same amber background (no distinction between empty/filled)
3. No way to selectively apply updates
4. No version tracking visible to users

**After v1.0.65:**
1. Green only on matched data columns (clear distinction)
2. Amber only on cells with actual suggestions (better hierarchy)
3. Checkboxes allow selective update application
4. Version number visible for troubleshooting

### Visual Comparison

#### Suggestion Column Appearance:

| Row | Before v1.0.65 | After v1.0.65 |
|-----|----------------|---------------|
| **Name** (with suggestion) | Green bg if matched, amber text | Transparent bg if matched, amber bg + checkbox if suggestion |
| **Phone** (with suggestion) | Green bg if matched, amber text | Transparent bg if matched, amber bg + checkbox if suggestion |
| **Email** (no suggestion) | Amber bg, gray dash | Transparent bg, gray dash |
| **Booking #** (with suggestion) | Green bg if matched, amber text | Transparent bg if matched, amber bg + checkbox if suggestion |

### Installation Instructions

**1. Update PHP File**
Replace your existing `hotel-booking-table.php` with `hotel-booking-table_v1_0_65.php`

**2. Update CSS File**
Replace `wp-content/plugins/hotel-booking-table/assets/style.css` with `hotel-restaurant-bookings_v1_0_63.css`

**3. Clear Cache**
- Clear browser cache
- Clear WordPress cache (if using caching plugin)
- Hard refresh the page (Ctrl+Shift+R or Cmd+Shift+R)

### Files Modified

1. **hotel-booking-table_v1_0_65.php**
   - Updated CSS version reference (line 297)
   - Version number already present (line 1756)
   - Checkbox logic already present in buildComparisonRow()
   - confirmMatch() already collecting checkbox data

2. **hotel-restaurant-bookings_v1_0_63.css** (NEW)
   - Fixed match-row highlighting to exclude suggestion column
   - Added `.has-suggestion` class with amber background
   - Added `.suggestion-cell-content` flexbox layout
   - Added `.suggestion-checkbox` styling
   - Added `.plugin-version` styling

### Testing Checklist

- Green highlighting only appears on first 3 columns for matched rows
- Suggestion cells with content have amber background
- Suggestion cells without content have transparent background
- Checkboxes appear on the right of suggestions
- Checkboxes are checked by default
- Checkboxes can be unchecked
- Confirm Match alert shows selected updates
- Version number displays at bottom: "v1.0.65"
- All styling looks consistent and professional

### Future API Integration

The checkbox data is collected and ready for API integration. When implementing the Resos update API:

```javascript
// In confirmMatch() function - line 2189
// TODO: Make API call to Resos to update the selected fields

fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'update_resos_booking',
        booking_id: bookingId,
        updates: JSON.stringify(updates)
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        location.reload(); // Refresh to show confirmed match
    } else {
        alert('Error: ' + data.message);
    }
});
```

### Backwards Compatibility

- All existing functionality preserved
- No database changes required
- Compatible with all previous API integrations
- No breaking changes to shortcode or configuration

### Known Issues

None reported.

### Credits

**Version**: 1.0.65
**Date**: October 2025
**Changes By**: Development Team
**CSS Version**: 1.0.63

### Quick Reference

**Plugin File**: `hotel-booking-table_v1_0_65.php`
**CSS File**: `hotel-restaurant-bookings_v1_0_63.css`
**Version Display**: v1.0.65 (visible at bottom of page)

**Ready for Production**

---

## [1.0.64] - 2025-10-24

### Status
- Stable production version
- Full matching functionality operational
- Previous stable baseline

---

## Version Numbering

- **1.0.x**: Current stable series with UI/UX refinements
- **1.1.x**: Planned MVC restructuring (see restructuring-plan.md)
- **1.2.x**: Planned automation features (email alerts, package checking)

---

## Temporary Modifications

### API Group Details Disabled
- `get_group_details()` function temporarily returns `null` to avoid 401 errors
- Re-enable when Newbook API credentials have group access permissions
- Location: Line ~336 in hotel-admin.php

---

*For detailed restructuring plans and future roadmap, see [restructuring-plan.md](restructuring-plan.md)*
