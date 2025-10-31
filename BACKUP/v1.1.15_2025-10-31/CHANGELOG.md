# Hotel Admin Plugin - Changelog

All notable changes to this project will be documented in this file.

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
