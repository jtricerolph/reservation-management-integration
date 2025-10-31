# API Preview/Execute Parity Requirements

## Critical Requirement

**ALL changes to outgoing API requests MUST be reflected in BOTH the execute functions AND the preview functions.**

The testing/sandbox mode relies on preview functions to show users exactly what will be sent to the API before executing. If preview and execute functions diverge, users will see incorrect data in the preview dialogue, leading to confusion and potential errors.

## Affected Functions

### Create Booking Flow

#### Execute Function (Production)
- **PHP**: `ajax_create_resos_booking()` in [hotel-admin.php](hotel-admin.php)
- **JavaScript**: `executeApiCall()` within `selectTimeSlot()` in [staying-today.js](assets/staying-today.js)

#### Preview Function (Testing/Sandbox)
- **PHP**: `ajax_preview_resos_create()` in [hotel-admin.php](hotel-admin.php)
- **JavaScript**: Preview fetch within `selectTimeSlot()` in [staying-today.js](assets/staying-today.js)

### Update/Match Booking Flow

#### Execute Function (Production)
- **PHP**: `ajax_confirm_resos_match()` in [hotel-admin.php](hotel-admin.php)
- **JavaScript**: `executeApiCall()` within `confirmMatch()` in [staying-today.js](assets/staying-today.js)

#### Preview Function (Testing/Sandbox)
- **PHP**: `ajax_preview_resos_match()` in [hotel-admin.php](hotel-admin.php)
- **JavaScript**: Preview fetch within `confirmMatch()` in [staying-today.js](assets/staying-today.js)

## Checklist for API Changes

When modifying any outgoing API request, ensure ALL of the following are updated:

### PHP Backend
- [ ] Update execute function (e.g., `ajax_create_resos_booking()`)
- [ ] Update corresponding preview function (e.g., `ajax_preview_resos_create()`)
- [ ] Verify both capture the same POST parameters
- [ ] Verify both apply the same transformations (phone formatting, customFields, etc.)
- [ ] Verify both construct identical request bodies

### JavaScript Frontend
- [ ] Update execute function's FormData construction
- [ ] Update preview function's FormData construction
- [ ] Verify both send identical parameters to backend
- [ ] Test in all three API modes: production, testing, sandbox

## Common Pitfalls

1. **Adding new fields**: When adding a new field to the API request (e.g., `openingHourName`):
   - Add to execute FormData
   - Add to preview FormData
   - Add PHP POST parameter capture in both functions
   - Add to request body construction in both functions

2. **Data transformations**: When adding transformations (e.g., phone formatting, customFields conversion):
   - Apply in both execute and preview PHP functions
   - Ensure identical logic in both code paths

3. **Conditional fields**: When adding conditional logic (e.g., only include if not empty):
   - Apply same conditions in both functions
   - Test with field present AND absent

## Testing Preview Parity

Before deploying API changes:

1. Set API mode to "Testing" or "Sandbox" in WordPress admin
2. Perform the action (create booking, update booking, etc.)
3. Review the preview dialogue - verify ALL expected fields are present
4. Compare preview data with actual request that would be sent
5. Switch to "Production" mode and verify behavior is identical

## Recent Examples

### openingHourName Addition (2025-01-30)
When adding `openingHourName` to create booking requests:

**Execute locations updated**:
- JavaScript FormData: [staying-today.js:759-761](assets/staying-today.js)
- PHP POST capture: [hotel-admin.php:1942](hotel-admin.php)
- PHP request body: [hotel-admin.php:1991-1994](hotel-admin.php)

**Preview locations updated**:
- JavaScript FormData: [staying-today.js:820-822](assets/staying-today.js)
- PHP POST capture: [hotel-admin.php:2218](hotel-admin.php)
- PHP request body: [hotel-admin.php:2271-2274](hotel-admin.php)

### Phone Formatting Addition
When adding phone number formatting with country code:

**Execute locations updated**:
- [hotel-admin.php:1956-1957](hotel-admin.php) - `ajax_create_resos_booking()`
- [hotel-admin.php:1837-1840](hotel-admin.php) - `ajax_confirm_resos_match()`

**Preview locations updated**:
- [hotel-admin.php:2231-2232](hotel-admin.php) - `ajax_preview_resos_create()`
- [hotel-admin.php:1451-1454](hotel-admin.php) - `ajax_preview_resos_match()`

### Opening Hour ID Change (2025-01-30)
Changed from sending `openingHourName` (string) to `openingHour` (ID reference):

**Execute locations updated**:
- JavaScript: Changed to extract both ID and name from `getOpeningHourForTime()`
- JavaScript FormData: [staying-today.js:774-776](assets/staying-today.js) - Send `opening_hour_id`
- PHP POST capture: Changed to capture `opening_hour_id` instead of `opening_hour_name`
- PHP request body: Changed to use `openingHour` field with ID value

**Preview locations updated**:
- JavaScript FormData: [staying-today.js:835-837](assets/staying-today.js) - Send `opening_hour_id`
- PHP POST capture: Same parameter name change as execute
- PHP request body: Same field name change as execute

**Backend data changes**:
- Added `_id` field extraction in `get_opening_hours_for_date()` at lines 969, 1004
- Function `getOpeningHourForTime()` now returns `{id, name}` object instead of just name string

## Summary

**Golden Rule**: If you change what gets sent to the Resos API, change it in BOTH the execute AND preview code paths. No exceptions.

Test in testing/sandbox mode before deploying to verify preview shows accurate data.
