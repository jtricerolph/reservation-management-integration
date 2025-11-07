// Hotel Admin - Staying Today Page JavaScript
// Version 1.0.75

// Track which comparisons are expanded
window.expandedRows = {};

// Store dietary requirement choices from Resos
window.dietaryChoices = [];

// ===================================
// API MODE FUNCTIONS
// ===================================

/**
 * Get current API mode (production, testing, sandbox)
 * @returns {string} - 'production', 'testing', or 'sandbox'
 */
window.getApiMode = function() {
    if (typeof hotelBookingAjax === 'undefined' || !hotelBookingAjax.apiMode) {
        return 'production'; // Default to production if not set
    }
    return hotelBookingAjax.apiMode;
};

/**
 * Fetch dietary requirement choices from Resos
 */
window.fetchDietaryChoices = function() {
    var formData = new FormData();
    formData.append('action', 'get_dietary_choices');

    fetch(hotelBookingAjax.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success && data.data.choices) {
            window.dietaryChoices = data.data.choices;
            console.log('Loaded ' + window.dietaryChoices.length + ' dietary choices from Resos:', window.dietaryChoices);
        } else {
            console.error('Failed to load dietary choices:', data);
        }
    })
    .catch(function(error) {
        console.error('Error fetching dietary choices:', error);
    });
};

// Debug: Log settings on page load
if (typeof hotelBookingAjax !== 'undefined') {
    console.log('=== HOTEL BOOKING SETTINGS ===');
    console.log('API Mode:', window.getApiMode());
    console.log('Full hotelBookingAjax:', hotelBookingAjax);
    console.log('==============================');

    // Fetch dietary choices from Resos
    window.fetchDietaryChoices();
}

/**
 * Get status icon (matching Resos status icons using Material Symbols)
 * @param {string} status - The booking status
 * @returns {string} - Material Symbols icon name for the status
 *
 * Valid Resos statuses: request, declined, approved, arrived, seated, left, no_show, canceled, waitlist
 */
window.getStatusIcon = function(status) {
    var statusLower = (status || '').toLowerCase();
    var icons = {
        // Valid Resos API statuses
        'request': 'help',                          // ? - Initial request (default)
        'declined': 'thumb_down',                   // 👎 - Request declined
        'approved': 'thumb_up',                     // 👍 - Booking confirmed
        'waitlist': 'pending_actions',              // ⏳ - On waitlist
        'arrived': 'directions_walk',               // 🚶 - Guest arrived
        'seated': 'airline_seat_recline_normal',    // 💺 - Guest seated
        'left': 'flight_takeoff',                   // ✈️ - Guest left (complete)
        'no_show': 'block',                         // 🚫 - Guest didn't show
        'canceled': 'cancel',                       // ✕ - Booking cancelled

        // Legacy/alias values for backwards compatibility
        'accepted': 'thumb_up',                     // Alias for approved
        'has left': 'flight_takeoff',               // Alias for left
        'cancelled': 'cancel',                      // Alternative spelling
        'no-show': 'block',                         // Alternative format
        'deleted': 'delete'                         // System status
    };
    return icons[statusLower] || 'help';
};

/**
 * Find the opening hour ID and name for a given time
 * Accounts for booking duration - the END time must also be within opening hours
 * @param {string} time - Time in format "HH:MM" (e.g., "12:30")
 * @param {Array} openingHours - Array of opening hour periods with {_id, name, open, close, duration}
 * @returns {object|null} - {id, name} or null if not found/doesn't fit
 */
window.getOpeningHourForTime = function(time, openingHours) {
    if (!time || !openingHours || !Array.isArray(openingHours)) {
        return null;
    }

    // Parse time string "HH:MM" to numeric format (HHMM)
    var timeParts = time.split(':');
    if (timeParts.length !== 2) return null;

    var hour = parseInt(timeParts[0]);
    var minute = parseInt(timeParts[1]);
    var timeNumeric = (hour * 100) + minute;

    // Find which opening hour period this time + duration falls into
    for (var i = 0; i < openingHours.length; i++) {
        var period = openingHours[i];
        var duration = period.duration || 120; // Default 2 hours (120 minutes)

        // Calculate end time of booking (start time + duration)
        var totalMinutes = (hour * 60) + minute + duration;
        var endHour = Math.floor(totalMinutes / 60);
        var endMinute = totalMinutes % 60;
        var endTimeNumeric = (endHour * 100) + endMinute;

        // Check if BOTH start time and end time are within opening hours
        if (timeNumeric >= period.open && endTimeNumeric <= period.close) {
            return {
                id: period._id || null,
                name: period.name || null
            };
        }
    }

    return null;
};

/**
 * Initialize status icons on page load
 */
window.initializeStatusIcons = function() {
    var statusIcons = document.querySelectorAll('.status-icon[data-status]');
    statusIcons.forEach(function(iconElement) {
        var status = iconElement.getAttribute('data-status');
        var iconName = window.getStatusIcon(status);

        // Add Material Symbols class and set icon
        iconElement.classList.add('material-symbols-outlined');
        iconElement.textContent = iconName;
        iconElement.setAttribute('title', status.charAt(0).toUpperCase() + status.slice(1));
    });
};

// Initialize status icons when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initializeStatusIcons);
} else {
    window.initializeStatusIcons();
}

/**
 * Show mode banner when not in production
 */
window.showModeBanner = function() {
    var apiMode = window.getApiMode();

    // Only show banner if NOT in production mode
    if (apiMode === 'production') {
        return;
    }

    // Check if banner already exists
    if (document.getElementById('api-mode-banner')) {
        return;
    }

    // Create banner element
    var banner = document.createElement('div');
    banner.id = 'api-mode-banner';
    banner.className = 'api-mode-banner api-mode-' + apiMode;

    var bannerContent = '';
    if (apiMode === 'testing') {
        bannerContent = '<strong>⚠️ TESTING MODE ACTIVE</strong> - API calls require confirmation before execution';
    } else if (apiMode === 'sandbox') {
        bannerContent = '<strong>🔒 SANDBOX MODE ACTIVE</strong> - No API calls will be executed (preview only)';
    }

    banner.innerHTML = bannerContent;

    // Insert banner below the colored title section, above date selection
    // Look for the hotel-booking-header (colored title section)
    var headerElement = document.querySelector('.hotel-booking-header');
    if (headerElement && headerElement.nextSibling) {
        // Insert after the header
        headerElement.parentNode.insertBefore(banner, headerElement.nextSibling);
    } else {
        // Fallback: look for date selector and insert before it
        var dateSelector = document.querySelector('.date-selector, input[type="date"]');
        if (dateSelector && dateSelector.parentNode) {
            dateSelector.parentNode.insertBefore(banner, dateSelector);
        } else {
            // Last fallback: insert at top of body
            document.body.insertBefore(banner, document.body.firstChild);
        }
    }
};

// Show banner on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.showModeBanner);
} else {
    window.showModeBanner();
}

/**
 * Show sandbox/testing popup with API call details
 * @param {Object} config - Configuration object
 * @param {string} config.method - HTTP method (GET, POST, PUT, PATCH, DELETE)
 * @param {string} config.endpoint - API endpoint URL
 * @param {Object} config.data - Request body data
 * @param {string} config.action - Human-readable action description
 * @param {Function} config.confirmCallback - Optional callback to execute on confirm (testing mode only)
 */
window.showSandboxPopup = function(config) {
    var apiMode = window.getApiMode();
    var isTestingMode = apiMode === 'testing' && typeof config.confirmCallback === 'function';
    var isSandboxMode = apiMode === 'sandbox';

    // Create overlay
    var overlay = document.createElement('div');
    overlay.className = 'sandbox-overlay';
    overlay.id = 'sandbox-overlay';

    // Build the HTML
    var html = '<div class="sandbox-popup">';

    // Header - dynamic based on mode
    html += '<div class="sandbox-header">';
    html += '<div class="sandbox-header-content">';
    if (isTestingMode) {
        html += '<h2>Testing Mode - Confirmation Required</h2>';
        html += '<p>Review data and confirm to execute API call</p>';
    } else {
        html += '<h2>Sandbox Mode - Preview Only</h2>';
        html += '<p>This action won\'t be executed</p>';
    }
    html += '</div>';
    html += '<button class="sandbox-close" onclick="window.closeSandboxPopup()" title="Close">×</button>';
    html += '</div>';

    // Body
    html += '<div class="sandbox-body">';

    // Action description
    html += '<div class="sandbox-section">';
    html += '<h3>Action</h3>';
    html += '<p style="font-size: 15px; color: #374151; line-height: 1.6;">' + config.action + '</p>';
    html += '</div>';

    // API Endpoint
    html += '<div class="sandbox-section">';
    html += '<h3>API Endpoint</h3>';
    html += '<div class="sandbox-endpoint">';
    html += '<span class="sandbox-method ' + config.method.toLowerCase() + '">' + config.method + '</span>';
    html += '<span>' + config.endpoint + '</span>';
    html += '</div>';
    html += '</div>';

    // Check if special fields need conversion to customFields
    var hasSpecialFields = config.data && (config.data.dbb || config.data.booking_ref || config.data.hotel_guest);
    var hasCustomFields = config.data && config.data.customFields;

    // Request Body
    html += '<div class="sandbox-section">';
    html += '<h3>Request Body / Data</h3>';

    // Show warning if booking not found
    if (config.warning) {
        html += '<div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px; margin-bottom: 12px; border-radius: 4px;">';
        html += '<p style="margin: 0; font-size: 14px; color: #991b1b;"><strong>⚠️ Warning:</strong></p>';
        html += '<p style="margin: 6px 0 0 0; font-size: 13px; color: #991b1b;">' + config.warning + '</p>';
        html += '</div>';
    }

    // If special fields need conversion, show conversion notice
    if (hasSpecialFields) {
        html += '<div style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: 12px; margin-bottom: 12px; border-radius: 4px;">';
        html += '<p style="margin: 0; font-size: 14px; color: #1e40af;"><strong>🔄 Field Conversion Process:</strong></p>';
        html += '<p style="margin: 8px 0 0 0; font-size: 13px; color: #1e3a8a;">The following fields will be converted to customFields format by the backend:</p>';
        html += '<ul style="margin: 6px 0 0 0; padding-left: 20px; font-size: 13px; color: #1e3a8a;">';
        if (config.data.dbb) html += '<li><code>dbb</code> → customField "DBB"</li>';
        if (config.data.booking_ref) html += '<li><code>booking_ref</code> → customField "Booking #"</li>';
        if (config.data.hotel_guest) html += '<li><code>hotel_guest</code> → customField "Hotel Guest"</li>';
        html += '</ul>';
        html += '<p style="margin: 8px 0 0 0; font-size: 12px; color: #1e40af;"><em>Backend will: 1) Fetch field definitions, 2) Get current booking, 3) Merge & convert, 4) Send complete customFields array.</em></p>';
        html += '</div>';
    }

    // If customFields are present directly, show merge notice
    if (hasCustomFields) {
        html += '<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin-bottom: 12px; border-radius: 4px;">';
        html += '<p style="margin: 0; font-size: 14px; color: #92400e;"><strong>⚠️ CustomFields Update Process:</strong></p>';
        html += '<ol style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px; color: #92400e;">';
        html += '<li>GET current booking from Resos</li>';
        html += '<li>Merge your changes into existing customFields</li>';
        html += '<li>PUT complete customFields array</li>';
        html += '</ol>';
        html += '<p style="margin: 8px 0 0 0; font-size: 12px; color: #78350f;"><em>This preserves all existing custom fields while updating only the selected ones.</em></p>';
        html += '</div>';
    }

    // Show the input data
    if (hasSpecialFields) {
        html += '<p style="font-size: 13px; color: #6b7280; margin-bottom: 6px;"><strong>Input Data (from UI):</strong></p>';
    }
    html += '<div class="sandbox-code">';
    html += '<pre>' + JSON.stringify(config.data, null, 2) + '</pre>';
    html += '</div>';

    // If special fields exist OR transformedData is provided, show what will actually be sent
    if (hasSpecialFields || hasCustomFields || config.transformedData) {
        var displayData = config.transformedData || null;

        // If we don't have actual transformedData, build example
        if (!displayData && hasSpecialFields) {
            displayData = {};
            var customFieldsArray = [];

            // Copy non-special fields
            for (var key in config.data) {
                if (key !== 'dbb' && key !== 'booking_ref' && key !== 'hotel_guest') {
                    displayData[key] = config.data[key];
                }
            }

            // Add example customFields structure
            if (config.data.dbb) {
                customFieldsArray.push({
                    _id: '[field_id_from_api]',
                    name: 'DBB',
                    value: '[choice_id_for_' + config.data.dbb + ']',
                    multipleChoiceValueName: config.data.dbb
                });
            }
            if (config.data.booking_ref) {
                customFieldsArray.push({
                    _id: '[field_id_from_api]',
                    name: 'Booking #',
                    value: config.data.booking_ref
                });
            }
            if (config.data.hotel_guest) {
                customFieldsArray.push({
                    _id: '[field_id_from_api]',
                    name: 'Hotel Guest',
                    value: '[choice_id_for_' + config.data.hotel_guest + ']',
                    multipleChoiceValueName: config.data.hotel_guest
                });
            }

            // Add note about existing fields
            customFieldsArray.push({
                _note: '[...plus all existing customFields from current booking]'
            });

            displayData.customFields = customFieldsArray;
        }

        if (displayData) {
            var labelText = config.transformedData ? 'Actual Transformed Data (will be sent to Resos API):' : 'Transformed Data (sent to Resos API):';
            html += '<p style="font-size: 13px; color: #6b7280; margin: 12px 0 6px 0;"><strong>' + labelText + '</strong></p>';
            html += '<div class="sandbox-code">';
            html += '<pre>' + JSON.stringify(displayData, null, 2) + '</pre>';
            html += '</div>';
        }
    }

    html += '</div>';

    // Info box - dynamic based on mode
    html += '<div class="sandbox-info">';
    if (isTestingMode) {
        html += '<p><strong>Testing Mode is enabled.</strong> Click "Confirm & Execute" to proceed with this API call, or "Cancel" to abort.</p>';
    } else if (isSandboxMode) {
        html += '<p><strong>Sandbox Mode is enabled.</strong> This API call will NOT be executed. To enable live API calls, change to Production mode in Settings → Hotel Booking Table.</p>';
    }
    html += '</div>';

    html += '</div>'; // End body

    // Footer - dynamic buttons based on mode
    html += '<div class="sandbox-footer">';
    if (isTestingMode) {
        html += '<button onclick="window.closeSandboxPopup()" style="background: #6b7280; margin-right: 10px;">Cancel</button>';
        html += '<button onclick="window.confirmSandboxAction()" style="background: #10b981;">Confirm & Execute</button>';
    } else {
        html += '<button onclick="window.closeSandboxPopup()">Close</button>';
    }
    html += '</div>';

    html += '</div>'; // End popup

    overlay.innerHTML = html;
    document.body.appendChild(overlay);

    // Store callback for testing mode
    if (isTestingMode) {
        window.sandboxConfirmCallback = config.confirmCallback;
    }

    // Allow closing by clicking overlay (but not in testing mode)
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay && !isTestingMode) {
            window.closeSandboxPopup();
        }
    });

    // Allow closing with Escape key
    document.addEventListener('keydown', window.sandboxEscapeHandler = function(e) {
        if (e.key === 'Escape') {
            window.closeSandboxPopup();
        }
    });
};

/**
 * Confirm and execute action (testing mode only)
 */
window.confirmSandboxAction = function() {
    if (window.sandboxConfirmCallback && typeof window.sandboxConfirmCallback === 'function') {
        var callback = window.sandboxConfirmCallback;
        window.closeSandboxPopup(); // Close popup first
        callback(); // Then execute the API call
    }
};

/**
 * Close sandbox popup
 */
window.closeSandboxPopup = function() {
    var overlay = document.getElementById('sandbox-overlay');
    if (overlay) {
        overlay.remove();
    }
    // Remove escape key handler
    if (window.sandboxEscapeHandler) {
        document.removeEventListener('keydown', window.sandboxEscapeHandler);
        window.sandboxEscapeHandler = null;
    }
    // Clear stored callback
    window.sandboxConfirmCallback = null;
};

// Close a comparison row
window.closeComparisonRow = function(uniqueId) {
    var comparisonRow = document.querySelector('tr.comparison-row[data-comparison-id="' + uniqueId + '"]');
    if (comparisonRow) {
        comparisonRow.remove();
    }
    var suggestion = document.querySelector('.restaurant-booking[data-unique-id="' + uniqueId + '"]');
    if (suggestion) {
        suggestion.classList.remove('expanded');
        var parentTr = suggestion.closest('tr');
        if (parentTr) {
            parentTr.classList.remove('has-expanded-comparison');
        }
    }
    delete window.expandedRows[uniqueId];
};

// Toggle comparison row for matched bookings
window.toggleComparisonRow = function(uniqueId, roomId, matchType) {
    matchType = matchType || 'suggested';
    if (window.expandedRows[uniqueId]) {
        window.closeComparisonRow(uniqueId);
        return;
    }
    // Close ALL other expanded rows
    for (var expId in window.expandedRows) {
        if (expId !== uniqueId) {
            window.closeComparisonRow(expId);
        }
    }
    var element = document.querySelector('.restaurant-booking[data-unique-id="' + uniqueId + '"]');
    if (!element) {
        console.error('Restaurant booking element not found for unique ID: ' + uniqueId);
        return;
    }
    var comparisonData = element.getAttribute('data-comparison');
    if (!comparisonData) {
        console.error('Missing comparison data for unique ID: ' + uniqueId);
        return;
    }
    try {
        var data = JSON.parse(comparisonData);
        if (!data.hotel || !data.resos) {
            console.error('Invalid comparison data structure');
            return;
        }
        var parentTr = element.closest('tr');
        if (!parentTr) {
            console.error('Could not find parent tr');
            return;
        }
        element.classList.add('expanded');
        parentTr.classList.add('has-expanded-comparison');
        var comparisonRowHtml = window.buildComparisonRow(data, uniqueId, roomId, matchType);
        var newTr = document.createElement('tr');
        newTr.className = 'comparison-row';
        newTr.setAttribute('data-comparison-id', uniqueId);
        newTr.innerHTML = '<td colspan="6">' + comparisonRowHtml + '</td>';
        parentTr.parentNode.insertBefore(newTr, parentTr.nextSibling);
        window.expandedRows[uniqueId] = newTr;

        // Scroll the booking row to the top of the page
        setTimeout(function() {
            parentTr.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    } catch (e) {
        console.error('Error handling comparison row:', e);
    }
};

// Toggle create booking row
window.toggleCreateBookingRow = function(roomNumber, bookingDate, partySize, buttonElement) {
    partySize = partySize || 2; // Default to 2 if not provided

    // Extract guest data from button element
    var guestData = null;
    if (buttonElement && buttonElement.getAttribute) {
        var guestInfoJson = buttonElement.getAttribute('data-guest-info');
        if (guestInfoJson) {
            try {
                guestData = JSON.parse(guestInfoJson);
            } catch (e) {
                console.error('Error parsing guest data:', e);
            }
        }
    }

    var uniqueId = 'create-' + roomNumber;
    if (window.expandedRows[uniqueId]) {
        window.closeComparisonRow(uniqueId);
        return;
    }
    // Close ALL other expanded rows
    for (var expId in window.expandedRows) {
        window.closeComparisonRow(expId);
    }
    var rows = document.querySelectorAll('.booking-table tbody tr');
    var parentTr = null;
    rows.forEach(function(row) {
        var actionCell = row.querySelector('.action-cell');
        if (actionCell && actionCell.textContent.includes('Create Booking')) {
            var roomCell = row.querySelector('.room-number');
            if (roomCell && roomCell.textContent.trim() === roomNumber) {
                parentTr = row;
            }
        }
    });
    if (!parentTr) {
        console.error('Could not find parent row for room: ' + roomNumber);
        return;
    }
    parentTr.classList.add('has-expanded-comparison');
    var html = window.buildCreateBookingSection(roomNumber, bookingDate, partySize, guestData);
    var newTr = document.createElement('tr');
    newTr.className = 'comparison-row';
    newTr.setAttribute('data-comparison-id', uniqueId);
    newTr.innerHTML = '<td colspan="6">' + html + '</td>';
    parentTr.parentNode.insertBefore(newTr, parentTr.nextSibling);
    window.expandedRows[uniqueId] = newTr;

    // Set up tooltips for Gantt bars
    window.setupGanttTooltips();

    // Set up auto-check for notification checkboxes
    window.setupNotificationAutoCheck(roomNumber);

    // Populate dietary checkboxes from Resos
    window.populateDietaryCheckboxes(roomNumber);

    // Auto-fetch available times with default party size
    window.fetchAvailableTimes(roomNumber, bookingDate);

    // Scroll the booking row to the top of the page
    setTimeout(function() {
        parentTr.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
};

// Confirm match with selected updates
window.confirmMatch = function(uniqueId, bookingId, roomId) {
    var comparisonRow = document.querySelector('tr.comparison-row[data-comparison-id="' + uniqueId + '"]');
    if (!comparisonRow) {
        console.error('Comparison row not found');
        return;
    }

    // Get the comparison data from the original element
    var element = document.querySelector('.restaurant-booking[data-unique-id="' + uniqueId + '"]');
    var comparisonData = null;
    if (element) {
        try {
            comparisonData = JSON.parse(element.getAttribute('data-comparison'));
        } catch (e) {
            console.error('Error parsing comparison data:', e);
        }
    }

    var checkedBoxes = comparisonRow.querySelectorAll('.suggestion-checkbox:checked');
    var updates = {};
    checkedBoxes.forEach(function(checkbox) {
        var field = checkbox.getAttribute('data-field');
        var value = checkbox.getAttribute('data-value');
        // Allow empty values (for clearing fields) - only check if field name exists
        if (field) {
            updates[field] = value || ''; // Use empty string if value is null/undefined
        }
    });

    // Build Resos API request body (only the fields to update)
    var resosRequestBody = {};
    for (var field in updates) {
        resosRequestBody[field] = updates[field];
    }

    // Build action description
    var updatesList = '';
    if (Object.keys(updates).length > 0) {
        for (var field in updates) {
            updatesList += field + ' → ' + updates[field] + ', ';
        }
        updatesList = updatesList.slice(0, -2); // Remove trailing comma
    } else {
        updatesList = 'No field updates selected';
    }

    // Define the actual API call function
    var executeApiCall = function() {
        // Prepare form data for WordPress AJAX
        var formData = new FormData();
        formData.append('action', 'confirm_resos_match');
        formData.append('nonce', hotelBookingAjax.nonce);
        formData.append('booking_id', bookingId);
        formData.append('updates', JSON.stringify(resosRequestBody));

        // Make AJAX call to WordPress
        fetch(hotelBookingAjax.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Reload the page to show updated data
                location.reload();
            } else {
                console.error('Error:', data.data);
                console.error('Failed to update booking #' + bookingId);
            }
        })
        .catch(function(error) {
            console.error('Request failed:', error);
        });
    };

    var apiMode = window.getApiMode();
    console.log('API Mode:', apiMode);

    // Handle different API modes
    if (apiMode === 'sandbox' || apiMode === 'testing') {
        // For sandbox/testing modes, fetch the preview data first
        var previewFormData = new FormData();
        previewFormData.append('action', 'preview_resos_match');
        previewFormData.append('nonce', hotelBookingAjax.nonce);
        previewFormData.append('booking_id', bookingId);
        previewFormData.append('updates', JSON.stringify(resosRequestBody));

        fetch(hotelBookingAjax.ajaxUrl, {
            method: 'POST',
            body: previewFormData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                var transformedData = data.data.transformed_data;
                var warning = data.data.warning || null;
                if (warning) {
                    console.warn('Preview warning:', warning);
                }

                // Show popup with actual transformed data
                var popupConfig = {
                    method: 'PUT',
                    endpoint: 'https://api.resos.com/v1/bookings/' + bookingId,
                    data: resosRequestBody,
                    transformedData: transformedData,
                    warning: warning,
                    action: 'Confirm Match: Update Resos booking #' + bookingId + ' for Room ' + roomId + ' with changes: ' + updatesList
                };

                if (apiMode === 'testing') {
                    popupConfig.confirmCallback = executeApiCall;
                }

                window.showSandboxPopup(popupConfig);
            } else {
                console.error('Preview failed:', data.data);
                console.error('Failed to generate preview: ' + (data.data.message || 'Unknown error'));
            }
        })
        .catch(function(error) {
            console.error('Preview request failed:', error);
        });
    } else {
        // Production Mode: Direct API call (no dialog)
        executeApiCall();
    }
};

// Select a time slot (just highlights the selection, doesn't create booking)
window.selectTimeSlot = function(time, isAvailable) {
    var roomNumber = window.currentRoomNumber || 'Unknown';

    // Store selected time and availability
    window.selectedTime = time;
    window.selectedTimeIsAvailable = isAvailable;

    // Remove selection from all time buttons for this room
    var timeButtons = document.querySelectorAll('#time-slots-grid-' + roomNumber + ' button[data-time]');
    timeButtons.forEach(function(btn) {
        btn.classList.remove('time-selected');
    });

    // Add selection to clicked button
    var clickedButton = document.querySelector('#time-slots-grid-' + roomNumber + ' button[data-time="' + time + '"]');
    if (clickedButton) {
        clickedButton.classList.add('time-selected');
    }

    // Enable the Create Booking button
    var createButton = document.getElementById('btn-create-booking-' + roomNumber);
    if (createButton) {
        createButton.disabled = false;
    }

    // Show and position the gantt sight line for the selected time
    var sightLine = document.getElementById('gantt-sight-line');
    var ganttTimeline = document.getElementById('gantt-timeline-' + roomNumber);

    if (sightLine && ganttTimeline && window.currentOpeningHours) {
        // Calculate time range from opening hours (same logic as setupTimeSlotHoverEffects)
        var startHour = 18;
        var endHour = 22;

        if (window.currentOpeningHours && Array.isArray(window.currentOpeningHours) && window.currentOpeningHours.length > 0) {
            var earliestOpen = 2400;
            var latestClose = 0;

            window.currentOpeningHours.forEach(function(period) {
                var open = period.open || 1800;
                var close = period.close || 2200;
                if (open < earliestOpen) earliestOpen = open;
                if (close > latestClose) latestClose = close;
            });

            startHour = Math.floor(earliestOpen / 100);
            endHour = Math.floor(latestClose / 100);
            if (latestClose % 100 > 0) {
                endHour++;
            }
        }

        var totalMinutes = (endHour - startHour) * 60;

        // Parse selected time
        var timeParts = time.split(':');
        var hours = parseInt(timeParts[0]);
        var minutes = parseInt(timeParts[1]);

        // Calculate position
        var minutesFromStart = (hours - startHour) * 60 + minutes;
        var positionPercent = (minutesFromStart / totalMinutes) * 100;

        // Show and position the sight line
        sightLine.style.left = positionPercent + '%';
        sightLine.style.display = 'block';
        sightLine.classList.add('sight-line-selected');
    }
};

// Custom confirmation modal
window.showConfirmModal = function(message, onConfirm) {
    // Create modal overlay
    var overlay = document.createElement('div');
    overlay.className = 'custom-modal-overlay';

    // Create modal content
    var modal = document.createElement('div');
    modal.className = 'custom-modal';

    var icon = document.createElement('div');
    icon.className = 'custom-modal-icon';
    icon.innerHTML = '<span class="material-symbols-outlined">warning</span>';

    var messageEl = document.createElement('div');
    messageEl.className = 'custom-modal-message';
    messageEl.textContent = message;

    var buttons = document.createElement('div');
    buttons.className = 'custom-modal-buttons';

    var cancelBtn = document.createElement('button');
    cancelBtn.className = 'custom-modal-btn custom-modal-btn-cancel';
    cancelBtn.innerHTML = '<span class="material-symbols-outlined">close</span> Cancel';
    cancelBtn.onclick = function() {
        document.body.removeChild(overlay);
    };

    var confirmBtn = document.createElement('button');
    confirmBtn.className = 'custom-modal-btn custom-modal-btn-confirm';
    confirmBtn.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Exclude Match';
    confirmBtn.onclick = function() {
        document.body.removeChild(overlay);
        onConfirm();
    };

    buttons.appendChild(cancelBtn);
    buttons.appendChild(confirmBtn);

    modal.appendChild(icon);
    modal.appendChild(messageEl);
    modal.appendChild(buttons);
    overlay.appendChild(modal);

    document.body.appendChild(overlay);

    // Close on overlay click
    overlay.onclick = function(e) {
        if (e.target === overlay) {
            document.body.removeChild(overlay);
        }
    };
};

// Exclude a suggested match by adding NOT-# note to Resos booking
window.excludeMatch = function(resosBookingId, hotelBookingId, uniqueId) {
    var message = 'Are you sure you want to exclude this match?\n\nThis will add a "NOT-#' + hotelBookingId + '" note to the Resos booking to prevent future matching against this booking.';

    window.showConfirmModal(message, function() {
        // Prepare form data for WordPress AJAX
        var formData = new FormData();
        formData.append('action', 'exclude_resos_match');
        formData.append('nonce', hotelBookingAjax.nonce);
        formData.append('resos_booking_id', resosBookingId);
        formData.append('hotel_booking_id', hotelBookingId);

        console.log('Excluding match: Resos booking ' + resosBookingId + ' from hotel booking #' + hotelBookingId);

        // Make AJAX call to WordPress
        fetch(hotelBookingAjax.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('Match excluded successfully');
                alert(data.data.message);
                // Reload the page to show updated data
                location.reload();
            } else {
                console.error('Error:', data.data);
                alert('Failed to exclude match: ' + (data.data.message || 'Unknown error'));
            }
        })
        .catch(function(error) {
            console.error('Request failed:', error);
            alert('Network error while excluding match. Please try again.');
        });
    });
};

// Create booking with the selected time
window.createBookingWithSelectedTime = function(roomNumber) {
    // Prevent duplicate submissions - check if request is already in progress
    if (window.createBookingInProgress) {
        console.log('Create booking already in progress, ignoring duplicate click');
        return;
    }

    var time = window.selectedTime;
    var isAvailable = window.selectedTimeIsAvailable;

    if (!time) {
        alert('Please select a time slot first.');
        return;
    }

    // If time is unavailable, show confirmation dialog
    if (!isAvailable) {
        var confirmed = confirm(
            'TIME UNAVAILABLE\n\n' +
            'The selected time (' + time + ') is showing as unavailable in the restaurant booking system.\n\n' +
            'This may indicate the restaurant is fully booked at this time.\n\n' +
            'Do you want to override and proceed with this time anyway?'
        );

        if (!confirmed) {
            return; // User cancelled, don't proceed
        }
    }

    // Get booking context from window variables
    var bookingDate = window.currentBookingDate || '';
    var guestData = window.currentGuestData || {};
    var partySize = window.currentPartySize || 2;

    // Get form values
    var guestName = document.getElementById('guest-name-' + roomNumber);
    var guestPhone = document.getElementById('guest-phone-' + roomNumber);
    var guestEmail = document.getElementById('guest-email-' + roomNumber);
    var bookingIdInput = document.getElementById('booking-id-' + roomNumber);
    var isHotelGuest = document.getElementById('hotel-guest-' + roomNumber);
    var hasPackage = document.getElementById('dbb-' + roomNumber);
    var partySizeInput = document.getElementById('party-size-' + roomNumber);

    // Get values for building request
    var guestNameValue = guestName ? guestName.value : '';
    var guestPhoneValue = guestPhone ? guestPhone.value : '';
    var guestEmailValue = guestEmail ? guestEmail.value : '';
    var partySizeValue = partySizeInput ? parseInt(partySizeInput.value) : partySize;

    // Get notification checkbox values
    var notificationSms = document.getElementById('notification-sms-' + roomNumber);
    var notificationEmail = document.getElementById('notification-email-' + roomNumber);
    var notificationSmsValue = notificationSms ? notificationSms.checked : false;
    var notificationEmailValue = notificationEmail ? notificationEmail.checked : false;

    // Get customField values
    var bookingRefValue = bookingIdInput ? bookingIdInput.value : '';
    var isHotelGuestValue = isHotelGuest && isHotelGuest.checked ? 'Yes' : '';
    var hasPackageValue = hasPackage && hasPackage.checked ? 'Yes' : '';

    // Get booking note
    var bookingNoteField = document.getElementById('booking-note-' + roomNumber);
    var bookingNoteValue = bookingNoteField ? bookingNoteField.value.trim() : '';

    // Get dietary requirements/allergies (checkboxes - multiselect custom field)
    // Collect choice IDs from dynamically generated checkboxes
    var dietaryRequirementIds = [];
    var dietaryCheckboxes = document.querySelectorAll('#dietary-checkboxes-' + roomNumber + ' .dietary-checkbox');
    dietaryCheckboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            var choiceId = checkbox.getAttribute('data-choice-id');
            if (choiceId) {
                dietaryRequirementIds.push(choiceId);
            }
        }
    });

    var dietaryRequirementsValue = dietaryRequirementIds.join(','); // Comma-separated IDs for backend

    // Get dietary other field (separate custom field)
    var dietOther = document.getElementById('diet-other-' + roomNumber);
    var dietaryOtherValue = dietOther && dietOther.value.trim() ? dietOther.value.trim() : '';

    // Find opening hour ID and name for this time
    var openingHour = window.getOpeningHourForTime(time, window.currentOpeningHours);
    var openingHourId = openingHour ? openingHour.id : null;
    var openingHourName = openingHour ? openingHour.name : null;

    // Note: In testing/sandbox mode, the actual transformed data will be fetched from backend
    // This is just for logging/debugging in production mode
    var resosRequestBody = {
        date: bookingDate,
        time: time,
        people: partySizeValue,
        guest: {
            name: guestNameValue,
            phone: guestPhoneValue,
            email: guestEmailValue,
            notificationSms: notificationSmsValue,
            notificationEmail: notificationEmailValue
        },
        languageCode: 'en'
    };
    if (openingHourId) {
        resosRequestBody.openingHourId = openingHourId;
    }

    // Build action description
    var actionDescription = 'Create New Booking: ' + guestNameValue + ' for room ' + roomNumber +
                           ' on ' + bookingDate + ' at ' + time + ' (' + partySizeValue + ' guests)' +
                           (openingHourName ? ' [' + openingHourName + ']' : '') +
                           (!isAvailable ? ' [OVERRIDE UNAVAILABLE TIME]' : '');

    // Get the create booking button reference
    var createButton = document.getElementById('btn-create-booking-' + roomNumber);

    // Define the actual API call function
    var executeApiCall = function() {
        // Set in-flight flag to prevent duplicate submissions
        window.createBookingInProgress = true;

        // Disable button and update UI
        if (createButton) {
            createButton.disabled = true;
            createButton.innerHTML = '<span class="material-symbols-outlined">schedule</span> Creating...';
        }
        console.log('Create Booking - Making API call...');
        console.log('Room Number:', roomNumber);
        console.log('Request Body:', resosRequestBody);

        // Prepare form data for WordPress AJAX
        var formData = new FormData();
        formData.append('action', 'create_resos_booking');
        formData.append('nonce', hotelBookingAjax.nonce);
        formData.append('date', bookingDate);
        formData.append('time', time);
        formData.append('people', partySizeValue);
        formData.append('guest_name', guestNameValue);
        formData.append('guest_phone', guestPhoneValue);
        formData.append('guest_email', guestEmailValue);
        formData.append('notification_sms', notificationSmsValue ? '1' : '0');
        formData.append('notification_email', notificationEmailValue ? '1' : '0');
        formData.append('referrer', window.location.href);
        formData.append('language_code', 'en');
        if (openingHourId) {
            formData.append('opening_hour_id', openingHourId);
        }

        // Add customFields if present
        if (bookingRefValue) {
            formData.append('hotel_booking_ref', bookingRefValue);
        }
        if (isHotelGuestValue) {
            formData.append('is_hotel_guest', isHotelGuestValue);
        }
        if (hasPackageValue) {
            formData.append('has_dbb', hasPackageValue);
        }

        // Add booking note if present
        if (bookingNoteValue) {
            formData.append('booking_note', bookingNoteValue);
        }

        // Add dietary requirements if present (multiselect checkboxes)
        if (dietaryRequirementsValue) {
            formData.append('dietary_requirements', dietaryRequirementsValue);
        }

        // Add dietary other field if present (text field)
        if (dietaryOtherValue) {
            formData.append('dietary_other', dietaryOtherValue);
        }

        // Make AJAX call to WordPress
        fetch(hotelBookingAjax.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Keep button disabled and show success state
                if (createButton) {
                    createButton.innerHTML = '<span class="material-symbols-outlined">check_circle</span> Created!';
                }
                // Reload the page to show updated data
                location.reload();
            } else {
                // Reset in-flight flag on error
                window.createBookingInProgress = false;

                // Re-enable button and restore original text
                if (createButton) {
                    createButton.disabled = false;
                    createButton.innerHTML = '<span class="material-symbols-outlined">add_circle</span> Create Booking';
                }

                // Show user-friendly error message
                var errorMessage = data.data && data.data.message ? data.data.message : 'Unknown error occurred';
                alert('Failed to create booking:\n\n' + errorMessage + '\n\nPlease try again or contact support if the problem persists.');

                console.error('Error:', data.data);
                console.error('Failed to create booking');
            }
        })
        .catch(function(error) {
            // Reset in-flight flag on network error
            window.createBookingInProgress = false;

            // Re-enable button and restore original text
            if (createButton) {
                createButton.disabled = false;
                createButton.innerHTML = '<span class="material-symbols-outlined">add_circle</span> Create Booking';
            }

            // Show user-friendly error message
            alert('Network error: Failed to create booking.\n\nPlease check your connection and try again.');

            console.error('Request failed:', error);
        });
    };

    var apiMode = window.getApiMode();
    console.log('API Mode:', apiMode);

    // Handle different API modes
    if (apiMode === 'sandbox' || apiMode === 'testing') {
        // Set in-flight flag and disable button for preview mode too
        window.createBookingInProgress = true;
        if (createButton) {
            createButton.disabled = true;
            createButton.innerHTML = '<span class="material-symbols-outlined">schedule</span> Loading Preview...';
        }

        // For sandbox/testing modes, fetch the preview data first
        var previewFormData = new FormData();
        previewFormData.append('action', 'preview_resos_create');
        previewFormData.append('nonce', hotelBookingAjax.nonce);
        previewFormData.append('date', bookingDate);
        previewFormData.append('time', time);
        previewFormData.append('people', partySizeValue);
        previewFormData.append('guest_name', guestNameValue);
        previewFormData.append('guest_phone', guestPhoneValue);
        previewFormData.append('guest_email', guestEmailValue);
        previewFormData.append('notification_sms', notificationSmsValue ? '1' : '0');
        previewFormData.append('notification_email', notificationEmailValue ? '1' : '0');
        previewFormData.append('referrer', window.location.href);
        previewFormData.append('language_code', 'en');
        if (openingHourId) {
            previewFormData.append('opening_hour_id', openingHourId);
        }

        // Add customFields if present
        if (bookingRefValue) {
            previewFormData.append('hotel_booking_ref', bookingRefValue);
        }
        if (isHotelGuestValue) {
            previewFormData.append('is_hotel_guest', isHotelGuestValue);
        }
        if (hasPackageValue) {
            previewFormData.append('has_dbb', hasPackageValue);
        }

        // Add booking note if present
        if (bookingNoteValue) {
            previewFormData.append('booking_note', bookingNoteValue);
        }

        // Add dietary requirements if present (multiselect checkboxes)
        if (dietaryRequirementsValue) {
            previewFormData.append('dietary_requirements', dietaryRequirementsValue);
        }

        // Add dietary other field if present (text field)
        if (dietaryOtherValue) {
            previewFormData.append('dietary_other', dietaryOtherValue);
        }

        fetch(hotelBookingAjax.ajaxUrl, {
            method: 'POST',
            body: previewFormData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                var transformedData = data.data.transformed_data;

                // Show popup with actual transformed data
                var popupConfig = {
                    method: 'POST',
                    endpoint: 'https://api.resos.com/v1/bookings',
                    data: transformedData,
                    transformedData: transformedData,
                    action: actionDescription
                };

                if (apiMode === 'testing') {
                    popupConfig.confirmCallback = executeApiCall;
                }

                window.showSandboxPopup(popupConfig);
            } else {
                // Reset in-flight flag on preview error
                window.createBookingInProgress = false;

                // Re-enable button and restore original text
                if (createButton) {
                    createButton.disabled = false;
                    createButton.innerHTML = '<span class="material-symbols-outlined">add_circle</span> Create Booking';
                }

                // Show user-friendly error message
                var errorMessage = data.data && data.data.message ? data.data.message : 'Unknown error';
                alert('Failed to generate preview:\n\n' + errorMessage + '\n\nPlease try again or contact support if the problem persists.');

                console.error('Preview failed:', data.data);
                console.error('Failed to generate preview: ' + (data.data.message || 'Unknown error'));
            }
        })
        .catch(function(error) {
            // Reset in-flight flag on network error
            window.createBookingInProgress = false;

            // Re-enable button and restore original text
            if (createButton) {
                createButton.disabled = false;
                createButton.innerHTML = '<span class="material-symbols-outlined">add_circle</span> Create Booking';
            }

            // Show user-friendly error message
            alert('Network error: Failed to load preview.\n\nPlease check your connection and try again.');

            console.error('Preview request failed:', error);
        });
    } else {
        // Production Mode: Direct API call (no dialog)
        executeApiCall();
    }
};

// Fetch available times from Resos API
window.fetchAvailableTimes = function(roomNumber, bookingDate) {
    var partySizeInput = document.getElementById('party-size-' + roomNumber);
    if (!partySizeInput) {
        console.error('Party size input not found for room:', roomNumber);
        return;
    }

    var partySize = parseInt(partySizeInput.value);
    if (!partySize || partySize < 1) {
        console.error('Invalid party size:', partySize);
        return;
    }

    var gridContainer = document.getElementById('time-slots-grid-' + roomNumber);
    if (!gridContainer) {
        console.error('Time slots grid not found for room:', roomNumber);
        return;
    }

    // Show loading state
    gridContainer.innerHTML = '<div class="time-slots-loading">Loading available times...</div>';

    // Build AJAX URL
    var ajaxUrl = hotelBookingAjax.ajaxUrl;
    var url = ajaxUrl + '?action=get_resos_available_times&date=' + encodeURIComponent(bookingDate) + '&people=' + partySize;

    // Make AJAX request
    fetch(url)
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Always show all time slots
            // If API returns times, mark those as available; otherwise all will be greyed out
            var times = [];
            var openingHours = null;
            var specialEvents = null;
            var onlineBookingAvailable = true; // Default to true

            if (data.success && data.data) {
                if (data.data.times && Array.isArray(data.data.times)) {
                    times = data.data.times;
                }
                if (data.data.openingHours) {
                    openingHours = data.data.openingHours;
                    // Store globally for use in selectTimeSlot
                    window.currentOpeningHours = openingHours;
                }
                if (data.data.specialEvents) {
                    specialEvents = data.data.specialEvents;
                }
                if (typeof data.data.onlineBookingAvailable !== 'undefined') {
                    onlineBookingAvailable = data.data.onlineBookingAvailable;
                }
            }

            // Update warning banners in the create booking section (above Gantt)
            var createBookingSection = document.querySelector('.create-booking-section');
            if (createBookingSection) {
                // Build combined warnings (unwrapped)
                var alertsHtml = '';

                // Add online booking closed warning if needed
                if (!onlineBookingAvailable) {
                    alertsHtml += '<div class="special-event-alert online-booking-closed">' +
                        '<span class="material-symbols-outlined">block</span>' +
                        '<div class="special-event-content">' +
                        '<strong>Online Bookings Closed:</strong> ' +
                        '<span>Online bookings for the day closed from main Resos planner screen</span>' +
                        '</div>' +
                        '</div>';
                }

                // Add special events warnings wrapped in horizontal container
                var specialEventsHtml = window.buildSpecialEventsAlert(specialEvents);
                if (specialEventsHtml) {
                    alertsHtml += '<div class="special-events-horizontal">' + specialEventsHtml + '</div>';
                }

                // Update or create the warnings banner
                var existingBanner = createBookingSection.querySelector('.special-events-banner');
                if (existingBanner) {
                    // Update the content
                    existingBanner.innerHTML = alertsHtml;
                    existingBanner.style.display = alertsHtml ? 'block' : 'none';
                } else if (alertsHtml) {
                    // Insert warnings at the beginning of create booking section
                    createBookingSection.insertAdjacentHTML('afterbegin', '<div class="special-events-banner">' + alertsHtml + '</div>');
                }
            }

            // Build time slots with opening hours and special events (no warnings here)
            gridContainer.innerHTML = window.buildTimeSlots(times, openingHours, specialEvents);

            // Populate opening time selector dropdown in header
            if (openingHours) {
                window.populateOpeningTimeSelector(openingHours);
            }

            // Add info message if no times available or if there was an error
            var infoContainer = document.getElementById('time-slots-info-' + roomNumber);
            if (infoContainer) {
                if (times.length === 0) {
                    infoContainer.innerHTML = 'All times shown. No times available from API - all times can be selected with override.';
                    infoContainer.style.display = 'block';
                } else {
                    infoContainer.innerHTML = ''; // Clear any previous message
                    infoContainer.style.display = 'none'; // Hide empty container
                }
            }

            // Rebuild Gantt with opening hours for full day view
            if (openingHours) {
                var ganttTimeline = document.getElementById('gantt-timeline-' + roomNumber);
                if (ganttTimeline) {
                    ganttTimeline.innerHTML = window.buildGanttChart(window.restaurantBookingsData || {}, openingHours, specialEvents, times, onlineBookingAvailable);
                    // Re-setup tooltips after rebuilding
                    window.setupGanttTooltips();
                }
            }

            // Set up hover listeners on time buttons for Gantt scrolling
            window.setupTimeButtonHoverListeners(roomNumber, openingHours);

            // Set up tooltips for time slot buttons
            window.setupTimeSlotTooltips();
        })
        .catch(function(error) {
            // Even on error, show all time slots (all will be greyed out) using default hours
            gridContainer.innerHTML = window.buildTimeSlots([], null, null);

            // Show error message in bottom info container
            var infoContainer = document.getElementById('time-slots-info-' + roomNumber);
            if (infoContainer) {
                infoContainer.innerHTML = 'Error loading availability. All times can be selected with override.';
                infoContainer.style.display = 'block';
            }

            console.error('Fetch error:', error);
        });
};

// Set up tooltips for Gantt chart bars
window.setupGanttTooltips = function() {
    var ganttBars = document.querySelectorAll('.gantt-booking-bar');

    ganttBars.forEach(function(bar) {
        bar.addEventListener('mouseenter', function(e) {
            var name = this.getAttribute('data-name');
            var people = this.getAttribute('data-people');
            var time = this.getAttribute('data-time');
            var room = this.getAttribute('data-room');
            var notesJson = this.getAttribute('data-notes');
            var tablesJson = this.getAttribute('data-tables');

            // Parse notes
            var notes = [];
            try {
                if (notesJson) {
                    notes = JSON.parse(notesJson);
                }
            } catch (err) {
                console.error('Error parsing notes:', err);
            }

            // Parse tables
            var tables = [];
            try {
                if (tablesJson) {
                    tables = JSON.parse(tablesJson);
                }
            } catch (err) {
                console.error('Error parsing tables:', err);
            }

            // Create or get tooltip
            var tooltip = document.getElementById('gantt-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'gantt-tooltip';
                tooltip.className = 'booking-tooltip';
                tooltip.style.position = 'absolute';
                tooltip.style.zIndex = '10000';
                document.body.appendChild(tooltip);
            }

            // Build tables HTML
            var tablesHtml = '';
            if (tables && tables.length > 0) {
                tablesHtml = '<div class="tooltip-row"><strong>Table(s):</strong> ' + tables.join(', ') + '</div>';
            }

            // Build notes HTML
            var notesHtml = '';
            if (notes && notes.length > 0) {
                notesHtml = '<div class="tooltip-row"><strong>Notes:</strong></div>';
                notes.forEach(function(note) {
                    // Handle both string notes and object notes with type/content
                    var noteContent = '';
                    var noteType = 'internal';

                    if (typeof note === 'string') {
                        noteContent = note;
                    } else if (note && typeof note === 'object') {
                        noteContent = note.content || JSON.stringify(note);
                        noteType = note.type || 'internal';
                    }

                    var noteClass = noteType === 'guest' ? 'tooltip-note-box-guest' : 'tooltip-note-box-internal';
                    notesHtml += '<div class="' + noteClass + '">' + noteContent + '</div>';
                });
            }

            tooltip.innerHTML =
                '<div class="tooltip-content">' +
                '<div class="tooltip-row"><strong>Guest:</strong> ' + name + '</div>' +
                '<div class="tooltip-row"><strong>Room:</strong> ' + room + '</div>' +
                '<div class="tooltip-row"><strong>Time:</strong> ' + time + '</div>' +
                '<div class="tooltip-row"><strong>Party Size:</strong> ' + people + ' pax</div>' +
                tablesHtml +
                notesHtml +
                '</div>';

            tooltip.style.display = 'block';

            // Position tooltip near the bar
            var rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + window.scrollX) + 'px';
            tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        });

        bar.addEventListener('mouseleave', function() {
            var tooltip = document.getElementById('gantt-tooltip');
            if (tooltip) {
                tooltip.style.display = 'none';
            }
        });
    });
};

// Set up tooltips for time slot buttons
window.setupTimeSlotTooltips = function() {
    var timeSlotButtons = document.querySelectorAll('.time-slot-btn[data-restriction]');

    timeSlotButtons.forEach(function(button) {
        button.addEventListener('mouseenter', function(e) {
            var restriction = this.getAttribute('data-restriction');
            if (!restriction) return;

            // Create or get tooltip element
            var tooltip = document.getElementById('timeslot-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'timeslot-tooltip';
                tooltip.className = 'timeslot-tooltip';
                tooltip.style.position = 'absolute';
                tooltip.style.display = 'none';
                tooltip.style.zIndex = '10000';
                document.body.appendChild(tooltip);
            }

            // Set tooltip content
            tooltip.innerHTML = '<div class="tooltip-restriction">' +
                '<span class="material-symbols-outlined">warning</span> ' +
                restriction +
                '</div>';

            // Position tooltip
            var rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + window.scrollX) + 'px';
            tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            tooltip.style.display = 'block';
        });

        button.addEventListener('mouseleave', function() {
            var tooltip = document.getElementById('timeslot-tooltip');
            if (tooltip) {
                tooltip.style.display = 'none';
            }
        });
    });
};

// Build comparison row HTML
window.buildComparisonRow = function(data, uniqueId, roomId, matchType) {
    matchType = matchType || 'suggested';
    var html = '<div class="comparison-row-content match-type-' + matchType + '">' +
        '<div class="comparison-table-wrapper">' +
        '<div class="comparison-header">Match Comparison</div>' +
        '<table class="comparison-table">' +
        '<thead>' +
        '<tr>' +
        '<th>Field</th>' +
        '<th>Newbook</th>' +
        '<th>Resos</th>' +
        '<th style="background-color: #fff3cd;">Suggested Updates</th>' +
        '</tr>' +
        '</thead>' +
        '<tbody>';

    var suggestions = data.suggested_updates || {};

    // Name row
    var nameMatch = data.matches && data.matches.name;
    var nameSuggestion = ('name' in suggestions) ? (suggestions.name || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var nameSuggestionCell = ('name' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + nameSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="name" data-value="' + (suggestions.name || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + nameSuggestion + '</td>';
    html += '<tr' + (nameMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>Guest Name</strong></td>' +
        '<td>' + (data.hotel.name || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        '<td>' + (data.resos.name || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        nameSuggestionCell +
        '</tr>';

    // Phone row
    var phoneMatch = data.matches && data.matches.phone;
    var phoneSuggestion = ('phone' in suggestions) ? (suggestions.phone || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var phoneSuggestionCell = ('phone' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + phoneSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="phone" data-value="' + (suggestions.phone || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + phoneSuggestion + '</td>';
    html += '<tr' + (phoneMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>Phone</strong></td>' +
        '<td>' + (data.hotel.phone || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        '<td>' + (data.resos.phone || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        phoneSuggestionCell +
        '</tr>';

    // Email row
    var emailMatch = data.matches && data.matches.email;
    var emailSuggestion = ('email' in suggestions) ? (suggestions.email || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var emailSuggestionCell = ('email' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + emailSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="email" data-value="' + (suggestions.email || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + emailSuggestion + '</td>';
    html += '<tr' + (emailMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>Email</strong></td>' +
        '<td>' + (data.hotel.email || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        '<td>' + (data.resos.email || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        emailSuggestionCell +
        '</tr>';

    // People row
    var peopleMatch = data.matches && data.matches.people;
    var peopleSuggestion = ('people' in suggestions) ? (suggestions.people || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var peopleSuggestionCell = ('people' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + peopleSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="people" data-value="' + (suggestions.people || '') + '"></div></td>' :
        '<td class="suggestion-cell">' + peopleSuggestion + '</td>';
    html += '<tr' + (peopleMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>People</strong></td>' +
        '<td>' + (data.hotel.people || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        '<td>' + (data.resos.people || '<em style="color: #adb5bd;">-</em>') + '</td>' +
        peopleSuggestionCell +
        '</tr>';

    // Tariff/Package row
    var dbbMatch = data.matches && data.matches.dbb;
    var hotelTariff = data.hotel.rate_type || '<em style="color: #adb5bd;">-</em>';
    var resosDBB = data.resos.dbb || '<em style="color: #adb5bd;">-</em>';
    var dbbSuggestion = ('dbb' in suggestions) ? (suggestions.dbb || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var dbbSuggestionCell = ('dbb' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + dbbSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="dbb" data-value="' + (suggestions.dbb || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + dbbSuggestion + '</td>';
    html += '<tr' + (dbbMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>Tariff/Package</strong></td>' +
        '<td>' + hotelTariff + '</td>' +
        '<td>' + resosDBB + '</td>' +
        dbbSuggestionCell +
        '</tr>';

    // Booking # row
    var bookingRefMatch = data.matches && data.matches.booking_ref;
    var hotelBookingRef = data.hotel.booking_id || '<em style="color: #adb5bd;">-</em>';
    var resosBookingRef = data.resos.booking_ref || '<em style="color: #adb5bd;">-</em>';
    var bookingRefSuggestion = ('booking_ref' in suggestions) ? (suggestions.booking_ref || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var bookingRefSuggestionCell = ('booking_ref' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + bookingRefSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="booking_ref" data-value="' + (suggestions.booking_ref || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + bookingRefSuggestion + '</td>';
    html += '<tr' + (bookingRefMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>Booking #</strong></td>' +
        '<td>' + hotelBookingRef + '</td>' +
        '<td>' + resosBookingRef + '</td>' +
        bookingRefSuggestionCell +
        '</tr>';

    // Hotel Guest row
    var hotelGuestValue = data.hotel.is_hotel_guest ? 'Yes' : '<em style="color: #adb5bd;">-</em>';
    var resosHotelGuest = data.resos.hotel_guest || '<em style="color: #adb5bd;">-</em>';
    var hotelGuestSuggestion = ('hotel_guest' in suggestions) ? (suggestions.hotel_guest || '<em style="color: #999;">(Remove)</em>') : '<em style="color: #adb5bd;">-</em>';
    var hotelGuestSuggestionCell = ('hotel_guest' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + hotelGuestSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="hotel_guest" data-value="' + (suggestions.hotel_guest || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + hotelGuestSuggestion + '</td>';
    html += '<tr>' +
        '<td><strong>Hotel Guest</strong></td>' +
        '<td>' + hotelGuestValue + '</td>' +
        '<td>' + resosHotelGuest + '</td>' +
        hotelGuestSuggestionCell +
        '</tr>';

    // Status row
    var hotelStatus = data.hotel.status || '';
    var hotelStatusDisplay = hotelStatus ? hotelStatus : '<em style="color: #adb5bd;">-</em>';
    var resosStatus = data.resos.status || 'request';
    var statusIconName = window.getStatusIcon(resosStatus);
    var statusIconHtml = '<span class="material-symbols-outlined">' + statusIconName + '</span>';
    var statusSuggestion = ('status' in suggestions) ? suggestions.status : '<em style="color: #adb5bd;">-</em>';
    var statusSuggestionIconName = ('status' in suggestions) ? window.getStatusIcon(suggestions.status) : '';
    var statusSuggestionIconHtml = statusSuggestionIconName ? '<span class="material-symbols-outlined">' + statusSuggestionIconName + '</span> ' : '';
    var statusSuggestionCell = ('status' in suggestions) ?
        '<td class="suggestion-cell has-suggestion"><div class="suggestion-cell-content"><span class="suggestion-text">' + statusSuggestionIconHtml + statusSuggestion + '</span><input type="checkbox" class="suggestion-checkbox" data-field="status" data-value="' + (suggestions.status || '').replace(/"/g, '&quot;') + '" checked></div></td>' :
        '<td class="suggestion-cell">' + statusSuggestion + '</td>';
    html += '<tr>' +
        '<td><strong>Status</strong></td>' +
        '<td>' + hotelStatusDisplay + '</td>' +
        '<td>' + statusIconHtml + ' ' + resosStatus + '</td>' +
        statusSuggestionCell +
        '</tr>';

    // Notes row - show search terms and highlight matches
    var notesMatch = data.matches && data.matches.notes;

    // Newbook column shows search terms: "room" / "booking#"
    var hotelRoom = data.hotel.room || '';
    var hotelBookingId = data.hotel.booking_id || '';
    var searchTermsDisplay = hotelRoom && hotelBookingId ?
        '"' + hotelRoom + '" / "' + hotelBookingId + '"' :
        '<em style="color: #adb5bd;">-</em>';

    // Resos column shows notes with matched parts underlined
    var resosNotesDisplay = '<em style="color: #adb5bd;">-</em>';
    if (data.resos.notes) {
        var notesText = data.resos.notes;
        // Escape HTML
        notesText = notesText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Underline matches (room and booking ID)
        if (hotelRoom) {
            var roomRegex = new RegExp('(' + hotelRoom.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            notesText = notesText.replace(roomRegex, '<u>$1</u>');
        }
        if (hotelBookingId) {
            var bookingRegex = new RegExp('(' + hotelBookingId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            notesText = notesText.replace(bookingRegex, '<u>$1</u>');
        }
        resosNotesDisplay = notesText;
    }

    html += '<tr' + (notesMatch ? ' class="match-row"' : '') + '>' +
        '<td><strong>Notes Search</strong></td>' +
        '<td>' + searchTermsDisplay + '</td>' +
        '<td>' + resosNotesDisplay + '</td>' +
        '<td class="suggestion-cell"><em style="color: #adb5bd;">-</em></td>' +
        '</tr>';

    html += '</tbody>' +
        '</table>' +
        '<div class="comparison-note">' +
        'Matching fields are highlighted in green. The "Suggested Updates" column shows recommended changes to synchronize Resos with Newbook data. Review the data above to confirm this is the correct match.' +
        '</div>' +
        '</div>' +
        '<div class="comparison-actions">' +
        '<div class="comparison-actions-buttons">' +
        '<button class="btn-close-comparison" onclick="closeComparisonRow(\'' + uniqueId + '\')">' +
        '<span class="material-symbols-outlined">close</span> Close' +
        '</button>';

    // Add Exclude Match button (only for suggested matches, not confirmed) - FIRST after Close
    var isConfirmedMatch = data.matches && data.matches.booking_ref;
    if (!isConfirmedMatch && data.resos && data.resos.id && data.hotel && data.hotel.booking_id) {
        html += '<button class="btn-exclude-match" onclick="excludeMatch(\'' + data.resos.id + '\', \'' + data.hotel.booking_id + '\', \'' + uniqueId + '\')">' +
            '<span class="material-symbols-outlined">close</span> Exclude Match' +
            '</button>';
    }

    // Add View in Resos button (for all matches) - SECOND after Exclude
    if (data.resos && data.resos.id && data.resos.restaurant_id) {
        var currentDate = (typeof hotelBookingAjax !== 'undefined' && hotelBookingAjax.currentDate) ?
            hotelBookingAjax.currentDate : '';
        var resosUrl = 'https://app.resos.com/' + data.resos.restaurant_id + '/bookings/timetable/' + currentDate + '/' + data.resos.id;
        html += '<button class="btn-view-resos" onclick="window.open(\'' + resosUrl + '\', \'_blank\')">' +
            '<span class="material-symbols-outlined">visibility</span> View in Resos' +
            '</button>';
    }

    // Only show Update button if there are suggested updates - LAST
    var hasSuggestions = suggestions && Object.keys(suggestions).length > 0;
    if (hasSuggestions) {
        var buttonLabel = isConfirmedMatch ? 'Update Selected' : 'Update Selected & Match';
        var buttonClass = isConfirmedMatch ? 'btn-confirm-match btn-update-confirmed' : 'btn-confirm-match';

        html += '<button class="' + buttonClass + '" onclick="confirmMatch(\'' + uniqueId + '\', \'' + data.resos.id + '\', \'' + roomId + '\')">' +
            '<span class="material-symbols-outlined">check_circle</span> ' + buttonLabel +
            '</button>';
    }

    html += '</div>' +
        '</div>' +
        '</div>';

    // Set up checkbox listeners after a brief delay to ensure DOM is ready
    setTimeout(function() {
        window.setupSuggestionCheckboxListeners(uniqueId);
    }, 10);

    return html;
};

// Set up checkbox listeners to grey out unchecked suggestions
window.setupSuggestionCheckboxListeners = function(uniqueId) {
    var comparisonRow = document.querySelector('tr.comparison-row[data-comparison-id="' + uniqueId + '"]');
    if (!comparisonRow) return;

    var checkboxes = comparisonRow.querySelectorAll('.suggestion-checkbox');
    checkboxes.forEach(function(checkbox) {
        // Function to update row styling based on checkbox state
        var updateRowStyling = function() {
            var suggestionCell = checkbox.closest('.suggestion-cell');
            if (!suggestionCell) return;

            var tableRow = suggestionCell.closest('tr');
            if (!tableRow) return;

            if (checkbox.checked) {
                // Remove greyed-out class when checked
                tableRow.classList.remove('suggestion-unchecked');
            } else {
                // Add greyed-out class when unchecked
                tableRow.classList.add('suggestion-unchecked');
            }
        };

        // Set initial state
        updateRowStyling();

        // Add change listener
        checkbox.addEventListener('change', updateRowStyling);
    });
};

// Load restaurant bookings data from JSON script tag
window.restaurantBookingsData = {};
try {
    var dataElement = document.getElementById('restaurant-bookings-data');
    if (dataElement) {
        window.restaurantBookingsData = JSON.parse(dataElement.textContent);
    }
} catch(e) {
    console.error('Error loading restaurant bookings data:', e);
}

// Build special events alert banner
window.buildSpecialEventsAlert = function(specialEvents) {
    if (!specialEvents || !Array.isArray(specialEvents) || specialEvents.length === 0) {
        return ''; // No special events
    }

    var alertsHtml = '';

    specialEvents.forEach(function(event) {
        // Skip special events that are OPEN (isOpen = true)
        // These are handled as opening hours, not restrictions
        if (event.isOpen === true) {
            return; // Skip this event
        }

        var eventName = event.name || 'Service unavailable';
        var timeInfo = '';

        if (event.open && event.close) {
            var openHour = Math.floor(event.open / 100);
            var openMin = event.open % 100;
            var closeHour = Math.floor(event.close / 100);
            var closeMin = event.close % 100;

            timeInfo = openHour + ':' + (openMin < 10 ? '0' + openMin : openMin) + ' - ' +
                closeHour + ':' + (closeMin < 10 ? '0' + closeMin : closeMin);
        }

        alertsHtml += '<div class="special-event-alert">' +
            '<span class="material-symbols-outlined">warning</span>' +
            '<div class="special-event-content">' +
            (timeInfo ? '<strong class="special-event-time">' + timeInfo + ':</strong>' : '<strong class="special-event-time">All Day:</strong>') +
            '<span>' + (eventName || 'Restricted Service') + '</span>' +
            '</div>' +
            '</div>';
    });

    return alertsHtml; // Return just the alerts, not wrapped
};

// Build create booking section HTML
window.buildCreateBookingSection = function(roomNumber, bookingDate, partySize, guestData) {
    partySize = partySize || 2; // Default to 2 if not provided
    guestData = guestData || {};

    // Store booking context for later use (in selectTimeSlot, etc.)
    window.currentRoomNumber = roomNumber;
    window.currentBookingDate = bookingDate;
    window.currentGuestData = guestData;
    window.currentPartySize = partySize;

    // Get special events from page load data (will be empty initially, updated via AJAX)
    var specialEvents = (typeof hotelBookingAjax !== 'undefined' && hotelBookingAjax.specialEvents) ? hotelBookingAjax.specialEvents : [];

    // Extract guest data with defaults
    var guestName = guestData.name || '';
    var guestPhone = guestData.phone || '';
    var guestEmail = guestData.email || '';
    var bookingId = guestData.booking_id || '';
    var hasPackage = guestData.has_package || false;

    // Build special events banner (will be updated via AJAX)
    var specialEventsHtml = window.buildSpecialEventsAlert(specialEvents);
    var bannerHtml = specialEventsHtml ? '<div class="special-events-banner"><div class="special-events-horizontal">' + specialEventsHtml + '</div></div>' : '';

    // Format the date for display
    var displayDate = bookingDate;
    try {
        var dateObj = new Date(bookingDate + 'T00:00:00'); // Add time to avoid timezone issues
        var options = { day: 'numeric', month: 'short', year: 'numeric' };
        displayDate = dateObj.toLocaleDateString('en-GB', options);
    } catch(e) {
        displayDate = bookingDate; // Fallback to original format if parsing fails
    }

    var html = '<div class="comparison-row-content match-type-create">' +
        '<div class="create-booking-section">' +
        bannerHtml +
        '<div class="gantt-container">' +
        '<div class="gantt-header">Restaurant Bookings for ' + displayDate + '</div>' +
        '<div class="gantt-timeline" id="gantt-timeline-' + roomNumber + '">' +
        '<div class="gantt-loading">Loading restaurant bookings...</div>' +
        '</div>' +
        '</div>' +
        '<div class="booking-form-container">' +
        '<div class="booking-form-row">' +
        '<div class="form-field form-field-name">' +
        '<label for="guest-name-' + roomNumber + '">Guest Name:</label>' +
        '<input type="text" id="guest-name-' + roomNumber + '" class="form-input" value="' + guestName.replace(/"/g, '&quot;') + '">' +
        '</div>' +
        '<div class="form-field form-field-phone">' +
        '<label for="guest-phone-' + roomNumber + '">Phone:</label>' +
        '<input type="text" id="guest-phone-' + roomNumber + '" class="form-input" value="' + guestPhone.replace(/"/g, '&quot;') + '">' +
        '</div>' +
        '<div class="form-field form-field-email">' +
        '<label for="guest-email-' + roomNumber + '">Email:</label>' +
        '<input type="email" id="guest-email-' + roomNumber + '" class="form-input" value="' + guestEmail.replace(/"/g, '&quot;') + '">' +
        '</div>' +
        '<div class="form-field form-field-booking-id">' +
        '<label for="booking-id-' + roomNumber + '">Booking #:</label>' +
        '<input type="text" id="booking-id-' + roomNumber + '" class="form-input" value="' + bookingId.replace(/"/g, '&quot;') + '">' +
        '</div>' +
        '<div class="form-field form-field-party-size">' +
        '<label for="party-size-' + roomNumber + '">Party Size:</label>' +
        '<input type="number" id="party-size-' + roomNumber + '" class="form-input" value="' + partySize + '" min="1" max="20" ' +
        'data-room="' + roomNumber + '" data-date="' + bookingDate + '" ' +
        'onchange="fetchAvailableTimes(\'' + roomNumber + '\', \'' + bookingDate + '\')">' +
        '</div>' +
        '<div class="form-field form-field-checkboxes-guest">' +
        '<label for="hotel-guest-' + roomNumber + '">' +
        '<input type="checkbox" id="hotel-guest-' + roomNumber + '" checked> Hotel Guest' +
        '</label>' +
        '<label for="dbb-' + roomNumber + '">' +
        '<input type="checkbox" id="dbb-' + roomNumber + '"' + (hasPackage ? ' checked' : '') + '> Package/DBB' +
        '</label>' +
        '</div>' +
        '<div class="form-field form-field-checkboxes-notifications">' +
        '<label for="notification-sms-' + roomNumber + '">' +
        '<input type="checkbox" id="notification-sms-' + roomNumber + '"' + (guestPhone ? ' checked' : '') + '> Allow SMS' +
        '</label>' +
        '<label for="notification-email-' + roomNumber + '">' +
        '<input type="checkbox" id="notification-email-' + roomNumber + '"' + (guestEmail ? ' checked' : '') + '> Allow Email' +
        '</label>' +
        '</div>' +
        '<div class="form-field form-field-allergies-button">' +
        '<button type="button" class="btn-allergies" onclick="toggleAllergiesSection(\'' + roomNumber + '\')">' +
        '<span class="material-symbols-outlined">expand_more</span> Allergies' +
        '</button>' +
        '</div>' +
        '<div class="form-field form-field-note-button">' +
        '<button type="button" class="btn-note" onclick="toggleNoteSection(\'' + roomNumber + '\')">' +
        '<span class="material-symbols-outlined">add</span> Note' +
        '</button>' +
        '</div>' +
        '<div class="allergies-section" id="allergies-section-' + roomNumber + '" style="display: none;">' +
        '<div class="allergies-content">' +
        '<label class="dietary-label">Dietary Requirements:</label>' +
        '<div class="dietary-checkboxes" id="dietary-checkboxes-' + roomNumber + '"></div>' +
        '<div class="dietary-other">' +
        '<label for="diet-other-' + roomNumber + '">Other:</label>' +
        '<input type="text" id="diet-other-' + roomNumber + '" class="form-input" placeholder="Please specify...">' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="note-section" id="note-section-' + roomNumber + '" style="display: none;">' +
        '<div class="note-content">' +
        '<label for="booking-note-' + roomNumber + '">Booking Note:</label>' +
        '<textarea id="booking-note-' + roomNumber + '" class="form-textarea" rows="3" placeholder="Add any special requests or notes for this booking..."></textarea>' +
        '</div>' +
        '</div>' +
        '<div class="time-slots-container">' +
        '<div class="time-slots-grid" id="time-slots-grid-' + roomNumber + '">' +
        '<div class="time-slots-loading">Checking availability...</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="comparison-actions">' +
        '<div class="time-slots-info-bottom" id="time-slots-info-' + roomNumber + '"></div>' +
        '<div class="comparison-actions-buttons">' +
        '<button class="btn-create-booking" id="btn-create-booking-' + roomNumber + '" onclick="createBookingWithSelectedTime(\'' + roomNumber + '\')" disabled>' +
        '<span class="material-symbols-outlined">add_circle</span> Create Booking' +
        '</button>' +
        '<button class="btn-close-comparison" onclick="closeComparisonRow(\'create-' + roomNumber + '\')">' +
        '<span class="material-symbols-outlined">close</span> Close' +
        '</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';

    return html;
};

/**
 * Populate dietary checkboxes dynamically from Resos choices
 */
window.populateDietaryCheckboxes = function(roomNumber) {
    var container = document.getElementById('dietary-checkboxes-' + roomNumber);
    if (!container) {
        console.error('Dietary checkboxes container not found for room:', roomNumber);
        return;
    }

    // Clear any existing checkboxes
    container.innerHTML = '';

    if (!window.dietaryChoices || window.dietaryChoices.length === 0) {
        container.innerHTML = '<p>Loading dietary options...</p>';
        return;
    }

    // Generate checkbox for each choice
    window.dietaryChoices.forEach(function(choice) {
        if (choice._id && choice.name) {
            var label = document.createElement('label');
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = 'diet-choice-' + choice._id + '-' + roomNumber;
            checkbox.className = 'dietary-checkbox';
            checkbox.setAttribute('data-choice-id', choice._id);
            checkbox.setAttribute('data-choice-name', choice.name);

            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(' ' + choice.name));
            container.appendChild(label);
        }
    });
};

// Setup auto-check for notification checkboxes based on field values
window.setupNotificationAutoCheck = function(roomNumber) {
    var phoneField = document.getElementById('guest-phone-' + roomNumber);
    var emailField = document.getElementById('guest-email-' + roomNumber);
    var smsCheckbox = document.getElementById('notification-sms-' + roomNumber);
    var emailCheckbox = document.getElementById('notification-email-' + roomNumber);

    if (phoneField && smsCheckbox) {
        phoneField.addEventListener('input', function() {
            var hasValue = this.value.trim().length > 0;
            smsCheckbox.checked = hasValue;
        });
    }

    if (emailField && emailCheckbox) {
        emailField.addEventListener('input', function() {
            var hasValue = this.value.trim().length > 0;
            emailCheckbox.checked = hasValue;
        });
    }
};

// Build Gantt chart visualization
window.buildGanttChart = function(bookings, openingHours, specialEvents, availableTimes, onlineBookingAvailable) {
    // Determine time range from opening hours or use defaults
    var startHour = 18;
    var endHour = 22;

    if (openingHours && Array.isArray(openingHours) && openingHours.length > 0) {
        // Find earliest open time and latest close time across all periods
        var earliestOpen = 2400; // Start with max time
        var latestClose = 0;     // Start with min time

        openingHours.forEach(function(period) {
            var open = period.open || 1800;
            var close = period.close || 2200;

            if (open < earliestOpen) earliestOpen = open;
            if (close > latestClose) latestClose = close;
        });

        // Convert HHMM to hours
        startHour = Math.floor(earliestOpen / 100);
        endHour = Math.floor(latestClose / 100);

        // If close time has minutes, round up to next hour for display
        if (latestClose % 100 > 0) {
            endHour++;
        }
    }

    var totalMinutes = (endHour - startHour) * 60;
    var bookingDuration = 120; // Default booking duration in minutes (2 hours)

    // Collect all bookings into a flat array with time data for sorting
    var allBookings = [];
    for (var key in bookings) {
        if (bookings.hasOwnProperty(key)) {
            var bookingList = bookings[key];
            bookingList.forEach(function(booking) {
                if (booking.resos_booking && booking.resos_booking.time) {
                    var time = booking.resos_booking.time;
                    var people = booking.resos_booking.people || 2;
                    var name = booking.resos_booking.name || 'Guest';
                    var room = booking.resos_booking.room || 'Unknown';
                    var notes = booking.resos_booking.notes || [];
                    var tables = booking.resos_booking.tables || [];

                    var timeParts = time.split(':');
                    var hours = parseInt(timeParts[0]);
                    var minutes = parseInt(timeParts[1]);

                    var minutesFromStart = (hours - startHour) * 60 + minutes;
                    if (minutesFromStart >= 0 && minutesFromStart < totalMinutes) {
                        allBookings.push({
                            time: time,
                            people: people,
                            name: name,
                            room: room,
                            notes: notes,
                            tables: tables,
                            hours: hours,
                            minutes: minutes,
                            minutesFromStart: minutesFromStart
                        });
                    }
                }
            });
        }
    }

    // Sort by start time (earliest at top)
    allBookings.sort(function(a, b) {
        return a.minutesFromStart - b.minutesFromStart;
    });

    // Build the gantt bars using grid-based layout for consistent spacing
    // Fixed grid row height, bookings span multiple rows based on party size
    var GRID_ROW_HEIGHT = 14; // Fixed height per grid row
    var MAX_PARTY_SIZE = 20;  // Cap party size for row span calculation (20 people = 11 rows)
    var gridRows = []; // Each grid row tracks occupied time segments

    allBookings.forEach(function(booking) {
        var bookingStart = booking.minutesFromStart;
        var bookingEnd = bookingStart + bookingDuration;
        if (bookingEnd > totalMinutes) {
            bookingEnd = totalMinutes; // Cap at end of day
        }

        // Calculate how many grid rows this booking should span (based on party size)
        // 1-3 people = 2 rows, 4-5 = 3 rows, 6-7 = 4 rows, ..., 19-20 = 11 rows
        var partySize = Math.min(booking.people, MAX_PARTY_SIZE);
        var rowSpan = Math.max(2, Math.floor(partySize / 2) + 1); // Baseline 2 rows, then +1 row per 2 people

        var buffer = 5; // 5-minute buffer between bookings

        // Find the first set of consecutive grid rows that can fit this booking
        var startGridRow = 0;
        var placed = false;

        while (!placed) {
            // Ensure we have enough grid rows
            while (gridRows.length < startGridRow + rowSpan) {
                gridRows.push({ occupied: [] }); // occupied is array of {start, end} time segments
            }

            // Check if rowSpan consecutive grid rows starting at startGridRow are all free
            var canPlace = true;

            for (var r = startGridRow; r < startGridRow + rowSpan; r++) {
                var row = gridRows[r];

                // Check if this time range conflicts with any occupied segment in this row
                for (var i = 0; i < row.occupied.length; i++) {
                    var seg = row.occupied[i];
                    // Check for overlap (with buffer)
                    if (!(bookingEnd + buffer <= seg.start || bookingStart >= seg.end + buffer)) {
                        canPlace = false;
                        break;
                    }
                }

                if (!canPlace) break;
            }

            if (canPlace) {
                // Place the booking here
                // Mark all spanned rows as occupied for this time range
                for (var r = startGridRow; r < startGridRow + rowSpan; r++) {
                    gridRows[r].occupied.push({
                        start: bookingStart,
                        end: bookingEnd
                    });
                }

                // Store placement info on booking object
                booking.gridRow = startGridRow;
                booking.rowSpan = rowSpan;
                placed = true;
            } else {
                // Try next grid row
                startGridRow++;
            }
        }
    });

    // Build HTML for all bookings with grid-based positioning
    var ganttBarsHtml = '';

    allBookings.forEach(function(booking) {
        var leftPercent = (booking.minutesFromStart / totalMinutes) * 100;
        var yPosition = 10 + (booking.gridRow * GRID_ROW_HEIGHT); // Fixed grid positioning
        var barHeight = (booking.rowSpan * GRID_ROW_HEIGHT) - 4; // -4 accounts for border (2px) + gap (2px)

        // Calculate booking end time and cap at endHour
        var bookingEndMinutes = booking.minutesFromStart + bookingDuration;
        var isCapped = false;
        if (bookingEndMinutes > totalMinutes) {
            bookingEndMinutes = totalMinutes;
            isCapped = true;
        }
        var actualBookingWidth = bookingEndMinutes - booking.minutesFromStart;
        var widthPercent = (actualBookingWidth / totalMinutes) * 100;

        // Format: badge with covers + "name - room" (or just name for non-residents)
        var displayText = booking.room === 'Non-Resident'
            ? booking.name
            : booking.name + ' - ' + booking.room;

        // JSON encode notes and tables for data attributes
        var notesJson = JSON.stringify(booking.notes);
        var tablesJson = JSON.stringify(booking.tables);

        // Add 'capped' class if bar extends to the edge
        var barClass = 'gantt-booking-bar' + (isCapped ? ' gantt-bar-capped' : '');

        ganttBarsHtml += '<div class="' + barClass + '" ' +
            'data-name="' + booking.name + '" ' +
            'data-people="' + booking.people + '" ' +
            'data-time="' + booking.time + '" ' +
            'data-room="' + booking.room + '" ' +
            'data-notes=\'' + notesJson.replace(/'/g, '&apos;') + '\' ' +
            'data-tables=\'' + tablesJson.replace(/'/g, '&apos;') + '\' ' +
            'style="left: ' + leftPercent + '%; top: ' + yPosition + 'px; width: ' + widthPercent + '%; height: ' + barHeight + 'px;">' +
            '<span class="gantt-party-size">' + booking.people + '</span>' +
            '<span class="gantt-bar-text">' + displayText + '</span>' +
            '</div>';
    });

    // Calculate total height based on grid rows used
    var totalHeight = gridRows.length > 0 ? 10 + (gridRows.length * GRID_ROW_HEIGHT) + 10 : 80;

    // Build closed time blocks (grey backgrounds for times outside opening hours)
    var closedBlocksHtml = '';
    if (openingHours && Array.isArray(openingHours) && openingHours.length > 0) {
        // Sort opening hours by start time
        var sortedHours = openingHours.slice().sort(function(a, b) {
            return (a.open || 0) - (b.open || 0);
        });

        // Add closed block from start of chart to first opening
        var firstOpen = sortedHours[0].open;
        var firstOpenMinutes = Math.floor(firstOpen / 100) * 60 + (firstOpen % 100);
        var minutesFromChartStart = firstOpenMinutes - (startHour * 60);

        if (minutesFromChartStart > 0) {
            var widthPercent = (minutesFromChartStart / totalMinutes) * 100;
            closedBlocksHtml += '<div class="gantt-closed-block outside-hours" style="left: 0%; width: ' + widthPercent + '%; height: ' + totalHeight + 'px;"></div>';
        }

        // Add closed blocks between opening hour periods
        for (var i = 0; i < sortedHours.length - 1; i++) {
            var currentClose = sortedHours[i].close;
            var nextOpen = sortedHours[i + 1].open;

            var closeMinutes = Math.floor(currentClose / 100) * 60 + (currentClose % 100);
            var openMinutes = Math.floor(nextOpen / 100) * 60 + (nextOpen % 100);

            var gapStart = closeMinutes - (startHour * 60);
            var gapEnd = openMinutes - (startHour * 60);
            var gapDuration = gapEnd - gapStart;

            if (gapDuration > 0) {
                var leftPercent = (gapStart / totalMinutes) * 100;
                var widthPercent = (gapDuration / totalMinutes) * 100;
                closedBlocksHtml += '<div class="gantt-closed-block outside-hours" style="left: ' + leftPercent + '%; width: ' + widthPercent + '%; height: ' + totalHeight + 'px;"></div>';
            }
        }

        // Add closed block from last close to end of chart
        var lastClose = sortedHours[sortedHours.length - 1].close;
        var lastCloseMinutes = Math.floor(lastClose / 100) * 60 + (lastClose % 100);
        var minutesFromClose = (endHour * 60) - lastCloseMinutes;

        if (minutesFromClose > 0) {
            var leftPercent = ((lastCloseMinutes - (startHour * 60)) / totalMinutes) * 100;
            var widthPercent = (minutesFromClose / totalMinutes) * 100;
            closedBlocksHtml += '<div class="gantt-closed-block outside-hours" style="left: ' + leftPercent + '%; width: ' + widthPercent + '%; height: ' + totalHeight + 'px;"></div>';
        }
    }

    // Add grey block for entire day if online booking is closed
    if (typeof onlineBookingAvailable !== 'undefined' && onlineBookingAvailable === false) {
        closedBlocksHtml += '<div class="gantt-closed-block outside-hours" style="left: 0%; width: 100%; height: ' + totalHeight + 'px;"></div>';
    }

    // Add grey blocks for special event restrictions (closed/restricted special dates)
    if (specialEvents && Array.isArray(specialEvents) && specialEvents.length > 0) {
        specialEvents.forEach(function(event) {
            // Skip events that are OPEN (isOpen = true) - these are not restrictions
            if (event.isOpen === true) {
                return;
            }

            // Check if this is a full-day closure (no open/close times)
            if (!event.open && !event.close) {
                // Grey out entire chart
                closedBlocksHtml += '<div class="gantt-closed-block outside-hours" style="left: 0%; width: 100%; height: ' + totalHeight + 'px;"></div>';
                return;
            }

            // Grey out the specific time range
            if (event.open && event.close) {
                var eventOpenMinutes = Math.floor(event.open / 100) * 60 + (event.open % 100);
                var eventCloseMinutes = Math.floor(event.close / 100) * 60 + (event.close % 100);

                var blockStart = eventOpenMinutes - (startHour * 60);
                var blockEnd = eventCloseMinutes - (startHour * 60);
                var blockDuration = blockEnd - blockStart;

                // Only add if within the chart range
                if (blockStart < totalMinutes && blockEnd > 0) {
                    blockStart = Math.max(0, blockStart);
                    blockEnd = Math.min(totalMinutes, blockEnd);
                    blockDuration = blockEnd - blockStart;

                    if (blockDuration > 0) {
                        var leftPercent = (blockStart / totalMinutes) * 100;
                        var widthPercent = (blockDuration / totalMinutes) * 100;
                        closedBlocksHtml += '<div class="gantt-closed-block outside-hours" style="left: ' + leftPercent + '%; width: ' + widthPercent + '%; height: ' + totalHeight + 'px;"></div>';
                    }
                }
            }
        });
    }

    // Add grey blocks for unavailable time slots (fully booked)
    if (openingHours && Array.isArray(openingHours) && availableTimes && Array.isArray(availableTimes)) {
        // Convert available times to a Set for fast lookup
        var availableSet = new Set(availableTimes);

        openingHours.forEach(function(period) {
            var periodStart = period.open || 1800;
            var periodClose = period.close || 2200;
            var interval = period.interval || 15;
            var duration = period.duration || 120;

            // Calculate last seating time (close time - duration)
            var closeHour = Math.floor(periodClose / 100);
            var closeMin = periodClose % 100;
            var durationHours = Math.floor(duration / 60);
            var durationMins = duration % 60;

            closeMin -= durationMins;
            closeHour -= durationHours;
            if (closeMin < 0) {
                closeMin += 60;
                closeHour--;
            }
            var lastSeating = closeHour * 100 + closeMin;

            // Generate all expected time slots for this period
            var currentHour = Math.floor(periodStart / 100);
            var currentMin = periodStart % 100;

            while (true) {
                var currentTime = currentHour * 100 + currentMin;
                if (currentTime > lastSeating) break;

                // Format as HH:MM
                var timeStr = currentHour + ':' + (currentMin < 10 ? '0' + currentMin : currentMin);

                // If this time is NOT available, grey it out
                if (!availableSet.has(timeStr)) {
                    // Calculate position on Gantt
                    var slotMinutes = (currentHour - startHour) * 60 + currentMin;

                    // Only add if within chart range
                    if (slotMinutes >= 0 && slotMinutes < totalMinutes) {
                        var leftPercent = (slotMinutes / totalMinutes) * 100;
                        var widthPercent = (interval / totalMinutes) * 100;
                        closedBlocksHtml += '<div class="gantt-closed-block" style="left: ' + leftPercent + '%; width: ' + widthPercent + '%; height: ' + totalHeight + 'px;"></div>';
                    }
                }

                // Increment by interval
                currentMin += interval;
                if (currentMin >= 60) {
                    currentMin -= 60;
                    currentHour++;
                }
            }
        });
    }

    // Build vertical interval lines (every 15 minutes, including hour marks)
    var intervalLinesHtml = '';
    for (var m = 0; m < totalMinutes; m += 15) {
        if (m > 0) { // Skip the first line at 0 since it's the left edge
            var lineLeftPercent = (m / totalMinutes) * 100;
            intervalLinesHtml += '<div class="gantt-interval-line" style="left: ' + lineLeftPercent + '%; height: ' + totalHeight + 'px;"></div>';
        }
    }

    // Build complete HTML with time axis (half-hourly), interval lines, and bookings
    var html = '<div class="gantt-time-axis">';
    for (var h = startHour; h < endHour; h++) {
        var position1 = ((h - startHour) * 60 / totalMinutes) * 100;
        html += '<div class="gantt-time-label" style="left: ' + position1 + '%;">' + h + ':00</div>';

        // Add half-hour marker
        var position2 = ((h - startHour) * 60 + 30) / totalMinutes * 100;
        html += '<div class="gantt-time-label" style="left: ' + position2 + '%;">' + h + ':30</div>';
    }
    html += '</div>';
    html += '<div class="gantt-bookings" style="height: ' + totalHeight + 'px;">';
    html += closedBlocksHtml; // Add closed blocks first (behind everything)
    html += intervalLinesHtml;
    html += ganttBarsHtml;
    // Add sight line element (hidden by default, shown on time button hover)
    html += '<div class="gantt-sight-line" id="gantt-sight-line" style="height: ' + totalHeight + 'px;"></div>';
    html += '</div>';

    return html;
};

// Build time slot buttons - always show all times, mark unavailable ones as greyed out
window.buildTimeSlots = function(availableTimes, openingHours, specialEvents) {
    var html = '';

    // Convert availableTimes to a Set for fast lookup
    var availableSet = new Set();
    if (availableTimes && Array.isArray(availableTimes)) {
        availableTimes.forEach(function(time) {
            availableSet.add(time);
        });
    }

    // Helper function to check if a time falls within a special event restriction
    function isTimeRestricted(timeStr, specialEvents) {
        if (!specialEvents || !Array.isArray(specialEvents)) {
            return null; // No restrictions
        }

        var timeParts = timeStr.split(':');
        var timeHour = parseInt(timeParts[0]);
        var timeMin = parseInt(timeParts[1]);
        var timeValue = timeHour * 100 + timeMin; // Convert to HHMM format

        for (var i = 0; i < specialEvents.length; i++) {
            var event = specialEvents[i];

            // Skip events that are OPEN (isOpen = true) - these are special open hours, not restrictions
            if (event.isOpen === true) {
                continue; // Not a restriction
            }

            // Check if this is a full-day closure (no open/close times)
            if (!event.open && !event.close) {
                return event.name || 'Service unavailable';
            }

            // Check if time falls within restricted period
            if (event.open && event.close) {
                if (timeValue >= event.open && timeValue < event.close) {
                    return event.name || 'Service unavailable';
                }
            }
        }

        return null; // Not restricted
    }

    // Determine opening hours periods
    var periods = [];

    if (openingHours && Array.isArray(openingHours)) {
        // Multiple opening hours periods (e.g., lunch + dinner)
        periods = openingHours;
    } else if (openingHours && typeof openingHours === 'object') {
        // Single opening hours period (backwards compatibility)
        periods = [openingHours];
    } else {
        // Default fallback - single period
        periods = [{
            open: 1800,
            close: 2200,
            interval: 15,
            duration: 120,
            name: ''
        }];
    }

    // If multiple periods, create tab navigation
    if (periods.length > 1) {
        html += '<div class="time-slots-header-with-tabs">';
        html += '<span class="time-slots-label">Select Session and Time:</span>';
        html += '<div class="time-slots-tabs">';
        periods.forEach(function(period, index) {
            var startTime = period.open || 1800;
            var closeTime = period.close || 2200;
            var periodName = period.name || '';
            var duration = period.duration || 120;

            // Calculate actual last seating time
            var closeHour = Math.floor(closeTime / 100);
            var closeMin = closeTime % 100;
            var durationHours = Math.floor(duration / 60);
            var durationMins = duration % 60;
            closeMin -= durationMins;
            closeHour -= durationHours;
            if (closeMin < 0) {
                closeMin += 60;
                closeHour--;
            }
            var actualEndTime = (closeHour * 100) + closeMin;
            var endHour = Math.floor(actualEndTime / 100);
            var endMin = actualEndTime % 100;
            var startHour = Math.floor(startTime / 100);
            var startMin = startTime % 100;

            var startTimeStr = startHour + ':' + (startMin < 10 ? '0' + startMin : startMin);
            var endTimeStr = endHour + ':' + (endMin < 10 ? '0' + endMin : endMin);
            var tabLabel = periodName || (startTimeStr + ' - ' + endTimeStr);

            // Determine if this should be the active tab (default to last period/dinner)
            var selector = document.getElementById('opening-time-selector');
            var selectedIndex = selector ? parseInt(selector.value) : -1;
            var isActive = false;
            if (selectedIndex >= 0 && !isNaN(selectedIndex)) {
                isActive = (index === selectedIndex);
            } else {
                isActive = (index === periods.length - 1);
            }

            html += '<button class="time-tab' + (isActive ? ' active' : '') + '" ' +
                'data-tab-index="' + index + '" ' +
                'onclick="switchTimeTab(' + index + ')">' +
                tabLabel +
                '</button>';
        });
        html += '</div>'; // Close time-slots-tabs
        html += '</div>'; // Close time-slots-header-with-tabs
    } else {
        // Single period - show header without tabs
        html += '<div class="time-slots-header">';
        html += '<span>Select Time:</span>';
        html += '</div>';
    }

    // Generate time slots for each opening hours period
    periods.forEach(function(period, index) {
        var startTime = period.open || 1800;
        var closeTime = period.close || 2200;
        var interval = period.interval || 15;
        var duration = period.duration || 120; // Booking duration in minutes
        var periodName = period.name || '';

        // Calculate actual last seating time by subtracting booking duration from close time
        // Example: If lunch closes at 16:00 (1600) but duration is 120 min (2 hours),
        // the last seating is at 14:00 (1400) since 16:00 - 2:00 = 14:00
        var closeHour = Math.floor(closeTime / 100);
        var closeMin = closeTime % 100;

        // Convert duration (minutes) to hours and minutes
        var durationHours = Math.floor(duration / 60);
        var durationMins = duration % 60;

        // Subtract duration from close time
        closeMin -= durationMins;
        closeHour -= durationHours;

        // Handle negative minutes
        if (closeMin < 0) {
            closeMin += 60;
            closeHour--;
        }

        // Calculate actual end time (last seating time)
        var actualEndTime = (closeHour * 100) + closeMin;
        var endHour = Math.floor(actualEndTime / 100);
        var endMin = actualEndTime % 100;

        // Convert start time to hours and minutes
        var startHour = Math.floor(startTime / 100);
        var startMin = startTime % 100;

        // Create section header with ACTUAL service time range
        var startTimeStr = startHour + ':' + (startMin < 10 ? '0' + startMin : startMin);
        var endTimeStr = endHour + ':' + (endMin < 10 ? '0' + endMin : endMin);
        var sectionTitle = periodName || (startTimeStr + ' - ' + endTimeStr);

        // Determine if this tab content should be shown
        var selector = document.getElementById('opening-time-selector');
        var selectedIndex = selector ? parseInt(selector.value) : -1;
        var isActive = false;

        if (selectedIndex >= 0 && !isNaN(selectedIndex)) {
            // Use dropdown selection
            isActive = (index === selectedIndex);
        } else {
            // Fallback to last period (dinner)
            isActive = (index === periods.length - 1);
        }

        var sectionId = 'time-section-' + index;
        var activeClass = isActive ? ' active' : '';
        var displayStyle = (isActive || periods.length === 1) ? 'flex' : 'none';

        html += '<div class="time-tab-content' + activeClass + '" id="' + sectionId + '" ' +
            'data-tab-index="' + index + '" ' +
            'style="display: ' + displayStyle + ';">';

        // Generate time slots from open to ACTUAL last seating time (not extended close time)
        var currentHour = startHour;
        var currentMin = startMin;

        while (currentHour < endHour || (currentHour === endHour && currentMin <= endMin)) {
            var timeStr = currentHour + ':' + (currentMin < 10 ? '0' + currentMin : currentMin);

            // Calculate if booking end time would fit within this period's opening hours
            var bookingEndTime = (currentHour * 60 + currentMin + duration);
            var endTimeHour = Math.floor(bookingEndTime / 60);
            var endTimeMin = bookingEndTime % 60;
            var endTimeNumeric = (endTimeHour * 100) + endTimeMin;

            // Check if the booking would fit (end time must be <= period close time)
            var fitsInOpeningHours = endTimeNumeric <= closeTime;

            // Only mark as available if:
            // 1. We have API data AND the time is in the available set
            // 2. AND the booking duration would fit within opening hours
            var isAvailable = availableSet.size > 0 && availableSet.has(timeStr) && fitsInOpeningHours;

            // Check for special event restrictions
            var restrictionReason = isTimeRestricted(timeStr, specialEvents);
            var isRestricted = restrictionReason !== null;

            var btnClass = 'time-slot-btn';
            var restrictionAttr = '';

            // Mark as unavailable if not in available set OR if restricted by special event
            if (!isAvailable || isRestricted) {
                btnClass += ' time-slot-unavailable';

                // Add restriction reason for tooltip
                if (isRestricted) {
                    restrictionAttr = ' data-restriction="' + restrictionReason.replace(/"/g, '&quot;') + '"';
                } else {
                    restrictionAttr = ' data-restriction="No availability"';
                }
            }

            html += '<button class="' + btnClass + '" ' +
                'data-time="' + timeStr + '" ' +
                'data-available="' + isAvailable + '" ' +
                restrictionAttr +
                'onclick="selectTimeSlot(\'' + timeStr + '\', ' + isAvailable + ')">' +
                timeStr + '</button>';

            // Increment by interval minutes
            currentMin += interval;
            if (currentMin >= 60) {
                currentMin -= 60;
                currentHour++;
            }
        }

        html += '</div>'; // Close time-tab-content
    });

    // Store periods data for dropdown population
    window.currentOpeningPeriods = periods;

    return html;
};

// Switch between time tabs
window.switchTimeTab = function(tabIndex) {
    // Get all tabs and tab contents
    var tabs = document.querySelectorAll('.time-tab');
    var tabContents = document.querySelectorAll('.time-tab-content');

    // Deactivate all tabs and hide all contents
    tabs.forEach(function(tab) {
        tab.classList.remove('active');
    });
    tabContents.forEach(function(content) {
        content.classList.remove('active');
        content.style.display = 'none';
    });

    // Activate selected tab and show its content
    var selectedTab = document.querySelector('.time-tab[data-tab-index="' + tabIndex + '"]');
    var selectedContent = document.querySelector('.time-tab-content[data-tab-index="' + tabIndex + '"]');

    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    if (selectedContent) {
        selectedContent.classList.add('active');
        selectedContent.style.display = 'flex';
    }

    // Update the dropdown to match the selected tab
    var selector = document.getElementById('opening-time-selector');
    if (selector) {
        selector.value = tabIndex;
    }
};

// Toggle allergies/dietary section
window.toggleAllergiesSection = function(roomNumber) {
    var section = document.getElementById('allergies-section-' + roomNumber);
    var button = document.querySelector('.btn-allergies');

    if (!section) return;

    if (section.style.display === 'none') {
        section.style.display = 'block';
        if (button) {
            var icon = button.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = 'expand_less';
        }
    } else {
        section.style.display = 'none';
        if (button) {
            var icon = button.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = 'expand_more';
        }
    }
};

// Toggle note section
window.toggleNoteSection = function(roomNumber) {
    var section = document.getElementById('note-section-' + roomNumber);
    var button = document.querySelector('.btn-note');

    if (!section) return;

    if (section.style.display === 'none') {
        section.style.display = 'block';
        if (button) {
            var icon = button.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = 'remove';
        }
    } else {
        section.style.display = 'none';
        if (button) {
            var icon = button.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = 'add';
        }
    }
};

// Switch service period from dropdown
window.switchServicePeriod = function() {
    var selector = document.getElementById('opening-time-selector');
    if (!selector) return;

    var selectedIndex = parseInt(selector.value);
    if (isNaN(selectedIndex)) return;

    // Collapse all sections
    var allSections = document.querySelectorAll('.time-slots-section');
    var allHeaders = document.querySelectorAll('.time-slots-section-header.collapsible');

    allSections.forEach(function(section, idx) {
        section.style.display = 'none';
    });

    allHeaders.forEach(function(header) {
        header.classList.remove('expanded');
        var icon = header.querySelector('.collapse-icon');
        if (icon) icon.textContent = '▶';
    });

    // Expand selected section
    var targetSectionId = 'time-section-' + selectedIndex;
    var targetSection = document.getElementById(targetSectionId);
    var targetHeader = document.querySelector('[data-section-id="' + targetSectionId + '"]');

    if (targetSection) {
        targetSection.style.display = 'flex';
    }

    if (targetHeader) {
        targetHeader.classList.add('expanded');
        var icon = targetHeader.querySelector('.collapse-icon');
        if (icon) icon.textContent = '▼';
    }
};

// Populate opening time selector dropdown
window.populateOpeningTimeSelector = function(openingHours) {
    var selector = document.getElementById('opening-time-selector');
    if (!selector || !openingHours || !Array.isArray(openingHours)) return;

    // Save the current selection before clearing
    var currentSelection = selector.value;

    // Clear existing options
    selector.innerHTML = '';

    // Add options for each period
    openingHours.forEach(function(period, index) {
        var startHour = Math.floor(period.open / 100);
        var startMin = period.open % 100;
        var endHour = Math.floor(period.close / 100);
        var endMin = period.close % 100;

        var startTimeStr = startHour + ':' + (startMin < 10 ? '0' + startMin : startMin);
        var endTimeStr = endHour + ':' + (endMin < 10 ? '0' + endMin : endMin);
        var optionText = period.name || (startTimeStr + ' - ' + endTimeStr);

        var option = document.createElement('option');
        option.value = index;
        option.textContent = optionText;

        selector.appendChild(option);
    });

    // Restore previous selection if it exists and is still valid
    if (currentSelection !== '' && currentSelection !== null) {
        var optionExists = Array.from(selector.options).some(function(opt) {
            return opt.value === currentSelection;
        });

        if (optionExists) {
            selector.value = currentSelection;
        } else {
            // Previous selection no longer valid, default to last period
            selector.value = openingHours.length - 1;
        }
    } else {
        // No previous selection, default to last period (dinner)
        selector.value = openingHours.length - 1;
    }
};

// Set up time button hover listeners for Gantt scroll and sight line
window.setupTimeButtonHoverListeners = function(roomNumber, openingHours) {
    if (!openingHours || !Array.isArray(openingHours) || openingHours.length === 0) {
        return; // No opening hours to work with
    }

    // Find the Gantt timeline container for this room
    var ganttTimeline = document.getElementById('gantt-timeline-' + roomNumber);
    if (!ganttTimeline) {
        return; // Gantt not found
    }

    // Find the sight line element
    var sightLine = document.getElementById('gantt-sight-line');
    if (!sightLine) {
        return; // Sight line not found
    }

    // Calculate time range from opening hours
    var earliestOpen = 2400;
    var latestClose = 0;

    openingHours.forEach(function(period) {
        var open = period.open || 1800;
        var close = period.close || 2200;

        if (open < earliestOpen) earliestOpen = open;
        if (close > latestClose) latestClose = close;
    });

    var startHour = Math.floor(earliestOpen / 100);
    var endHour = Math.floor(latestClose / 100);
    if (latestClose % 100 > 0) {
        endHour++;
    }

    var totalMinutes = (endHour - startHour) * 60;

    // Find all time slot buttons in this room's time slots grid
    var timeSlotButtons = document.querySelectorAll('#time-slots-grid-' + roomNumber + ' .time-slot-btn');

    timeSlotButtons.forEach(function(button) {
        // Mouse enter: show sight line and scroll to time
        button.addEventListener('mouseenter', function() {
            var timeStr = this.getAttribute('data-time');
            if (!timeStr) return;

            // Parse time (format: "HH:MM")
            var timeParts = timeStr.split(':');
            var hours = parseInt(timeParts[0]);
            var minutes = parseInt(timeParts[1]);

            // Calculate position as percentage of total timeline
            var minutesFromStart = (hours - startHour) * 60 + minutes;
            var positionPercent = (minutesFromStart / totalMinutes) * 100;

            // Show and position the sight line
            sightLine.style.left = positionPercent + '%';
            sightLine.style.display = 'block';

            // Scroll the Gantt timeline to center on this time
            // Calculate the scroll position to center the time in the viewport
            var ganttWidth = ganttTimeline.scrollWidth;
            var viewportWidth = ganttTimeline.clientWidth;
            var timePositionPx = (positionPercent / 100) * ganttWidth;
            var scrollPosition = timePositionPx - (viewportWidth / 2);

            // Ensure scroll position is within bounds
            scrollPosition = Math.max(0, Math.min(scrollPosition, ganttWidth - viewportWidth));

            // Smooth scroll to the position
            ganttTimeline.scrollTo({
                left: scrollPosition,
                behavior: 'smooth'
            });
        });

        // Mouse leave: hide sight line (unless it's showing the selected time)
        button.addEventListener('mouseleave', function() {
            // If a time is selected, reposition the sight line back to the selected time
            if (sightLine.classList.contains('sight-line-selected') && window.selectedTime) {
                // Parse selected time
                var selectedTimeParts = window.selectedTime.split(':');
                var selectedHours = parseInt(selectedTimeParts[0]);
                var selectedMinutes = parseInt(selectedTimeParts[1]);

                // Calculate position for selected time
                var selectedMinutesFromStart = (selectedHours - startHour) * 60 + selectedMinutes;
                var selectedPositionPercent = (selectedMinutesFromStart / totalMinutes) * 100;

                // Reposition to selected time
                sightLine.style.left = selectedPositionPercent + '%';
                sightLine.style.display = 'block';
            } else {
                // No selected time, hide the sight line
                sightLine.style.display = 'none';
            }
        });
    });
};

// Update date function
function updateDate() {
    var date = document.getElementById('booking-date').value;
    if (date) {
        var url = new URL(window.location.href);
        url.searchParams.set('date', date);
        window.location.href = url.toString();
    }
}

// Create Resos booking placeholder
function createResosBooking(roomNumber) {
    // TODO: Implement Resos booking creation when ready
    console.log('Create Resos booking for room:', roomNumber);
}

// Tooltip functionality
document.addEventListener('DOMContentLoaded', function() {
    // Create tooltip element
    var tooltip = document.createElement('div');
    tooltip.className = 'booking-tooltip';
    tooltip.style.display = 'none';
    document.body.appendChild(tooltip);

    // Add event listeners to all elements with tooltips
    var tooltipElements = document.querySelectorAll('.has-tooltip');

    tooltipElements.forEach(function(element) {
        element.addEventListener('mouseenter', function(e) {
            var bookingId = this.getAttribute('data-booking-id') || '-';
            var rateType = this.getAttribute('data-rate-type') || '-';
            var rate = this.getAttribute('data-rate') || '-';
            var source = this.getAttribute('data-source') || 'None';
            var agent = this.getAttribute('data-agent') || 'None';
            var notesJson = this.getAttribute('data-notes') || '[]';

            var notes = [];
            try {
                notes = JSON.parse(notesJson);
            } catch(e) {
                notes = [];
            }

            var notesHtml = '';
            if (notes.length > 0) {
                notes.forEach(function(note) {
                    // Handle both string notes and object notes with type/content
                    var noteContent = '';
                    var noteType = '';

                    if (typeof note === 'string') {
                        noteContent = note;
                    } else if (note && typeof note === 'object') {
                        noteContent = note.content || JSON.stringify(note);
                        noteType = note.type || '';
                    }

                    // Add note type as label if available
                    var noteLabel = noteType ? '<strong>' + noteType + ':</strong> ' : '';
                    notesHtml += '<div class="tooltip-note-box">' + noteLabel + noteContent + '</div>';
                });
            } else {
                notesHtml = '<div class="tooltip-note-box"><em>No notes</em></div>';
            }

            tooltip.innerHTML =
                '<div class="tooltip-content">' +
                '<div class="tooltip-row"><strong>Booking ID:</strong> ' + bookingId + '</div>' +
                '<div class="tooltip-row"><strong>Rate Type:</strong> ' + rateType + '</div>' +
                '<div class="tooltip-row"><strong>Rate:</strong> ' + rate + '</div>' +
                '<div class="tooltip-row"><strong>Source:</strong> ' + source + '</div>' +
                '<div class="tooltip-row"><strong>Agent:</strong> ' + agent + '</div>' +
                '<div class="tooltip-row"><strong>Notes:</strong></div>' +
                notesHtml +
                '<div class="tooltip-instruction"><em>Click the guest name to open this booking in Newbook</em></div>' +
                '</div>';

            tooltip.style.display = 'block';

            var rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + window.scrollX) + 'px';
            tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        });

        element.addEventListener('mouseleave', function() {
            tooltip.style.display = 'none';
        });
    });

    // Restaurant booking tooltips
    var resosTooltipElements = document.querySelectorAll('.restaurant-booking.has-comparison-tooltip');
    var resosTooltip = document.createElement('div');
    resosTooltip.className = 'booking-tooltip resos-booking-tooltip';
    resosTooltip.style.display = 'none';
    document.body.appendChild(resosTooltip);

    resosTooltipElements.forEach(function(element) {
        element.addEventListener('mouseenter', function(e) {
            var guestName = this.getAttribute('data-tooltip-guest-name') || '-';
            var phone = this.getAttribute('data-tooltip-phone') || '-';
            var email = this.getAttribute('data-tooltip-email') || '-';
            var status = this.getAttribute('data-status') || 'request';
            var tables = this.getAttribute('data-tooltip-tables') || '-';
            var notesJson = this.getAttribute('data-tooltip-notes') || '[]';
            var bookingId = this.getAttribute('data-tooltip-booking-id') || '';
            var bookingDate = this.getAttribute('data-tooltip-date') || '';

            // Parse notes
            var notes = [];
            try {
                notes = JSON.parse(notesJson);
            } catch (e) {
                console.error('Error parsing restaurant booking notes:', e);
            }

            // Build notes HTML
            var notesHtml = '';
            if (notes && notes.length > 0) {
                notesHtml = '<div class="tooltip-row"><strong>Notes:</strong></div>';
                notes.forEach(function(note) {
                    var noteClass = note.type === 'guest' ? 'tooltip-note-box-guest' : 'tooltip-note-box-internal';
                    notesHtml += '<div class="' + noteClass + '">' + note.content + '</div>';
                });
            } else {
                notesHtml = '<div class="tooltip-row"><strong>Notes:</strong></div><div class="tooltip-note-box"><em>No notes</em></div>';
            }

            // Format status for display
            var statusDisplay = status.charAt(0).toUpperCase() + status.slice(1);

            resosTooltip.innerHTML =
                '<div class="tooltip-content">' +
                '<div class="tooltip-row"><strong>Guest:</strong> ' + guestName + '</div>' +
                '<div class="tooltip-row"><strong>Phone:</strong> ' + phone + '</div>' +
                '<div class="tooltip-row"><strong>Email:</strong> ' + email + '</div>' +
                '<div class="tooltip-row"><strong>Status:</strong> ' + statusDisplay + '</div>' +
                '<div class="tooltip-row"><strong>Table(s):</strong> ' + tables + '</div>' +
                notesHtml +
                '<div class="tooltip-instruction"><em>Click to open this booking in Resos</em></div>' +
                '</div>';

            resosTooltip.style.display = 'block';

            var rect = this.getBoundingClientRect();
            resosTooltip.style.left = (rect.left + window.scrollX) + 'px';
            resosTooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
        });

        element.addEventListener('mouseleave', function() {
            resosTooltip.style.display = 'none';
        });

        // Add click handler to open Resos booking in new tab
        element.addEventListener('click', function(e) {
            // Get data from element attributes
            var restaurantId = this.getAttribute('data-tooltip-restaurant-id') || '';
            var bookingId = this.getAttribute('data-tooltip-booking-id') || '';
            var bookingDate = this.getAttribute('data-tooltip-date') || '';

            // Only open link if we have all required data
            if (restaurantId && bookingId && bookingDate) {
                var url = 'https://app.resos.com/' + restaurantId + '/bookings/timetable/' + bookingDate + '/' + bookingId;
                window.open(url, '_blank', 'noopener,noreferrer');
            } else {
                console.warn('Missing data for Resos booking link:', {
                    restaurantId: restaurantId,
                    bookingId: bookingId,
                    bookingDate: bookingDate
                });
            }
        });
    });

    // Populate opening hours dropdown on page load (if opening hours are available)
    if (typeof hotelBookingAjax !== 'undefined' && hotelBookingAjax.openingHours) {
        var openingHours = hotelBookingAjax.openingHours;

        // Populate the dropdown selector
        if (openingHours && Array.isArray(openingHours) && openingHours.length > 0) {
            window.populateOpeningTimeSelector(openingHours);
        }
    }

    // Handle auto-action URL parameter (from Chrome extension deep links)
    window.handleAutoAction = function() {
        var urlParams = new URLSearchParams(window.location.search);
        var autoAction = urlParams.get('auto-action');
        var resosId = urlParams.get('resos_id');
        var bookingId = urlParams.get('booking_id');
        var date = urlParams.get('date');

        if (!autoAction || !bookingId) {
            return; // No auto-action or booking_id, exit
        }

        console.log('Handling auto-action:', autoAction);

        // Wait a bit longer to ensure all elements are rendered
        setTimeout(function() {
            var bookingRow = document.querySelector('tr[data-booking-id="' + bookingId + '"]');
            if (!bookingRow) {
                console.warn('Booking row not found for auto-action');
                return;
            }

            if (autoAction === 'match' && resosId) {
                // Find the restaurant booking element with this resos_id within the booking row
                var restaurantBookings = bookingRow.querySelectorAll('.restaurant-booking');
                var targetBooking = null;

                restaurantBookings.forEach(function(booking) {
                    var tooltipBookingId = booking.getAttribute('data-tooltip-booking-id');
                    if (tooltipBookingId === resosId) {
                        targetBooking = booking;
                    }
                });

                if (targetBooking) {
                    // Get the unique-id and trigger the comparison row
                    var uniqueId = targetBooking.getAttribute('data-unique-id');
                    var roomNumber = targetBooking.getAttribute('data-room');
                    var isConfirmed = targetBooking.classList.contains('confirmed-match');
                    var matchType = isConfirmed ? 'primary' : 'suggested';

                    console.log('Auto-opening comparison row:', uniqueId, matchType);

                    // Trigger the comparison row
                    if (targetBooking.classList.contains('expandable-match') || isConfirmed) {
                        targetBooking.click();
                    } else {
                        // Fallback: call toggleComparisonRow directly
                        window.toggleComparisonRow(uniqueId, roomNumber, matchType);
                    }
                } else {
                    console.warn('Restaurant booking not found for resos_id:', resosId);
                }

            } else if (autoAction === 'create' && date) {
                // Find the "Create Booking" button for this booking row
                var createButton = bookingRow.querySelector('.btn-create-booking');

                if (createButton) {
                    console.log('Auto-opening create booking form');

                    // Trigger the create booking button
                    createButton.click();
                } else {
                    console.warn('Create booking button not found');
                }
            }

            // Clean up URL parameters after handling (optional)
            var url = new URL(window.location.href);
            url.searchParams.delete('auto-action');
            url.searchParams.delete('resos_id');
            window.history.replaceState({}, '', url.toString());

        }, 1000); // Wait 1 second for all elements to render
    };

    // Handle booking deep linking via booking_id URL parameter
    window.handleBookingDeepLink = function() {
        var urlParams = new URLSearchParams(window.location.search);
        var bookingId = urlParams.get('booking_id');
        var date = urlParams.get('date');

        if (!bookingId) {
            return; // No booking_id parameter, exit
        }

        console.log('Deep linking to booking ID:', bookingId);

        // If date is already provided in URL (e.g., from Chrome extension),
        // just scroll to the booking without fetching
        if (date) {
            console.log('Date already provided:', date);
            setTimeout(function() {
                var row = document.querySelector('tr[data-booking-id="' + bookingId + '"]');
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Highlight the row briefly
                    row.style.transition = 'background-color 0.3s';
                    row.style.backgroundColor = '#fff3cd';

                    setTimeout(function() {
                        row.style.backgroundColor = '';
                    }, 2000);

                    // Handle auto-action after scrolling
                    window.handleAutoAction();
                } else {
                    console.warn('Booking row not found for ID:', bookingId);
                }
            }, 500);
            return;
        }

        // Fetch booking data from NewBook API
        fetch(hotelBookingAjax.ajaxUrl + '?action=get_booking_by_id&booking_id=' + bookingId, {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (!data.success) {
                console.error('Failed to fetch booking:', data.data.message);
                alert('Booking not found: ' + (data.data.message || 'Unknown error'));
                return;
            }

            var bookingData = data.data;
            console.log('Booking data:', bookingData);

            if (bookingData.num_nights === 1) {
                // Single night stay - auto load that date
                var date = bookingData.nights[0];
                window.loadDateAndScrollToBooking(date, bookingId);
            } else if (bookingData.num_nights > 1) {
                // Multiple nights - show date selection popup
                window.showNightSelectionPopup(bookingData.nights, bookingId);
            } else {
                alert('No valid nights found for this booking');
            }
        })
        .catch(function(error) {
            console.error('Error fetching booking:', error);
            alert('Error fetching booking data');
        });
    };

    // Load a specific date and scroll to booking row
    window.loadDateAndScrollToBooking = function(date, bookingId) {
        // Update the date picker and reload the page
        var url = new URL(window.location.href);
        var currentParams = new URLSearchParams(window.location.search);

        url.searchParams.set('date', date);

        // Remove booking_id so we don't trigger deep link again
        url.searchParams.delete('booking_id');

        // Preserve auto-action and resos_id parameters if they exist
        if (currentParams.has('auto-action')) {
            url.searchParams.set('auto-action', currentParams.get('auto-action'));
        }
        if (currentParams.has('resos_id')) {
            url.searchParams.set('resos_id', currentParams.get('resos_id'));
        }

        // Add scroll target
        url.hash = 'booking-' + bookingId;

        window.location.href = url.toString();
    };

    // Show popup for selecting which night to view (multi-night stays)
    window.showNightSelectionPopup = function(nights, bookingId) {
        // Create popup overlay
        var overlay = document.createElement('div');
        overlay.id = 'night-selection-overlay';
        overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';

        // Create popup content
        var popup = document.createElement('div');
        popup.style.cssText = 'background: white; padding: 30px; border-radius: 8px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';

        var html = '<h2 style="margin-top: 0; color: #333;">Select Night to View</h2>';
        html += '<p style="margin-bottom: 20px; color: #666;">This booking spans multiple nights. Please select which night you\'d like to view:</p>';
        html += '<div style="display: flex; flex-direction: column; gap: 10px;">';

        nights.forEach(function(night, index) {
            var nightDate = new Date(night);
            var formatted = nightDate.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: '2-digit'
            });
            html += '<button class="night-select-btn" data-date="' + night + '" style="padding: 12px 20px; font-size: 16px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s;">';
            html += 'Night ' + (index + 1) + ': ' + formatted;
            html += '</button>';
        });

        html += '</div>';
        html += '<button id="cancel-night-selection" style="margin-top: 20px; padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%;">Cancel</button>';

        popup.innerHTML = html;
        overlay.appendChild(popup);
        document.body.appendChild(overlay);

        // Add hover effect to buttons
        var style = document.createElement('style');
        style.textContent = '.night-select-btn:hover { background: #5568d3 !important; }';
        document.head.appendChild(style);

        // Add click handlers
        popup.querySelectorAll('.night-select-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var selectedDate = this.getAttribute('data-date');
                document.body.removeChild(overlay);
                window.loadDateAndScrollToBooking(selectedDate, bookingId);
            });
        });

        document.getElementById('cancel-night-selection').addEventListener('click', function() {
            document.body.removeChild(overlay);

            // Remove booking_id from URL
            var url = new URL(window.location.href);
            url.searchParams.delete('booking_id');
            window.history.replaceState({}, '', url.toString());
        });
    };

    // Scroll to booking row if hash is present
    if (window.location.hash) {
        var hash = window.location.hash.substring(1); // Remove #
        if (hash.startsWith('booking-')) {
            setTimeout(function() {
                var bookingId = hash.replace('booking-', '');
                var row = document.querySelector('tr[data-booking-id="' + bookingId + '"]');

                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Highlight the row briefly
                    row.style.transition = 'background-color 0.3s';
                    row.style.backgroundColor = '#fff3cd';

                    setTimeout(function() {
                        row.style.backgroundColor = '';
                    }, 2000);

                    // Handle auto-action after scrolling
                    window.handleAutoAction();
                } else {
                    console.warn('Booking row not found for ID:', bookingId);
                }
            }, 500); // Wait for table to render
        }
    }

    // Initialize deep linking
    window.handleBookingDeepLink();
});
