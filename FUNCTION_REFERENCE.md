# Function Reference - Hotel Admin Plugin
**Version**: 1.1.15
**Last Updated**: 2025-10-31

## Table of Contents
- [Core Functions](#core-functions)
- [Admin Functions](#admin-functions)
- [API Functions](#api-functions)
- [AJAX Handlers](#ajax-handlers)
- [Data Processing Functions](#data-processing-functions)
- [Utility Functions](#utility-functions)
- [JavaScript Functions](#javascript-functions)

---

## Core Functions

### Plugin Initialization

| Function | Line | Purpose | Dependencies |
|----------|------|---------|--------------|
| `__construct()` | 27 | Initialize plugin, register hooks | WordPress core |
| `prevent_texturize()` | 57 | Prevent WordPress from converting quotes | WordPress filters |
| `enqueue_styles()` | 366 | Load CSS files with version control | CSS_VERSION constant |
| `enqueue_scripts()` | 388 | Load JavaScript files with version control | JS_VERSION constant |

---

## Admin Functions

### Settings & Configuration

| Function | Line | Purpose | Dependencies |
|----------|------|---------|--------------|
| `add_admin_menu()` | 65 | Add settings page to WordPress admin | WordPress admin |
| `register_settings()` | 78 | Register all plugin settings | WordPress settings API |
| `render_settings_page()` | 287 | Render the settings page HTML | Admin menu |
| `settings_section_callback()` | 177 | Newbook settings section | Settings API |
| `resos_section_callback()` | 184 | Resos settings section | Settings API |
| `testing_section_callback()` | 259 | Testing mode settings section | Settings API |

### Field Callbacks

| Function | Line | Purpose |
|----------|------|---------|
| `username_field_callback()` | 191 | Newbook username field |
| `password_field_callback()` | 200 | Newbook password field |
| `api_key_field_callback()` | 209 | Newbook API key field |
| `region_field_callback()` | 218 | Newbook region selector |
| `hotel_id_field_callback()` | 232 | Hotel ID field |
| `resos_api_key_field_callback()` | 241 | Resos API key field |
| `package_inventory_name_field_callback()` | 250 | Package inventory name field |
| `mode_field_callback()` | 274 | API mode selector (Production/Testing/Sandbox) |

---

## API Functions

### Newbook PMS API

| Function | Line | Purpose | Returns |
|----------|------|---------|---------|
| `call_api()` | 502 | Main Newbook API caller | API response array |
| `get_bookings_data()` | 583 | Fetch hotel bookings for date | Bookings array |
| `get_rooms_data()` | 614 | Fetch room list | Rooms array |
| `get_group_details()` | 459 | Get group booking details (DISABLED) | null (temporarily) |

### Resos Restaurant API

| Function | Line | Purpose | Returns |
|----------|------|---------|---------|
| `get_restaurant_bookings_data()` | 634 | Fetch restaurant bookings | Bookings array |
| `get_resos_available_times()` | 719 | Get available booking times | Times array |
| `get_resos_available_dates()` | 790 | Get available dates range | Dates array |
| `get_resos_opening_hours()` | 870 | Get restaurant opening hours | Hours array |
| `get_opening_hours_for_date()` | 937 | Get hours for specific date | Hours object |
| `get_special_events_for_date()` | 1043 | Get special events/closures | Events array |

---

## AJAX Handlers

### Preview Functions (Testing/Sandbox Modes)

| Function | Line | Purpose | Action Hook |
|----------|------|---------|-------------|
| `ajax_preview_resos_match()` | 1127 | Preview update booking request | `wp_ajax_preview_resos_match` |
| `ajax_preview_resos_create()` | 2294 | Preview create booking request | `wp_ajax_preview_resos_create` |

### Execute Functions (All Modes)

| Function | Line | Purpose | Action Hook |
|----------|------|---------|-------------|
| `ajax_confirm_resos_match()` | 1488 | Update existing Resos booking | `wp_ajax_confirm_resos_match` |
| `ajax_create_resos_booking()` | 1936 | Create new Resos booking | `wp_ajax_create_resos_booking` |
| `ajax_get_available_times()` | 1082 | Get available time slots | `wp_ajax_get_available_times` |
| `ajax_get_dietary_choices()` | 2561 | Get dietary requirements options | `wp_ajax_get_dietary_choices` |

---

## Data Processing Functions

### Matching & Comparison

| Function | Line | Purpose | Returns |
|----------|------|---------|---------|
| `match_resos_to_hotel_booking()` | 3138 | Match restaurant to hotel booking | Match array with confidence |
| `prepare_comparison_data()` | 2696 | Prepare booking comparison table | Comparison array |
| `prepare_guest_data_for_create_booking()` | 3010 | Extract guest data for booking | Guest data array |
| `normalize_for_matching()` | 2621 | Normalize strings for comparison | Normalized string |
| `normalize_phone_for_matching()` | 2636 | Normalize phone numbers | Normalized phone |
| `extract_surname()` | 2648 | Extract surname from full name | Surname string |

### Data Organization

| Function | Line | Purpose | Returns |
|----------|------|---------|---------|
| `organize_rooms()` | 3447 | Organize rooms by groups | Organized array |
| `get_primary_guest_name()` | 3386 | Extract primary guest name | Name string |
| `get_night_status()` | 3405 | Determine night of stay | Status string |
| `get_note_types()` | 429 | Get Resos note categories | Note types array |

---

## Utility Functions

### Error Handling

| Function | Line | Purpose |
|----------|------|---------|
| `add_error()` | 352 | Add error message to queue |
| `get_errors()` | 359 | Retrieve error messages |

### Formatting

| Function | Line | Purpose | Returns |
|----------|------|---------|---------|
| `format_phone_for_resos()` | 2664 | Format phone to international | Formatted phone (+44...) |
| `get_hotel_id()` | 490 | Get hotel ID from settings/attributes | Hotel ID |

### Testing

| Function | Line | Purpose |
|----------|------|---------|
| `test_available_dates()` | 847 | Test Resos date availability |

---

## Main Rendering Function

| Function | Line | Purpose | Returns |
|----------|------|---------|---------|
| `render_booking_table()` | 3522 | Main shortcode handler | HTML output |

**Shortcode**: `[hotel-table-bookings-by-date]`

---

## JavaScript Functions (staying-today.js)

### Core UI Functions

| Function | Purpose | Location |
|----------|---------|----------|
| `toggleComparisonRow()` | Show/hide booking comparison | Global |
| `toggleCreateBookingRow()` | Show/hide create booking form | Global |
| `buildComparisonRow()` | Build comparison table HTML | Inline |
| `buildCreateBookingSection()` | Build create booking UI | Inline |
| `buildGanttChart()` | Render Gantt chart visualization | Inline |
| `buildTimeSlots()` | Generate time slot buttons | Inline |

### Time & Date Functions

| Function | Purpose | Location |
|----------|---------|----------|
| `selectTimeSlot()` | Handle time slot selection | Inline |
| `createBookingWithSelectedTime()` | Execute booking creation | Inline |
| `isTimeRestricted()` | Check if time has restrictions | Line 2217 |
| `updateDate()` | Update selected date | Line 2720 |

### Gantt Chart Functions

| Function | Purpose | Location |
|----------|---------|----------|
| `setupGanttTooltips()` | Initialize Gantt tooltips | Inline |
| `toggleTimeSection()` | Expand/collapse time sections | Inline |
| `getOpeningHourForTime()` | Get opening hour for time slot | Inline |

### API Interaction

| Function | Purpose | Location |
|----------|---------|----------|
| `createResosBooking()` | Trigger booking creation | Line 2730 |
| `confirmMatch()` | Confirm booking match | Inline |
| `fetchAvailableTimes()` | Get available times via AJAX | Inline |
| `fetchDietaryChoices()` | Load dietary options | Inline |
| `populateDietaryCheckboxes()` | Build dietary checkboxes | Inline |

### Form Handling

| Function | Purpose | Location |
|----------|---------|----------|
| `setupNotificationAutoCheck()` | Auto-check SMS/email boxes | Inline |
| `toggleAllergiesSection()` | Show/hide allergies section | Inline |
| `format_phone_for_resos()` | Format phone number (PHP mirror) | Inline |

### Event Handlers

| Function | Purpose |
|----------|---------|
| `executeApiCall()` | Execute API call after confirmation |
| `updateRowStyling()` | Update row visual state |

---

## API Endpoints Used

### Newbook PMS
- `GET /property/bookings` - Get bookings
- `GET /property/rooms` - Get rooms
- `GET /group/{id}` - Get group details (disabled)
- `GET /inventory/packages` - Get packages

### Resos
- `GET /bookings` - Get restaurant bookings
- `POST /bookings` - Create booking
- `PATCH /bookings/{id}` - Update booking
- `POST /bookings/{id}/restaurantNote` - Add note
- `GET /config/booking` - Get booking configuration
- `GET /availability/times` - Get available times
- `GET /availability/dates` - Get available dates

---

## Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `VERSION` | '1.1.15' | Plugin version |
| `JS_VERSION` | '1.0.75' | JavaScript version |
| `CSS_VERSION` | '1.0.75' | CSS version |

---

## Critical Functions for API Parity

⚠️ **These function pairs MUST remain synchronized:**

1. **Create Booking**:
   - `ajax_create_resos_booking()` (execute)
   - `ajax_preview_resos_create()` (preview)

2. **Update Booking**:
   - `ajax_confirm_resos_match()` (execute)
   - `ajax_preview_resos_match()` (preview)

**See**: `API_PREVIEW_PARITY.md` for detailed requirements

---

## Notes

- **Line numbers** are approximate and may change with edits
- **Dependencies** should be checked when modifying functions
- **Always update** this reference when adding/modifying functions
- **Version constants** must be updated when modifying JS/CSS

---

*Last Updated: 2025-10-31*
*Reference Version: 1.0.0*