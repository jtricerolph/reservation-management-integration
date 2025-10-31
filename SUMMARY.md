# Hotel-Restaurant Integration Plugin - Summary

## What It Is
WordPress plugin that integrates **Newbook PMS** (hotel bookings) with **Resos** (restaurant bookings). It displays hotel guests by date, automatically matches them with restaurant reservations, suggests missing data, and provides bidirectional sync between systems via outgoing API requests.

## Current Status
- **Version**: 1.1.15
- **Status**: Fully functional, production-ready
- **Phase**: Phase 2 complete with enhanced dietary requirements and notes
- **Modes**: Production, Testing (with preview), Sandbox (preview-only)

---

## Key Files to Work With

### Main Plugin Files
```
hotel-admin.php              - Main plugin file (monolithic structure)
assets/style.css            - Styles for UI and components
assets/staying-today.js     - JavaScript for UI logic and API calls
```

### Essential Documentation
```
CHANGELOG.md                - Version history and changes
summary.md                  - This file - quick overview
API_PREVIEW_PARITY.md      - Guidelines for API preview/execute parity
RESOS_STATUS_VALUES.md     - Resos booking status reference
```

---

## Code Structure

### Main Class
`Hotel_Booking_Table`

### Core Functions

**PHP (hotel-admin.php)**:
- `match_resos_to_hotel_booking()` - Matching algorithm
- `prepare_comparison_data()` - Generates suggestions
- `ajax_create_resos_booking()` - Creates new Resos bookings (POST)
- `ajax_confirm_resos_match()` - Updates existing Resos bookings (PUT)
- `ajax_preview_resos_create()` - Preview create request transformation
- `ajax_preview_resos_match()` - Preview update request transformation
- `ajax_get_dietary_choices()` - Fetches dietary options from Resos custom fields
- `format_phone_for_resos()` - International phone formatting

**JavaScript (staying-today.js)**:
- `buildComparisonRow()` - Comparison UI builder
- `buildCreateBookingSection()` - Create booking UI with gantt chart
- `selectTimeSlot()` - Time selection with gantt sight line
- `createBookingWithSelectedTime()` - Execute booking creation
- `setupNotificationAutoCheck()` - Auto-check SMS/Email checkboxes
- `fetchDietaryChoices()` - Loads dietary choices from Resos on page load
- `populateDietaryCheckboxes()` - Dynamically generates dietary checkboxes

### Key Features
1. **Incoming Data**: Auto-matching via booking ID, name, phone, email
2. **Outgoing Data**: Create and update Resos bookings from hotel data
3. **Confidence Scoring**: Primary/Suggested match classification
4. **Smart Suggestions**: Fills missing Resos data from hotel info
5. **Package Detection**: Identifies DBB/package via inventory items
6. **Dynamic Dietary Requirements**: Automatically syncs with Resos custom field configuration
7. **Booking Notes**: Restaurant notes added via separate API endpoint
8. **Visual Tools**: Comparison tables, gantt charts, time selection UI
9. **Testing Modes**: Production, Testing (preview+confirm), Sandbox (preview-only)
10. **API Parity**: Preview matches exact request body before execution
11. **Version Display**: Plugin and JavaScript versions shown in page footer

---

## APIs Used

### Newbook PMS REST API (Incoming)
- **Endpoint**: `https://api.{region}.newbook.cloud/rest/v1/`
- **Auth**: Basic auth (username + password + API key)
- **Methods**: GET
- **Retrieves**: Bookings, rooms, guest details, inventory items, tariffs

### Resos API (Bidirectional)
- **Endpoint**: `https://api.resos.com/v1/`
- **Auth**: API key in headers
- **Methods**:
  - GET: Restaurant bookings, custom fields, opening hours, available times
  - POST: Create new bookings, add restaurant notes
  - PUT: Update existing bookings
- **Features**:
  - Guest data (name, phone, email)
  - Custom fields (Hotel Guest, Booking #, DBB, Dietary Requirements)
  - Dynamic dietary requirements from custom field configuration
  - Restaurant notes (POST to `/bookings/{id}/restaurantNote`)
  - Opening hours and time slots
  - Status management
  - Notification preferences (SMS/Email)

---

## UI Highlights

- **Green checkmark (✓)**: Confirmed matches (Booking # match)
- **Amber arrow (▶)**: Suggested matches (expandable comparison)
- **Blue circle (Updates)**: Confirmed matches with available updates
- **Green button (Create)**: No restaurant match - create new booking
- **4-column comparison**: Field | Newbook Data | Resos Data | Suggested Updates
- **Checkboxes**: User selects which suggested updates to apply
- **Gantt Chart**: Visual timeline of restaurant bookings for the day
- **Time Selection**: Interactive time slot buttons with availability
- **Collapsible Sections**: Allergies/dietary requirements, booking notes, time periods
- **Dynamic Forms**: Dietary checkboxes auto-populated from Resos configuration
- **Version Info**: Plugin and JS versions displayed in footer
- **Shortcode**: `[hotel-table-bookings-by-date]`

---

## API Modes (Settings)

### Production Mode
- Direct API execution
- No confirmation dialogs
- Immediate booking creation/updates

### Testing Mode
- Shows preview dialogue before API call
- Displays exact transformed request body
- "Confirm & Execute" or "Cancel" options
- API call made after user confirmation

### Sandbox Mode
- Preview-only, no API execution
- Shows transformed request body
- "Close Preview" button only
- Safe for testing without affecting live data

**Mode Banner**: Orange gradient banner appears at top of page in Testing/Sandbox modes

---

## Development Workflow

1. Make changes to `hotel-admin.php`, `assets/style.css`, or `assets/staying-today.js`
2. Increment version number in plugin header for major releases
3. Test in Sandbox mode, then Testing mode
4. Document changes in `CHANGELOG.md`
5. Create backup in `BACKUP/` folder before major changes
6. Clear browser cache (Ctrl+F5) to load new assets

---

## Completed Features

✅ **Phase 1**: Display and matching
- Auto-matching algorithm
- Visual comparison tables
- Confidence scoring

✅ **Phase 2**: Outgoing API integration
- Create new Resos bookings (POST)
- Update existing bookings (PUT)
- Testing and Sandbox modes
- Preview/execute parity system
- Phone number formatting
- Opening hours integration
- Time slot selection with gantt visualization

✅ **Phase 2 Enhancements** (v1.1.15):
- Dynamic dietary requirements (synced with Resos configuration)
- Restaurant booking notes functionality
- Version management and display
- Code cleanup and optimization
- Improved UI consistency

---

## Configuration Requirements

**WordPress Admin > Settings > Hotel Booking Table**:
```
Newbook PMS Settings:
- API Username
- API Password
- API Key
- API Region (e.g., 'au', 'uk', 'us')
- Hotel ID

Resos Settings:
- API Key

Package Detection:
- Inventory Item Name (e.g., "Dinner Allocation", "DBB")

API Mode:
- Production / Testing / Sandbox
```

---

## Common Code Locations

**hotel-admin.php**:
- Booking matching logic: ~line 2850 (`match_resos_to_hotel_booking()`)
- Suggestion algorithm: ~line 2505 (`prepare_comparison_data()`)
- Create booking API: ~line 1936 (`ajax_create_resos_booking()`)
- Update booking API: ~line 1488 (`ajax_confirm_resos_match()`)
- Preview functions: ~line 2235 (create), ~line 1951 (update)
- Phone formatting: ~line 2444 (`format_phone_for_resos()`)

**staying-today.js**:
- Comparison row builder: ~line 1252 (`buildComparisonRow()`)
- Create booking UI: ~line 1533 (`buildCreateBookingSection()`)
- Time selection: ~line 687 (`selectTimeSlot()`)
- Booking execution: ~line 758 (`createBookingWithSelectedTime()`)
- Gantt chart builder: ~line 1687 (`buildGanttChart()`)
- Time slot builder: ~line 2089 (`buildTimeSlots()`)

**style.css**:
- Primary color (purple): `#667eea`
- Amber/warning: `#f59e0b`
- Success/green: `#28a745`, `#52c47a`
- Gantt chart: ~line 815
- Comparison tables: ~line 715
- Time slot buttons: ~line 1326
- API mode banners: ~line 12

---

**Version 1.1.15** - Enhanced with dynamic dietary requirements, booking notes, and improved version management.