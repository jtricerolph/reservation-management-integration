# API Documentation - Reservation Management Integration

**Version**: 1.0.0
**Last Updated**: 2025-10-31

## Table of Contents
- [Overview](#overview)
- [Newbook PMS API](#newbook-pms-api)
- [Resos Restaurant API](#resos-restaurant-api)
- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Testing](#testing)
- [Widget Integration](#widget-integration)

---

## Overview

This plugin integrates with two external APIs:
1. **Newbook PMS**: Hotel property management system
2. **Resos**: Restaurant reservation system

All API calls are made server-side using WordPress's `wp_remote_get()` and `wp_remote_post()` functions.

---

## Newbook PMS API

### Base URL
```
https://api.{region}.newbook.cloud/rest/
```

**Regions**:
- `au` - Australia
- `us` - United States
- `eu` - Europe

### Authentication
**Method**: Basic Authentication + API Key Header

```php
$headers = array(
    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
    'X-Api-Key' => $api_key
);
```

### Endpoints

#### 1. Get Bookings
**Endpoint**: `GET /property/bookings`

**Purpose**: Retrieve hotel bookings for a specific date range

**Parameters**:
```php
array(
    'hotelId' => '123',           // Required: Property ID
    'arrivalDate' => '2025-10-31', // Required: YYYY-MM-DD
    'departureDate' => '2025-11-01' // Required: YYYY-MM-DD
)
```

**Response Example**:
```json
{
    "bookings": [
        {
            "bookingid": 12345,
            "confirmNo": "ABC123",
            "status": "Confirmed",
            "arrivalDate": "2025-10-31T14:00:00",
            "departureDate": "2025-11-01T10:00:00",
            "guests": [
                {
                    "firstName": "John",
                    "lastName": "Smith",
                    "email": "john.smith@email.com",
                    "phone": "07700900123",
                    "isPrimary": true
                }
            ],
            "rooms": ["201"],
            "inventory": [
                {
                    "name": "Dinner, Bed & Breakfast Package",
                    "quantity": 1
                }
            ],
            "group": null,
            "totalGuests": 2,
            "totalAdults": 2,
            "totalChildren": 0
        }
    ]
}
```

**Used By**:
- Main booking table display
- New bookings view (planned)
- Dashboard (planned)

#### 2. Get Rooms
**Endpoint**: `GET /property/rooms`

**Purpose**: Retrieve list of all rooms in the property

**Parameters**:
```php
array(
    'hotelId' => '123' // Required: Property ID
)
```

**Response Example**:
```json
{
    "rooms": [
        {
            "roomNumber": "201",
            "roomType": "Double",
            "status": "Clean",
            "floor": 2
        },
        {
            "roomNumber": "202",
            "roomType": "Twin",
            "status": "Clean",
            "floor": 2
        }
    ]
}
```

**Used By**:
- Room organization and grouping
- Vacant room identification

#### 3. Get Group Details (Currently Disabled)
**Endpoint**: `GET /group/{groupId}`

**Purpose**: Retrieve group booking information

**Note**: ⚠️ **Currently disabled** - returns `null` to avoid 401 errors. Re-enable when API credentials have group access permissions.

**Parameters**:
```php
array(
    'group_id' => '456' // Required: Group ID
)
```

**Expected Response**:
```json
{
    "groupId": 456,
    "groupName": "Smith Wedding",
    "contactName": "Jane Smith",
    "totalRooms": 10,
    "arrivalDate": "2025-10-31",
    "departureDate": "2025-11-02"
}
```

#### 4. Get Packages
**Endpoint**: `GET /inventory/packages`

**Purpose**: Retrieve available packages

**Parameters**:
```php
array(
    'hotelId' => '123' // Required: Property ID
)
```

**Response**: Array of package objects including DBB (Dinner, Bed & Breakfast) packages

---

## Resos Restaurant API

### Base URL
```
https://app.resOS.com/api
```

### Authentication
**Method**: API Key in Headers

```php
$headers = array(
    'Authorization' => 'Bearer ' . $api_key,
    'Content-Type' => 'application/json'
);
```

### Endpoints

#### 1. Get Restaurant Bookings
**Endpoint**: `GET /bookings`

**Purpose**: Retrieve restaurant bookings for a specific date

**Parameters**:
```php
array(
    'date' => '2025-10-31',     // Required: YYYY-MM-DD
    'venueId' => 'abc123',       // Optional: Venue identifier
    'status' => 'confirmed'      // Optional: Booking status
)
```

**Response Example**:
```json
{
    "bookings": [
        {
            "_id": "507f1f77bcf86cd799439011",
            "guest": {
                "name": "John Smith",
                "email": "john@email.com",
                "phone": "+447700900123"
            },
            "time": "18:30",
            "partySize": 2,
            "status": "confirmed",
            "customFields": [
                {
                    "id": "hotel_guest",
                    "name": "Hotel Guest",
                    "value": true
                },
                {
                    "id": "booking_reference",
                    "name": "Booking #",
                    "value": "ABC123"
                }
            ],
            "tables": [
                {
                    "name": "Table 9",
                    "area": {
                        "name": "Top Section"
                    }
                }
            ],
            "restaurantNotes": [
                {
                    "restaurantNote": "Anniversary dinner"
                }
            ],
            "comments": [
                {
                    "comment": "Vegetarian main course please",
                    "role": "customer"
                }
            ]
        }
    ]
}
```

**Used By**:
- Booking matching algorithm
- Gantt chart visualization
- Recent Resos view (planned)

#### 2. Create Booking
**Endpoint**: `POST /bookings`

**Purpose**: Create a new restaurant booking

**Request Body**:
```json
{
    "name": "John Smith",
    "email": "john@email.com",
    "phone": "+447700900123",
    "time": "2025-10-31 18:30",
    "partySize": 2,
    "openingHourId": "evening_service_id",
    "customFields": [
        {
            "id": "hotel_guest",
            "value": true
        },
        {
            "id": "booking_reference",
            "value": "ABC123"
        },
        {
            "id": "package_dbb",
            "value": true
        },
        {
            "id": "dietary_requirements",
            "value": [
                {
                    "_id": "diet_001",
                    "name": "Vegetarian",
                    "value": true
                }
            ]
        }
    ],
    "notificationPreferences": {
        "sms": true,
        "email": true
    }
}
```

**Response Example**:
```json
{
    "bookingId": "507f1f77bcf86cd799439012",
    "status": "confirmed",
    "confirmationCode": "RES123"
}
```

**Used By**:
- Create booking from hotel guest
- Manual booking creation

#### 3. Update Booking
**Endpoint**: `PATCH /bookings/{bookingId}`

**Purpose**: Update an existing restaurant booking

**Request Body** (partial update):
```json
{
    "customFields": [
        {
            "id": "hotel_guest",
            "value": true
        },
        {
            "id": "booking_reference",
            "value": "ABC123"
        }
    ]
}
```

**Note**: CustomFields array is "all or nothing" - must include ALL custom fields, not just changes

**Response**: Updated booking object

**Used By**:
- Match confirmation
- Suggested updates application

#### 4. Add Booking Note
**Endpoint**: `POST /bookings/{bookingId}/restaurantNote`

**Purpose**: Add a note to an existing booking

**Request Body**:
```json
{
    "restaurantNote": "Guest has nut allergy - kitchen informed"
}
```

**Response**:
```json
{
    "success": true,
    "noteId": "note_123"
}
```

**Used By**:
- Post-creation note addition
- Special requirements documentation

#### 5. Get Booking Configuration
**Endpoint**: `GET /config/booking`

**Purpose**: Retrieve booking configuration including custom fields

**Response Example**:
```json
{
    "customFields": [
        {
            "id": "hotel_guest",
            "name": "Hotel Guest",
            "type": "boolean"
        },
        {
            "id": "dietary_requirements",
            "name": "Dietary Requirements",
            "type": "multiselect",
            "choices": [
                {
                    "_id": "diet_001",
                    "name": "Vegetarian"
                },
                {
                    "_id": "diet_002",
                    "name": "Vegan"
                },
                {
                    "_id": "diet_003",
                    "name": "Gluten Free"
                }
            ]
        }
    ]
}
```

**Used By**:
- Dynamic dietary requirements loading
- Custom field configuration

#### 6. Get Available Times
**Endpoint**: `GET /availability/times`

**Purpose**: Get available booking times for a specific date

**Parameters**:
```php
array(
    'date' => '2025-10-31',     // Required: YYYY-MM-DD
    'partySize' => 2,            // Required: Number of guests
    'areaId' => 'main_dining'   // Optional: Specific area
)
```

**Response Example**:
```json
{
    "availableTimes": [
        {
            "time": "18:00",
            "available": true
        },
        {
            "time": "18:15",
            "available": true
        },
        {
            "time": "18:30",
            "available": false
        }
    ]
}
```

**Used By**:
- Time slot generation
- Availability checking

#### 7. Get Available Dates
**Endpoint**: `GET /availability/dates`

**Purpose**: Get available dates in a date range

**Parameters**:
```php
array(
    'fromDate' => '2025-10-31',  // Required: YYYY-MM-DD
    'toDate' => '2025-11-07',    // Required: YYYY-MM-DD
    'partySize' => 2              // Optional: Party size
)
```

**Response**: Array of available dates

**Used By**:
- Availability overview (planned)
- Calendar displays

#### 8. Get Opening Hours
**Endpoint**: `GET /openingHours`

**Purpose**: Retrieve restaurant opening hours configuration

**Response Example**:
```json
{
    "openingHours": [
        {
            "date": "2025-10-31",
            "periods": [
                {
                    "name": "Lunch",
                    "open": 1200,
                    "close": 1400,
                    "seating": {
                        "interval": 15,
                        "duration": 120
                    }
                },
                {
                    "name": "Dinner",
                    "open": 1700,
                    "close": 2100,
                    "seating": {
                        "interval": 15,
                        "duration": 120
                    }
                }
            ],
            "specialEvents": [
                {
                    "name": "Private Event",
                    "startTime": "15:00",
                    "endTime": "17:00"
                }
            ]
        }
    ]
}
```

**Cached**: 1 hour (reduced from 24 hours for faster updates)

**Used By**:
- Time slot generation
- Service period display
- Gantt chart boundaries

---

## Authentication

### Storing Credentials

All API credentials are stored in WordPress options:

```php
// Newbook credentials
get_option('hotel_booking_username')
get_option('hotel_booking_password')
get_option('hotel_booking_api_key')
get_option('hotel_booking_region')
get_option('hotel_booking_default_hotel_id')

// Resos credentials
get_option('hotel_booking_resos_api_key')
```

### Security Considerations

- **Never** hardcode API keys in code
- Store credentials encrypted in database
- Use WordPress nonces for AJAX calls
- Validate user capabilities before API calls

---

## Error Handling

### Common Error Responses

#### Newbook Errors
```json
{
    "error": {
        "code": 401,
        "message": "Unauthorized - Invalid credentials"
    }
}
```

#### Resos Errors
```json
{
    "error": {
        "type": "ValidationError",
        "message": "Invalid party size",
        "field": "partySize"
    }
}
```

### Error Handling Pattern

```php
$response = wp_remote_get($url, $args);

if (is_wp_error($response)) {
    error_log('API Error: ' . $response->get_error_message());
    return false;
}

$code = wp_remote_retrieve_response_code($response);
if ($code !== 200) {
    error_log('API returned code ' . $code);
    return false;
}

$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON decode error');
    return false;
}
```

---

## Rate Limiting

### Newbook
- **Default**: 1000 requests per hour
- **Burst**: 10 requests per second
- **Headers**: `X-RateLimit-Remaining`, `X-RateLimit-Reset`

### Resos
- **Default**: 500 requests per hour
- **Burst**: 5 requests per second
- **Headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

### Caching Strategy

To reduce API calls:
- **Opening hours**: Cached 1 hour
- **Room list**: Cached 24 hours
- **Available times**: Cached 5 minutes
- **Bookings**: Not cached (real-time data)

---

## Testing

### API Modes

The plugin supports three API modes:

1. **Production Mode**
   - Direct API execution
   - No confirmation dialogs
   - Use in live environment

2. **Testing Mode**
   - Shows preview dialog before execution
   - User can confirm or cancel
   - Good for development/testing

3. **Sandbox Mode**
   - Shows preview only
   - No actual API calls made
   - Safe for demonstration

### Testing Endpoints

```php
// Test Newbook connection
function test_newbook_connection() {
    $response = $this->call_api('property/info');
    return !empty($response);
}

// Test Resos connection
function test_resos_connection() {
    $response = $this->get_resos_opening_hours();
    return !empty($response);
}
```

### Mock Data

For testing without API access:

```php
// Enable mock mode (add to wp-config.php)
define('HOTEL_BOOKING_MOCK_MODE', true);

// Mock responses returned instead of API calls
if (defined('HOTEL_BOOKING_MOCK_MODE') && HOTEL_BOOKING_MOCK_MODE) {
    return $this->get_mock_data('bookings');
}
```

---

## Widget Integration

### Using APIs in External Plugins

External plugins (like a booking widget) can use these APIs:

```php
// Check if main plugin is active
if (!class_exists('Hotel_Booking_Table')) {
    return;
}

// Use the API functions
$plugin = new Hotel_Booking_Table();
$bookings = $plugin->get_bookings_data('123', '2025-10-31');
```

### Available Public Methods

```php
// These methods can be called from external plugins
public function get_bookings_data($hotel_id, $date)
public function get_rooms_data($hotel_id)
public function get_restaurant_bookings_data($date)
public function get_resos_available_times($date, $people)
```

### Creating Bookings from Widget

```php
// Prepare booking data
$booking_data = array(
    'name' => 'John Smith',
    'email' => 'john@email.com',
    'phone' => '+447700900123',
    'time' => '2025-10-31 18:30',
    'partySize' => 2,
    'customFields' => array(
        array('id' => 'hotel_guest', 'value' => true),
        array('id' => 'booking_reference', 'value' => 'ABC123')
    )
);

// Create booking via AJAX
wp_ajax_create_resos_booking($booking_data);
```

---

## Request/Response Examples

### Complete Examples Directory

For detailed request/response examples, see:
- `/docs/api/newbook-examples.md` (to be created)
- `/docs/api/resos-examples.md` (to be created)

### Quick Example: Create Restaurant Booking

**Request**:
```bash
curl -X POST https://app.resOS.com/api/bookings \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Smith",
    "email": "john@email.com",
    "phone": "+447700900123",
    "time": "2025-10-31 18:30",
    "partySize": 2,
    "openingHourId": "evening_001"
  }'
```

**Response**:
```json
{
    "bookingId": "507f1f77bcf86cd799439012",
    "status": "confirmed",
    "confirmationCode": "RES123",
    "message": "Booking confirmed for John Smith at 18:30"
}
```

---

## Future API Integrations

### Planned Integrations

1. **POS/Till System**
   - Endpoint: TBD
   - Purpose: Financial reporting, spend analysis
   - Data: Transaction amounts, items ordered

2. **Email Service**
   - Provider: TBD (SendGrid, Mailgun, etc.)
   - Purpose: Automated booking confirmations
   - Templates: Welcome, confirmation, reminder

3. **SMS Service**
   - Provider: TBD (Twilio, etc.)
   - Purpose: Booking reminders
   - Format: Short confirmation codes

---

## Troubleshooting

### Common Issues

1. **401 Unauthorized**
   - Check API credentials in settings
   - Verify API key is active
   - Check user permissions

2. **Empty Responses**
   - Check network connectivity
   - Verify API endpoint URLs
   - Check firewall rules

3. **Timeout Errors**
   - Increase timeout in wp_remote_get()
   - Check API server status
   - Reduce request size

4. **JSON Decode Errors**
   - Check response content type
   - Verify API returns valid JSON
   - Check for BOM or extra characters

### Debug Mode

Enable debug logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// In plugin code
error_log('API Request: ' . print_r($request, true));
error_log('API Response: ' . print_r($response, true));
```

---

## API Version History

| API | Version | Last Updated | Notes |
|-----|---------|--------------|-------|
| Newbook | v1 | 2025-10 | Stable |
| Resos | v2 | 2025-10 | Using latest endpoints |

---

## Notes for Developers

1. **Always** check API mode before making calls
2. **Always** handle errors gracefully
3. **Always** validate data before sending
4. **Always** use WordPress functions for HTTP requests
5. **Never** expose API keys in frontend code
6. **Never** make API calls from JavaScript (use AJAX)

---

*Last Updated: 2025-10-31*
*Documentation Version: 1.0.0*

## Appendix: Quick Setup

### For New Developers

1. Get API credentials from:
   - Newbook: Contact property manager
   - Resos: Admin panel > API settings

2. Configure in WordPress:
   - Navigate to Settings > Hotel Booking Table
   - Enter all credentials
   - Select appropriate region
   - Test connections

3. Verify setup:
   - Switch to Sandbox mode
   - Test create booking flow
   - Check preview accuracy
   - Switch to Production when ready