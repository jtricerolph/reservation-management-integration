# Hotel-Admin Plugin - Comprehensive System Summary
**Version:** 1.1.15
**Last Updated:** October 31, 2025
**Status:** Production Active

---

## Executive Overview

The Hotel-Admin plugin is a WordPress integration system that bridges **Newbook PMS** (Property Management System) and **Resos** (Restaurant Booking System). It provides a unified interface for hotel staff to view guest bookings, match them with restaurant reservations, and manage dining bookings directly from WordPress.

### Core Purpose
- Display hotel guests staying on any given date
- Automatically match hotel guests with their restaurant bookings
- Identify guests without restaurant reservations
- Provide interface to create new restaurant bookings
- Visualize restaurant capacity and bookings via Gantt chart

---

## System Architecture

### Technology Stack
- **Backend:** PHP 7.4+ (WordPress plugin)
- **Frontend:** Vanilla JavaScript (ES5 compatible)
- **Styling:** Custom CSS with Material Symbols Outlined icons
- **APIs:** RESTful integration with Newbook and Resos
- **Database:** WordPress transient cache (1-hour TTL for opening hours)

### File Structure
```
hotel-admin/
├── hotel-admin.php           (2,690 lines) - Main plugin file
├── assets/
│   ├── style.css            (1,200+ lines) - All styling
│   ├── staying-today.js     (1,700+ lines) - All JavaScript
│   └── SYSTEM_SUMMARY.md    (this file)
├── CHANGELOG.md             - Version history (documented to v1.0.67)
├── changelog.md             - Additional changelog notes
├── readme.md                - Basic plugin info
├── summary.md               - Import summary for VS Code
├── restructuring-plan.md    - Future MVC refactoring plan
└── page-layout-summary.md   - Page layout documentation
```

---

## Key Features & Functionality

### 1. "Staying Today" View (Primary Interface)
**Shortcode:** `[hotel-table-bookings-by-date]`

**Features:**
- Date selector to view any date
- Room-organized display (grouped by floor)
- Hotel booking details per room
- Restaurant booking status with visual indicators
- Multi-night stay tracking (e.g., "Night 2 of 3")
- Group booking handling (separated section at bottom)
- Expandable comparison tooltips
- Create booking interface

**Visual Indicators:**
- ✓ **Green checkmark**: Confirmed match (booking ID match)
- ▶ **Amber arrow**: Suggested match (name/date fuzzy match)
- 🔵 **Blue "Check Updates"**: Confirmed match with available updates
- 🟢 **Green "Create Booking"**: No restaurant match, can create booking
- Empty cell: No booking, no action available

### 2. Matching Engine
**Algorithm Priority:**
1. **Booking ID Match** (Confirmed)
   - Checks Newbook booking ID against Resos custom field
   - 100% confidence score

2. **Fuzzy Match** (Suggested)
   - Surname extraction and normalization
   - Date matching (must be on same date)
   - Party size comparison
   - Confidence scoring (weighted algorithm)

**Normalization:**
- Strips accents/diacritics
- Converts to lowercase
- Removes special characters
- Phone number normalization (removes spaces, dashes, country codes)

### 3. Comparison System
**Data Points Compared:**
- Guest Name (hotel vs restaurant)
- Party Size (occupancy vs covers)
- Phone Number
- Email Address
- Notes/Comments
- Special Requests
- Booking Reference

**Suggestion Types:**
- **Missing Data**: Restaurant booking missing info that hotel has
- **Conflicting Data**: Different values between systems
- **Additional Data**: Extra info in one system

**User Interaction:**
- Expandable comparison rows
- Checkbox selection for updates (UI ready, API pending)
- Color-coded cells (green = matched, amber = suggested)
- Notes display (green boxes = guest messages, blue boxes = internal notes)

### 4. Create Booking Interface

**Components:**
a) **Date & Party Size Selector**
   - Pre-filled from hotel booking data
   - Adjustable before availability check

b) **Opening Hours Display**
   - Dynamic sections based on Resos opening hours
   - Collapsible time period sections (e.g., Lunch, Dinner)
   - "Expand All" button to view all periods at once

c) **Time Slot Selection**
   - Buttons generated from Resos intervals (typically 15-min)
   - Color-coded availability:
     - Grey background: Unavailable/full
     - Green hover: Available
   - Clicking slot performs real-time availability check

d) **Gantt Chart Visualization**
   - Timeline from restaurant opening to closing
   - Horizontal bars representing existing bookings
   - Bar features:
     - Party size badge (circular, left side)
     - Guest name and room number
     - Color-coded by status
     - Sorted by start time (earliest at top)
   - Closed periods shown as grey backgrounds
   - 15-minute interval gridlines (dashed)

e) **Booking Details Form** (Future - API pending)
   - Guest information (pre-filled)
   - Contact details
   - Special requests
   - Notes

**Data Flow:**
```
User clicks "Create Booking"
→ buildCreateBookingRow() generates interface
→ Populates opening hours dropdown (from server data)
→ buildTimeSlots() generates time buttons
→ User selects date/party size
→ fetchAvailableTimes() AJAX call to Resos
→ buildGanttChart() visualizes bookings
→ User selects time slot
→ (Future) Submit booking via Resos API
```

### 5. Gantt Chart System

**Purpose:** Visual representation of restaurant bookings for capacity planning

**Data Sources:**
- **All Resos bookings** for selected date (not just matched)
- Opening hours from Resos API
- Special events (closures, private bookings)
- Time restrictions (last booking times)

**Visual Elements:**
- **Timeline:** Horizontal axis showing time (18:00-22:00 typical)
- **Booking Bars:**
  - Width: 2-hour duration (standard booking length)
  - Height: Fixed (not variable)
  - Position: Vertical stacking, sorted by start time
  - Capped bars: Sharp corners when extending to closing time
- **Party Size Badge:** Circular badge on left side of bar (24px diameter)
- **Room Identifier:**
  - Hotel guests: Shows room number only (e.g., "202")
  - Non-residents: Shows "Non-Resident"
- **Tooltips:** Hover shows full details (name, party size, room, tables, notes)
- **Closed Periods:** Grey background for non-operating hours
- **Interval Lines:** Dashed vertical lines every 15 minutes

**Recent Changes (v1.0.67):**
- Room display changed from "Room 202" to just "202"
- Non-residents show only name, no room identifier
- Fixed bar overflow with `overflow: hidden`
- Capped bars get sharp right corners (visual indicator)
- Time header shows 18:00-21:30 (not 22:00)

---

## API Integration

### Newbook PMS API
**Base URL:** `https://api.newbook.cloud/rest/`
**Authentication:** Basic Auth (Username + Password + API Key in headers)

**Endpoints Used:**
- `GET /property/bookings` - Fetch hotel bookings
- `GET /property/rooms` - Fetch room list
- `GET /booking/{id}/details` - Fetch booking details
- `GET /group/{id}` - Fetch group booking details (disabled - 401 errors)

**Key Data Retrieved:**
- Booking ID, confirmation number
- Guest name (first, last)
- Arrival/departure dates
- Room number
- Occupancy (adults, children)
- Status (confirmed, unconfirmed, cancelled, etc.)
- Custom fields (dinner allocation, dietary requirements)
- Inventory items (package detection)
- Group information
- Contact details (phone, email)

**Caching:** None (real-time data)

### Resos Restaurant API
**Base URL:** `https://api.getresos.com/v1/`
**Authentication:** API Key in headers

**Endpoints Used:**
- `GET /bookings` - Fetch restaurant bookings by date
- `GET /available-dates` - Check online booking availability
- `GET /opening-hours` - Fetch operating hours (cached 1 hour)
- `POST /check-availability` - Real-time slot availability (future)
- `POST /bookings` - Create new booking (future)
- `PUT /bookings/{id}` - Update booking (future)

**Key Data Retrieved:**
- Booking ID (`_id` MongoDB format)
- Guest name (`guest.name` nested field)
- Date and time
- Party size (`covers`)
- Table assignments (array with area names)
- Status (confirmed, pending, cancelled)
- Custom fields (Newbook booking reference)
- Notes:
  - `comments` array (guest messages, filtered by role !== 'system')
  - `restaurantNotes` array (internal staff notes)
- Special events (closures, private functions)

**Caching:**
- Opening hours: 1 hour (WordPress transient)
- Bookings: None (real-time)

**Recent Change:** Opening hours cache reduced from 24 hours to 1 hour for faster updates when Resos settings change

---

## Data Flow & Processing

### Page Load Sequence
1. WordPress renders page with shortcode
2. PHP fetches date from query param or defaults to today
3. Parallel API calls:
   - Newbook: Get bookings for date range
   - Newbook: Get room list
   - Resos: Get restaurant bookings for date
   - Resos: Get opening hours (cached)
4. Matching engine processes all bookings
5. Room organization (floor grouping, sorting)
6. Group booking separation
7. Comparison data preparation for matched bookings
8. HTML rendering with embedded data
9. JavaScript initialization:
   - Parse restaurant bookings JSON
   - Setup event listeners
   - Initialize tooltips

### Matching Process
```php
foreach ($restaurant_bookings as $resos_booking) {
    // 1. Check for booking ID match
    if (resos_booking has Newbook ID in custom field) {
        → Mark as CONFIRMED match
        → Store match relationship
        → Continue to next
    }

    // 2. Try fuzzy matching
    foreach ($hotel_bookings as $hotel_booking) {
        // Extract surnames
        $hotel_surname = extract_surname($hotel_booking['name']);
        $resos_surname = extract_surname($resos_booking['name']);

        // Normalize both
        $hotel_norm = normalize_for_matching($hotel_surname);
        $resos_norm = normalize_for_matching($resos_surname);

        // Check match criteria
        if ($hotel_norm === $resos_norm
            AND dates match
            AND party sizes similar) {
            → Calculate confidence score
            → Store as SUGGESTED match
            → Break (one match per booking)
        }
    }

    // 3. No match found
    if (no match) {
        → Mark as NON-RESIDENT
        → Display in Gantt with no room identifier
    }
}
```

### Comparison Data Preparation
```php
function prepare_comparison_data($hotel, $resos, $date) {
    $comparison = [];

    // Compare each field
    $fields = ['name', 'party_size', 'phone', 'email', 'notes'];

    foreach ($fields as $field) {
        $hotel_value = extract_field($hotel, $field);
        $resos_value = extract_field($resos, $field);

        $comparison[$field] = [
            'hotel' => $hotel_value,
            'resos' => $resos_value,
            'match' => ($hotel_value == $resos_value),
            'suggestion' => determine_suggestion($hotel_value, $resos_value)
        ];
    }

    return $comparison;
}
```

---

## UI Components & Styling

### Color Palette
```css
/* Primary Brand Colors */
--color-primary-blue: #667eea;        /* Opening hours sections, expand button */
--color-primary-hover: #5568d3;       /* Hover states */
--color-primary-active: #4557c2;      /* Active/pressed states */

/* Match Status Colors */
--color-confirmed: #d4edda;           /* Green - confirmed match background */
--color-suggested: #fff3cd;           /* Amber - suggested match background */
--color-confirmed-checkmark: #28a745; /* Green checkmark icon */
--color-suggested-arrow: #f59e0b;     /* Amber arrow icon */

/* Booking Status Colors */
--color-status-confirmed: #28a745;    /* Green */
--color-status-unconfirmed: #ffc107;  /* Amber */
--color-status-cancelled: #dc3545;    /* Red */
--color-status-quote: #6f42c1;        /* Purple */
--color-status-arrived: #007bff;      /* Blue */
--color-status-departed: #6c757d;     /* Grey */

/* UI Element Colors */
--color-border: #dee2e6;              /* Table borders, dividers */
--color-background-grey: #f8f9fa;     /* Section backgrounds */
--color-group-highlight: #e7f3ff;     /* Group section background */

/* Time Slot Colors */
--color-time-unavailable: #e9ecef;    /* Unavailable slot background */
--color-time-hover: #1e7e34;          /* Available slot hover (green) */

/* Note Box Colors */
--color-note-guest: #d4edda;          /* Green - guest message background */
--color-note-internal: #d1ecf1;       /* Blue - internal note background */
```

### Typography
- **Font Family:** System font stack (San Francisco, Segoe UI, Roboto)
- **Base Size:** 14px
- **Headings:** 600 weight, uppercase section headers
- **Guest Names:** 14px, 600 weight, #2c3e50
- **Icons:** Material Symbols Outlined, 18-20px

### Layout Structure
```
.booking-table-wrapper
  └── .booking-table-header (date selector)
  └── .booking-table (main table)
      ├── thead (column headers)
      └── tbody
          ├── .room-row (main rows)
          │   ├── Room Number cell
          │   ├── Guest Info cell
          │   ├── Night Status cell
          │   ├── Restaurant Booking cell (expandable)
          │   └── Actions cell
          ├── .comparison-row (expandable, inserted dynamically)
          │   └── .comparison-wrapper
          │       ├── .comparison-header
          │       ├── .comparison-table (4-column)
          │       ├── .gantt-chart-container (if create booking)
          │       └── .comparison-actions (buttons)
          └── .group-section (separated at bottom)
```

### Key CSS Classes

**Table Structure:**
- `.booking-table` - Main table
- `.room-row` - Individual room rows
- `.comparison-row` - Expandable comparison (colspan 6)
- `.group-section` - Separated group bookings area

**Match Indicators:**
- `.match-confirmed` - Green background cell
- `.match-suggested` - Amber background cell
- `.restaurant-booking` - Clickable match status text

**Comparison View:**
- `.comparison-wrapper` - Slide-out container with shadow
- `.comparison-table` - 4-column data comparison
- `.suggestion-cell` - Amber cells with checkboxes
- `.matched-field` - Green highlighted matching fields

**Gantt Chart:**
- `.gantt-chart-container` - Full chart wrapper
- `.gantt-timeline` - Timeline with overflow hidden
- `.gantt-booking-bar` - Individual booking bars
- `.gantt-party-size` - Circular badge (24px)
- `.gantt-bar-capped` - Sharp corners for end-of-day bookings
- `.gantt-closed-time` - Grey background for closed hours

**Time Slots:**
- `.time-slots-section-header` - Collapsible period headers (blue)
- `.time-slots-section` - Horizontal flex container
- `.time-slot-btn` - Individual time buttons
- `.time-slot-unavailable` - Greyed out slots
- `.btn-expand-all` - Expand all button (blue)

**Buttons:**
- `.btn-create` - Green create booking button
- `.btn-check-match` - Amber check match button
- `.btn-check-updates` - Blue check updates button
- `.btn-expand-all` - Blue expand all button (v1.0.131 update)
- `.btn-close-comparison` - Grey close button

---

## JavaScript Architecture

### Global Namespace
```javascript
window.expandedRows = {};  // Tracks which comparisons are open
window.hotelBookingAjax    // WordPress localized data (AJAX URL, nonce, etc.)
```

### Core Functions

**1. Comparison Management**
```javascript
toggleComparisonRow(uniqueId, roomId, matchType)
  → Opens/closes comparison rows
  → Ensures only one comparison open at a time
  → Parses JSON data from data-comparison attribute
  → Builds and inserts comparison HTML

closeComparisonRow(uniqueId)
  → Removes comparison row from DOM
  → Removes CSS classes from parent row
  → Cleans up expandedRows tracking

buildComparisonRow(data, uniqueId, roomId, matchType)
  → Generates HTML for comparison table
  → Includes checkboxes for suggested updates
  → Different button labels for confirmed vs suggested
```

**2. Create Booking Interface**
```javascript
toggleCreateBookingRow(roomNumber, bookingDate, partySize, buttonElement)
  → Opens create booking interface
  → Parses guest data from button
  → Calls buildCreateBookingHtml()
  → Auto-fetches available times on load

buildCreateBookingHtml(roomNumber, bookingDate, guestData)
  → Generates complete booking form HTML
  → Includes date selector, party size, opening hours dropdown
  → Creates time slots container (populated by AJAX)
  → Creates Gantt chart container

fetchAvailableTimes(roomNumber, bookingDate)
  → AJAX call to get availability data
  → Fetches: available times, opening hours, special events, bookings
  → Calls buildTimeSlots() with results
```

**3. Time Slot System**
```javascript
buildTimeSlots(roomNumber, data)
  → Parses opening hours array (multiple periods per day)
  → Creates collapsible sections per period (Lunch, Dinner, etc.)
  → Generates time slot buttons based on interval from API
  → Applies unavailable styling to full slots
  → Attaches click handlers
  → Calls buildGanttChart() with booking data

expandAllTimeSections()
  → Expands all collapsible time period sections
  → Toggles button text "Expand All" ↔ "Collapse All"
  → Shows all available times at once
```

**4. Gantt Chart Rendering**
```javascript
buildGanttChart(bookings, openingHours, specialEvents, availableTimes, onlineBookingAvailable)
  → Determines time range from opening hours
  → Calculates total minutes (e.g., 18:00-22:00 = 240 mins)
  → Sorts bookings by start time
  → Builds closed time blocks (grey backgrounds)
  → Builds time header (half-hourly labels)
  → Builds interval lines (15-min dashed lines)
  → Loops through bookings and creates bars:
      - Calculates left position percentage
      - Calculates width (caps at closing time)
      - Adds "gantt-bar-capped" class if extends to close
      - Formats room identifier (number only or "Non-Resident")
      - Adds party size badge
      - Stores tooltip data in attributes
  → Applies dynamic height to container
  → Calls setupGanttTooltips()

setupGanttTooltips()
  → Attaches mouseenter/mouseleave to all .gantt-booking-bar
  → Creates tooltip div on hover
  → Parses notes JSON and displays in color-coded boxes
  → Parses tables JSON and displays as comma-separated list
  → Positions tooltip relative to cursor
  → Removes tooltip on mouse leave
```

**5. Helper Functions**
```javascript
formatTime(timeString)
  → Converts "18:00:00" to "18:00" or "6:00 PM" based on needs

parseTime(timeString)
  → Converts time string to minutes from midnight
  → Used for Gantt calculations

getWeekdayName(dateString)
  → Returns day name (Monday, Tuesday, etc.)
```

### Event Handling
- **Click Events:** Inline onclick attributes (for compatibility)
- **AJAX Calls:** Native fetch() API with WordPress nonce
- **Form Submission:** Prevented (future API integration)
- **Collapsible Sections:** Click toggles .expanded class

### Data Attributes
Used for passing data from PHP to JavaScript:
```html
data-unique-id="room123-date20251027"
data-comparison='{"hotel":{...},"resos":{...}}'
data-guest-info='{"name":"John Smith","room":"202",...}'
data-name="John Smith"
data-room="202"
data-time="18:00"
data-people="4"
data-notes='[{"type":"guest","content":"..."}]'
data-tables='["Table 9 (Top Section)"]'
```

---

## Configuration & Settings

### WordPress Admin Settings Page
**Location:** Settings → Hotel Booking Table

**Required Fields:**
1. **Newbook API Username** - API credentials username
2. **Newbook API Password** - API credentials password
3. **Newbook API Key** - API key (passed as X-API-KEY header)
4. **API Region** - Region code (e.g., 'au' for Australia)
5. **Hotel ID** - Property ID in Newbook system
6. **Resos API Key** - Restaurant system API key
7. **Package Inventory Name** - Name of dinner package item (e.g., "Dinner Allocation")

**Storage:** WordPress options table
**Retrieval:** `get_option('hotel_booking_api_username')`, etc.

### Shortcode Usage
```
[hotel-table-bookings-by-date]
```
**Query Parameters:**
- `?date=YYYY-MM-DD` - View specific date (default: today)

**Example:**
```
https://yoursite.com/bookings/?date=2025-10-28
```

---

## Known Issues & Limitations

### Current Limitations
1. **Group Booking API Disabled**
   - `get_group_details()` returns null due to 401 errors
   - Newbook API credentials lack group access permissions
   - Workaround: Group data stored in individual booking records
   - Location: Line ~336 in hotel-admin.php

2. **Booking Creation Not Implemented**
   - UI is complete and functional
   - API integration pending
   - Checkboxes ready for update selection
   - Future: Will POST to Resos API to create/update bookings

3. **No Booking Cancellation**
   - Can view cancelled hotel bookings
   - Cannot cancel corresponding restaurant bookings
   - Future feature for cleanup workflow

4. **Single View Only**
   - Only "Staying Today" view implemented
   - Future views planned (see restructuring-plan.md):
     - Bookings Placed (by placement date)
     - Bookings Cancelled (cleanup view)
     - Bookings by Arrival (forward-looking)

5. **Performance Considerations**
   - No pagination (loads all bookings for date range)
   - API calls not parallelized (sequential)
   - No request debouncing on availability checks

### Known Bugs
None currently documented. Previous issues resolved:
- ✅ Gantt bar overflow (fixed v1.0.67)
- ✅ WordPress encoding && operators (fixed by external JS)
- ✅ Opening hours cache too long (reduced to 1 hour)
- ✅ Expand All button styling (fixed v1.0.131)

---

## Version History Highlights

### v1.0.131 (Current - Oct 27, 2025)
- **Changed:** Expand All button styling to match opening hours blue (#667eea)
- **Style Update:** Solid blue background with consistent hover states

### v1.0.67 (Oct 26, 2025)
- **Changed:** Room display format (just "202" not "Room 202")
- **Changed:** Non-residents show name only, no room identifier
- **Changed:** Removed 22:00 from time header display
- **Fixed:** Gantt bar overflow with overflow: hidden
- **Fixed:** Capped bar styling (sharp corners at closing time)

### v1.0.66 (Oct 26, 2025)
- **Added:** Gantt chart with tooltips, notes, and table info
- **Added:** Dynamic height based on booking count
- **Added:** 15-minute interval gridlines
- **Added:** Half-hourly time headers
- **Added:** Expandable comparisons for confirmed matches
- **Added:** Smart action buttons (Create/Check Match/Check Updates)
- **Added:** Material Symbols Outlined icons
- **Fixed:** JavaScript syntax error (moved to external file)
- **Fixed:** Gantt height calculation
- **Fixed:** Vacant room actions

### v1.0.65 (Oct 25, 2025)
- **Added:** Checkboxes for selective update application
- **Added:** Version number in footer
- **Changed:** Enhanced suggestion column styling (amber)

### v1.0.64 (Oct 24, 2025)
- Stable production baseline
- Full matching functionality operational

---

## Future Development Roadmap

### Phase 1: Current State (Complete)
✅ Display hotel bookings by date
✅ Match with restaurant bookings
✅ Comparison tooltips with suggestions
✅ Create booking UI with Gantt chart
✅ Opening hours integration
✅ Group booking handling

### Phase 2: API Integration (Next)
- [ ] Implement booking creation via Resos API
- [ ] Implement booking updates via Resos API
- [ ] Handle API errors gracefully
- [ ] Add confirmation/success messages
- [ ] Update UI after successful creation

### Phase 3: Additional Views (Planned)
- [ ] Bookings Placed view (by placement date)
- [ ] Bookings Cancelled view (cleanup workflow)
- [ ] Bookings by Arrival view (forward-looking)
- [ ] See restructuring-plan.md for full MVC architecture

### Phase 4: Automation (Planned)
- [ ] Email alerts for cancelled bookings (daily digest)
- [ ] Package guest checker (ensure all have tables)
- [ ] Guest marketing automation (reminder emails)
- [ ] Feedback system (opt-out for dining)

### Phase 5: Optimization (Future)
- [ ] Implement pagination for large datasets
- [ ] Parallelize API calls
- [ ] Add request debouncing
- [ ] Implement more granular caching
- [ ] Performance profiling and optimization

---

## Development Guidelines

### Code Style
- **PHP:** WordPress coding standards
- **JavaScript:** ES5 compatible, camelCase naming
- **CSS:** BEM-inspired naming, kebab-case
- **Comments:** DocBlock format for functions

### Version Numbering
- Format: `MAJOR.MINOR.PATCH` (currently 1.0.x)
- Increment PATCH for bug fixes and minor changes
- Increment MINOR for new features
- Increment MAJOR for breaking changes or major refactors
- Update in 4 locations:
  1. Plugin header comment (line 6)
  2. CSS enqueue version (line 323)
  3. JS enqueue version (line 337)
  4. Footer display version (line 2613)

### Testing Checklist
Before deploying changes:
- [ ] Test on staging environment first
- [ ] Verify date selector works
- [ ] Test match indicators display correctly
- [ ] Verify expandable comparisons open/close
- [ ] Test create booking interface loads
- [ ] Verify Gantt chart renders correctly
- [ ] Check time slots populate
- [ ] Test with different party sizes
- [ ] Verify group bookings appear in separate section
- [ ] Check responsive design (mobile/tablet)
- [ ] Clear WordPress transients/cache
- [ ] Increment version number
- [ ] Update CHANGELOG.md

### Debugging
**PHP Errors:**
```bash
# View WordPress debug log
tail -f /wp-content/debug.log

# Or Docker logs
docker logs container-name
```

**JavaScript Errors:**
- Open browser DevTools Console (F12)
- Check for error messages
- Verify AJAX responses in Network tab

**Common Issues:**
- **Blank page:** PHP fatal error, check debug.log
- **Comparison won't open:** Check data-comparison JSON validity
- **Gantt not loading:** Check AJAX response, verify bookings data
- **Time slots empty:** Check opening hours API response
- **Styles not updating:** Clear browser cache, verify version number incremented

---

## API Response Examples

### Newbook Booking Response (Simplified)
```json
{
  "bookingid": 12345,
  "confirmNo": "ABC123",
  "firstName": "John",
  "lastName": "Smith",
  "arrivalDate": "2025-10-28",
  "departureDate": "2025-10-30",
  "roomNo": "202",
  "adults": 2,
  "children": 0,
  "status": "Confirmed",
  "email": "john@example.com",
  "phone": "+61 412 345 678",
  "customFields": [
    {"name": "DinnerAllocation", "value": "Yes"}
  ],
  "inventoryItems": [
    {"name": "Dinner Allocation", "quantity": 2}
  ]
}
```

### Resos Booking Response (Simplified)
```json
{
  "_id": "507f1f77bcf86cd799439011",
  "guest": {
    "name": "John Smith"
  },
  "date": "2025-10-28",
  "time": "19:00",
  "covers": 2,
  "status": "confirmed",
  "tables": [
    {"name": "9", "area": {"name": "Top Section"}}
  ],
  "comments": [
    {
      "role": "user",
      "comment": "Window table please",
      "createdAt": "2025-10-20T10:30:00Z"
    }
  ],
  "restaurantNotes": [
    {
      "restaurantNote": "VIP guest, ensure best table",
      "createdAt": "2025-10-20T11:00:00Z"
    }
  ],
  "customFields": [
    {"name": "NewbookBookingID", "value": "12345"}
  ]
}
```

### Resos Opening Hours Response
```json
{
  "openingHours": [
    {
      "title": "Dinner Service",
      "start": "18:00:00",
      "end": "22:00:00",
      "period": {
        "interval": 15,
        "duration": 120,
        "lastBooking": "20:00:00"
      }
    }
  ],
  "specialEvents": [
    {
      "title": "Christmas Closure",
      "date": "2025-12-25",
      "type": "closure"
    }
  ]
}
```

---

## Security Considerations

### API Key Protection
- ✅ API keys stored in WordPress options (database)
- ✅ Never exposed in JavaScript or HTML
- ✅ All API calls proxied through PHP
- ✅ AJAX requests use WordPress nonces

### Input Validation
- ✅ Date parameters sanitized with `sanitize_text_field()`
- ✅ Numeric values validated with `absint()`
- ✅ SQL injection prevented (WordPress handles DB queries)
- ✅ XSS prevented with `esc_html()`, `esc_attr()`, etc.

### Authentication
- WordPress capability checks for admin settings
- AJAX nonce verification on all requests
- Basic auth for Newbook API
- API key auth for Resos API

### Data Privacy
- No personally identifiable information logged
- Error messages don't expose system paths
- API responses not cached long-term
- Guest data only shown to authenticated users

---

## Performance Metrics

### Page Load Time
- Initial render: ~2-3 seconds (includes 2 API calls)
- Comparison expand: Instant (pre-loaded data)
- Create booking open: ~500ms (fetches availability)
- Gantt chart render: <100ms (client-side)

### API Response Times
- Newbook bookings: 1-2 seconds
- Resos bookings: 500ms-1s
- Resos availability: 300-500ms
- Resos opening hours: <100ms (cached)

### Optimization Opportunities
1. Parallelize API calls (currently sequential)
2. Cache Newbook room list (rarely changes)
3. Lazy-load create booking interface
4. Implement pagination for large date ranges
5. Add loading skeletons for better UX

---

## Support & Troubleshooting

### Common User Issues

**1. "I don't see any bookings"**
- Check date selector is set correctly
- Verify API credentials in settings
- Check debug.log for API errors
- Ensure hotel ID is correct

**2. "Matches aren't showing correctly"**
- Verify Resos custom field is set correctly (NewbookBookingID)
- Check guest name formatting (surnames must match)
- Ensure dates align exactly
- Review matching algorithm in code

**3. "Create booking button doesn't work"**
- Feature is UI-only, API not yet implemented
- Button should open interface, not create booking
- Check for JavaScript errors in console

**4. "Gantt chart is empty"**
- Verify restaurant bookings exist for that date
- Check Resos API key is valid
- Ensure opening hours are configured in Resos
- Look for JavaScript errors

**5. "Time slots not appearing"**
- Check opening hours API response
- Verify date is within operating days
- Check for special events (closures)
- Ensure interval is configured in Resos

### Developer Troubleshooting

**Enable WordPress Debug Mode:**
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Check API Responses:**
```php
// Add to hotel-admin.php temporarily
error_log('Newbook Response: ' . print_r($bookings_data, true));
error_log('Resos Response: ' . print_r($restaurant_bookings, true));
```

**Test APIs Directly:**
```bash
# Newbook API
curl -X GET "https://api.newbook.cloud/rest/property/bookings" \
  -H "Authorization: Basic BASE64_ENCODED_CREDS" \
  -H "X-API-KEY: your-api-key"

# Resos API
curl -X GET "https://api.getresos.com/v1/bookings?date=2025-10-28" \
  -H "Authorization: Bearer your-api-key"
```

**Clear All Caches:**
```bash
# WordPress transients
DELETE FROM wp_options WHERE option_name LIKE '_transient%';

# Browser cache
Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)
```

---

## Contact & Contribution

### File Locations for Updates
- **Main logic:** `/wp-content/plugins/hotel-admin/hotel-admin.php`
- **Styling:** `/wp-content/plugins/hotel-admin/assets/style.css`
- **JavaScript:** `/wp-content/plugins/hotel-admin/assets/staying-today.js`
- **Documentation:** `/wp-content/plugins/hotel-admin/CHANGELOG.md`

### Before Making Changes
1. Back up current files
2. Test on staging environment
3. Document changes in CHANGELOG.md
4. Increment version number
5. Test thoroughly before production deploy

---

## Glossary

**Newbook PMS:** Property Management System for hotels - manages reservations, rooms, guests, billing

**Resos:** Restaurant reservation and table management system

**Confirmed Match:** Booking reference ID found in both systems (100% match)

**Suggested Match:** High-confidence fuzzy match based on name, date, party size

**Non-Resident:** Restaurant booking without matching hotel reservation (walk-in guest)

**Gantt Chart:** Visual timeline showing bookings as horizontal bars

**Opening Hours:** Restaurant operating times with intervals and last booking times

**Special Events:** Closures, private bookings, or special operating days in Resos

**Transient:** WordPress temporary cache storage (TTL-based)

**Party Size:** Number of people in a booking (occupancy for hotel, covers for restaurant)

**Inventory Item:** Newbook feature for tracking packages/add-ons (e.g., dinner allocation)

**Custom Field:** Additional data fields in either Newbook or Resos for storing extra info

---

## Document Changelog

**v1.1.15 - October 31, 2025**
- Updated for dynamic dietary requirements system
- Added booking notes functionality documentation
- Updated version management details
- Documented new AJAX endpoints and JavaScript functions

**v1.0 - October 27, 2025**
- Initial comprehensive system summary created
- Documented all features through v1.0.131
- Included API details, architecture, and troubleshooting guide

---

## Version 1.1.15 Feature Additions

### Dynamic Dietary Requirements
The plugin now fetches dietary requirement options directly from Resos custom field configuration:
- **New Endpoint:** `ajax_get_dietary_choices()` - Fetches available choices from Resos `/customFields` endpoint
- **Frontend:** `fetchDietaryChoices()` loads choices on page load
- **UI Generation:** `populateDietaryCheckboxes()` dynamically creates checkbox options
- **Data Format:** Uses choice IDs for matching (not names) for reliability
- **Auto-Sync:** Changes in Resos settings automatically reflect in the booking form

### Booking Notes
Restaurant notes can now be added during booking creation:
- **API Endpoint:** POST to `/bookings/{id}/restaurantNote` after booking creation
- **UI:** Collapsible note section with textarea in create booking form
- **Sequential:** Note added automatically after successful booking creation
- **Bug Fix:** Corrected booking ID extraction to handle string responses from Resos

### Version Management
- **Constants:** `Hotel_Booking_Table::VERSION` and `Hotel_Booking_Table::JS_VERSION`
- **Footer Display:** "Plugin v1.1.15 | JS v1.0.75" shown at bottom of page
- **Cache Busting:** Asset versions use constants for synchronized cache invalidation

---

**End of System Summary**

For additional documentation:
- See [CHANGELOG.md](../CHANGELOG.md) for version history
- See [summary.md](../summary.md) for quick reference
- See [restructuring-plan.md](../restructuring-plan.md) for future MVC refactoring
- See [API_PREVIEW_PARITY.md](../API_PREVIEW_PARITY.md) for API development guidelines

**Status:** Production Active | **Version:** 1.1.15 | **Last Updated:** October 31, 2025
