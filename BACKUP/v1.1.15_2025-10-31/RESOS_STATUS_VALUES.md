# Resos API Status Values

## Valid Status Values

According to Resos API documentation, the `status` field can be one of:

- **`request`** - Initial booking request (default)
- **`declined`** - Booking request declined
- **`approved`** - Booking approved/confirmed
- **`arrived`** - Guest has arrived
- **`seated`** - Guest has been seated
- **`left`** - Guest has left
- **`no_show`** - Guest did not show up
- **`canceled`** - Booking was cancelled
- **`waitlist`** - Booking is on waitlist

**Default:** `request`

## Status Workflow

Typical booking lifecycle:
```
request → approved → arrived → seated → left
         ↓
      declined/canceled/no_show
```

## Status Update Logic

### When to Suggest "approved"

Only suggest changing status to `approved` if current status is:
- `request` (initial state)
- `declined` (give another chance)
- `waitlist` (moving from waitlist to confirmed)

### Do NOT Suggest Changing

Do NOT suggest status changes if current status is:
- `approved` - Already confirmed
- `arrived` - Guest already arrived
- `seated` - Guest already seated
- `left` - Booking complete
- `no_show` - Final state
- `canceled` - Final state

## Implementation Notes

**Filtering Bookings:**
Bookings with status `canceled`, `no_show`, or `deleted` are filtered out from the matching system entirely (see `get_restaurant_bookings_data()` in hotel-admin.php).

**Material Symbols Icons:**
Status icons use Material Symbols font matching the rest of the UI.
