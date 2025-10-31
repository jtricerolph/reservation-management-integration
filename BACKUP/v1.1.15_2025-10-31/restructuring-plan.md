# Hotel Restaurant Bookings - Restructuring & Modularization Plan
## Version 1.1.0 - MVC Architecture Implementation

---

## Executive Summary

This document outlines the complete restructuring of the hotel-restaurant-bookings WordPress plugin from a single-file monolithic structure (v1.0.64) to a modular MVC architecture (v1.1.0+). This restructuring will support 5+ distinct pages/views while maintaining clean, reusable code.

---

## Current State Analysis (v1.0.64)

### Existing Structure
```
hotel-booking-table/
├── hotel-booking-table_v1_0_64.php  (2,207 lines - MONOLITHIC)
└── assets/
    └── style.css  (hotel-restaurant-bookings.css)
```

### Current Functionality
- **Single View**: "Staying Today" - Room-organized bookings for selected date
- **Primary Features**:
  - Newbook API integration (hotel bookings)
  - Resos API integration (restaurant bookings)
  - Matching logic (confirmed & suggested matches)
  - Comparison tooltips
  - Group booking handling
  - Settings/admin interface

### Current Functions (Key Areas)
1. **API Communication**: `call_api()`, `get_bookings_data()`, `get_rooms_data()`, `get_restaurant_bookings_data()`
2. **Data Processing**: `prepare_comparison_data()`, `normalize_for_matching()`, `extract_surname()`
3. **Matching Logic**: `match_resos_to_hotel_booking()` (200+ lines)
4. **Display Logic**: `render_booking_table()` (500+ lines), room organization, tooltips
5. **Admin/Settings**: Admin menu, settings registration, callbacks

---

## Planned Pages Summary

### Page 1: Staying Today (Current - v1.0.64)
**Purpose**: View bookings by room for a selected date
- **Primary Sort**: By room number
- **Filters**: Date selector
- **Display**: Room-based rows with night status (1 of 3, etc.)
- **Groups**: Separated sections at bottom
- **Actions**: Create booking button

### Page 2: Bookings Placed
**Purpose**: Backlog of new incoming bookings by placement date
- **Primary Sort**: By booking placement datetime (descending - newest first)
- **Filters**: 
  - Start date (default: today)
  - Days back (1-30)
  - Match status checkboxes: ✓ Booking# match, ✓ Suggested match, ✓ None
- **Display**: 
  - Day sections (each day of placed bookings)
  - Time column (HH:MM of placement)
  - Booking ID, Guest name, Status badge
  - Sub-rows for each night of multi-night bookings (indented)
- **Groups**: Highlighted with header row + border wrapper (not separated)
- **Actions**: Create booking for each night
- **Status**: Confirmed (green), Unconfirmed (amber), Cancelled (red), Quote (purple), Arrived (blue), Departed (grey)

### Page 3: Bookings Cancelled
**Purpose**: Cancelled bookings requiring restaurant booking cleanup
- **Primary Sort**: By booking cancellation datetime (descending)
- **Filters**:
  - Start date
  - Days back
  - Match status: ✓ Booking# match (default), ✓ Suggested match (default), ☐ None
  - "See All" option if no results with defaults
- **Display**: Similar to Page 2 but cancellation-focused
- **Actions**: 
  - "Cancel Booking" (confirmed matches)
  - "Match & Cancel" (suggested matches)
  - NO create booking button
- **Future**: Automated cron job to email daily summary

### Page 4: Bookings by Arrival Date
**Purpose**: Forward-looking view of upcoming arrivals
- **Primary Sort**: By arrival date
- **Filters**:
  - Date range
  - Match status checkboxes (same as Page 2)
- **Display**: 
  - Arrival date sections
  - Main booking row per arrival
  - Sub-rows for each night (similar to Page 2)
- **Groups**: Same as Page 2 (highlighted, not separated)
- **Actions**: Create booking per night

### Page 5: Create Booking Interface (Modal/Popup)
**Purpose**: Reusable booking creation wizard
- **Used By**: Pages 1, 2, and 4
- **Features**:
  - Availability checker (Resos API)
  - Time slot restrictions validator
  - Gantt-style visualization of existing bookings
  - Horizontal timeline with booking bars
  - Bar thickness correlates to party size (2ppl=20px, 4ppl=30px, 10ppl=60px)
  - Party size indicators at bar start/end
  - Form for booking details (auto-populated from hotel data)
- **Actions**: Submit booking, Cancel/close

---

## Proposed MVC Directory Structure

```
hotel-booking-table/
│
├── hotel-booking-table.php          # Main plugin file (bootstrap only)
│
├── includes/                         # Core plugin files
│   │
│   ├── class-plugin-core.php        # Main plugin class (initialization)
│   ├── class-activator.php          # Plugin activation hooks
│   ├── class-deactivator.php        # Plugin deactivation hooks
│   │
│   ├── models/                       # DATA LAYER
│   │   ├── class-newbook-api.php    # Newbook API communication
│   │   ├── class-resos-api.php      # Resos API communication
│   │   ├── class-booking.php        # Booking data model
│   │   ├── class-room.php           # Room data model
│   │   └── class-settings.php       # Settings/options handler
│   │
│   ├── controllers/                  # LOGIC/PROCESSING LAYER
│   │   ├── class-matching-engine.php         # Matching & comparison logic
│   │   ├── class-data-processor.php          # Data normalization, transformation
│   │   ├── class-booking-organizer.php       # Sorting, grouping, organizing
│   │   ├── class-availability-checker.php    # Resos availability checks
│   │   └── class-booking-creator.php         # Booking creation logic
│   │
│   ├── views/                        # DISPLAY/RENDERING LAYER
│   │   ├── pages/                    # Full page views
│   │   │   ├── class-staying-today-view.php        # Page 1
│   │   │   ├── class-bookings-placed-view.php      # Page 2
│   │   │   ├── class-bookings-cancelled-view.php   # Page 3
│   │   │   └── class-bookings-arrival-view.php     # Page 4
│   │   │
│   │   ├── components/               # Reusable UI components
│   │   │   ├── class-table-renderer.php       # Table structure renderer
│   │   │   ├── class-row-renderer.php         # Individual row renderer
│   │   │   ├── class-tooltip-renderer.php     # Tooltip/comparison display
│   │   │   ├── class-filter-bar.php           # Date/filter controls
│   │   │   ├── class-booking-modal.php        # Create booking interface (Page 5)
│   │   │   └── class-gantt-visualizer.php     # Gantt chart for availability
│   │   │
│   │   └── admin/                    # Admin interface views
│   │       ├── class-settings-page.php        # Settings page renderer
│   │       └── class-admin-notices.php        # Admin notices
│   │
│   └── utilities/                    # HELPER FUNCTIONS
│       ├── class-date-helper.php     # Date manipulation utilities
│       ├── class-string-helper.php   # String normalization utilities
│       └── class-validator.php       # Input validation
│
├── assets/                           # FRONTEND ASSETS
│   ├── css/
│   │   ├── admin.css                # Admin-specific styles
│   │   ├── common.css               # Shared styles (colors, typography)
│   │   ├── tables.css               # Table-specific styles
│   │   ├── components.css           # Component styles (tooltips, modals)
│   │   └── pages/
│   │       ├── staying-today.css    # Page 1 specific
│   │       ├── bookings-placed.css  # Page 2 specific
│   │       ├── bookings-cancelled.css  # Page 3 specific
│   │       └── bookings-arrival.css    # Page 4 specific
│   │
│   └── js/
│       ├── admin.js                 # Admin interface JavaScript
│       ├── common.js                # Shared JavaScript utilities
│       ├── tooltips.js              # Tooltip functionality
│       ├── booking-modal.js         # Booking creation modal
│       ├── gantt-chart.js           # Gantt visualization
│       └── pages/
│           ├── staying-today.js     # Page 1 specific
│           ├── bookings-placed.js   # Page 2 specific
│           ├── bookings-cancelled.js # Page 3 specific
│           └── bookings-arrival.js   # Page 4 specific
│
├── languages/                        # Translation files
│   └── hotel-booking-table.pot
│
└── README.md                         # Documentation
```

---

## Detailed File Responsibilities

### MODELS (Data Layer)

#### `class-newbook-api.php`
**Responsibility**: All Newbook API communication
```php
class Newbook_API {
    public function __construct($credentials)
    public function call_api($action, $data)
    public function get_bookings($hotel_id, $date_from, $date_to)
    public function get_rooms($hotel_id)
    public function get_booking_details($booking_id)
    public function test_connection()
}
```

**Migrated Functions**:
- `call_api()` - Core API communication
- `get_bookings_data()` - Fetch bookings
- `get_rooms_data()` - Fetch rooms

**New Functions**:
- `get_bookings_by_placement_date()` - For Page 2
- `get_cancelled_bookings()` - For Page 3
- `get_bookings_by_arrival_date()` - For Page 4

---

#### `class-resos-api.php`
**Responsibility**: All Resos restaurant API communication
```php
class Resos_API {
    public function __construct($api_key)
    public function get_bookings($date)
    public function check_availability($date, $time, $party_size)
    public function get_time_restrictions($date)
    public function create_booking($booking_data)
    public function cancel_booking($booking_id)
    public function update_booking($booking_id, $booking_data)
}
```

**Migrated Functions**:
- `get_restaurant_bookings_data()` - Fetch Resos bookings

**New Functions**:
- `check_availability()` - For Page 5 availability checking
- `get_time_restrictions()` - For Page 5 time slot validation
- `create_booking()` - For booking creation
- `cancel_booking()` - For Page 3 cancellation

---

#### `class-booking.php`
**Responsibility**: Booking data model and manipulation
```php
class Booking {
    public $booking_id;
    public $guest_name;
    public $arrival_date;
    public $departure_date;
    public $status;
    public $room_number;
    public $group_id;
    // ... other properties
    
    public function __construct($data)
    public function get_nights()
    public function get_occupancy()
    public function is_package_booking()
    public function get_custom_field($field_name)
    public function to_array()
}
```

**Purpose**: Standardized booking object for internal use

---

#### `class-room.php`
**Responsibility**: Room data model
```php
class Room {
    public $room_number;
    public $floor;
    public $type;
    public $status;
    
    public function __construct($data)
    public function is_numeric()
    public function to_array()
}
```

---

#### `class-settings.php`
**Responsibility**: Plugin settings management
```php
class Settings {
    public static function get_newbook_credentials()
    public static function get_resos_api_key()
    public static function get_hotel_id()
    public static function get_package_inventory_name()
    public static function register_settings()
}
```

**Migrated Functions**:
- All `register_settings()` logic
- All field callbacks
- Settings retrieval methods

---

### CONTROLLERS (Logic/Processing Layer)

#### `class-matching-engine.php`
**Responsibility**: Core matching logic between hotel and restaurant bookings
```php
class Matching_Engine {
    public function match_resos_to_hotel($resos_booking, $hotel_booking)
    public function find_suggested_matches($resos_booking, $all_hotel_bookings)
    public function calculate_match_confidence($resos, $hotel)
    public function prepare_comparison_data($hotel_booking, $resos_booking, $date)
}
```

**Migrated Functions**:
- `match_resos_to_hotel_booking()` - Core matching algorithm (200+ lines)
- `prepare_comparison_data()` - Detailed comparison preparation (250+ lines)
- All matching confidence scoring logic

**Match Types**:
1. **Booking # Match**: Direct booking reference match
2. **Suggested Match**: High-confidence fuzzy match (surname + date + occupancy)
3. **No Match**: No suitable match found

---

#### `class-data-processor.php`
**Responsibility**: Data transformation and normalization
```php
class Data_Processor {
    public function normalize_string($string)
    public function normalize_phone($phone)
    public function extract_surname($full_name)
    public function parse_date($date_string, $format)
    public function calculate_night_status($input_date, $arrival, $departure)
}
```

**Migrated Functions**:
- `normalize_for_matching()` - String normalization
- `normalize_phone_for_matching()` - Phone number normalization
- `extract_surname()` - Surname extraction
- `get_night_status()` - Night calculation (1 of 3, etc.)

---

#### `class-booking-organizer.php`
**Responsibility**: Sorting, grouping, and organizing bookings
```php
class Booking_Organizer {
    public function organize_by_room($bookings, $rooms)
    public function organize_by_placement_date($bookings, $days_back)
    public function organize_by_cancellation_date($bookings, $days_back)
    public function organize_by_arrival_date($bookings, $date_range)
    public function group_bookings($bookings)
    public function sort_rooms($rooms)
}
```

**Migrated Functions**:
- `organize_rooms()` - Room organization with groups
- All sorting logic

**New Functions**:
- `organize_by_placement_date()` - For Page 2
- `organize_by_cancellation_date()` - For Page 3
- `organize_by_arrival_date()` - For Page 4

---

#### `class-availability-checker.php`
**Responsibility**: Restaurant availability checking for booking creation
```php
class Availability_Checker {
    private $resos_api;
    
    public function __construct($resos_api)
    public function check_time_slot($date, $time, $party_size)
    public function get_available_times($date, $party_size)
    public function get_restrictions($date)
    public function get_existing_bookings($date)
}
```

**Purpose**: Used by Page 5 (Create Booking Interface)

---

#### `class-booking-creator.php`
**Responsibility**: Booking creation and validation
```php
class Booking_Creator {
    private $resos_api;
    private $availability_checker;
    
    public function __construct($resos_api, $availability_checker)
    public function validate_booking_data($data)
    public function create_booking($hotel_booking, $booking_data)
    public function get_booking_form_data($hotel_booking)
}
```

**Purpose**: Handles the actual creation process for Page 5

---

### VIEWS (Display/Rendering Layer)

#### Page Views

##### `class-staying-today-view.php` (Page 1)
**Responsibility**: Render "Staying Today" view (current page)
```php
class Staying_Today_View {
    private $matching_engine;
    private $booking_organizer;
    private $table_renderer;
    
    public function render($hotel_id, $selected_date)
    private function render_filters()
    private function render_table($organized_data)
    private function render_room_row($room, $booking)
    private function render_group_section($group_data)
}
```

**Display Logic**:
- Room-based organization
- Group separation at bottom
- Night status badges (1 of 3, 2 of 3, etc.)
- Green confirmed matches
- Amber suggested matches
- Create booking action button

---

##### `class-bookings-placed-view.php` (Page 2)
**Responsibility**: Render "Bookings Placed" backlog view
```php
class Bookings_Placed_View {
    private $matching_engine;
    private $booking_organizer;
    private $table_renderer;
    
    public function render($start_date, $days_back, $match_filters)
    private function render_filters()
    private function render_day_section($date, $bookings)
    private function render_booking_row($booking)
    private function render_night_sub_rows($booking)
    private function render_status_badge($status)
    private function render_group_highlight($group_data)
}
```

**Display Logic**:
- Day sections (newest first within each day)
- Placement time column (HH:MM)
- Booking ID, Guest name, Status badge
- Sub-rows for each night (indented)
- Groups highlighted with header row + border (not separated)
- Match filter checkboxes

**Columns**:
1. Time (placement)
2. Booking ID
3. Guest Name + Status Badge
4. Occupancy
5. Nights (total nights badge + sub-rows for each night)
6. Restaurant Booking (per night in sub-rows)
7. Actions (per night in sub-rows)

---

##### `class-bookings-cancelled-view.php` (Page 3)
**Responsibility**: Render "Bookings Cancelled" cleanup view
```php
class Bookings_Cancelled_View {
    private $matching_engine;
    private $booking_organizer;
    private $table_renderer;
    
    public function render($start_date, $days_back, $match_filters)
    private function render_filters()
    private function render_day_section($date, $bookings)
    private function render_booking_row($booking)
    private function render_night_sub_rows($booking)
    private function render_cancel_action($match_type, $resos_booking_id)
}
```

**Display Logic**:
- Similar to Page 2 but cancellation-focused
- Cancellation datetime instead of placement datetime
- Default filters: ✓ Booking# match, ✓ Suggested match, ☐ None
- "See All" option if no results
- Action buttons:
  - "Cancel Booking" (confirmed matches)
  - "Match & Cancel" (suggested matches)
  - NO create booking button

---

##### `class-bookings-arrival-view.php` (Page 4)
**Responsibility**: Render "Bookings by Arrival Date" forward-looking view
```php
class Bookings_Arrival_View {
    private $matching_engine;
    private $booking_organizer;
    private $table_renderer;
    
    public function render($date_range, $match_filters)
    private function render_filters()
    private function render_arrival_section($date, $bookings)
    private function render_booking_row($booking)
    private function render_night_sub_rows($booking)
}
```

**Display Logic**:
- Arrival date sections
- Main booking row per arrival
- Sub-rows for each night (same as Page 2)
- Groups highlighted (same as Page 2)
- Create booking action per night

---

#### Component Views

##### `class-table-renderer.php`
**Responsibility**: Render table structure (reusable)
```php
class Table_Renderer {
    public function render_table_open($columns, $classes = array())
    public function render_table_header($columns)
    public function render_table_close()
    public function render_section_header($title, $columns_span)
}
```

**Purpose**: Consistent table structure across all pages

---

##### `class-row-renderer.php`
**Responsibility**: Render individual table rows (reusable)
```php
class Row_Renderer {
    public function render_booking_row($booking, $columns_config)
    public function render_sub_row($data, $indent_level, $columns_config)
    public function render_cell($content, $class, $attributes = array())
    public function render_match_status($match_data)
    public function render_actions($actions_config)
}
```

**Purpose**: Consistent row rendering with match highlighting

---

##### `class-tooltip-renderer.php`
**Responsibility**: Render comparison tooltips
```php
class Tooltip_Renderer {
    public function render_tooltip_trigger($guest_name, $tooltip_data)
    public function render_comparison_table($comparison_data)
    public function render_notes($notes)
}
```

**Migrated Logic**: Current tooltip JavaScript functionality

---

##### `class-filter-bar.php`
**Responsibility**: Render filter controls
```php
class Filter_Bar {
    public function render_date_selector($current_date, $page_slug)
    public function render_days_back_selector($current_value, $page_slug)
    public function render_match_checkboxes($current_filters, $page_slug)
    public function render_date_range_selector($start_date, $end_date, $page_slug)
}
```

**Purpose**: Consistent filter UI across pages

---

##### `class-booking-modal.php` (Page 5)
**Responsibility**: Render create booking modal/interface
```php
class Booking_Modal {
    private $availability_checker;
    private $gantt_visualizer;
    
    public function render_modal($hotel_booking_data)
    private function render_booking_form($pre_filled_data)
    private function render_availability_section($date, $party_size)
    private function render_gantt_chart($existing_bookings)
    private function render_time_slot_selector($available_times)
}
```

**Features**:
- Pre-populated form from hotel booking
- Real-time availability checking
- Time slot validation
- Gantt chart visualization

---

##### `class-gantt-visualizer.php`
**Responsibility**: Render Gantt-style booking visualization
```php
class Gantt_Visualizer {
    public function render($bookings, $date, $time_range)
    private function calculate_bar_positions($bookings)
    private function calculate_bar_thickness($party_size)
    private function render_timeline()
    private function render_booking_bar($booking, $position, $thickness)
}
```

**Display Logic**:
- Horizontal timeline (time on X-axis)
- Booking bars with variable thickness based on party size
  - 2 people: 20px height
  - 4 people: 30px height
  - 10 people: 60px height
  - Scaled proportionally, not linear
- Party size numbers at bar start/end
- Color-coded by booking status

**Example Thickness Calculation**:
```php
// Base: 20px for 2 people
// Formula: base_height + (party_size - 2) * scale_factor
// Scale factor: 5px per additional person (diminishing)
$base = 20;
$scale = 5;
$thickness = $base + (($party_size - 2) * $scale);
// Cap at reasonable maximum (e.g., 80px for 14+ people)
```

---

### UTILITIES (Helper Functions)

#### `class-date-helper.php`
```php
class Date_Helper {
    public static function parse_date($date_string, $format = 'Y-m-d')
    public static function format_date($date_object, $format = 'Y-m-d')
    public static function calculate_nights($arrival, $departure)
    public static function get_night_number($check_date, $arrival, $departure)
    public static function is_date_in_range($check_date, $start_date, $end_date)
}
```

---

#### `class-string-helper.php`
```php
class String_Helper {
    public static function normalize($string)
    public static function normalize_phone($phone)
    public static function extract_surname($full_name)
    public static function sanitize_for_display($string)
}
```

---

#### `class-validator.php`
```php
class Validator {
    public static function validate_date($date_string)
    public static function validate_booking_data($data, $required_fields)
    public static function validate_api_credentials($credentials)
    public static function sanitize_input($input, $type)
}
```

---

## CSS Structure & Style Guide

### CSS File Organization

#### `common.css` - Shared Styles
```css
/* Color Palette */
:root {
    --color-confirmed-match: #d4edda;      /* Green */
    --color-suggested-match: #fff3cd;      /* Amber */
    --color-no-match: transparent;
    --color-status-confirmed: #28a745;
    --color-status-unconfirmed: #ffc107;
    --color-status-cancelled: #dc3545;
    --color-status-quote: #6f42c1;
    --color-status-arrived: #007bff;
    --color-status-departed: #6c757d;
    --color-group-highlight: #e7f3ff;
    --color-border: #dee2e6;
}

/* Typography */
.guest-name { /* ... */ }
.booking-id { /* ... */ }
.status-badge { /* ... */ }

/* Spacing */
.table-section { /* ... */ }
.sub-row { /* ... */ }
```

#### `tables.css` - Table Styles
```css
/* Base table structure */
.hotel-booking-table { /* ... */ }
.hotel-booking-table thead { /* ... */ }
.hotel-booking-table tbody { /* ... */ }

/* Row types */
.room-row { /* ... */ }
.booking-row { /* ... */ }
.sub-row { /* ... */ }
.group-header-row { /* ... */ }

/* Cell types */
.match-confirmed { /* ... */ }
.match-suggested { /* ... */ }
.status-cell { /* ... */ }
```

#### `components.css` - Component Styles
```css
/* Tooltips */
.tooltip-trigger { /* ... */ }
.tooltip-content { /* ... */ }
.comparison-table { /* ... */ }

/* Modals */
.booking-modal { /* ... */ }
.modal-overlay { /* ... */ }

/* Filters */
.filter-bar { /* ... */ }
.date-selector { /* ... */ }

/* Gantt Chart */
.gantt-container { /* ... */ }
.gantt-timeline { /* ... */ }
.booking-bar { /* ... */ }
```

### Page-Specific Styles

#### `staying-today.css` (Page 1)
```css
/* Room organization specific styles */
.room-section { /* ... */ }
.group-section { /* ... */ }
.night-status-badge { /* ... */ }
```

#### `bookings-placed.css` (Page 2)
```css
/* Placement date specific styles */
.day-section { /* ... */ }
.placement-time { /* ... */ }
.group-highlight-wrapper { /* ... */ }
.indented-night-row { /* ... */ }
```

#### `bookings-cancelled.css` (Page 3)
```css
/* Cancellation specific styles */
.cancellation-section { /* ... */ }
.cancel-action-btn { /* ... */ }
```

#### `bookings-arrival.css` (Page 4)
```css
/* Arrival date specific styles */
.arrival-section { /* ... */ }
```

---

## Shortcode Implementation

### Shortcode Registration
```php
// In class-plugin-core.php
add_shortcode('hotel-bookings-staying-today', array($this, 'render_staying_today'));
add_shortcode('hotel-bookings-placed', array($this, 'render_bookings_placed'));
add_shortcode('hotel-bookings-cancelled', array($this, 'render_bookings_cancelled'));
add_shortcode('hotel-bookings-arrival', array($this, 'render_bookings_arrival'));
```

### Shortcode Usage
```
[hotel-bookings-staying-today hotel_id="1"]
[hotel-bookings-placed hotel_id="1" days_back="7"]
[hotel-bookings-cancelled hotel_id="1" days_back="30"]
[hotel-bookings-arrival hotel_id="1" days_forward="30"]
```

---

## Migration Strategy (v1.0.64 → v1.1.0)

### Phase 1: Core Infrastructure (Week 1)
**Goal**: Set up new structure without breaking existing functionality

**Tasks**:
1. Create new directory structure
2. Create base classes (empty shells)
3. Create `class-plugin-core.php` as new bootstrap
4. Update main plugin file to use new core class
5. Test that plugin still activates

**Deliverable**: New structure in place, old code still functional

---

### Phase 2: Extract Models (Week 2)
**Goal**: Move all API and data handling to models

**Tasks**:
1. Create `class-newbook-api.php` and migrate API functions
2. Create `class-resos-api.php` and migrate Resos functions
3. Create `class-booking.php` and `class-room.php` models
4. Create `class-settings.php` and migrate settings logic
5. Update existing code to use new models
6. Test all API calls work correctly

**Migration Order**:
- Settings first (least dependencies)
- Newbook API second
- Resos API third
- Data models last

**Deliverable**: All API communication through model classes

---

### Phase 3: Extract Controllers (Week 3)
**Goal**: Move all business logic to controllers

**Tasks**:
1. Create `class-matching-engine.php` and migrate matching logic
2. Create `class-data-processor.php` and migrate utility functions
3. Create `class-booking-organizer.php` and migrate organization logic
4. Update existing rendering code to use controllers
5. Test matching and organization work correctly

**Critical Functions to Migrate**:
- `match_resos_to_hotel_booking()` → Matching_Engine
- `prepare_comparison_data()` → Matching_Engine
- `normalize_for_matching()` → Data_Processor
- `organize_rooms()` → Booking_Organizer

**Deliverable**: All business logic in controllers

---

### Phase 4: Extract Page 1 View (Week 4)
**Goal**: Refactor existing page into new view architecture

**Tasks**:
1. Create `class-staying-today-view.php`
2. Create `class-table-renderer.php`
3. Create `class-row-renderer.php`
4. Create `class-tooltip-renderer.php`
5. Migrate existing `render_booking_table()` logic
6. Test Page 1 renders identically to v1.0.64
7. Extract and organize CSS into new structure

**Validation**: Side-by-side comparison of old vs new rendering

**Deliverable**: Page 1 fully refactored and functional

---

### Phase 5: Build Pages 2, 3, 4 (Weeks 5-7)
**Goal**: Implement new page views using established architecture

**Week 5 - Page 2 (Bookings Placed)**:
1. Add `get_bookings_by_placement_date()` to Newbook API
2. Add `organize_by_placement_date()` to Booking_Organizer
3. Create `class-bookings-placed-view.php`
4. Implement day sections and sub-rows
5. Create `bookings-placed.css`
6. Test filtering and display

**Week 6 - Page 3 (Bookings Cancelled)**:
1. Add `get_cancelled_bookings()` to Newbook API
2. Add `organize_by_cancellation_date()` to Booking_Organizer
3. Create `class-bookings-cancelled-view.php`
4. Implement cancellation actions (stub for now)
5. Create `bookings-cancelled.css`
6. Test filtering with default "See All" behavior

**Week 7 - Page 4 (Bookings by Arrival)**:
1. Add `get_bookings_by_arrival_date()` to Newbook API
2. Add `organize_by_arrival_date()` to Booking_Organizer
3. Create `class-bookings-arrival-view.php`
4. Reuse sub-row logic from Page 2
5. Create `bookings-arrival.css`
6. Test date range filtering

**Deliverable**: All 4 main pages functional

---

### Phase 6: Build Page 5 (Create Booking) (Week 8)
**Goal**: Implement booking creation interface

**Tasks**:
1. Create `class-availability-checker.php`
2. Create `class-booking-creator.php`
3. Add availability methods to Resos API
4. Create `class-booking-modal.php`
5. Create `class-gantt-visualizer.php`
6. Implement Gantt chart with variable thickness bars
7. Create modal CSS and JavaScript
8. Wire up to Pages 1, 2, and 4

**Gantt Implementation Details**:
- Calculate time slots (30-minute intervals)
- Fetch existing bookings for selected date
- Calculate bar positions based on start/end times
- Calculate bar thickness based on party size
- Add party size labels at bar ends
- Add hover tooltips with booking details

**Deliverable**: Fully functional booking creation interface

---

### Phase 7: JavaScript Refactoring (Week 9)
**Goal**: Modularize JavaScript into separate files

**Tasks**:
1. Extract tooltip code to `tooltips.js`
2. Create `booking-modal.js` for modal interactions
3. Create `gantt-chart.js` for visualization
4. Create page-specific JS files
5. Create `common.js` for shared utilities
6. Set up proper script enqueueing with dependencies
7. Test all interactive features

**JavaScript Architecture**:
```javascript
// common.js - Shared utilities
window.HotelBookings = window.HotelBookings || {};
HotelBookings.Utils = {
    formatDate: function(date) { /* ... */ },
    ajaxRequest: function(action, data, callback) { /* ... */ }
};

// tooltips.js - Tooltip functionality
HotelBookings.Tooltips = {
    init: function() { /* ... */ },
    show: function(element, data) { /* ... */ },
    hide: function() { /* ... */ }
};

// booking-modal.js - Modal interactions
HotelBookings.BookingModal = {
    open: function(bookingData) { /* ... */ },
    close: function() { /* ... */ },
    submit: function() { /* ... */ }
};
```

**Deliverable**: Clean, modular JavaScript

---

### Phase 8: Testing & Documentation (Week 10)
**Goal**: Comprehensive testing and documentation

**Tasks**:
1. Unit testing for controllers and models
2. Integration testing for full workflows
3. Cross-browser testing
4. Performance testing (large datasets)
5. Create developer documentation
6. Create user documentation
7. Create migration guide from v1.0.64

**Test Cases**:
- Matching accuracy (confirmed & suggested)
- Group booking handling
- Multi-night booking sub-rows
- Filter combinations
- Booking creation workflow
- API error handling
- Settings validation

**Deliverable**: Production-ready v1.1.0 with full documentation

---

## Code Style & Standards

### PHP Standards
```php
// Class naming: Class_Name_With_Underscores
class Booking_Organizer {
    // Method naming: snake_case
    public function organize_by_room($bookings, $rooms) {
        // Variable naming: snake_case
        $organized_data = array();
        
        // Constants: UPPERCASE_WITH_UNDERSCORES
        const MAX_BOOKINGS_PER_PAGE = 100;
        
        // Array usage: Use array() for WordPress compatibility
        $results = array(
            'bookings' => $bookings,
            'count' => count($bookings)
        );
        
        return $results;
    }
}
```

### JavaScript Standards
```javascript
// Object naming: PascalCase for constructors
var BookingModal = function() {
    // Method naming: camelCase
    this.openModal = function(data) {
        // Variable naming: camelCase
        var modalElement = document.getElementById('booking-modal');
        
        // Constants: UPPERCASE_WITH_UNDERSCORES
        var MAX_PARTY_SIZE = 20;
    };
};

// Namespace everything under HotelBookings
window.HotelBookings = window.HotelBookings || {};
HotelBookings.Modal = new BookingModal();
```

### CSS Standards
```css
/* Class naming: BEM-inspired with kebab-case */
.booking-table { /* Block */ }
.booking-table__row { /* Element */ }
.booking-table__row--highlighted { /* Modifier */ }

/* Component-specific prefix */
.hb-modal { /* hb = hotel-bookings prefix */ }
.hb-gantt-chart { }
```

### Documentation Standards
```php
/**
 * Match a restaurant booking to a hotel booking
 *
 * Performs fuzzy matching based on guest name, occupancy, and date.
 * Returns match type and confidence score.
 *
 * @since 1.1.0
 * @param array $resos_booking Restaurant booking data
 * @param array $hotel_booking Hotel booking data
 * @return array {
 *     @type string $type Match type: 'confirmed', 'suggested', or 'none'
 *     @type int $confidence Confidence score 0-100
 *     @type array $matches Array of matching field details
 * }
 */
public function match_resos_to_hotel($resos_booking, $hotel_booking) {
    // Implementation
}
```

---

## Reusability Strategy

### Key Reusable Components

#### 1. Table Rendering
**Used by**: All pages
**Files**: `class-table-renderer.php`, `class-row-renderer.php`
**Configuration**:
```php
$columns_config = array(
    'time' => array('label' => 'Time', 'class' => 'time-column', 'width' => '80px'),
    'booking_id' => array('label' => 'Booking ID', 'class' => 'booking-id-column'),
    // ... more columns
);

$table_renderer = new Table_Renderer();
$table_renderer->render_table_open($columns_config, array('staying-today-table'));
```

#### 2. Sub-Row Rendering (Multi-Night Bookings)
**Used by**: Pages 2, 3, 4
**File**: `class-row-renderer.php`
**Pattern**:
```php
// Main booking row
$row_renderer->render_booking_row($booking, $columns_config);

// Sub-rows for each night
foreach ($booking->get_nights_array() as $night) {
    $row_renderer->render_sub_row($night, 1, $columns_config);
}
```

#### 3. Match Status Display
**Used by**: All pages
**File**: `class-row-renderer.php`
**Pattern**:
```php
$match_data = array(
    'type' => 'confirmed', // or 'suggested', 'none'
    'resos_booking' => $resos_booking,
    'confidence' => 95
);
$row_renderer->render_match_status($match_data);
```

#### 4. Comparison Tooltips
**Used by**: All pages
**Files**: `class-tooltip-renderer.php`, `tooltips.js`
**Pattern**:
```php
$comparison_data = $matching_engine->prepare_comparison_data($hotel_booking, $resos_booking, $date);
$tooltip_renderer->render_tooltip_trigger($guest_name, $comparison_data);
```

#### 5. Group Highlighting
**Used by**: Pages 2, 3, 4
**File**: `class-row-renderer.php`
**Pattern**:
```php
// Different from Page 1's group separation
// Pages 2, 3, 4 use inline highlighting with wrapper
if ($booking->is_in_group()) {
    echo '<div class="group-highlight-wrapper">';
    // Render group header
    echo '<div class="group-header">' . $booking->get_group_name() . '</div>';
    // Render booking rows
    echo '</div>';
}
```

#### 6. Filter Bar
**Used by**: All pages (with variations)
**File**: `class-filter-bar.php`
**Pattern**:
```php
$filter_bar = new Filter_Bar();

// Page 1: Simple date selector
$filter_bar->render_date_selector($current_date, 'staying-today');

// Page 2: Date + Days Back + Match Checkboxes
$filter_bar->render_date_selector($current_date, 'bookings-placed');
$filter_bar->render_days_back_selector(7, 'bookings-placed');
$filter_bar->render_match_checkboxes($current_filters, 'bookings-placed');
```

#### 7. Booking Actions
**Used by**: Pages 1, 2, 4 (create), Page 3 (cancel)
**File**: `class-row-renderer.php`
**Pattern**:
```php
$actions_config = array(
    array(
        'type' => 'create_booking',
        'label' => 'Create Booking',
        'class' => 'btn-create',
        'data' => array('booking_id' => $booking->id, 'night' => $night_num)
    )
);
$row_renderer->render_actions($actions_config);
```

---

## Future Enhancements (Post v1.1.0)

### Automation Features

#### 1. Cancelled Booking Email Alerts (Page 3 Enhancement)
**Goal**: Daily automated email to reception with cancelled bookings summary
**Implementation**:
```php
// New file: includes/automation/class-cancelled-booking-checker.php
class Cancelled_Booking_Checker {
    public function run_daily_check()
    public function generate_email_report($matched_count, $suggested_count)
    public function schedule_cron_job()
}
```
**Cron Schedule**: Daily at 9:00 AM
**Email Content**:
- X confirmed matches found (link to Page 3)
- Y suggested matches found (link to Page 3)
- Date range checked
- Direct link to Page 3 with filters pre-applied

#### 2. Package Guest Table Booking Checker
**Goal**: Ensure all DBB/package guests have restaurant bookings
**Implementation**:
```php
// New file: includes/automation/class-package-booking-checker.php
class Package_Booking_Checker {
    public function check_package_guests($date_range)
    public function get_guests_without_tables($date_range)
    public function generate_missing_bookings_report()
    public function schedule_cron_job()
}
```
**Cron Schedule**: Daily at 10:00 AM
**Email Content**:
- X package guests without tables (link to list)
- Arrivals in next 7 days
- Action required

#### 3. Guest Marketing Automation
**Goal**: Automated reminder/marketing emails to guests without bookings
**Implementation**:
```php
// New file: includes/automation/class-guest-marketing.php
class Guest_Marketing {
    public function send_initial_confirmation() // At booking
    public function send_2_day_reminder()       // 2 days post-booking
    public function send_5_day_promotion()      // 5 days post-booking (with offer)
    public function send_pre_arrival_reminder() // 3 days before arrival
    public function check_opt_out_status($booking_id)
}
```

**Email Schedule**:
- **Day 0** (Booking): Confirmation with restaurant info
- **Day 2**: Gentle reminder (only if no match found)
- **Day 5**: Promotional offer (% discount, expires in X days)
- **Day -3** (Before arrival): Final reminder

**Custom Field**: `no_dinner_required` in Newbook
- Set via feedback link in emails
- Checked before sending subsequent emails
- Displayed in UI as greyed-out "✗ Guest has alternate plans"

#### 4. Feedback Link Integration
**Goal**: Allow guests to indicate they don't need restaurant booking
**Implementation**:
```php
// New endpoint: /feedback/?booking_id=123&token=abc
class Guest_Feedback_Handler {
    public function handle_feedback_submission($booking_id, $token)
    public function update_newbook_custom_field($booking_id, $field, $value)
    public function verify_token($booking_id, $token)
}
```

**UI Update**:
- Add "Alternate Plans" filter checkbox to all pages
- Display as greyed-out cell: "✗ Guest has alternate plans"
- Different from "No match" - indicates explicit opt-out

---

## Performance Considerations

### Caching Strategy
```php
// Implement transient caching for API responses
set_transient('hotel_bookings_' . $date, $bookings_data, 5 * MINUTE_IN_SECONDS);

// Cache matching results for frequently accessed dates
set_transient('matching_results_' . $date, $matches, 10 * MINUTE_IN_SECONDS);
```

### Pagination
```php
// For large datasets, implement pagination
// Pages 2, 3, 4 may need this for long date ranges
class Booking_Paginator {
    public function paginate($results, $per_page = 50)
    public function render_pagination($total_pages, $current_page)
}
```

### Lazy Loading
```javascript
// Load Gantt chart data only when modal opens
HotelBookings.BookingModal = {
    open: function(bookingData) {
        // Fetch existing bookings on-demand
        this.loadGanttData(bookingData.date);
    }
};
```

---

## Security Considerations

### Input Validation
```php
// Validate all user inputs
$validator = new Validator();
$clean_date = $validator->validate_date($_GET['date']);
$clean_hotel_id = absint($_GET['hotel_id']);
```

### Nonce Verification
```php
// For all AJAX requests
if (!wp_verify_nonce($_POST['nonce'], 'hotel_booking_create')) {
    wp_die('Security check failed');
}
```

### Capability Checks
```php
// Ensure user has permission
if (!current_user_can('manage_hotel_bookings')) {
    wp_die('Insufficient permissions');
}
```

### API Key Security
```php
// Never expose API keys in JavaScript
// Always proxy through PHP
public function ajax_create_booking() {
    check_ajax_referer('hotel_booking_create');
    
    $resos_api = new Resos_API(get_option('hotel_booking_resos_api_key'));
    // ... handle request
}
```

---

## Testing Strategy

### Unit Tests
```php
// Test matching engine
class Test_Matching_Engine extends WP_UnitTestCase {
    public function test_confirmed_match_with_booking_number() { /* ... */ }
    public function test_suggested_match_with_surname_and_date() { /* ... */ }
    public function test_no_match_with_different_surname() { /* ... */ }
}

// Test data processor
class Test_Data_Processor extends WP_UnitTestCase {
    public function test_normalize_string() { /* ... */ }
    public function test_extract_surname() { /* ... */ }
    public function test_calculate_night_status() { /* ... */ }
}
```

### Integration Tests
```php
// Test full workflow
class Test_Booking_Workflow extends WP_UnitTestCase {
    public function test_staying_today_page_render() { /* ... */ }
    public function test_booking_creation_workflow() { /* ... */ }
    public function test_cancellation_workflow() { /* ... */ }
}
```

### Manual Test Cases
1. **Matching Accuracy**
   - Test with exact booking number match
   - Test with surname match + same dates
   - Test with surname match + different dates
   - Test with partial name matches
   - Test with groups (all members should match)

2. **Multi-Night Bookings**
   - Create 3-night booking
   - Verify main row shows total nights
   - Verify sub-rows show each night correctly
   - Verify night numbering (1 of 3, 2 of 3, 3 of 3)

3. **Filters**
   - Test date selector on all pages
   - Test days back selector (Pages 2, 3, 4)
   - Test match checkbox combinations
   - Test "See All" fallback on Page 3

4. **Booking Creation**
   - Test modal opens with pre-filled data
   - Test availability checking
   - Test time slot restrictions
   - Test Gantt chart loads correctly
   - Test bar thickness scales with party size
   - Test form validation
   - Test successful submission

5. **Groups**
   - Test group separation on Page 1
   - Test group highlighting on Pages 2, 3, 4
   - Test group tooltip information

---

## Version Numbering

### Semantic Versioning
- **v1.1.0**: Major restructure, MVC architecture
- **v1.2.0**: Automation features added (cancelled checker, package checker)
- **v1.3.0**: Guest marketing automation added
- **v1.4.0**: Feedback system and opt-out functionality
- **v2.0.0**: Major UI/UX overhaul (if needed)

### Feature Flags
```php
// Enable/disable features during development
define('HB_ENABLE_BOOKINGS_PLACED', true);
define('HB_ENABLE_BOOKINGS_CANCELLED', true);
define('HB_ENABLE_AUTOMATION', false); // Post-v1.1.0
```

---

## Migration Checklist (v1.0.64 → v1.1.0)

### Pre-Migration
- [ ] Back up current plugin files
- [ ] Export all WordPress settings
- [ ] Document any customizations
- [ ] Test on staging environment
- [ ] Note current CSS file location

### During Migration
- [ ] Create new directory structure
- [ ] Migrate settings (automatic via Settings class)
- [ ] Update shortcodes on existing pages
- [ ] Add new shortcodes for Pages 2, 3, 4
- [ ] Test each page individually
- [ ] Verify matching accuracy unchanged
- [ ] Check all CSS loads correctly
- [ ] Test tooltip functionality
- [ ] Verify API connections work

### Post-Migration
- [ ] Monitor error logs for issues
- [ ] Gather user feedback on new pages
- [ ] Document any edge cases discovered
- [ ] Plan for Phase 2 (automation features)

### Rollback Plan
If issues arise:
1. Deactivate v1.1.0 plugin
2. Reactivate v1.0.64 plugin
3. Restore shortcodes to original
4. Settings should remain intact
5. Document issues encountered

---

## Summary

This restructuring plan transforms the hotel-restaurant-bookings plugin from a 2,207-line monolithic file into a clean, modular MVC architecture with 5 distinct pages and reusable components. The new structure will:

1. **Improve Maintainability**: Separated concerns make debugging and updates easier
2. **Enable Scalability**: Adding new pages/features requires less refactoring
3. **Promote Reusability**: Shared components reduce code duplication
4. **Enhance Testing**: Modular code is easier to unit test
5. **Support Future Growth**: Architecture ready for automation and marketing features

**Key Benefits**:
- ✅ 5 distinct page views (vs 1 currently)
- ✅ Reusable components across all pages
- ✅ Clean separation of data, logic, and display
- ✅ Consistent UI/UX across all pages
- ✅ Easy to add new features
- ✅ Ready for automation phase

**Estimated Timeline**: 10 weeks for v1.1.0
**Estimated Timeline**: +4 weeks for automation features (v1.2.0+)

---

## Next Steps

1. **Review & Approve**: Review this plan and provide feedback
2. **Prioritize**: Confirm page priorities (are all 5 needed immediately?)
3. **Begin Phase 1**: Set up directory structure
4. **API Clarification**: Provide sample API responses for Newbook and Resos if needed
5. **Design Review**: Confirm UI mockups for new pages (especially sub-row layout)

**Questions to Address**:
- Should Page 5 (booking modal) be implemented in v1.1.0 or deferred to v1.2.0?
- Are there any additional pages/views needed beyond the 5 described?
- What's the priority order for Pages 2, 3, 4? (suggest: 2 → 4 → 3)
- Should we include basic automation (email alerts) in v1.1.0 or save for v1.2.0?

---

**Document Version**: 1.0  
**Created**: October 2025  
**Author**: Project Team  
**Status**: Draft for Review