<?php
/**
 * Plugin Name: Reservation Management Integration for NewBook & ResOS
 * Plugin URI: https://yourwebsite.com
 * Description: Integrates NewBook PMS hotel bookings with ResOS restaurant reservations. Displays bookings, enables matching, and allows creation/updating of restaurant bookings. Use shortcode [hotel-table-bookings-by-date] or [rmi-bookings-table]
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rmi-newbook-resos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Hotel_Booking_Table {

    const VERSION = '2.0.0';
    const JS_VERSION = '2.0.0';

    private $errors = array();
    private $api_base_url = 'https://api.newbook.cloud/rest/';
    
    public function __construct() {
        // Legacy shortcode for backward compatibility
        add_shortcode('hotel-table-bookings-by-date', array($this, 'render_booking_table'));
        // New shortcode with RMI prefix
        add_shortcode('rmi-bookings-table', array($this, 'render_booking_table'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX handlers
        add_action('wp_ajax_get_resos_available_times', array($this, 'ajax_get_available_times'));
        add_action('wp_ajax_nopriv_get_resos_available_times', array($this, 'ajax_get_available_times'));
        add_action('wp_ajax_test_resos_dates', array($this, 'test_available_dates'));
        add_action('wp_ajax_nopriv_test_resos_dates', array($this, 'test_available_dates'));
        add_action('wp_ajax_confirm_resos_match', array($this, 'ajax_confirm_resos_match'));
        add_action('wp_ajax_nopriv_confirm_resos_match', array($this, 'ajax_confirm_resos_match'));
        add_action('wp_ajax_preview_resos_match', array($this, 'ajax_preview_resos_match'));
        add_action('wp_ajax_nopriv_preview_resos_match', array($this, 'ajax_preview_resos_match'));
        add_action('wp_ajax_create_resos_booking', array($this, 'ajax_create_resos_booking'));
        add_action('wp_ajax_nopriv_create_resos_booking', array($this, 'ajax_create_resos_booking'));
        add_action('wp_ajax_preview_resos_create', array($this, 'ajax_preview_resos_create'));
        add_action('wp_ajax_nopriv_preview_resos_create', array($this, 'ajax_preview_resos_create'));
        add_action('wp_ajax_get_dietary_choices', array($this, 'ajax_get_dietary_choices'));
        add_action('wp_ajax_nopriv_get_dietary_choices', array($this, 'ajax_get_dietary_choices'));

        // Prevent WordPress from encoding HTML entities in our shortcode output
        add_filter('no_texturize_shortcodes', array($this, 'prevent_texturize'));
    }

    /**
     * Prevent WordPress from texturizing our shortcode (which encodes && to &#038;&#038;)
     */
    public function prevent_texturize($shortcodes) {
        $shortcodes[] = 'hotel-table-bookings-by-date';
        return $shortcodes;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Reservation Management Settings',
            'Reservation Management',
            'manage_options',
            'hotel-booking-table',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('hotel_booking_table_settings', 'hotel_booking_api_username');
        register_setting('hotel_booking_table_settings', 'hotel_booking_api_password');
        register_setting('hotel_booking_table_settings', 'hotel_booking_api_key');
        register_setting('hotel_booking_table_settings', 'hotel_booking_api_region');
        register_setting('hotel_booking_table_settings', 'hotel_booking_default_hotel_id');
        register_setting('hotel_booking_table_settings', 'hotel_booking_resos_api_key');
        register_setting('hotel_booking_table_settings', 'hotel_booking_package_inventory_name');
        register_setting('hotel_booking_table_settings', 'hotel_booking_mode');
        
        add_settings_section(
            'hotel_booking_api_section',
            'Newbook API Configuration',
            array($this, 'settings_section_callback'),
            'hotel-booking-table'
        );
        
        add_settings_field(
            'hotel_booking_api_username',
            'API Username',
            array($this, 'username_field_callback'),
            'hotel-booking-table',
            'hotel_booking_api_section'
        );
        
        add_settings_field(
            'hotel_booking_api_password',
            'API Password',
            array($this, 'password_field_callback'),
            'hotel-booking-table',
            'hotel_booking_api_section'
        );
        
        add_settings_field(
            'hotel_booking_api_key',
            'API Key',
            array($this, 'api_key_field_callback'),
            'hotel-booking-table',
            'hotel_booking_api_section'
        );
        
        add_settings_field(
            'hotel_booking_api_region',
            'API Region',
            array($this, 'region_field_callback'),
            'hotel-booking-table',
            'hotel_booking_api_section'
        );
        
        add_settings_field(
            'hotel_booking_default_hotel_id',
            'Default Hotel ID',
            array($this, 'hotel_id_field_callback'),
            'hotel-booking-table',
            'hotel_booking_api_section'
        );
        
        add_settings_section(
            'hotel_booking_resos_section',
            'Resos Restaurant API Configuration',
            array($this, 'resos_section_callback'),
            'hotel-booking-table'
        );
        
        add_settings_field(
            'hotel_booking_resos_api_key',
            'Resos API Key',
            array($this, 'resos_api_key_field_callback'),
            'hotel-booking-table',
            'hotel_booking_resos_section'
        );

        add_settings_field(
            'hotel_booking_package_inventory_name',
            'Package Inventory Item Name',
            array($this, 'package_inventory_name_field_callback'),
            'hotel-booking-table',
            'hotel_booking_resos_section'
        );

        add_settings_section(
            'hotel_booking_testing_section',
            'API Mode Configuration',
            array($this, 'testing_section_callback'),
            'hotel-booking-table'
        );

        add_settings_field(
            'hotel_booking_mode',
            'API Mode',
            array($this, 'mode_field_callback'),
            'hotel-booking-table',
            'hotel_booking_testing_section'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Enter your Newbook API credentials below. These will be used to fetch booking and room data.</p>';
    }
    
    /**
     * Resos settings section callback
     */
    public function resos_section_callback() {
        echo '<p>Enter your Resos API key to display restaurant bookings alongside hotel bookings.</p>';
    }
    
    /**
     * Username field callback
     */
    public function username_field_callback() {
        $value = get_option('hotel_booking_api_username', '');
        echo '<input type="text" name="hotel_booking_api_username" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Newbook API username</p>';
    }
    
    /**
     * Password field callback
     */
    public function password_field_callback() {
        $value = get_option('hotel_booking_api_password', '');
        echo '<input type="password" name="hotel_booking_api_password" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Newbook API password</p>';
    }
    
    /**
     * API Key field callback
     */
    public function api_key_field_callback() {
        $value = get_option('hotel_booking_api_key', '');
        echo '<input type="text" name="hotel_booking_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Newbook API key (found in Newbook under your Instance settings)</p>';
    }
    
    /**
     * Region field callback
     */
    public function region_field_callback() {
        $value = get_option('hotel_booking_api_region', 'au');
        echo '<select name="hotel_booking_api_region">';
        echo '<option value="au"' . selected($value, 'au', false) . '>Australia (au)</option>';
        echo '<option value="nz"' . selected($value, 'nz', false) . '>New Zealand (nz)</option>';
        echo '<option value="us"' . selected($value, 'us', false) . '>United States (us)</option>';
        echo '<option value="eu"' . selected($value, 'eu', false) . '>Europe (eu)</option>';
        echo '</select>';
        echo '<p class="description">Select your Newbook server region</p>';
    }
    
    /**
     * Hotel ID field callback
     */
    public function hotel_id_field_callback() {
        $value = get_option('hotel_booking_default_hotel_id', '1');
        echo '<input type="text" name="hotel_booking_default_hotel_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Default hotel ID to use if not specified in shortcode</p>';
    }
    
    /**
     * Resos API Key field callback
     */
    public function resos_api_key_field_callback() {
        $value = get_option('hotel_booking_resos_api_key', '');
        echo '<input type="text" name="hotel_booking_resos_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Resos restaurant booking system API key (optional)</p>';
    }

    /**
     * Package Inventory Item Name field callback
     */
    public function package_inventory_name_field_callback() {
        $value = get_option('hotel_booking_package_inventory_name', '');
        echo '<input type="text" name="hotel_booking_package_inventory_name" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Text to search for in inventory item descriptions to identify package items (e.g., "Dinner Allocation")</p>';
    }

    /**
     * Testing section callback
     */
    public function testing_section_callback() {
        echo '<p>Select the API mode to control how API actions (creating/updating bookings) are handled.</p>';
        echo '<div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 15px 0;">';
        echo '<h4 style="margin-top: 0;">Available Modes:</h4>';
        echo '<ul>';
        echo '<li><strong>Production:</strong> API calls execute directly (live updates to Resos/Newbook)</li>';
        echo '<li><strong>Testing Mode:</strong> Dialog shows data to be sent, with confirmation button to execute or cancel</li>';
        echo '<li><strong>Sandbox Mode:</strong> Dialog shows data to be sent, but no API calls are ever executed (safe preview only)</li>';
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Mode field callback - dropdown with 3 options
     */
    public function mode_field_callback() {
        $value = get_option('hotel_booking_mode', 'production');
        echo '<select name="hotel_booking_mode" class="regular-text">';
        echo '<option value="production"' . selected($value, 'production', false) . '>Production (Live API calls)</option>';
        echo '<option value="testing"' . selected($value, 'testing', false) . '>Testing Mode (Confirm before execution)</option>';
        echo '<option value="sandbox"' . selected($value, 'sandbox', false) . '>Sandbox Mode (Preview only, no API calls)</option>';
        echo '</select>';
        echo '<p class="description">Choose how API actions should be handled when updating or creating bookings.</p>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'hotel_booking_messages',
                'hotel_booking_message',
                'Settings Saved',
                'updated'
            );
        }
        
        settings_errors('hotel_booking_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('hotel_booking_table_settings');
                do_settings_sections('hotel-booking-table');
                submit_button('Save Settings');
                ?>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Shortcode Usage</h2>
            <p>Use either of the following shortcodes in any page or post:</p>
            <code>[rmi-bookings-table]</code> (recommended)
            <br>or<br>
            <code>[hotel-table-bookings-by-date]</code> (legacy, for backward compatibility)
            <p>You can also specify a custom hotel ID:</p>
            <code>[rmi-bookings-table hotel_id="1"]</code>
            
            <hr style="margin: 30px 0;">
            
            <h2>Test API Connection</h2>
            <p>Click the button below to test your API credentials:</p>
            <button type="button" class="button" onclick="testApiConnection()">Test Connection</button>
            <div id="api-test-result" style="margin-top: 15px;"></div>
            
            <script>
            function testApiConnection() {
                var resultDiv = document.getElementById('api-test-result');
                resultDiv.innerHTML = '<p>Testing connection...</p>';
                
                jQuery.post(ajaxurl, {
                    action: 'test_hotel_booking_api'
                }, function(response) {
                    if (response.success) {
                        resultDiv.innerHTML = '<div class="notice notice-success"><p>&#10004; Connection successful! Found ' + response.data.rooms + ' rooms.</p></div>';
                    } else {
                        resultDiv.innerHTML = '<div class="notice notice-error"><p>&#10004; Connection failed: ' + response.data.message + '</p></div>';
                    }
                });
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * Add error message to be displayed
     */
    private function add_error($message) {
        $this->errors[] = $message;
    }
    
    /**
     * Get all error messages
     */
    private function get_errors() {
        return $this->errors;
    }
    
    /**
     * Enqueue plugin styles
     */
    public function enqueue_styles() {
        if (has_shortcode(get_post()->post_content, 'hotel-table-bookings-by-date')) {
            // Enqueue Material Symbols Outlined font
            wp_enqueue_style(
                'material-symbols-outlined',
                'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
                array(),
                null
            );

            wp_enqueue_style(
                'hotel-booking-table-styles',
                plugin_dir_url(__FILE__) . 'assets/style.css',
                array('material-symbols-outlined'),
                self::JS_VERSION
            );
        }
    }

    /**
     * Enqueue plugin scripts
     */
    public function enqueue_scripts() {
        if (has_shortcode(get_post()->post_content, 'hotel-table-bookings-by-date')) {
            wp_enqueue_script(
                'hotel-booking-table-scripts',
                plugin_dir_url(__FILE__) . 'assets/staying-today.js',
                array(),
                self::JS_VERSION,
                true // Load in footer
            );

            // Get current date from query parameter or use today
            $input_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

            // Get opening hours for the current date
            $opening_hours = $this->get_opening_hours_for_date($input_date);

            // Get special events for the current date
            $special_events = $this->get_special_events_for_date($input_date);

            // Get API mode setting (production, testing, sandbox)
            $api_mode = get_option('hotel_booking_mode', 'production');

            // Pass AJAX URL, opening hours, special events, and API mode to JavaScript
            wp_localize_script(
                'hotel-booking-table-scripts',
                'hotelBookingAjax',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('hotel-booking-nonce'),
                    'currentDate' => $input_date,
                    'openingHours' => $opening_hours,
                    'specialEvents' => $special_events,
                    'apiMode' => $api_mode // 'production', 'testing', or 'sandbox'
                )
            );
        }
    }
    
    /**
     * Get note types from API (cached for 24 hours)
     */
    private function get_note_types() {
        // Check cache first
        $cached = get_transient('hotel_booking_note_types');
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from API
        $response = $this->call_api('notes_types', array());
        
        if (!$response || !isset($response['data'])) {
            return array();
        }
        
        // Build lookup array by type_id
        $note_types = array();
        foreach ($response['data'] as $type) {
            $note_types[$type['note_type_id']] = $type['note_type_name'];
        }
        
        // Cache for 24 hours
        set_transient('hotel_booking_note_types', $note_types, 24 * HOUR_IN_SECONDS);
        
        return $note_types;
    }
    
    /**
     * Get group details from API (cached for 1 hour per group)
     * TEMPORARILY DISABLED - API 401 error (permissions issue)
     */
    private function get_group_details($group_id) {
        // TEMPORARY: Return null to bypass API call until credentials have group access
        return null;

        if (empty($group_id)) {
            return null;
        }

        // Check cache first
        $cache_key = 'hotel_booking_group_' . $group_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Fetch from API
        $response = $this->call_api('bookings_groups_get', array('id' => $group_id));

        if (!$response || !isset($response['data'])) {
            return null;
        }

        // Cache for 1 hour
        set_transient($cache_key, $response['data'], HOUR_IN_SECONDS);

        return $response['data'];
    }
    
    /**
     * Get hotel ID from settings or shortcode attribute
     */
    private function get_hotel_id($atts) {
        // Check if hotel_id is passed via shortcode
        if (isset($atts['hotel_id'])) {
            return $atts['hotel_id'];
        }
        
        return get_option('hotel_booking_default_hotel_id', '1');
    }
    
    /**
     * Make API request
     */
    private function call_api($action, $data = array()) {
        $username = get_option('hotel_booking_api_username');
        $password = get_option('hotel_booking_api_password');
        $api_key = get_option('hotel_booking_api_key');
        $region = get_option('hotel_booking_api_region', 'au');
        
        if (empty($username) || empty($password) || empty($api_key)) {
            $error_msg = 'API credentials not configured. Please go to Settings > Reservation Management';
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return false;
        }
        
        // Build the URL
        $url = $this->api_base_url . $action;
        
        // Add required parameters to data
        $data['region'] = $region;
        $data['api_key'] = $api_key;
        
        // Prepare request body as JSON
        $body = json_encode($data);
        
        // Prepare request arguments
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ),
            'body' => $body
        );
        
        // Make request
        $response = wp_remote_post($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'API request failed: ' . $response->get_error_message();
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return false;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check response code
        if ($response_code !== 200) {
            $error_msg = 'API returned error code: ' . $response_code . ' for action: ' . $action;
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return false;
        }
        
        // Parse JSON response
        $data_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Failed to parse API response: ' . json_last_error_msg();
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return false;
        }
        
        // Check if API returned success
        if (isset($data_response['success']) && $data_response['success'] === 'false') {
            $error_msg = 'API Error: ' . (isset($data_response['message']) ? $data_response['message'] : 'Unknown error');
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return false;
        }
        
        return $data_response;
    }
    
    /**
     * Get bookings data from API
     */
    private function get_bookings_data($hotel_id, $selected_date) {
        // Format dates for the API request
        $period_from = $selected_date . ' 00:00:00';
        $period_to = $selected_date . ' 23:59:59';
        
        // Prepare the data for bookings request
        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'list_type' => 'staying'
        );
        
        $response = $this->call_api('bookings_list', $data);
        
        if (!$response) {
            return array();
        }
        
        if (!isset($response['data'])) {
            $error_msg = 'Bookings API response missing "data" field';
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return array();
        }
        
        return $response['data'];
    }
    
    /**
     * Get rooms data from API
     */
    private function get_rooms_data($hotel_id) {
        $response = $this->call_api('sites_list', array());
        
        if (!$response) {
            return array();
        }
        
        if (!isset($response['data'])) {
            $error_msg = 'Rooms API response missing "data" field';
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return array();
        }
        
        return $response['data'];
    }
    
    /**
     * Get restaurant bookings data from Resos API
     */
    private function get_restaurant_bookings_data($selected_date) {
        $resos_api_key = get_option('hotel_booking_resos_api_key');
        
        // If no API key, return empty array (restaurant bookings are optional)
        if (empty($resos_api_key)) {
            return array();
        }
        
        // Format dates for Resos API (they use date only, not datetime)
        $from_date = $selected_date;
        $to_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
        
        // Build the Resos API URL
        $url = 'https://api.resos.com/v1/bookings';
        $url .= '?fromDateTime=' . urlencode($from_date);
        $url .= '&toDateTime=' . urlencode($to_date);
        $url .= '&limit=100';
        // Removed &onlyConfirmed=true to include all bookings (request, waitlist, approved, etc.)
        // We filter out unwanted statuses (canceled, no_show, deleted) in code instead
        $url .= '&expand=customFields'; // Request custom fields to be included
        
        // Prepare request arguments with Basic Auth
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':')
            )
        );
        
        // Make request
        $response = wp_remote_get($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'Resos API request failed: ' . $response->get_error_message();
            error_log('RMI: ' . $error_msg);
            $this->add_error($error_msg);
            return array();
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check response code
        if ($response_code !== 200) {
            $error_msg = 'Resos API returned error code: ' . $response_code;
            error_log('RMI: ' . $error_msg);
            // Don't add to errors since Resos is optional
            return array();
        }
        
        // Parse JSON response
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Hotel Booking Table: Failed to parse Resos API response: ' . json_last_error_msg());
            return array();
        }
        
        // Resos returns bookings directly as an array
        // Filter out bookings with final/invalid statuses
        if (is_array($data)) {
            $filtered_bookings = array();
            foreach ($data as $booking) {
                $status = isset($booking['status']) ? strtolower($booking['status']) : '';
                // Exclude: canceled (Resos API spelling), cancelled (alt), no_show (Resos API), no-show (alt), deleted
                $excluded_statuses = array('canceled', 'cancelled', 'no_show', 'no-show', 'deleted');

                if (!in_array($status, $excluded_statuses)) {
                    $filtered_bookings[] = $booking;
                } else {
                    error_log("Excluding Resos booking with status '{$status}': " . ($booking['_id'] ?? 'unknown ID'));
                }
            }
            return $filtered_bookings;
        }

        return array();
    }

    /**
     * Get available booking times from Resos API
     */
    private function get_resos_available_times($date, $people, $area_id = null) {
        $resos_api_key = get_option('hotel_booking_resos_api_key');

        // If no API key, return empty array
        if (empty($resos_api_key)) {
            return array('success' => false, 'message' => 'No Resos API key configured');
        }

        // Build the Resos API URL
        $url = 'https://api.resos.com/v1/bookingFlow/times';
        $url .= '?date=' . urlencode($date);
        $url .= '&people=' . urlencode($people);

        if (!empty($area_id)) {
            $url .= '&areaId=' . urlencode($area_id);
        }

        // Prepare request arguments with Basic Auth
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':')
            )
        );

        // Make request
        $response = wp_remote_get($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'Resos API request failed: ' . $response->get_error_message();
            error_log('RMI: ' . $error_msg);
            return array('success' => false, 'message' => $error_msg);
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Check response code
        if ($response_code !== 200) {
            $error_msg = 'Resos API returned error code: ' . $response_code;
            error_log('Hotel Booking Table: ' . $error_msg . ' - Response: ' . $response_body);
            return array('success' => false, 'message' => $error_msg);
        }

        // Parse JSON response
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Hotel Booking Table: Failed to parse Resos API response: ' . json_last_error_msg());
            return array('success' => false, 'message' => 'Failed to parse API response');
        }

        // Extract all available times from the opening hours array
        $all_available_times = array();
        if (is_array($data)) {
            foreach ($data as $opening_hour) {
                if (isset($opening_hour['availableTimes']) && is_array($opening_hour['availableTimes'])) {
                    $all_available_times = array_merge($all_available_times, $opening_hour['availableTimes']);
                }
            }
        }

        return array('success' => true, 'times' => $all_available_times);
    }

    /**
     * Test function: Get available dates from Resos API
     */
    private function get_resos_available_dates($from_date, $to_date) {
        $resos_api_key = get_option('hotel_booking_resos_api_key');

        // If no API key, return empty array
        if (empty($resos_api_key)) {
            return array('success' => false, 'message' => 'No Resos API key configured');
        }

        // Build the Resos API URL
        $url = 'https://api.resos.com/v1/bookingFlow/dates';
        $url .= '?fromDate=' . urlencode($from_date);
        $url .= '&toDate=' . urlencode($to_date);

        // Prepare request arguments with Basic Auth
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':')
            )
        );

        // Make request
        $response = wp_remote_get($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'Resos API request failed: ' . $response->get_error_message();
            error_log('RMI: ' . $error_msg);
            return array('success' => false, 'message' => $error_msg);
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Check response code
        if ($response_code !== 200) {
            $error_msg = 'Resos API returned error code: ' . $response_code;
            error_log('Hotel Booking Table: ' . $error_msg . ' - Response: ' . $response_body);
            return array('success' => false, 'message' => $error_msg);
        }

        // Parse JSON response
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Hotel Booking Table: Failed to parse Resos API response: ' . json_last_error_msg());
            return array('success' => false, 'message' => 'Failed to parse API response');
        }

        return array('success' => true, 'dates' => $data);
    }

    /**
     * Test function: Check if a specific date is available for online booking
     */
    public function test_available_dates() {
        // Test with October 28-31, 2025
        $result = $this->get_resos_available_dates('2025-10-28', '2025-10-31');

        echo '<pre>';
        echo 'Testing Resos Available Dates API for Oct 28-31, 2025:' . "\n\n";
        if ($result['success']) {
            echo 'Available dates for online booking:' . "\n";
            print_r($result['dates']);
        } else {
            echo 'Error: ' . $result['message'];
        }
        echo '</pre>';

        // Check the debug log for more details
        echo '<p>Check /wp-content/debug.log for detailed API response</p>';

        die();
    }

    /**
     * Get opening hours from Resos API (cached for 1 hour)
     */
    private function get_resos_opening_hours() {
        // Check cache first
        $cached = get_transient('resos_opening_hours');
        if ($cached !== false) {
            return $cached;
        }

        $resos_api_key = get_option('hotel_booking_resos_api_key');

        // If no API key, return empty array
        if (empty($resos_api_key)) {
            return array();
        }

        // Build the Resos API URL
        $url = 'https://api.resos.com/v1/openingHours?showDeleted=false&onlySpecial=false&type=restaurant';

        // Prepare request arguments with Basic Auth
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':')
            )
        );

        // Make request
        $response = wp_remote_get($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'Resos Opening Hours API request failed: ' . $response->get_error_message();
            error_log('RMI: ' . $error_msg);
            return array();
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Check response code
        if ($response_code !== 200) {
            $error_msg = 'Resos Opening Hours API returned error code: ' . $response_code;
            error_log('Hotel Booking Table: ' . $error_msg . ' - Response: ' . $response_body);
            return array();
        }

        // Parse JSON response
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Hotel Booking Table: Failed to parse Resos Opening Hours API response: ' . json_last_error_msg());
            return array();
        }

        // Cache for 1 hour (allows quicker updates when you change settings in Resos)
        if (is_array($data)) {
            set_transient('resos_opening_hours', $data, HOUR_IN_SECONDS);
            return $data;
        }

        return array();
    }

    /**
     * Get opening hours for a specific date (returns ALL opening hours for that day)
     */
    private function get_opening_hours_for_date($date) {
        $opening_hours = $this->get_resos_opening_hours();

        if (empty($opening_hours)) {
            // Default fallback - single period
            return array(
                array(
                    'open' => 1800, // 18:00
                    'close' => 2200, // 22:00
                    'interval' => 15,
                    'duration' => 120,
                    'name' => ''
                )
            );
        }

        // Get day of week (1=Monday, 7=Sunday)
        $day_of_week = date('N', strtotime($date));
        $target_date = date('Y-m-d', strtotime($date));

        // First, check if there are special events for this specific date
        $special_open_hours = array();

        foreach ($opening_hours as $hours) {
            $is_special = isset($hours['special']) && $hours['special'] === true;

            if ($is_special && isset($hours['date'])) {
                $event_date = date('Y-m-d', strtotime($hours['date']));

                // Check if this special event applies to this date
                if ($event_date === $target_date) {
                    // Check if it's an OPEN special event (not a closure)
                    $is_open = isset($hours['isOpen']) && !empty($hours['isOpen']);

                    if ($is_open && isset($hours['open']) && isset($hours['close'])) {
                        // This is a special OPEN period (e.g., Christmas special hours)
                        $special_open_hours[] = array(
                            '_id' => isset($hours['_id']) ? $hours['_id'] : '',
                            'open' => intval($hours['open']),
                            'close' => intval($hours['close']),
                            'interval' => isset($hours['seating']['interval']) ? intval($hours['seating']['interval']) : 15,
                            'duration' => isset($hours['seating']['duration']) ? intval($hours['seating']['duration']) : 120,
                            'name' => isset($hours['name']) ? $hours['name'] : ''
                        );
                    }
                }
            }
        }

        // If we found special open hours for this date, use those INSTEAD of regular hours
        if (!empty($special_open_hours)) {
            // Sort by opening time
            usort($special_open_hours, function($a, $b) {
                return $a['open'] - $b['open'];
            });
            return $special_open_hours;
        }

        // No special open hours, so find regular (recurring) opening hours for this day
        $day_hours = array();

        foreach ($opening_hours as $hours) {
            // Skip special events - we only want regular recurring hours
            $is_special = isset($hours['special']) && $hours['special'] === true;

            if ($is_special) {
                continue; // Skip special events
            }

            // Only include entries that match the day of week
            if (isset($hours['day']) && intval($hours['day']) === intval($day_of_week)) {
                $day_hours[] = array(
                    '_id' => isset($hours['_id']) ? $hours['_id'] : '',
                    'open' => isset($hours['open']) ? intval($hours['open']) : 1800,
                    'close' => isset($hours['close']) ? intval($hours['close']) : 2200,
                    'interval' => isset($hours['seating']['interval']) ? intval($hours['seating']['interval']) : 15,
                    'duration' => isset($hours['seating']['duration']) ? intval($hours['seating']['duration']) : 120,
                    'name' => isset($hours['name']) ? $hours['name'] : ''
                );
            }
        }

        // If we found hours for this day, return them
        if (!empty($day_hours)) {
            // Sort by opening time
            usort($day_hours, function($a, $b) {
                return $a['open'] - $b['open'];
            });
            return $day_hours;
        }

        // Default fallback if no match found
        return array(
            array(
                'open' => 1800,
                'close' => 2200,
                'interval' => 15,
                'duration' => 120,
                'name' => ''
            )
        );
    }

    /**
     * Get special events for a specific date
     */
    private function get_special_events_for_date($date) {
        $opening_hours = $this->get_resos_opening_hours();

        if (empty($opening_hours)) {
            return array();
        }

        $special_events = array();
        $target_date = date('Y-m-d', strtotime($date));

        foreach ($opening_hours as $hours) {
            // Only process special events
            if (!isset($hours['special']) || $hours['special'] !== true) {
                continue;
            }

            // Check if this special event applies to the target date
            if (isset($hours['date'])) {
                $event_date = date('Y-m-d', strtotime($hours['date']));

                // Match single date events
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

    /**
     * AJAX handler for getting available booking times
     */
    public function ajax_get_available_times() {
        // Get parameters
        $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $people = isset($_GET['people']) ? intval($_GET['people']) : 2;
        $area_id = isset($_GET['areaId']) ? sanitize_text_field($_GET['areaId']) : null;

        // Validate parameters
        if (empty($date)) {
            wp_send_json_error(array('message' => 'Date is required'));
            return;
        }

        // Get available times
        $result = $this->get_resos_available_times($date, $people, $area_id);

        // Also get opening hours for this date
        $opening_hours = $this->get_opening_hours_for_date($date);

        // Also get special events for this date
        $special_events = $this->get_special_events_for_date($date);

        // Check if date is available for online booking
        // We check a range from the date to the next day (toDate is exclusive)
        $next_day = date('Y-m-d', strtotime($date . ' +1 day'));
        $dates_result = $this->get_resos_available_dates($date, $next_day);
        $online_booking_available = false;
        if ($dates_result['success'] && is_array($dates_result['dates'])) {
            $online_booking_available = in_array($date, $dates_result['dates']);
        }

        if ($result['success']) {
            // Add opening hours, special events, and online booking status to response
            $result['openingHours'] = $opening_hours;
            $result['specialEvents'] = $special_events;
            $result['onlineBookingAvailable'] = $online_booking_available;
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for previewing transformed data before sending to Resos
     * Returns the actual payload that would be sent without executing the PUT request
     */
    public function ajax_preview_resos_match() {
        // Verify nonce for security
        check_ajax_referer('hotel-booking-nonce', 'nonce');

        // Get parameters from POST
        $booking_id = isset($_POST['booking_id']) ? sanitize_text_field($_POST['booking_id']) : '';
        $updates = isset($_POST['updates']) ? json_decode(stripslashes($_POST['updates']), true) : array();

        // Validate parameters
        if (empty($booking_id)) {
            wp_send_json_error(array('message' => 'Booking ID is required'));
            return;
        }

        if (empty($updates) || !is_array($updates)) {
            wp_send_json_error(array('message' => 'No updates provided'));
            return;
        }

        // Get Resos API key
        $resos_api_key = get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            wp_send_json_error(array('message' => 'Resos API key not configured'));
            return;
        }

        // Map of our internal field names to Resos customField names
        $custom_field_map = array(
            'dbb' => 'DBB',
            'booking_ref' => 'Booking #',
            'hotel_guest' => 'Hotel Guest'
        );

        // Check if any special fields (dbb, booking_ref, hotel_guest) need to be converted to customFields
        $needs_custom_field_conversion = false;
        foreach ($custom_field_map as $internal_name => $resos_name) {
            if (isset($updates[$internal_name])) {
                $needs_custom_field_conversion = true;
                break;
            }
        }

        // Flag to track if we already handled customFields
        $custom_fields_already_merged = false;
        $booking_not_found = false;

        // If we need to convert fields to customFields format
        if ($needs_custom_field_conversion) {
            $custom_fields_already_merged = true; // We'll handle the full merge here

            // Fetch customField definitions from Resos
            $custom_fields_url = 'https://api.resos.com/v1/customFields';
            $cf_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $cf_response = wp_remote_get($custom_fields_url, $cf_args);
            if (is_wp_error($cf_response)) {
                wp_send_json_error(array('message' => 'Failed to fetch customField definitions: ' . $cf_response->get_error_message()));
                return;
            }

            $cf_code = wp_remote_retrieve_response_code($cf_response);
            $cf_body = wp_remote_retrieve_body($cf_response);

            if ($cf_code !== 200) {
                wp_send_json_error(array('message' => 'Failed to fetch customField definitions. Status: ' . $cf_code));
                return;
            }

            $custom_field_definitions = json_decode($cf_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array('message' => 'Failed to parse customField definitions'));
                return;
            }

            // Fetch current booking to get existing customFields
            $booking_url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id) . '?expand=customFields';
            $booking_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $booking_response = wp_remote_get($booking_url, $booking_args);
            if (is_wp_error($booking_response)) {
                wp_send_json_error(array('message' => 'Failed to fetch current booking: ' . $booking_response->get_error_message()));
                return;
            }

            $booking_code = wp_remote_retrieve_response_code($booking_response);
            $booking_body = wp_remote_retrieve_body($booking_response);

            // If booking doesn't exist (404), we can still show preview without existing fields
            $existing_custom_fields = array();

            if ($booking_code === 404) {
                $booking_not_found = true;
                error_log('Warning: Booking not found in Resos (404). Preview will show new customFields only.');
            } elseif ($booking_code !== 200) {
                wp_send_json_error(array('message' => 'Failed to fetch current booking. Status: ' . $booking_code));
                return;
            } else {
                $current_booking = json_decode($booking_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error(array('message' => 'Failed to parse current booking'));
                    return;
                }

                // Get existing customFields
                $existing_custom_fields = isset($current_booking['customFields']) ? $current_booking['customFields'] : array();
            }

            // Build customFields array with updates
            $updated_custom_fields = $existing_custom_fields;

            // Process each special field
            foreach ($custom_field_map as $internal_name => $resos_name) {
                if (!isset($updates[$internal_name])) {
                    continue; // This field wasn't updated
                }

                $new_value = $updates[$internal_name];

                // If value is empty, we want to clear this field (it will be filtered out later)
                // Add a placeholder that will be filtered out
                if (empty($new_value) && $new_value !== 0 && $new_value !== '0') {
                    error_log("[DEBUG] Clearing field {$resos_name} (empty value)");
                    // Find the field ID to mark it for removal
                    foreach ($updated_custom_fields as $index => $existing_field) {
                        if (isset($existing_field['name']) && $existing_field['name'] === $resos_name) {
                            // Mark field with empty value - it will be filtered out later
                            $updated_custom_fields[$index]['value'] = '';
                            break;
                        }
                    }
                    // Remove from updates array
                    unset($updates[$internal_name]);
                    continue;
                }

                // Find the customField definition
                $field_definition = null;
                foreach ($custom_field_definitions as $def) {
                    if ($def['name'] === $resos_name) {
                        $field_definition = $def;
                        break;
                    }
                }

                if (!$field_definition) {
                    continue;
                }

                // Debug: Log the field definition structure
                error_log("Field definition for {$resos_name}: " . json_encode($field_definition));

                // Determine if this is a multiple choice field (radio, dropdown, checkbox)
                $field_type = isset($field_definition['type']) ? $field_definition['type'] : '';
                $is_multiple_choice = in_array($field_type, array('radio', 'dropdown', 'checkbox'));

                // Prepare the field value structure
                $field_value_data = array(
                    '_id' => $field_definition['_id'],
                    'name' => $field_definition['name']
                );

                if ($is_multiple_choice) {
                    error_log("[DEBUG] Processing as multiple choice field");
                    // For multiple choice fields, find the choice ID that matches the value name
                    $choice_id = null;
                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        error_log("[DEBUG] Has multipleChoiceSelections: " . count($field_definition['multipleChoiceSelections']) . " choices");
                        foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                            if (isset($choice['name']) && $choice['name'] === $new_value) {
                                $choice_id = $choice['_id'];
                                error_log("[DEBUG] Found matching choice ID: {$choice_id}");
                                break;
                            }
                        }
                    } else {
                        error_log("[DEBUG] No multipleChoiceSelections found or not an array");
                    }

                    if ($choice_id) {
                        // For multiple choice: value = choice ID, multipleChoiceValueName = display text
                        $field_value_data['value'] = $choice_id;
                        $field_value_data['multipleChoiceValueName'] = $new_value;
                        error_log("[DEBUG] Setting multiple choice for {$field_definition['name']}: value={$choice_id}, multipleChoiceValueName={$new_value}");
                    } else {
                        error_log("[DEBUG] WARNING: Could not find choice ID for {$field_definition['name']} with value '{$new_value}'");
                        error_log("[DEBUG] Available choices: " . json_encode($field_definition['multipleChoiceSelections'] ?? []));
                        continue; // Skip this field if we can't find the choice
                    }
                } else {
                    error_log("[DEBUG] Processing as regular field");
                    // For regular fields, just set the value
                    $field_value_data['value'] = $new_value;
                }

                // Check if this customField already exists in the booking
                $field_exists = false;
                foreach ($updated_custom_fields as $index => $existing_field) {
                    if (isset($existing_field['_id']) && $existing_field['_id'] === $field_definition['_id']) {
                        // Update existing field with proper structure
                        $updated_custom_fields[$index] = $field_value_data;
                        $field_exists = true;
                        break;
                    }
                }

                // If field doesn't exist, add it
                if (!$field_exists) {
                    $updated_custom_fields[] = $field_value_data;
                }

                // Remove the simple field from updates
                unset($updates[$internal_name]);
            }

            // Filter out fields with empty/null values (to clear them)
            // CustomFields is "all or nothing" - omitting a field effectively removes it
            $updated_custom_fields = array_filter($updated_custom_fields, function($field) {
                // Keep field if value is not empty/null
                // Handle both 'value' and 'values' (radio buttons vs other field types)
                $value = isset($field['value']) ? $field['value'] : (isset($field['values']) ? $field['values'] : null);
                return !empty($value) || $value === 0 || $value === '0';
            });

            // Re-index array to avoid gaps in indices
            $updated_custom_fields = array_values($updated_custom_fields);

            // ALWAYS add customFields to updates if we processed custom field conversions
            // Even if empty (to clear all custom fields) - this prevents "No updates provided" error
            $updates['customFields'] = $updated_custom_fields;
            error_log('[PREVIEW] Final customFields after filtering: ' . json_encode($updated_custom_fields));
        }

        // Check if we're updating customFields - if so, we need to fetch current booking first
        // (Skip if we already handled this in the conversion logic above)
        if (isset($updates['customFields']) && !$custom_fields_already_merged) {
            // Fetch current booking to get existing customFields
            $booking_url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id) . '?expand=customFields';
            $get_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $get_response = wp_remote_get($booking_url, $get_args);

            if (is_wp_error($get_response)) {
                wp_send_json_error(array('message' => 'Failed to fetch current booking: ' . $get_response->get_error_message()));
                return;
            }

            $get_code = wp_remote_retrieve_response_code($get_response);
            $get_body = wp_remote_retrieve_body($get_response);

            // Handle booking not found (404)
            $existing_custom_fields = array();

            if ($get_code === 404) {
                $booking_not_found = true;
                error_log('Warning: Booking not found in Resos (404). Preview will show new customFields only.');
            } elseif ($get_code !== 200) {
                wp_send_json_error(array('message' => 'Failed to fetch current booking. Status: ' . $get_code));
                return;
            } else {
                $current_booking = json_decode($get_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error(array('message' => 'Failed to parse current booking'));
                    return;
                }

                // Get existing customFields from the booking
                $existing_custom_fields = isset($current_booking['customFields']) ? $current_booking['customFields'] : array();
            }

            // Merge the updated customFields with existing ones
            $updated_custom_fields_from_request = $updates['customFields'];

            // Create a map of existing fields by _id for easy lookup
            $existing_fields_map = array();
            foreach ($existing_custom_fields as $field) {
                if (isset($field['_id'])) {
                    $existing_fields_map[$field['_id']] = $field;
                }
            }

            // Update existing fields or add them to the map
            foreach ($updated_custom_fields_from_request as $updated_field) {
                if (isset($updated_field['_id'])) {
                    $existing_fields_map[$updated_field['_id']] = $updated_field;
                }
            }

            // Convert map back to array
            $merged_custom_fields = array_values($existing_fields_map);

            // Filter out fields with empty/null values (to clear them)
            // CustomFields is "all or nothing" - omitting a field effectively removes it
            $merged_custom_fields = array_filter($merged_custom_fields, function($field) {
                // Keep field if value is not empty/null
                // Handle both 'value' and 'values' (radio buttons vs other field types)
                $value = isset($field['value']) ? $field['value'] : (isset($field['values']) ? $field['values'] : null);
                return !empty($value) || $value === 0 || $value === '0';
            });

            // Re-index array to avoid gaps in indices
            $updates['customFields'] = array_values($merged_custom_fields);
        }

        // Transform guest fields to nested structure
        // Resos API expects: {"guest": {"name": "...", "email": "...", "phone": "..."}}
        $guest_fields = array('name', 'email', 'phone');
        $guest_data = array();
        foreach ($guest_fields as $field) {
            if (isset($updates[$field])) {
                $value = $updates[$field];
                // Format phone for Resos API (requires + and country code)
                if ($field === 'phone') {
                    $value = $this->format_phone_for_resos($value);
                }
                $guest_data[$field] = $value;
                unset($updates[$field]); // Remove from top level
            }
        }
        if (!empty($guest_data)) {
            $updates['guest'] = $guest_data;
            error_log('[PREVIEW] Transformed guest fields to nested structure: ' . json_encode($guest_data));
        }

        // Return the prepared payload
        $response = array(
            'transformed_data' => $updates,
            'booking_id' => $booking_id
        );

        // Add warning if booking wasn't found
        if ($booking_not_found) {
            $response['warning'] = 'Booking not found in Resos. Preview shows new fields only - existing customFields could not be merged.';
        }

        wp_send_json_success($response);
    }

    /**
     * AJAX handler for confirming a match and updating Resos booking
     */
    public function ajax_confirm_resos_match() {
        // Verify nonce for security
        check_ajax_referer('hotel-booking-nonce', 'nonce');

        // Get parameters from POST
        $booking_id = isset($_POST['booking_id']) ? sanitize_text_field($_POST['booking_id']) : '';
        $updates = isset($_POST['updates']) ? json_decode(stripslashes($_POST['updates']), true) : array();

        // Validate parameters
        if (empty($booking_id)) {
            wp_send_json_error(array('message' => 'Booking ID is required'));
            return;
        }

        if (empty($updates) || !is_array($updates)) {
            wp_send_json_error(array('message' => 'No updates provided'));
            return;
        }

        // Get Resos API key
        $resos_api_key = get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            wp_send_json_error(array('message' => 'Resos API key not configured'));
            return;
        }

        // Map of our internal field names to Resos customField names
        $custom_field_map = array(
            'dbb' => 'DBB',
            'booking_ref' => 'Booking #',
            'hotel_guest' => 'Hotel Guest'
        );

        // Check if any special fields (dbb, booking_ref, hotel_guest) need to be converted to customFields
        $needs_custom_field_conversion = false;
        foreach ($custom_field_map as $internal_name => $resos_name) {
            if (isset($updates[$internal_name])) {
                $needs_custom_field_conversion = true;
                break;
            }
        }

        // Flag to track if we already handled customFields
        $custom_fields_already_merged = false;

        // If we need to convert fields to customFields format
        if ($needs_custom_field_conversion) {
            error_log('=== CUSTOM FIELD CONVERSION NEEDED ===');
            $custom_fields_already_merged = true; // We'll handle the full merge here

            // Fetch customField definitions from Resos
            $custom_fields_url = 'https://api.resos.com/v1/customFields';
            $cf_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $cf_response = wp_remote_get($custom_fields_url, $cf_args);
            if (is_wp_error($cf_response)) {
                $error_msg = 'Failed to fetch customField definitions: ' . $cf_response->get_error_message();
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $cf_code = wp_remote_retrieve_response_code($cf_response);
            $cf_body = wp_remote_retrieve_body($cf_response);

            if ($cf_code !== 200) {
                $error_msg = 'Failed to fetch customField definitions. Status: ' . $cf_code;
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $custom_field_definitions = json_decode($cf_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Failed to parse customField definitions';
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            error_log('Fetched ' . count($custom_field_definitions) . ' customField definitions');

            // Fetch current booking to get existing customFields
            $booking_url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id) . '?expand=customFields';
            $booking_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $booking_response = wp_remote_get($booking_url, $booking_args);
            if (is_wp_error($booking_response)) {
                $error_msg = 'Failed to fetch current booking: ' . $booking_response->get_error_message();
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $booking_code = wp_remote_retrieve_response_code($booking_response);
            $booking_body = wp_remote_retrieve_body($booking_response);

            if ($booking_code !== 200) {
                $error_msg = 'Failed to fetch current booking. Status: ' . $booking_code;
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $current_booking = json_decode($booking_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Failed to parse current booking';
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            // Get existing customFields
            $existing_custom_fields = isset($current_booking['customFields']) ? $current_booking['customFields'] : array();
            error_log('Existing customFields: ' . json_encode($existing_custom_fields));

            // Build customFields array with updates
            $updated_custom_fields = $existing_custom_fields;

            // Process each special field
            foreach ($custom_field_map as $internal_name => $resos_name) {
                if (!isset($updates[$internal_name])) {
                    continue; // This field wasn't updated
                }

                $new_value = $updates[$internal_name];
                error_log("Converting $internal_name => $resos_name with value: $new_value");

                // If value is empty, we want to clear this field (it will be filtered out later)
                // Mark existing field with empty value so it gets filtered out
                if (empty($new_value) && $new_value !== 0 && $new_value !== '0') {
                    error_log("[CONFIRM] Clearing field {$resos_name} (empty value)");
                    // Find the field and mark it for removal
                    foreach ($updated_custom_fields as $index => $existing_field) {
                        if (isset($existing_field['name']) && $existing_field['name'] === $resos_name) {
                            // Mark field with empty value - it will be filtered out later
                            $updated_custom_fields[$index]['value'] = '';
                            error_log("[CONFIRM] Marked field for removal: {$resos_name}");
                            break;
                        }
                    }
                    // Remove from updates array
                    unset($updates[$internal_name]);
                    continue;
                }

                // Find the customField definition
                $field_definition = null;
                foreach ($custom_field_definitions as $def) {
                    if ($def['name'] === $resos_name) {
                        $field_definition = $def;
                        break;
                    }
                }

                if (!$field_definition) {
                    error_log("WARNING: Could not find customField definition for '$resos_name'");
                    continue;
                }

                // Debug: Log the field definition structure
                error_log("Field definition for {$resos_name}: " . json_encode($field_definition));

                // Determine if this is a multiple choice field (radio, dropdown, checkbox)
                $field_type = isset($field_definition['type']) ? $field_definition['type'] : '';
                $is_multiple_choice = in_array($field_type, array('radio', 'dropdown', 'checkbox'));
                error_log("[CONFIRM] Field type: '{$field_type}', is_multiple_choice: " . ($is_multiple_choice ? 'true' : 'false'));

                // Prepare the field value structure
                $field_value_data = array(
                    '_id' => $field_definition['_id'],
                    'name' => $field_definition['name']
                );

                if ($is_multiple_choice) {
                    error_log("[DEBUG] Processing as multiple choice field");
                    // For multiple choice fields, find the choice ID that matches the value name
                    $choice_id = null;
                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        error_log("[DEBUG] Has multipleChoiceSelections: " . count($field_definition['multipleChoiceSelections']) . " choices");
                        foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                            if (isset($choice['name']) && $choice['name'] === $new_value) {
                                $choice_id = $choice['_id'];
                                error_log("[DEBUG] Found matching choice ID: {$choice_id}");
                                break;
                            }
                        }
                    } else {
                        error_log("[DEBUG] No multipleChoiceSelections found or not an array");
                    }

                    if ($choice_id) {
                        // For multiple choice: value = choice ID, multipleChoiceValueName = display text
                        $field_value_data['value'] = $choice_id;
                        $field_value_data['multipleChoiceValueName'] = $new_value;
                        error_log("[DEBUG] Setting multiple choice for {$field_definition['name']}: value={$choice_id}, multipleChoiceValueName={$new_value}");
                    } else {
                        error_log("[DEBUG] WARNING: Could not find choice ID for {$field_definition['name']} with value '{$new_value}'");
                        error_log("[DEBUG] Available choices: " . json_encode($field_definition['multipleChoiceSelections'] ?? []));
                        continue; // Skip this field if we can't find the choice
                    }
                } else {
                    error_log("[DEBUG] Processing as regular field");
                    // For regular fields, just set the value
                    $field_value_data['value'] = $new_value;
                }

                // Check if this customField already exists in the booking
                $field_exists = false;
                foreach ($updated_custom_fields as $index => $existing_field) {
                    if (isset($existing_field['_id']) && $existing_field['_id'] === $field_definition['_id']) {
                        // Update existing field with proper structure
                        $updated_custom_fields[$index] = $field_value_data;
                        $field_exists = true;
                        error_log("Updated existing customField: {$field_definition['_id']}");
                        break;
                    }
                }

                // If field doesn't exist, add it
                if (!$field_exists) {
                    $updated_custom_fields[] = $field_value_data;
                    error_log("Added new customField: {$field_definition['_id']}");
                }

                // Remove the simple field from updates
                unset($updates[$internal_name]);
            }

            // Filter out fields with empty/null values (to clear them)
            // CustomFields is "all or nothing" - omitting a field effectively removes it
            $updated_custom_fields = array_filter($updated_custom_fields, function($field) {
                // Keep field if value is not empty/null
                // Handle both 'value' and 'values' (radio buttons vs other field types)
                $value = isset($field['value']) ? $field['value'] : (isset($field['values']) ? $field['values'] : null);
                return !empty($value) || $value === 0 || $value === '0';
            });

            // Re-index array to avoid gaps in indices
            $updated_custom_fields = array_values($updated_custom_fields);

            // ALWAYS add customFields to updates if we processed custom field conversions
            // Even if empty (to clear all custom fields) - this prevents "No updates provided" error
            $updates['customFields'] = $updated_custom_fields;
            error_log('[CONFIRM] Final customFields after filtering: ' . json_encode($updated_custom_fields));

            error_log('=====================================');
        }

        // Build the Resos API URL
        $url = 'https://api.resos.com/v1/bookings/' . urlencode($booking_id);

        // Check if we're updating customFields - if so, we need to fetch current booking first
        // (Skip if we already handled this in the conversion logic above)
        if (isset($updates['customFields']) && !$custom_fields_already_merged) {
            error_log('=== CUSTOM FIELDS UPDATE DETECTED ===');
            error_log('Fetching current booking to merge customFields...');

            // Make GET request to fetch current booking
            $get_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $get_response = wp_remote_get($url . '?expand=customFields', $get_args);

            if (is_wp_error($get_response)) {
                $error_msg = 'Failed to fetch current booking: ' . $get_response->get_error_message();
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $get_code = wp_remote_retrieve_response_code($get_response);
            $get_body = wp_remote_retrieve_body($get_response);

            if ($get_code !== 200) {
                $error_msg = 'Failed to fetch current booking. Status: ' . $get_code;
                error_log($error_msg . ' - Response: ' . $get_body);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $current_booking = json_decode($get_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Failed to parse current booking data';
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            // Get the full current customFields array
            $current_custom_fields = isset($current_booking['customFields']) ? $current_booking['customFields'] : array();
            error_log('Current customFields: ' . json_encode($current_custom_fields));

            // Merge the updates into the current customFields
            // The updates['customFields'] contains the changed fields
            $updated_custom_fields = $updates['customFields'];

            // Merge: Update existing fields, keep unchanged fields
            foreach ($current_custom_fields as $index => $current_field) {
                $field_updated = false;
                foreach ($updated_custom_fields as $updated_field) {
                    if (isset($current_field['_id']) && isset($updated_field['_id']) &&
                        $current_field['_id'] === $updated_field['_id']) {
                        // This field is being updated, replace it
                        $current_custom_fields[$index] = $updated_field;
                        $field_updated = true;
                        break;
                    }
                }
            }

            // Filter out fields with empty/null values (to clear them)
            // CustomFields is "all or nothing" - omitting a field effectively removes it
            $current_custom_fields = array_filter($current_custom_fields, function($field) {
                // Keep field if value is not empty/null
                // Handle both 'value' and 'values' (radio buttons vs other field types)
                $value = isset($field['value']) ? $field['value'] : (isset($field['values']) ? $field['values'] : null);
                return !empty($value) || $value === 0 || $value === '0';
            });

            // Re-index array to avoid gaps in indices
            $current_custom_fields = array_values($current_custom_fields);

            // Replace the updates customFields with the complete merged array
            $updates['customFields'] = $current_custom_fields;
            error_log('Merged customFields (after filtering empty values): ' . json_encode($updates['customFields']));
            error_log('=====================================');
        }

        // Transform guest fields to nested structure
        // Resos API expects: {"guest": {"name": "...", "email": "...", "phone": "..."}}
        $guest_fields = array('name', 'email', 'phone');
        $guest_data = array();
        foreach ($guest_fields as $field) {
            if (isset($updates[$field])) {
                $value = $updates[$field];
                // Format phone for Resos API (requires + and country code)
                if ($field === 'phone') {
                    $value = $this->format_phone_for_resos($value);
                }
                $guest_data[$field] = $value;
                unset($updates[$field]); // Remove from top level
            }
        }
        if (!empty($guest_data)) {
            $updates['guest'] = $guest_data;
            error_log('Transformed guest fields to nested structure: ' . json_encode($guest_data));
        }

        // Prepare request arguments with Basic Auth
        $request_body = json_encode($updates);
        $args = array(
            'method' => 'PUT',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body
        );

        // Log the request details
        error_log('=== RESOS API REQUEST ===');
        error_log('Method: PUT');
        error_log('URL: ' . $url);
        error_log('Request Body: ' . $request_body);
        error_log('========================');

        // Make request to Resos API
        $response = wp_remote_request($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'Resos API request failed: ' . $response->get_error_message();
            error_log('Hotel Booking Table - Confirm Match: ' . $error_msg);
            wp_send_json_error(array('message' => $error_msg));
            return;
        }

        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Log the response details
        error_log('=== RESOS API RESPONSE ===');
        error_log('Status Code: ' . $response_code);
        error_log('Response Body: ' . $response_body);
        error_log('==========================');

        // Check response code
        if ($response_code !== 200) {
            $error_msg = 'Resos API returned error code: ' . $response_code;
            error_log('Hotel Booking Table - Confirm Match ERROR: ' . $error_msg);
            wp_send_json_error(array(
                'message' => $error_msg,
                'response_code' => $response_code,
                'response_body' => $response_body
            ));
            return;
        }

        // Parse JSON response
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Failed to parse Resos API response: ' . json_last_error_msg();
            error_log('Hotel Booking Table - Confirm Match: ' . $error_msg);
            wp_send_json_error(array('message' => $error_msg));
            return;
        }

        // Success!
        error_log('=== CONFIRM MATCH SUCCESS ===');
        error_log('Booking ID: ' . $booking_id);
        error_log('Updates Applied: ' . json_encode($updates));
        error_log('=============================');

        wp_send_json_success(array(
            'message' => 'Booking updated successfully',
            'booking_id' => $booking_id,
            'updates' => $updates,
            'booking_data' => $data
        ));
    }

    /**
     * AJAX handler for creating a new Resos booking
     */
    public function ajax_create_resos_booking() {
        // Verify nonce for security
        check_ajax_referer('hotel-booking-nonce', 'nonce');

        // Get parameters from POST
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $people = isset($_POST['people']) ? intval($_POST['people']) : 2;
        $guest_name = isset($_POST['guest_name']) ? sanitize_text_field($_POST['guest_name']) : '';
        $guest_phone = isset($_POST['guest_phone']) ? sanitize_text_field($_POST['guest_phone']) : '';
        $guest_email = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';
        $notification_sms = isset($_POST['notification_sms']) && $_POST['notification_sms'] === '1';
        $notification_email = isset($_POST['notification_email']) && $_POST['notification_email'] === '1';
        $referrer = isset($_POST['referrer']) ? esc_url_raw($_POST['referrer']) : '';
        $language_code = isset($_POST['language_code']) ? sanitize_text_field($_POST['language_code']) : 'en';
        $opening_hour_id = isset($_POST['opening_hour_id']) ? sanitize_text_field($_POST['opening_hour_id']) : '';

        // CustomField values
        $hotel_booking_ref = isset($_POST['hotel_booking_ref']) ? sanitize_text_field($_POST['hotel_booking_ref']) : '';
        $is_hotel_guest = isset($_POST['is_hotel_guest']) ? sanitize_text_field($_POST['is_hotel_guest']) : '';
        $has_dbb = isset($_POST['has_dbb']) ? sanitize_text_field($_POST['has_dbb']) : '';

        // Booking note (to be added via separate endpoint after booking creation)
        $booking_note = isset($_POST['booking_note']) ? sanitize_textarea_field($_POST['booking_note']) : '';

        // Dietary requirements (comma-separated values from frontend for multiselect checkboxes)
        $dietary_requirements = isset($_POST['dietary_requirements']) ? sanitize_text_field($_POST['dietary_requirements']) : '';

        // Dietary other (separate text field for additional allergies)
        $dietary_other = isset($_POST['dietary_other']) ? sanitize_text_field($_POST['dietary_other']) : '';

        // Validate required parameters
        if (empty($date) || empty($time)) {
            wp_send_json_error(array('message' => 'Date and time are required'));
            return;
        }

        if (empty($guest_name)) {
            wp_send_json_error(array('message' => 'Guest name is required'));
            return;
        }

        // Get Resos API key
        $resos_api_key = get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            wp_send_json_error(array('message' => 'Resos API key not configured'));
            return;
        }

        // Format phone for Resos API (requires + and country code)
        $guest_phone = $this->format_phone_for_resos($guest_phone);

        // Build the base booking data
        $booking_data = array(
            'date' => $date,
            'time' => $time,
            'people' => $people,
            'guest' => array(
                'name' => $guest_name,
                'phone' => $guest_phone,
                'email' => $guest_email,
                'notificationSms' => $notification_sms,
                'notificationEmail' => $notification_email
            ),
            'source' => 'api',
            'status' => 'approved',
            'languageCode' => $language_code
        );

        // Add referrer if provided
        if (!empty($referrer)) {
            $booking_data['referrer'] = $referrer;
        }

        // Add opening hour ID if provided (Resos expects openingHourId field)
        if (!empty($opening_hour_id)) {
            $booking_data['openingHourId'] = $opening_hour_id;
        }

        // Handle customFields if any are provided
        $custom_fields_to_add = array();

        // Map of internal field names to Resos customField names
        $custom_field_map = array(
            'hotel_booking_ref' => 'Booking #',
            'is_hotel_guest' => 'Hotel Guest',
            'has_dbb' => 'DBB',
            'dietary_requirements' => ' Dietary Requirements',  // Note: has leading space in Resos!
            'dietary_other' => 'Other Dietary Requirements'
        );

        // Check if any customFields need to be added
        $needs_custom_fields = false;
        if (!empty($hotel_booking_ref) || !empty($is_hotel_guest) || !empty($has_dbb) || !empty($dietary_requirements) || !empty($dietary_other)) {
            $needs_custom_fields = true;
        }

        if ($needs_custom_fields) {
            error_log('[CREATE] Custom fields detected, fetching definitions...');

            // Fetch customField definitions from Resos
            $custom_fields_url = 'https://api.resos.com/v1/customFields';
            $cf_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $cf_response = wp_remote_get($custom_fields_url, $cf_args);
            if (is_wp_error($cf_response)) {
                $error_msg = 'Failed to fetch customField definitions: ' . $cf_response->get_error_message();
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $cf_code = wp_remote_retrieve_response_code($cf_response);
            $cf_body = wp_remote_retrieve_body($cf_response);

            if ($cf_code !== 200) {
                $error_msg = 'Failed to fetch customField definitions. Status: ' . $cf_code;
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $custom_field_definitions = json_decode($cf_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Failed to parse customField definitions';
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            // Process each custom field
            $field_values = array(
                'hotel_booking_ref' => $hotel_booking_ref,
                'is_hotel_guest' => $is_hotel_guest,
                'has_dbb' => $has_dbb,
                'dietary_requirements' => $dietary_requirements,  // Comma-separated list for multiselect
                'dietary_other' => $dietary_other  // Text field
            );

            foreach ($field_values as $internal_name => $value) {
                if (empty($value)) {
                    continue; // Skip empty values
                }

                $resos_name = $custom_field_map[$internal_name];

                // Find the customField definition
                $field_definition = null;
                foreach ($custom_field_definitions as $def) {
                    if ($def['name'] === $resos_name) {
                        $field_definition = $def;
                        break;
                    }
                }

                if (!$field_definition) {
                    error_log("[CREATE] WARNING: Could not find customField definition for '{$resos_name}'");
                    continue;
                }

                // Determine if this is a multiple choice field
                $field_type = isset($field_definition['type']) ? $field_definition['type'] : '';
                $is_multiple_choice = in_array($field_type, array('radio', 'dropdown', 'checkbox'));

                // Prepare the field value structure
                $field_value_data = array(
                    '_id' => $field_definition['_id'],
                    'name' => $field_definition['name']
                );

                // Special handling for multiselect checkbox fields (dietary requirements)
                if ($internal_name === 'dietary_requirements' && $is_multiple_choice) {
                    // Split comma-separated choice IDs
                    $selected_ids = array_filter(array_map('trim', explode(',', $value)));
                    $choice_objects = array();

                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($selected_ids as $selected_id) {
                            // Match by choice ID (sent from frontend)
                            foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                                if (isset($choice['_id']) && $choice['_id'] === $selected_id) {
                                    $choice_objects[] = array(
                                        '_id' => $choice['_id'],
                                        'name' => $choice['name'],
                                        'value' => true
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    if (!empty($choice_objects)) {
                        $field_value_data['value'] = $choice_objects;  // Array of objects for multiselect
                    } else {
                        error_log("[CREATE] WARNING: No valid choices found for dietary requirements");
                        continue;
                    }
                } elseif ($is_multiple_choice) {
                    // For single choice fields, find the choice ID
                    $choice_id = null;
                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                            if (isset($choice['name']) && $choice['name'] === $value) {
                                $choice_id = $choice['_id'];
                                break;
                            }
                        }
                    }

                    if ($choice_id) {
                        $field_value_data['value'] = $choice_id;
                        $field_value_data['multipleChoiceValueName'] = $value;
                    } else {
                        error_log("[CREATE] WARNING: Could not find choice ID for {$resos_name} with value '{$value}'");
                        continue;
                    }
                } else {
                    // For regular fields, just set the value
                    $field_value_data['value'] = $value;
                }

                $custom_fields_to_add[] = $field_value_data;
            }

            // Add customFields to booking data if we have any
            if (!empty($custom_fields_to_add)) {
                $booking_data['customFields'] = $custom_fields_to_add;
            }
        }

        // Build the Resos API URL
        $url = 'https://api.resos.com/v1/bookings';

        // Prepare request arguments
        $request_body = json_encode($booking_data);
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body
        );

        // Make request to Resos API
        $response = wp_remote_request($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = 'Resos API request failed: ' . $response->get_error_message();
            error_log('Hotel Booking Table - Create Booking: ' . $error_msg);
            wp_send_json_error(array('message' => $error_msg));
            return;
        }

        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Check response code
        if ($response_code !== 200 && $response_code !== 201) {
            $error_msg = 'Resos API returned error code: ' . $response_code;
            error_log('Hotel Booking Table - Create Booking ERROR: ' . $error_msg);
            wp_send_json_error(array(
                'message' => $error_msg,
                'response_code' => $response_code,
                'response_body' => $response_body
            ));
            return;
        }

        // Parse JSON response
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Failed to parse Resos API response: ' . json_last_error_msg();
            error_log('Hotel Booking Table - Create Booking: ' . $error_msg);
            wp_send_json_error(array('message' => $error_msg));
            return;
        }

        // Success!
        error_log('=== CREATE BOOKING SUCCESS ===');
        error_log('Booking created for: ' . $guest_name);
        error_log('Date/Time: ' . $date . ' ' . $time);
        error_log('==============================');

        // If there's a booking note, add it via separate endpoint
        if (!empty($booking_note)) {
            // Resos returns booking ID as a simple string or as object with _id
            $booking_id = '';
            if (is_string($data)) {
                $booking_id = $data;
            } elseif (is_array($data) && isset($data['_id'])) {
                $booking_id = $data['_id'];
            }

            if (!empty($booking_id)) {
                error_log('=== ADDING BOOKING NOTE ===');
                error_log('Booking ID: ' . $booking_id);
                error_log('Note: ' . $booking_note);

                // Build note URL (Resos restaurantNote endpoint - only visible to restaurant)
                $note_url = 'https://api.resos.com/v1/bookings/' . $booking_id . '/restaurantNote';

                // Prepare note data
                $note_data = array(
                    'text' => $booking_note
                );

                $note_request_body = json_encode($note_data);
                $note_args = array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                        'Content-Type' => 'application/json'
                    ),
                    'body' => $note_request_body
                );

                // Make request to add note
                $note_response = wp_remote_request($note_url, $note_args);

                if (is_wp_error($note_response)) {
                    error_log('WARNING: Failed to add note: ' . $note_response->get_error_message());
                    // Don't fail the entire booking if note fails
                } else {
                    $note_response_code = wp_remote_retrieve_response_code($note_response);
                    if ($note_response_code === 200 || $note_response_code === 201) {
                        error_log('Note added successfully');
                    } else {
                        error_log('WARNING: Failed to add note. Status: ' . $note_response_code);
                    }
                }

                error_log('===========================');
            }
        }

        wp_send_json_success(array(
            'message' => 'Booking created successfully',
            'booking_data' => $data
        ));
    }

    /**
     * AJAX handler for previewing a new Resos booking (shows transformed data without creating)
     */
    public function ajax_preview_resos_create() {
        // Verify nonce for security
        check_ajax_referer('hotel-booking-nonce', 'nonce');

        // Get parameters from POST
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $people = isset($_POST['people']) ? intval($_POST['people']) : 2;
        $guest_name = isset($_POST['guest_name']) ? sanitize_text_field($_POST['guest_name']) : '';
        $guest_phone = isset($_POST['guest_phone']) ? sanitize_text_field($_POST['guest_phone']) : '';
        $guest_email = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';
        $notification_sms = isset($_POST['notification_sms']) && $_POST['notification_sms'] === '1';
        $notification_email = isset($_POST['notification_email']) && $_POST['notification_email'] === '1';
        $referrer = isset($_POST['referrer']) ? esc_url_raw($_POST['referrer']) : '';
        $language_code = isset($_POST['language_code']) ? sanitize_text_field($_POST['language_code']) : 'en';
        $opening_hour_id = isset($_POST['opening_hour_id']) ? sanitize_text_field($_POST['opening_hour_id']) : '';

        // CustomField values
        $hotel_booking_ref = isset($_POST['hotel_booking_ref']) ? sanitize_text_field($_POST['hotel_booking_ref']) : '';
        $is_hotel_guest = isset($_POST['is_hotel_guest']) ? sanitize_text_field($_POST['is_hotel_guest']) : '';
        $has_dbb = isset($_POST['has_dbb']) ? sanitize_text_field($_POST['has_dbb']) : '';

        // Booking note (to be added via separate endpoint after booking creation)
        $booking_note = isset($_POST['booking_note']) ? sanitize_textarea_field($_POST['booking_note']) : '';

        // Dietary requirements (comma-separated values from frontend for multiselect checkboxes)
        $dietary_requirements = isset($_POST['dietary_requirements']) ? sanitize_text_field($_POST['dietary_requirements']) : '';

        // Dietary other (separate text field for additional allergies)
        $dietary_other = isset($_POST['dietary_other']) ? sanitize_text_field($_POST['dietary_other']) : '';

        // Validate required parameters
        if (empty($date) || empty($time)) {
            wp_send_json_error(array('message' => 'Date and time are required'));
            return;
        }

        if (empty($guest_name)) {
            wp_send_json_error(array('message' => 'Guest name is required'));
            return;
        }

        // Get Resos API key
        $resos_api_key = get_option('hotel_booking_resos_api_key');
        if (empty($resos_api_key)) {
            wp_send_json_error(array('message' => 'Resos API key not configured'));
            return;
        }

        // Format phone for Resos API (requires + and country code)
        $guest_phone = $this->format_phone_for_resos($guest_phone);

        // Build the base booking data
        $booking_data = array(
            'date' => $date,
            'time' => $time,
            'people' => $people,
            'guest' => array(
                'name' => $guest_name,
                'phone' => $guest_phone,
                'email' => $guest_email,
                'notificationSms' => $notification_sms,
                'notificationEmail' => $notification_email
            ),
            'source' => 'api',
            'status' => 'approved',
            'languageCode' => $language_code
        );

        // Add referrer if provided
        if (!empty($referrer)) {
            $booking_data['referrer'] = $referrer;
        }

        // Add opening hour ID if provided (Resos expects openingHourId field)
        if (!empty($opening_hour_id)) {
            $booking_data['openingHourId'] = $opening_hour_id;
        }

        // Handle customFields if any are provided
        $custom_fields_to_add = array();

        // Map of internal field names to Resos customField names
        $custom_field_map = array(
            'hotel_booking_ref' => 'Booking #',
            'is_hotel_guest' => 'Hotel Guest',
            'has_dbb' => 'DBB',
            'dietary_requirements' => ' Dietary Requirements',  // Note: has leading space in Resos!
            'dietary_other' => 'Other Dietary Requirements'
        );

        // Check if any customFields need to be added
        $needs_custom_fields = false;
        if (!empty($hotel_booking_ref) || !empty($is_hotel_guest) || !empty($has_dbb) || !empty($dietary_requirements) || !empty($dietary_other)) {
            $needs_custom_fields = true;
        }

        if ($needs_custom_fields) {
            error_log('[PREVIEW CREATE] Custom fields detected, fetching definitions...');

            // Fetch customField definitions from Resos
            $custom_fields_url = 'https://api.resos.com/v1/customFields';
            $cf_args = array(
                'method' => 'GET',
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                    'Accept' => 'application/json'
                )
            );

            $cf_response = wp_remote_get($custom_fields_url, $cf_args);
            if (is_wp_error($cf_response)) {
                $error_msg = 'Failed to fetch customField definitions: ' . $cf_response->get_error_message();
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $cf_code = wp_remote_retrieve_response_code($cf_response);
            $cf_body = wp_remote_retrieve_body($cf_response);

            if ($cf_code !== 200) {
                $error_msg = 'Failed to fetch customField definitions. Status: ' . $cf_code;
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }

            $custom_field_definitions = json_decode($cf_body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Failed to parse customField definitions';
                error_log($error_msg);
                wp_send_json_error(array('message' => $error_msg));
                return;
            }


            // Log all custom field names and types for debugging
            foreach ($custom_field_definitions as $def) {
            }

            // Process each custom field
            $field_values = array(
                'hotel_booking_ref' => $hotel_booking_ref,
                'is_hotel_guest' => $is_hotel_guest,
                'has_dbb' => $has_dbb,
                'dietary_requirements' => $dietary_requirements,  // Comma-separated list for multiselect
                'dietary_other' => $dietary_other  // Text field
            );

            foreach ($field_values as $internal_name => $value) {
                if (empty($value)) {
                    continue; // Skip empty values
                }

                $resos_name = $custom_field_map[$internal_name];
                error_log("[PREVIEW CREATE] Processing {$internal_name} => {$resos_name} with value: {$value}");

                // Find the customField definition
                $field_definition = null;
                foreach ($custom_field_definitions as $def) {
                    if ($def['name'] === $resos_name) {
                        $field_definition = $def;
                        break;
                    }
                }

                if (!$field_definition) {
                    error_log("[PREVIEW CREATE] WARNING: Could not find customField definition for '{$resos_name}'");
                    continue;
                }

                // Determine if this is a multiple choice field
                $field_type = isset($field_definition['type']) ? $field_definition['type'] : '';
                $is_multiple_choice = in_array($field_type, array('radio', 'dropdown', 'checkbox'));

                // Prepare the field value structure
                $field_value_data = array(
                    '_id' => $field_definition['_id'],
                    'name' => $field_definition['name']
                );

                // Special handling for multiselect checkbox fields (dietary requirements)
                if ($internal_name === 'dietary_requirements' && $is_multiple_choice) {

                    // Split comma-separated choice IDs
                    $selected_ids = array_filter(array_map('trim', explode(',', $value)));
                    $choice_objects = array();

                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($selected_ids as $selected_id) {
                            // Match by choice ID (sent from frontend)
                            foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                                if (isset($choice['_id']) && $choice['_id'] === $selected_id) {
                                    $choice_objects[] = array(
                                        '_id' => $choice['_id'],
                                        'name' => $choice['name'],
                                        'value' => true
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    if (!empty($choice_objects)) {
                        $field_value_data['value'] = $choice_objects;  // Array of objects for multiselect
                    } else {
                        error_log("[PREVIEW CREATE] WARNING: No valid choices found for dietary requirements");
                        continue;
                    }
                } elseif ($is_multiple_choice) {
                    // For single choice fields, find the choice ID
                    $choice_id = null;
                    if (isset($field_definition['multipleChoiceSelections']) && is_array($field_definition['multipleChoiceSelections'])) {
                        foreach ($field_definition['multipleChoiceSelections'] as $choice) {
                            if (isset($choice['name']) && $choice['name'] === $value) {
                                $choice_id = $choice['_id'];
                                error_log("[PREVIEW CREATE] Found matching choice ID: {$choice_id}");
                                break;
                            }
                        }
                    }

                    if ($choice_id) {
                        $field_value_data['value'] = $choice_id;
                        $field_value_data['multipleChoiceValueName'] = $value;
                    } else {
                        error_log("[PREVIEW CREATE] WARNING: Could not find choice ID for {$resos_name} with value '{$value}'");
                        continue;
                    }
                } else {
                    // For regular fields, just set the value
                    $field_value_data['value'] = $value;
                }

                $custom_fields_to_add[] = $field_value_data;
            }

            // Add customFields to booking data if we have any
            if (!empty($custom_fields_to_add)) {
                $booking_data['customFields'] = $custom_fields_to_add;
            }
        }

        // Prepare response
        $response_data = array(
            'transformed_data' => $booking_data
        );

        // If there's a booking note, include information about the separate note request
        if (!empty($booking_note)) {
            $response_data['note_data'] = array(
                'text' => $booking_note,
                'endpoint' => 'POST /v1/bookings/{id}/restaurantNote',
                'note' => 'Restaurant note (separate API call after booking creation)'
            );
        }

        // Return the prepared payload
        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler to fetch dietary requirement choices from Resos
     */
    public function ajax_get_dietary_choices() {
        // Get Resos API key from settings
        $resos_api_key = get_option('hotel_booking_resos_api_key', '');

        if (empty($resos_api_key)) {
            wp_send_json_error(array('message' => 'Resos API key not configured'));
            return;
        }

        // Fetch custom field definitions from Resos
        $custom_fields_url = 'https://api.resos.com/v1/customFields';
        $custom_fields_args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($resos_api_key . ':'),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );

        $custom_fields_response = wp_remote_get($custom_fields_url, $custom_fields_args);

        if (is_wp_error($custom_fields_response)) {
            wp_send_json_error(array('message' => 'Failed to fetch custom fields: ' . $custom_fields_response->get_error_message()));
            return;
        }

        $custom_fields_body = wp_remote_retrieve_body($custom_fields_response);
        $custom_fields_data = json_decode($custom_fields_body, true);

        if (!is_array($custom_fields_data)) {
            wp_send_json_error(array('message' => 'Invalid custom fields response'));
            return;
        }

        // Find the " Dietary Requirements" field (note the leading space!)
        $dietary_field = null;
        foreach ($custom_fields_data as $field) {
            // Trim the name for comparison since Resos has " Dietary Requirements" with leading space
            if (isset($field['name']) && trim($field['name']) === 'Dietary Requirements' && isset($field['type']) && $field['type'] === 'checkbox') {
                $dietary_field = $field;
                break;
            }
        }

        if (!$dietary_field || !isset($dietary_field['multipleChoiceSelections'])) {
            wp_send_json_error(array('message' => 'Dietary requirements field not found in Resos'));
            return;
        }

        // Return the choices
        wp_send_json_success(array(
            'choices' => $dietary_field['multipleChoiceSelections'],
            'field_id' => $dietary_field['_id'],
            'field_name' => $dietary_field['name']
        ));
    }

    /**
     * Normalize string for matching (lowercase, remove hyphens, apostrophes, spaces)
     */
    private function normalize_for_matching($string) {
        if (empty($string)) {
            return '';
        }

        $normalized = strtolower(trim($string));
        $normalized = str_replace(array('-', "'", ' ', '.'), '', $normalized);
        
        return $normalized;
    }
    
    /**
     * Normalize phone number for matching - removes ALL non-numeric characters
     * Handles spaces, brackets, hyphens, plus signs, etc.
     */
    private function normalize_phone_for_matching($phone) {
        if (empty($phone)) {
            return '';
        }
        
        // Remove all non-digit characters (spaces, brackets, hyphens, plus signs, etc.)
        return preg_replace('/\D/', '', trim($phone));
    }
    
    /**
     * Extract surname from full name
     */
    private function extract_surname($full_name) {
        if (empty($full_name)) {
            return '';
        }

        $parts = explode(' ', trim($full_name));
        return end($parts); // Return last part as surname
    }

    /**
     * Format phone number for Resos API
     * Resos requires phone in international format with "+" prefix
     * If phone is empty, returns empty string (optional field)
     * If phone already starts with "+", returns as-is
     * Otherwise, adds "+44" (UK) as default country code
     */
    private function format_phone_for_resos($phone) {
        if (empty($phone)) {
            return ''; // Phone is optional
        }

        $phone = trim($phone);

        // If already has + prefix, assume it's properly formatted
        if (strpos($phone, '+') === 0) {
            return $phone;
        }

        // Strip all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits)) {
            return ''; // No digits found
        }

        // If starts with 0 (UK local format), strip the leading 0
        if (strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }

        // Add UK country code (+44) as default
        // TODO: Make this configurable based on hotel location
        return '+44' . $digits;
    }

    /**
     * Prepare comparison data for tooltip
     */
    private function prepare_comparison_data($hotel_booking, $resos_booking, $input_date) {
        // Extract hotel guest data from guests array with contact_details
        $hotel_guest_name = '';
        $hotel_phone = '';
        $hotel_email = '';
        $hotel_mobile = '';
        $hotel_landline = '';
        
        if (isset($hotel_booking['guests']) && is_array($hotel_booking['guests'])) {
            foreach ($hotel_booking['guests'] as $guest) {
                if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                    // Get name
                    $hotel_guest_name = trim($guest['firstname'] . ' ' . $guest['lastname']);
                    
                    // Extract phone and email from contact_details array, separating mobile and landline
                    if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                        foreach ($guest['contact_details'] as $contact) {
                            if (isset($contact['type']) && isset($contact['content'])) {
                                if ($contact['type'] === 'phone') {
                                    // Check if it's a mobile or landline based on label/subtype if available
                                    $contact_label = isset($contact['label']) ? strtolower($contact['label']) : '';
                                    if (strpos($contact_label, 'mobile') !== false || strpos($contact_label, 'cell') !== false) {
                                        if (empty($hotel_mobile)) {
                                            $hotel_mobile = strval($contact['content']);
                                        }
                                    } else {
                                        if (empty($hotel_landline)) {
                                            $hotel_landline = strval($contact['content']);
                                        }
                                    }
                                    // If no specific type found, use as general phone
                                    if (empty($hotel_phone)) {
                                        $hotel_phone = strval($contact['content']);
                                    }
                                } elseif ($contact['type'] === 'email' && empty($hotel_email)) {
                                    $hotel_email = strval($contact['content']);
                                }
                            }
                        }
                    }
                    break;
                }
            }
            
            // If no primary client found, use first guest
            if (empty($hotel_guest_name) && count($hotel_booking['guests']) > 0) {
                $guest = $hotel_booking['guests'][0];
                $hotel_guest_name = trim($guest['firstname'] . ' ' . $guest['lastname']);
                
                // Extract phone and email from contact_details array
                if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                    foreach ($guest['contact_details'] as $contact) {
                        if (isset($contact['type']) && isset($contact['content'])) {
                            if ($contact['type'] === 'phone') {
                                $contact_label = isset($contact['label']) ? strtolower($contact['label']) : '';
                                if (strpos($contact_label, 'mobile') !== false || strpos($contact_label, 'cell') !== false) {
                                    if (empty($hotel_mobile)) {
                                        $hotel_mobile = strval($contact['content']);
                                    }
                                } else {
                                    if (empty($hotel_landline)) {
                                        $hotel_landline = strval($contact['content']);
                                    }
                                }
                                if (empty($hotel_phone)) {
                                    $hotel_phone = strval($contact['content']);
                                }
                            } elseif ($contact['type'] === 'email' && empty($hotel_email)) {
                                $hotel_email = strval($contact['content']);
                            }
                        }
                    }
                }
            }
        }
        
        // Prefer mobile over landline for the main phone
        $hotel_preferred_phone = !empty($hotel_mobile) ? $hotel_mobile : (!empty($hotel_landline) ? $hotel_landline : $hotel_phone);
        
        $hotel_booking_id = isset($hotel_booking['booking_id']) ? strval($hotel_booking['booking_id']) : '';
        $hotel_room = isset($hotel_booking['site_name']) ? strval($hotel_booking['site_name']) : '';
        $hotel_status = isset($hotel_booking['booking_status']) ? strval($hotel_booking['booking_status']) : '';

        // Extract occupancy (people count) from hotel booking - sum adults, children, and infants
        $hotel_adults = isset($hotel_booking['booking_adults']) ? intval($hotel_booking['booking_adults']) : 0;
        $hotel_children = isset($hotel_booking['booking_children']) ? intval($hotel_booking['booking_children']) : 0;
        $hotel_infants = isset($hotel_booking['booking_infants']) ? intval($hotel_booking['booking_infants']) : 0;
        $hotel_people = $hotel_adults + $hotel_children + $hotel_infants;

        // Extract rate type for the input date
        $hotel_rate_type = '-';
        if (isset($hotel_booking['tariffs_quoted']) && is_array($hotel_booking['tariffs_quoted'])) {
            foreach ($hotel_booking['tariffs_quoted'] as $tariff) {
                if (isset($tariff['stay_date']) && $tariff['stay_date'] == $input_date) {
                    $hotel_rate_type = isset($tariff['label']) ? $tariff['label'] : '-';
                    break;
                }
            }
        }
        
        // Extract Resos custom fields
        $resos_booking_ref = '';
        $resos_hotel_guest = '';
        $resos_dbb = '';
        
        if (isset($resos_booking['customFields']) && is_array($resos_booking['customFields'])) {
            foreach ($resos_booking['customFields'] as $field) {
                if (isset($field['name'])) {
                    if ($field['name'] === 'Booking #' && isset($field['value'])) {
                        $resos_booking_ref = trim($field['value']);
                    } elseif ($field['name'] === 'Hotel Guest' && isset($field['multipleChoiceValueName'])) {
                        $resos_hotel_guest = $field['multipleChoiceValueName'];
                    } elseif ($field['name'] === 'DBB' && isset($field['multipleChoiceValueName'])) {
                        $resos_dbb = $field['multipleChoiceValueName'];
                    }
                }
            }
        }
        
        // Extract Resos notes
        $resos_notes = '';
        if (isset($resos_booking['restaurantNotes']) && is_array($resos_booking['restaurantNotes'])) {
            $notes_array = array();
            foreach ($resos_booking['restaurantNotes'] as $note) {
                if (isset($note['restaurantNote'])) {
                    $notes_array[] = $note['restaurantNote'];
                }
            }
            $resos_notes = implode(' ', $notes_array);
        }
        
        // Extract Resos data
        $resos_booking_id = isset($resos_booking['_id']) ? $resos_booking['_id'] : (isset($resos_booking['id']) ? $resos_booking['id'] : '');
        $resos_guest_name = isset($resos_booking['guest']['name']) ? trim($resos_booking['guest']['name']) : '';
        $resos_phone = isset($resos_booking['guest']['phone']) ? trim($resos_booking['guest']['phone']) : '';
        $resos_email = isset($resos_booking['guest']['email']) ? trim($resos_booking['guest']['email']) : '';
        $resos_people = isset($resos_booking['people']) ? intval($resos_booking['people']) : 0;
        $resos_status = isset($resos_booking['status']) ? $resos_booking['status'] : 'request';

        // Determine which fields match for highlighting
        $matches = array();
        
        // Check name match
        if (!empty($hotel_guest_name) && !empty($resos_guest_name)) {
            $hotel_surname = $this->extract_surname($hotel_guest_name);
            $resos_surname = $this->extract_surname($resos_guest_name);
            if ($this->normalize_for_matching($hotel_surname) === $this->normalize_for_matching($resos_surname) && strlen($hotel_surname) > 2) {
                $matches['name'] = true;
            }
        }
        
        // Check phone match
        if (!empty($hotel_phone) && !empty($resos_phone)) {
            $normalized_hotel = $this->normalize_phone_for_matching($hotel_phone);
            $normalized_resos = $this->normalize_phone_for_matching($resos_phone);
            
            if (strlen($normalized_hotel) >= 8 && strlen($normalized_resos) >= 8) {
                $hotel_last_8 = substr($normalized_hotel, -8);
                $resos_last_8 = substr($normalized_resos, -8);
                if ($hotel_last_8 === $resos_last_8) {
                    $matches['phone'] = true;
                }
            }
        }
        
        // Check email match
        if (!empty($hotel_email) && !empty($resos_email)) {
            if ($this->normalize_for_matching($hotel_email) === $this->normalize_for_matching($resos_email)) {
                $matches['email'] = true;
            }
        }
        
        // Check booking reference match
        if (!empty($hotel_booking_id) && !empty($resos_booking_ref)) {
            if ($hotel_booking_id == $resos_booking_ref) {
                $matches['booking_ref'] = true;
            }
        }

        // Check notes match - look for room number or booking ID in notes
        if (!empty($resos_notes) && (!empty($hotel_room) || !empty($hotel_booking_id))) {
            $notes_normalized = strtolower($resos_notes);
            $room_found = !empty($hotel_room) && stripos($notes_normalized, strtolower($hotel_room)) !== false;
            $booking_found = !empty($hotel_booking_id) && stripos($notes_normalized, $hotel_booking_id) !== false;

            if ($room_found || $booking_found) {
                $matches['notes'] = true;
            }
        }

        // Check people match
        if ($hotel_people > 0 && $resos_people > 0 && $hotel_people == $resos_people) {
            $matches['people'] = true;
        }

        // Check for package inventory item on this date
        $hotel_has_package = false;
        $package_inventory_name = get_option('hotel_booking_package_inventory_name', '');

        if (!empty($package_inventory_name) && isset($hotel_booking['inventory_items']) && is_array($hotel_booking['inventory_items'])) {
            foreach ($hotel_booking['inventory_items'] as $item) {
                if (isset($item['stay_date']) && $item['stay_date'] == $input_date) {
                    if (isset($item['description']) && stripos($item['description'], $package_inventory_name) !== false) {
                        $hotel_has_package = true;
                        break;
                    }
                }
            }
        }

        // Check package/DBB match
        // Match if: (hotel has package AND resos = "Yes") OR (hotel no package AND resos is empty/No)
        if ($hotel_has_package && $resos_dbb === 'Yes') {
            $matches['dbb'] = true;
        } elseif (!$hotel_has_package && (empty($resos_dbb) || $resos_dbb === 'No')) {
            $matches['dbb'] = true;
        }

        // Calculate suggested updates for Resos
        $suggested_updates = array();

        // Guest Name: Suggest if Resos doesn't have full name (just has surname)
        if (!empty($hotel_guest_name) && !empty($resos_guest_name)) {
            // If names don't match exactly and Resos name is shorter or just surname
            if (strtolower(trim($hotel_guest_name)) !== strtolower(trim($resos_guest_name))) {
                $resos_surname = $this->extract_surname($resos_guest_name);
                // If Resos name is just the surname, suggest full name
                if (strtolower(trim($resos_guest_name)) === strtolower(trim($resos_surname))) {
                    $suggested_updates['name'] = $hotel_guest_name;
                }
            }
        } elseif (!empty($hotel_guest_name) && empty($resos_guest_name)) {
            // If Resos has no name, suggest hotel name
            $suggested_updates['name'] = $hotel_guest_name;
        }
        
        // Phone: Suggest if Resos doesn't have one
        if (empty($resos_phone) && !empty($hotel_preferred_phone)) {
            $suggested_updates['phone'] = $hotel_preferred_phone;
        }
        
        // Email: Suggest if Resos doesn't have one
        if (empty($resos_email) && !empty($hotel_email)) {
            $suggested_updates['email'] = $hotel_email;
        }
        
        // Hotel Guest: Suggest "Yes" if not already set to "Yes"
        if ($resos_hotel_guest !== 'Yes') {
            $suggested_updates['hotel_guest'] = 'Yes';
        }
        
        // Booking #: Always suggest the Newbook booking ID if different
        if ($resos_booking_ref !== $hotel_booking_id) {
            $suggested_updates['booking_ref'] = $hotel_booking_id;
        }
        
        // Tariff/Package: Suggest "Yes" if hotel has package, suggest clearing if hotel doesn't
        // DBB is a "Yes only" radio button (no "No" option, just Yes or empty)
        // To clear: omit the field from customFields array (handled in ajax_confirm_resos_match)
        if ($hotel_has_package && $resos_dbb !== 'Yes') {
            $suggested_updates['dbb'] = 'Yes';
        } elseif (!$hotel_has_package && $resos_dbb === 'Yes') {
            // Suggest clearing DBB (empty string = omit from customFields array when updating)
            $suggested_updates['dbb'] = '';
        }

        // People/Covers: Suggest hotel occupancy if different from Resos covers
        // NOTE: This is intentionally added to suggested_updates but will NOT be checked by default
        // as differences are often legitimate (non-residents joining, meeting others, etc.)
        if ($hotel_people > 0 && $resos_people > 0 && $hotel_people != $resos_people) {
            $suggested_updates['people'] = $hotel_people;
        }

        // Status: Suggest "approved" only if currently in early stages (request, declined, waitlist)
        // Do NOT suggest for arrived, seated, left, no_show, canceled (later stages or final states)
        $early_stage_statuses = array('request', 'declined', 'waitlist');
        if (in_array(strtolower($resos_status), $early_stage_statuses)) {
            $suggested_updates['status'] = 'approved';
        }

        return array(
            'hotel' => array(
                'name' => $hotel_guest_name,
                'phone' => $hotel_preferred_phone,
                'email' => $hotel_email,
                'booking_id' => $hotel_booking_id,
                'room' => $hotel_room,
                'people' => $hotel_people,
                'notes' => $hotel_room . ' / #' . $hotel_booking_id, // Show what we're matching against
                'is_hotel_guest' => true, // Always true for hotel bookings
                'rate_type' => $hotel_rate_type,
                'has_package' => $hotel_has_package,
                'status' => $hotel_status
            ),
            'resos' => array(
                'id' => $resos_booking_id,
                'name' => $resos_guest_name,
                'phone' => $resos_phone,
                'email' => $resos_email,
                'booking_ref' => $resos_booking_ref,
                'people' => $resos_people,
                'notes' => $resos_notes,
                'hotel_guest' => $resos_hotel_guest,
                'dbb' => $resos_dbb,
                'status' => $resos_status
            ),
            'matches' => $matches,
            'suggested_updates' => $suggested_updates
        );
    }

    /**
     * Prepare guest data for create booking form
     */
    private function prepare_guest_data_for_create_booking($hotel_booking, $input_date) {
        // Extract hotel guest data from guests array with contact_details
        $hotel_guest_name = '';
        $hotel_phone = '';
        $hotel_email = '';
        $hotel_mobile = '';
        $hotel_landline = '';
        $hotel_emails = array(); // Track all emails to filter out agent forwards

        if (isset($hotel_booking['guests']) && is_array($hotel_booking['guests'])) {
            foreach ($hotel_booking['guests'] as $guest) {
                if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                    // Get name
                    $hotel_guest_name = trim($guest['firstname'] . ' ' . $guest['lastname']);

                    // Extract phone and email from contact_details array
                    if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                        foreach ($guest['contact_details'] as $contact) {
                            if (isset($contact['type']) && isset($contact['content'])) {
                                if ($contact['type'] === 'phone') {
                                    $contact_label = isset($contact['label']) ? strtolower($contact['label']) : '';
                                    if (strpos($contact_label, 'mobile') !== false || strpos($contact_label, 'cell') !== false) {
                                        if (empty($hotel_mobile)) {
                                            $hotel_mobile = strval($contact['content']);
                                        }
                                    } else {
                                        if (empty($hotel_landline)) {
                                            $hotel_landline = strval($contact['content']);
                                        }
                                    }
                                    if (empty($hotel_phone)) {
                                        $hotel_phone = strval($contact['content']);
                                    }
                                } elseif ($contact['type'] === 'email') {
                                    $email = strval($contact['content']);
                                    $hotel_emails[] = $email;
                                }
                            }
                        }
                    }
                    break;
                }
            }

            // If no primary client found, use first guest
            if (empty($hotel_guest_name) && count($hotel_booking['guests']) > 0) {
                $guest = $hotel_booking['guests'][0];
                $hotel_guest_name = trim($guest['firstname'] . ' ' . $guest['lastname']);

                if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                    foreach ($guest['contact_details'] as $contact) {
                        if (isset($contact['type']) && isset($contact['content'])) {
                            if ($contact['type'] === 'phone') {
                                $contact_label = isset($contact['label']) ? strtolower($contact['label']) : '';
                                if (strpos($contact_label, 'mobile') !== false || strpos($contact_label, 'cell') !== false) {
                                    if (empty($hotel_mobile)) {
                                        $hotel_mobile = strval($contact['content']);
                                    }
                                } else {
                                    if (empty($hotel_landline)) {
                                        $hotel_landline = strval($contact['content']);
                                    }
                                }
                                if (empty($hotel_phone)) {
                                    $hotel_phone = strval($contact['content']);
                                }
                            } elseif ($contact['type'] === 'email') {
                                $email = strval($contact['content']);
                                $hotel_emails[] = $email;
                            }
                        }
                    }
                }
            }
        }

        // Prefer mobile over landline for the main phone
        $hotel_preferred_phone = !empty($hotel_mobile) ? $hotel_mobile : (!empty($hotel_landline) ? $hotel_landline : $hotel_phone);

        // Filter out booking.com and expedia forwarding addresses, prefer personal emails
        $hotel_preferred_email = '';
        $agent_emails = array();
        foreach ($hotel_emails as $email) {
            $email_lower = strtolower($email);
            if (stripos($email_lower, 'booking.com') !== false || stripos($email_lower, 'expedia') !== false) {
                $agent_emails[] = $email;
            } else {
                if (empty($hotel_preferred_email)) {
                    $hotel_preferred_email = $email;
                }
            }
        }
        // If no personal email found, use first agent email
        if (empty($hotel_preferred_email) && !empty($agent_emails)) {
            $hotel_preferred_email = $agent_emails[0];
        }

        $hotel_booking_id = isset($hotel_booking['booking_id']) ? strval($hotel_booking['booking_id']) : '';

        // Check for package inventory item on this date
        $hotel_has_package = false;
        $package_inventory_name = get_option('hotel_booking_package_inventory_name', '');

        if (!empty($package_inventory_name) && isset($hotel_booking['inventory_items']) && is_array($hotel_booking['inventory_items'])) {
            foreach ($hotel_booking['inventory_items'] as $item) {
                if (isset($item['stay_date']) && $item['stay_date'] == $input_date) {
                    if (isset($item['description']) && stripos($item['description'], $package_inventory_name) !== false) {
                        $hotel_has_package = true;
                        break;
                    }
                }
            }
        }

        return array(
            'name' => $hotel_guest_name,
            'phone' => $hotel_preferred_phone,
            'email' => $hotel_preferred_email,
            'booking_id' => $hotel_booking_id,
            'has_package' => $hotel_has_package
        );
    }


    /**
     * Match Resos bookings to hotel bookings
     * Returns array with match type and confidence
     */
    private function match_resos_to_hotel_booking($resos_booking, $hotel_booking) {
        $matches = array();
        
        // Extract hotel booking identifiers
        $hotel_booking_id = isset($hotel_booking['booking_id']) ? strval($hotel_booking['booking_id']) : '';
        $hotel_agent_ref = isset($hotel_booking['booking_reference_id']) ? strval($hotel_booking['booking_reference_id']) : '';
        $hotel_room_number = isset($hotel_booking['site_name']) ? strval($hotel_booking['site_name']) : '';
        
        // Extract guest info including contact details
        $hotel_guest_name = '';
        $hotel_phone = '';
        $hotel_email = '';
        
        if (isset($hotel_booking['guests']) && is_array($hotel_booking['guests'])) {
            foreach ($hotel_booking['guests'] as $guest) {
                if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                    $hotel_guest_name = trim($guest['firstname'] . ' ' . $guest['lastname']);
                    
                    // Extract phone and email from contact_details array
                    if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                        foreach ($guest['contact_details'] as $contact) {
                            if (isset($contact['type']) && isset($contact['content'])) {
                                if ($contact['type'] === 'phone' && empty($hotel_phone)) {
                                    $hotel_phone = strval($contact['content']);
                                } elseif ($contact['type'] === 'email' && empty($hotel_email)) {
                                    $hotel_email = strval($contact['content']);
                                }
                            }
                        }
                    }
                    break;
                }
            }
            
            if (empty($hotel_guest_name) && count($hotel_booking['guests']) > 0) {
                $guest = $hotel_booking['guests'][0];
                $hotel_guest_name = trim($guest['firstname'] . ' ' . $guest['lastname']);
                
                // Extract phone and email from contact_details array
                if (isset($guest['contact_details']) && is_array($guest['contact_details'])) {
                    foreach ($guest['contact_details'] as $contact) {
                        if (isset($contact['type']) && isset($contact['content'])) {
                            if ($contact['type'] === 'phone' && empty($hotel_phone)) {
                                $hotel_phone = strval($contact['content']);
                            } elseif ($contact['type'] === 'email' && empty($hotel_email)) {
                                $hotel_email = strval($contact['content']);
                            }
                        }
                    }
                }
            }
        }
        
        $hotel_surname = $this->extract_surname($hotel_guest_name);
        
        // Extract Resos booking data
        $resos_hotel_booking_ref = '';
        
        // Check for hotel booking number in custom fields
        if (isset($resos_booking['customFields']) && is_array($resos_booking['customFields'])) {
            foreach ($resos_booking['customFields'] as $field) {
                if (isset($field['name']) && $field['name'] === 'Booking #') {
                    if (isset($field['value'])) {
                        $resos_hotel_booking_ref = trim($field['value']);
                        break;
                    }
                }
            }
        }
        
        // Get notes from restaurant notes
        $resos_notes = '';
        if (isset($resos_booking['restaurantNotes']) && is_array($resos_booking['restaurantNotes'])) {
            $notes_array = array();
            foreach ($resos_booking['restaurantNotes'] as $note) {
                if (isset($note['restaurantNote'])) {
                    $notes_array[] = $note['restaurantNote'];
                }
            }
            $resos_notes = implode(' ', $notes_array);
        }
        
        // Get guest info
        $resos_guest_name = isset($resos_booking['guest']['name']) ? trim($resos_booking['guest']['name']) : '';
        $resos_surname = $this->extract_surname($resos_guest_name);
        $resos_phone = isset($resos_booking['guest']['phone']) ? trim($resos_booking['guest']['phone']) : '';
        $resos_email = isset($resos_booking['guest']['email']) ? trim($resos_booking['guest']['email']) : '';
        
        // PRIORITY 1: Direct hotel booking number match (from custom field)
        if (!empty($resos_hotel_booking_ref) && !empty($hotel_booking_id)) {
            if ($resos_hotel_booking_ref == $hotel_booking_id) {
                return array(
                    'matched' => true,
                    'match_type' => 'booking_id',
                    'match_label' => 'Direct booking number match',
                    'confidence' => 'high',
                    'is_primary' => true
                );
            }
        }
        
        // PRIORITY 2: Agent reference match (from custom field)
        if (!empty($resos_hotel_booking_ref) && !empty($hotel_agent_ref)) {
            if ($resos_hotel_booking_ref == $hotel_agent_ref) {
                return array(
                    'matched' => true,
                    'match_type' => 'agent_ref',
                    'match_label' => 'Direct agent reference match',
                    'confidence' => 'high',
                    'is_primary' => true
                );
            }
        }
        
        // PRIORITY 3: Check notes for booking ID
        if (!empty($resos_notes) && !empty($hotel_booking_id)) {
            if (stripos($resos_notes, $hotel_booking_id) !== false) {
                return array(
                    'matched' => true,
                    'match_type' => 'notes_booking_id',
                    'match_label' => 'Booking number found in notes',
                    'confidence' => 'high',
                    'is_primary' => true
                );
            }
        }
        
        // PRIORITY 4: Check notes for agent reference
        if (!empty($resos_notes) && !empty($hotel_agent_ref)) {
            if (stripos($resos_notes, $hotel_agent_ref) !== false) {
                return array(
                    'matched' => true,
                    'match_type' => 'notes_agent_ref',
                    'match_label' => 'Agent reference found in notes',
                    'confidence' => 'high',
                    'is_primary' => true
                );
            }
        }
        
        // PRIORITY 5: Check notes for room number
        if (!empty($resos_notes) && !empty($hotel_room_number)) {
            // Look for room number patterns in notes
            if (preg_match('/\b' . preg_quote($hotel_room_number, '/') . '\b/i', $resos_notes)) {
                $matches[] = array(
                    'type' => 'room_number',
                    'label' => 'Room',
                    'score' => 8
                );
            }
        }
        
        // PRIORITY 6: Surname match (normalized)
        if (!empty($hotel_surname) && !empty($resos_surname)) {
            $normalized_hotel_surname = $this->normalize_for_matching($hotel_surname);
            $normalized_resos_surname = $this->normalize_for_matching($resos_surname);
            
            if ($normalized_hotel_surname === $normalized_resos_surname && strlen($normalized_hotel_surname) > 2) {
                $matches[] = array(
                    'type' => 'surname',
                    'label' => 'Surname',
                    'score' => 7
                );
            }
        }
        
        // PRIORITY 7: Phone number match (enhanced - removes ALL non-numeric characters)
        if (!empty($hotel_phone) && !empty($resos_phone)) {
            $normalized_hotel_phone = $this->normalize_phone_for_matching($hotel_phone);
            $normalized_resos_phone = $this->normalize_phone_for_matching($resos_phone);
            
            // Match if last 8 digits match (to handle country codes)
            if (strlen($normalized_hotel_phone) >= 8 && strlen($normalized_resos_phone) >= 8) {
                $hotel_last_8 = substr($normalized_hotel_phone, -8);
                $resos_last_8 = substr($normalized_resos_phone, -8);
                
                if ($hotel_last_8 === $resos_last_8) {
                    $matches[] = array(
                        'type' => 'phone',
                        'label' => 'Phone',
                        'score' => 9
                    );
                }
            }
        }
        
        // PRIORITY 8: Email match (normalized)
        if (!empty($hotel_email) && !empty($resos_email)) {
            $normalized_hotel_email = $this->normalize_for_matching($hotel_email);
            $normalized_resos_email = $this->normalize_for_matching($resos_email);
            
            if ($normalized_hotel_email === $normalized_resos_email) {
                $matches[] = array(
                    'type' => 'email',
                    'label' => 'Email',
                    'score' => 10
                );
            }
        }
        
        // Evaluate secondary matches
        if (!empty($matches)) {
            // Sort by score (highest first)
            usort($matches, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            // Get the best match
            $best_match = $matches[0];
            
            // Determine confidence based on score and number of matches
            $total_score = array_sum(array_column($matches, 'score'));
            $match_count = count($matches);
            
            $confidence = 'low';
            if ($total_score >= 15 || $match_count >= 2) {
                $confidence = 'medium';
            }
            if ($total_score >= 20 || $match_count >= 3) {
                $confidence = 'high';
            }
            
            // Build match labels
            $match_labels = array_column($matches, 'label');
            
            return array(
                'matched' => true,
                'match_type' => $best_match['type'],
                'match_label' => implode(' + ', $match_labels),
                'confidence' => $confidence,
                'is_primary' => false,
                'match_count' => $match_count
            );
        }
        
        // No match found
        return array(
            'matched' => false,
            'match_type' => null,
            'match_label' => null,
            'confidence' => null,
            'is_primary' => false
        );
    }
    
    /**
     * Get primary guest name from booking
     */
    private function get_primary_guest_name($booking) {
        if (isset($booking['guests']) && is_array($booking['guests'])) {
            foreach ($booking['guests'] as $guest) {
                if (isset($guest['primary_client']) && $guest['primary_client'] == '1') {
                    return trim($guest['firstname'] . ' ' . $guest['lastname']);
                }
            }
            // If no primary client found, return first guest
            if (count($booking['guests']) > 0) {
                $guest = $booking['guests'][0];
                return trim($guest['firstname'] . ' ' . $guest['lastname']);
            }
        }
        return '-';
    }
    
    /**
     * Calculate night status - returns "1 of 2", "2 of 2" etc
     */
    private function get_night_status($input_date, $arrival_datetime, $departure_datetime) {
        try {
            // Parse dates
            $input = new DateTime($input_date);
            $input->setTime(0, 0, 0);
            
            $arrival = new DateTime($arrival_datetime);
            $arrival->setTime(0, 0, 0);
            
            $departure = new DateTime($departure_datetime);
            $departure->setTime(0, 0, 0);
            
            // Calculate total nights (departure - arrival)
            $total_nights = $arrival->diff($departure)->days;
            
            // If total nights is 0, it's same-day booking (shouldn't happen but handle it)
            if ($total_nights == 0) {
                $total_nights = 1;
            }
            
            // Calculate which night this is (input - arrival + 1)
            $days_since_arrival = $arrival->diff($input)->days;
            $night_num = $days_since_arrival + 1;
            
            // Ensure night_num doesn't exceed total_nights
            if ($night_num > $total_nights) {
                $night_num = $total_nights;
            }
            
            // Always show the night number format
            return array('status' => "$night_num of $total_nights", 'class' => 'active');
            
        } catch (Exception $e) {
            error_log('Night status calculation error: ' . $e->getMessage());
            return array('status' => '-', 'class' => 'vacant');
        }
    }
    
    /**
     * Organize rooms - regular rooms first (sorted numerically), then grouped rooms at bottom
     * Groups come from bookings, not room data
     */
    private function organize_rooms($all_rooms, $bookings_by_room) {
        $regular_rooms = array();
        $grouped_rooms = array();
        $rooms_in_groups = array(); // Track which rooms are in groups
        
        // First, identify which rooms have bookings with groups
        foreach ($bookings_by_room as $room_num => $booking) {
            if (!empty($booking['bookings_group_id'])) {
                $group_id = $booking['bookings_group_id'];
                $group_name = isset($booking['bookings_group_name']) ? $booking['bookings_group_name'] : 'Group ' . $group_id;
                
                $rooms_in_groups[$room_num] = array(
                    'group_id' => $group_id,
                    'group_name' => $group_name
                );
            }
        }
        
        // Now organize all rooms
        foreach ($all_rooms as $room) {
            $room_name = $room['site_name'];
            
            // Skip non-numeric room names that aren't in a group
            if (!is_numeric($room_name) && !isset($rooms_in_groups[$room_name])) {
                continue;
            }
            
            // Check if this room has a booking with a group
            if (isset($rooms_in_groups[$room_name])) {
                $group_id = $rooms_in_groups[$room_name]['group_id'];
                $group_name = $rooms_in_groups[$room_name]['group_name'];
                
                if (!isset($grouped_rooms[$group_id])) {
                    $grouped_rooms[$group_id] = array(
                        'name' => $group_name,
                        'rooms' => array()
                    );
                }
                
                $grouped_rooms[$group_id]['rooms'][] = $room;
            } else {
                // Regular room (no group booking)
                $regular_rooms[] = $room;
            }
        }
        
        // Sort regular rooms numerically by site_name
        usort($regular_rooms, function($a, $b) {
            return intval($a['site_name']) - intval($b['site_name']);
        });
        
        // Sort rooms within each group
        foreach ($grouped_rooms as $group_id => $group_data) {
            usort($grouped_rooms[$group_id]['rooms'], function($a, $b) {
                // Try numeric sort first
                if (is_numeric($a['site_name']) && is_numeric($b['site_name'])) {
                    return intval($a['site_name']) - intval($b['site_name']);
                }
                // Fall back to string comparison
                return strcmp($a['site_name'], $b['site_name']);
            });
        }
        
        // Sort groups by group_id
        ksort($grouped_rooms);
        
        return array(
            'regular' => $regular_rooms,
            'grouped' => $grouped_rooms
        );
    }
    
    /**
     * Render the booking table
     */
    public function render_booking_table($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'hotel_id' => get_option('hotel_booking_default_hotel_id', '1'),
        ), $atts);
        
        // Get hotel ID
        $hotel_id = $this->get_hotel_id($atts);
        
        // Get input date from URL parameter or use today
        $input_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        
        // Get data from APIs
        $bookings = $this->get_bookings_data($hotel_id, $input_date);
        $all_rooms = $this->get_rooms_data($hotel_id);
        $restaurant_bookings = $this->get_restaurant_bookings_data($input_date);
        $note_types = $this->get_note_types();
        
        // Create lookup arrays
        $bookings_by_room = array();
        foreach ($bookings as $booking) {
            $room_number = $booking['site_name'];
            $bookings_by_room[$room_number] = $booking;
        }
        
        // Match restaurant bookings to hotel bookings
        $matched_restaurant_bookings = array();
        
        if (!empty($restaurant_bookings) && is_array($restaurant_bookings)) {
            foreach ($restaurant_bookings as $rest_booking) {
                $best_match = null;
                $best_match_room = null;
                
                // Try to match against each hotel booking
                foreach ($bookings_by_room as $room_num => $hotel_booking) {
                    $match_result = $this->match_resos_to_hotel_booking($rest_booking, $hotel_booking);
                    
                    if ($match_result['matched']) {
                        // If this is a primary match (booking ID or agent ref), use it immediately
                        if ($match_result['is_primary']) {
                            $best_match = $match_result;
                            $best_match_room = $room_num;
                            break;
                        }
                        
                        // Otherwise, keep track of the best secondary match
                        if ($best_match === null || $match_result['confidence'] === 'high') {
                            $best_match = $match_result;
                            $best_match_room = $room_num;
                        }
                    }
                }
                
                // If we found a match, add it to the matched bookings
                if ($best_match !== null && $best_match_room !== null) {
                    // Extract restaurant booking details
                    $booking_time = '';
                    if (isset($rest_booking['time'])) {
                        $booking_time = $rest_booking['time'];
                    } elseif (isset($rest_booking['dateTime'])) {
                        $booking_time = date('H:i', strtotime($rest_booking['dateTime']));
                    }
                    
                    $party_size = isset($rest_booking['people']) ? intval($rest_booking['people']) : 0;
                    
                    $booking_data = array(
                        'time' => $booking_time,
                        'people' => $party_size,
                        'match_info' => $best_match,
                        'resos_booking' => $rest_booking,
                        'hotel_booking' => $bookings_by_room[$best_match_room]
                    );
                    // Store multiple bookings per room
                    if (!isset($matched_restaurant_bookings[$best_match_room])) {
                        $matched_restaurant_bookings[$best_match_room] = array();
                    }
                    
                    $matched_restaurant_bookings[$best_match_room][] = $booking_data;
                }
            }
        }

        // Prepare ALL restaurant bookings for Gantt chart (not just matched ones)
        $all_restaurant_bookings = array('all' => array());
        if (!empty($restaurant_bookings) && is_array($restaurant_bookings)) {
            foreach ($restaurant_bookings as $rest_booking) {
                $booking_time = '';
                if (isset($rest_booking['time'])) {
                    $booking_time = $rest_booking['time'];
                } elseif (isset($rest_booking['dateTime'])) {
                    $booking_time = date('H:i', strtotime($rest_booking['dateTime']));
                }

                $party_size = isset($rest_booking['people']) ? intval($rest_booking['people']) : 0;

                // Try different possible name fields from Resos API (guest.name is the primary field)
                $guest_name = 'Guest';
                if (isset($rest_booking['guest']['name']) && !empty($rest_booking['guest']['name'])) {
                    $guest_name = $rest_booking['guest']['name'];
                } elseif (isset($rest_booking['name']) && !empty($rest_booking['name'])) {
                    $guest_name = $rest_booking['name'];
                } elseif (isset($rest_booking['guestName']) && !empty($rest_booking['guestName'])) {
                    $guest_name = $rest_booking['guestName'];
                } elseif (isset($rest_booking['customerName']) && !empty($rest_booking['customerName'])) {
                    $guest_name = $rest_booking['customerName'];
                } elseif (isset($rest_booking['firstName']) && isset($rest_booking['lastName'])) {
                    $guest_name = trim($rest_booking['firstName'] . ' ' . $rest_booking['lastName']);
                } elseif (isset($rest_booking['customer']['name']) && !empty($rest_booking['customer']['name'])) {
                    $guest_name = $rest_booking['customer']['name'];
                }

                // Determine room number from matched bookings
                $room_identifier = 'Non-Resident';

                // Check if this booking is in matched_restaurant_bookings
                // Try multiple ID fields and also match by name+time if ID not available
                $match_attempts = 0;
                foreach ($matched_restaurant_bookings as $room => $bookings) {
                    foreach ($bookings as $matched) {
                        $match_attempts++;
                        $is_match = false;

                        // Try matching by ID (Resos uses '_id' field)
                        $rest_id = $rest_booking['_id'] ?? $rest_booking['id'] ?? $rest_booking['bookingId'] ?? $rest_booking['reservationId'] ?? null;
                        $matched_id = $matched['resos_booking']['_id'] ?? $matched['resos_booking']['id'] ?? $matched['resos_booking']['bookingId'] ?? $matched['resos_booking']['reservationId'] ?? null;

                        if ($rest_id !== null && $matched_id !== null && $rest_id === $matched_id) {
                            $is_match = true;
                        }

                        // Fallback: Match by name and time (Resos stores name at guest.name)
                        if (!$is_match) {
                            $matched_name = $matched['resos_booking']['guest']['name'] ?? $matched['resos_booking']['name'] ?? '';
                            $matched_time = $matched['resos_booking']['time'] ?? '';
                            if (!empty($guest_name) && !empty($booking_time)
                                && $guest_name === $matched_name
                                && $booking_time === $matched_time) {
                                $is_match = true;
                            }
                        }

                        if ($is_match) {
                            $room_identifier = $room;
                            break 2;
                        }
                    }
                }

                // Extract notes from Resos booking
                $notes = array();

                // Check for restaurantNotes array (staff/internal notes)
                if (isset($rest_booking['restaurantNotes']) && is_array($rest_booking['restaurantNotes'])) {
                    foreach ($rest_booking['restaurantNotes'] as $note_obj) {
                        if (isset($note_obj['restaurantNote']) && !empty($note_obj['restaurantNote'])) {
                            $notes[] = array(
                                'type' => 'internal',
                                'content' => $note_obj['restaurantNote']
                            );
                        }
                    }
                }

                // Check for guest comments array (messages from guest)
                if (isset($rest_booking['comments']) && is_array($rest_booking['comments'])) {
                    foreach ($rest_booking['comments'] as $comment_obj) {
                        // Only include non-system comments (guest messages)
                        if (isset($comment_obj['comment'])
                            && isset($comment_obj['role'])
                            && $comment_obj['role'] !== 'system'
                            && !empty($comment_obj['comment'])) {
                            $notes[] = array(
                                'type' => 'guest',
                                'content' => $comment_obj['comment']
                            );
                        }
                    }
                }

                // Extract table information
                $tables_info = array();
                if (isset($rest_booking['tables']) && is_array($rest_booking['tables'])) {
                    foreach ($rest_booking['tables'] as $table) {
                        if (isset($table['name'])) {
                            $table_str = $table['name'];
                            // Add area if available
                            if (isset($table['area']['name']) && !empty($table['area']['name'])) {
                                $table_str .= ' (' . $table['area']['name'] . ')';
                            }
                            $tables_info[] = $table_str;
                        }
                    }
                }

                $all_restaurant_bookings['all'][] = array(
                    'resos_booking' => array(
                        'name' => $guest_name,
                        'time' => $booking_time,
                        'people' => $party_size,
                        'room' => $room_identifier,
                        'notes' => $notes,
                        'tables' => $tables_info
                    )
                );
            }
        }

        // Organize rooms (needs bookings data for grouping)
        $organized_rooms = $this->organize_rooms($all_rooms, $bookings_by_room);
        
        // Start output
        ob_start();
        
        // Display errors if any
        if (!empty($this->errors)) {
            echo '<div class="hotel-booking-errors" style="background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
            echo '<strong>Errors:</strong><ul style="margin: 10px 0 0 20px;">';
            foreach ($this->errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
        
        ?>
        <div class="hotel-booking-container">
            <div class="hotel-booking-header">
                <div class="header-left">
                    <h1>Hotel Bookings by Date</h1>
                    <div class="subtitle">Guest restaurant booking creation and matching tool</div>
                </div>
                <div class="viewing-date"><?php echo date('l, F j, Y', strtotime($input_date)); ?></div>
            </div>

            <div class="date-selector">
                <div class="date-selector-left">
                    <label for="booking-date">Select Date:</label>
                    <input type="date" id="booking-date" value="<?php echo esc_attr($input_date); ?>" onchange="updateDate()">
                    <button onclick="updateDate()">View</button>
                </div>

                <div class="date-selector-right">
                    <label for="opening-time-selector" class="service-period-label">Default Service Period:</label>
                    <select id="opening-time-selector" onchange="switchServicePeriod()">
                        <option value="">Loading...</option>
                    </select>
                </div>
            </div>
            
            <div class="hotel-booking-content">
                <?php if (empty($organized_rooms['regular']) && empty($organized_rooms['grouped'])): ?>
                    <div class="no-rooms-message">
                        <p>No rooms found. Please check your API configuration.</p>
                    </div>
                <?php else: ?>
                    
                    <?php if (!empty($organized_rooms['regular'])): ?>
                        <!-- Regular Rooms Table -->
                        <table class="booking-table">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Room</th>
                                    <th>Guest Name</th>
                                    <th style="width: 80px;">Occupancy</th>
                                    <th style="width: 150px;">Night Status</th>
                                    <th style="width: 200px;">Restaurant Booking</th>
                                    <th style="width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($organized_rooms['regular'] as $room): ?>
                                    <?php
                                    $room_number = $room['site_name'];
                                    $room_category = isset($room['type_name']) ? $room['type_name'] : '';
                                    $has_booking = isset($bookings_by_room[$room_number]);
                                    
                                    if ($has_booking) {
                                        $booking = $bookings_by_room[$room_number];
                                        $guest_name = $this->get_primary_guest_name($booking);
                                        $booking_status = isset($booking['booking_status']) ? strtolower($booking['booking_status']) : '';
                                        $night_status = $this->get_night_status(
                                            $input_date,
                                            $booking['booking_arrival'],
                                            $booking['booking_departure']
                                        );

                                        // Get occupancy info
                                        $adults = isset($booking['booking_adults']) ? intval($booking['booking_adults']) : 0;
                                        $children = isset($booking['booking_children']) ? intval($booking['booking_children']) : 0;
                                        $infants = isset($booking['booking_infants']) ? intval($booking['booking_infants']) : 0;
                                        
                                        // Build occupancy string
                                        $occupancy_parts = array($adults . 'A');
                                        if ($children > 0) $occupancy_parts[] = $children . 'C';
                                        if ($infants > 0) $occupancy_parts[] = $infants . 'I';
                                        $occupancy_string = implode(', ', $occupancy_parts);

                                        // Calculate total party size for restaurant (adults + children, excluding infants)
                                        $total_party_size = $adults + $children;
                                        
                                        // Get rate info for today
                                        $rate_type = '-';
                                        $rate_amount = '-';
                                        if (isset($booking['tariffs_quoted']) && is_array($booking['tariffs_quoted'])) {
                                            foreach ($booking['tariffs_quoted'] as $tariff) {
                                                if (isset($tariff['stay_date']) && $tariff['stay_date'] == $input_date) {
                                                    $rate_type = isset($tariff['label']) ? $tariff['label'] : '-';
                                                    $rate_amount = isset($tariff['calculated_amount']) ? '&pound;' . number_format($tariff['calculated_amount'], 2) : '-';
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        // Build tooltip content - use separate data attributes
                                        $tooltip_booking_id = isset($booking['booking_id']) ? $booking['booking_id'] : '-';
                                        $tooltip_rate_type = esc_attr($rate_type);
                                        $tooltip_rate = esc_attr($rate_amount);
                                        $tooltip_source = isset($booking['booking_source_name']) ? esc_attr($booking['booking_source_name']) : 'None';
                                        $tooltip_agent = isset($booking['travel_agent_name']) && !empty($booking['travel_agent_name']) ? esc_attr($booking['travel_agent_name']) : 'None';
                                        
                                        // Prepare notes with type names
                                        $tooltip_notes = array();
                                        if (isset($booking['notes']) && is_array($booking['notes'])) {
                                            foreach ($booking['notes'] as $note) {
                                                $note_type_id = isset($note['type_id']) ? $note['type_id'] : '';
                                                $note_type_name = isset($note_types[$note_type_id]) ? $note_types[$note_type_id] : 'Note';
                                                $tooltip_notes[] = array(
                                                    'type' => $note_type_name,
                                                    'content' => isset($note['content']) ? $note['content'] : ''
                                                );
                                            }
                                        }
                                        $tooltip_notes_json = esc_attr(json_encode($tooltip_notes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));

                                        // Prepare guest data for create booking form
                                        $guest_data = $this->prepare_guest_data_for_create_booking($booking, $input_date);
                                        $guest_data_json = esc_attr(json_encode($guest_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
                                    }
                                    ?>
                                    <tr data-room-id="<?php echo esc_attr($room_number); ?>">
                                        <td>
                                            <div class="room-number"><?php echo esc_html($room_number); ?></div>
                                            <?php if ($room_category): ?>
                                                <span class="room-category"><?php echo esc_html($room_category); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_booking): ?>
                                                <div class="guest-info-cell">
                                                    <a href="https://appeu.newbook.cloud/bookings_view/<?php echo esc_attr($tooltip_booking_id); ?>"
                                                       target="_blank"
                                                       rel="noopener noreferrer"
                                                       class="guest-name-link has-tooltip"
                                                       data-booking-id="<?php echo esc_attr($tooltip_booking_id); ?>"
                                                       data-rate-type="<?php echo $tooltip_rate_type; ?>"
                                                       data-rate="<?php echo $tooltip_rate; ?>"
                                                       data-source="<?php echo $tooltip_source; ?>"
                                                       data-agent="<?php echo $tooltip_agent; ?>"
                                                       data-notes="<?php echo $tooltip_notes_json; ?>">
                                                        <span class="guest-name">
                                                            <?php echo esc_html($guest_name); ?>
                                                        </span>
                                                    </a>
                                                    <?php if ($booking_status): ?>
                                                        <span class="booking-status status-<?php echo esc_attr($booking_status); ?>">
                                                            <?php echo esc_html(ucfirst($booking_status)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="empty-cell">Vacant</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="occupancy-cell">
                                            <?php if ($has_booking): ?>
                                                <span class="occupancy"><?php echo esc_html($occupancy_string); ?></span>
                                            <?php else: ?>
                                                <span class="empty-cell">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($has_booking): ?>
                                                <span class="night-status <?php echo esc_attr($night_status['class']); ?>">
                                                    <?php echo esc_html($night_status['status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="night-status vacant">Vacant</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($matched_restaurant_bookings[$room_number])): ?>
                                                <?php foreach ($matched_restaurant_bookings[$room_number] as $index => $rest_booking): ?>
                                                    <?php 
                                                    $is_confirmed = $rest_booking['match_info']['is_primary'];
                                                    $match_class = $is_confirmed ? 'confirmed-match' : 'suggested-match';
                                                    
                                                    // Prepare comparison data for both suggested AND confirmed matches
                                                    $comparison_data_attr = '';
                                                    if (isset($rest_booking['hotel_booking']) && isset($rest_booking['resos_booking'])) {
                                                        $comparison = $this->prepare_comparison_data($rest_booking['hotel_booking'], $rest_booking['resos_booking'], $input_date);
                                                        $comparison_data_attr = ' data-comparison=\'' . esc_attr(json_encode($comparison, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) . '\'';
                                                    }
                                                    
                                                    $unique_id = 'rest-' . $room_number . '-' . $index;

                                                    // Extract tooltip data from resos_booking
                                                    $tooltip_guest_name = isset($rest_booking['resos_booking']['guest']['name']) ? $rest_booking['resos_booking']['guest']['name'] : '';
                                                    $tooltip_phone = isset($rest_booking['resos_booking']['guest']['phone']) ? $rest_booking['resos_booking']['guest']['phone'] : '';
                                                    $tooltip_email = isset($rest_booking['resos_booking']['guest']['email']) ? $rest_booking['resos_booking']['guest']['email'] : '';
                                                    $tooltip_status = isset($rest_booking['resos_booking']['status']) ? $rest_booking['resos_booking']['status'] : 'request';
                                                    $tooltip_booking_id = isset($rest_booking['resos_booking']['_id']) ? $rest_booking['resos_booking']['_id'] : '';
                                                    $tooltip_restaurant_id = isset($rest_booking['resos_booking']['restaurantId']) ? $rest_booking['resos_booking']['restaurantId'] : '';

                                                    // Extract tables
                                                    $tooltip_tables = array();
                                                    if (isset($rest_booking['resos_booking']['tables']) && is_array($rest_booking['resos_booking']['tables'])) {
                                                        foreach ($rest_booking['resos_booking']['tables'] as $table) {
                                                            if (isset($table['name'])) {
                                                                $tooltip_tables[] = $table['name'];
                                                            }
                                                        }
                                                    }
                                                    $tooltip_tables_str = !empty($tooltip_tables) ? implode(', ', $tooltip_tables) : '';

                                                    // Extract notes
                                                    $tooltip_notes = array();
                                                    if (isset($rest_booking['resos_booking']['restaurantNotes']) && is_array($rest_booking['resos_booking']['restaurantNotes'])) {
                                                        foreach ($rest_booking['resos_booking']['restaurantNotes'] as $note) {
                                                            if (isset($note['restaurantNote'])) {
                                                                $tooltip_notes[] = array('type' => 'internal', 'content' => $note['restaurantNote']);
                                                            }
                                                        }
                                                    }
                                                    if (isset($rest_booking['resos_booking']['comments']) && is_array($rest_booking['resos_booking']['comments'])) {
                                                        foreach ($rest_booking['resos_booking']['comments'] as $comment) {
                                                            if (isset($comment['comment']) && empty($comment['system'])) {
                                                                $tooltip_notes[] = array('type' => 'guest', 'content' => $comment['comment']);
                                                            }
                                                        }
                                                    }
                                                    $tooltip_notes_json = esc_attr(json_encode($tooltip_notes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
                                                    ?>
                                                    <div class="restaurant-booking has-comparison-tooltip <?php echo $match_class; ?> <?php echo !$is_confirmed ? 'expandable-match' : ''; ?>"
                                                         <?php echo $comparison_data_attr; ?>
                                                         data-room="<?php echo esc_attr($room_number); ?>"
                                                         data-index="<?php echo esc_attr($index); ?>"
                                                         data-unique-id="<?php echo esc_attr($unique_id); ?>"
                                                         data-status="<?php echo esc_attr($tooltip_status); ?>"
                                                         data-tooltip-guest-name="<?php echo esc_attr($tooltip_guest_name); ?>"
                                                         data-tooltip-phone="<?php echo esc_attr($tooltip_phone); ?>"
                                                         data-tooltip-email="<?php echo esc_attr($tooltip_email); ?>"
                                                         data-tooltip-tables="<?php echo esc_attr($tooltip_tables_str); ?>"
                                                         data-tooltip-notes="<?php echo $tooltip_notes_json; ?>"
                                                         data-tooltip-booking-id="<?php echo esc_attr($tooltip_booking_id); ?>"
                                                         data-tooltip-restaurant-id="<?php echo esc_attr($tooltip_restaurant_id); ?>"
                                                         data-tooltip-date="<?php echo esc_attr($input_date); ?>">
                                                        <div class="booking-time">
                                                            <?php echo esc_html($rest_booking['time']); ?>
                                                            (<?php echo esc_html($rest_booking['people']); ?> pax)
                                                        </div>
                                                        <div class="match-indicator">
                                                            <small>
                                                                <span class="status-icon" data-status="<?php echo esc_attr($rest_booking['resos_booking']['status'] ?? 'request'); ?>"></span>
                                                                <?php if ($is_confirmed): ?>
                                                                    &#10004; Booking # match
                                                                <?php else: ?>
                                                                    Matched: <?php echo esc_html($rest_booking['match_info']['match_label']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="empty-cell">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-cell">
                                            <?php
                                            // Determine which button to show based on match status
                                            $has_matches = isset($matched_restaurant_bookings[$room_number]) && !empty($matched_restaurant_bookings[$room_number]);

                                            if ($has_matches):
                                                $first_match = $matched_restaurant_bookings[$room_number][0];
                                                $is_confirmed_match = $first_match['match_info']['is_primary'];
                                                $match_unique_id = 'rest-' . $room_number . '-0';

                                                // Check if there are suggested updates for confirmed matches
                                                $has_updates = false;
                                                if ($is_confirmed_match && isset($first_match['hotel_booking']) && isset($first_match['resos_booking'])) {
                                                    $comp_check = $this->prepare_comparison_data($first_match['hotel_booking'], $first_match['resos_booking'], $input_date);
                                                    $has_updates = !empty($comp_check['suggested_updates']);
                                                }

                                                if ($is_confirmed_match && $has_updates):
                                            ?>
                                                <button class="btn-check-updates" onclick="toggleComparisonRow('<?php echo esc_js($match_unique_id); ?>', '<?php echo esc_js($room_number); ?>', 'updates')">
                                                    <span class="material-symbols-outlined">update</span> Check Updates
                                                </button>
                                            <?php elseif ($is_confirmed_match && !$has_updates): ?>
                                                <button class="btn-view-details" onclick="toggleComparisonRow('<?php echo esc_js($match_unique_id); ?>', '<?php echo esc_js($room_number); ?>', 'confirmed')">
                                                    <span class="material-symbols-outlined">visibility</span> View Details
                                                </button>
                                            <?php elseif (!$is_confirmed_match): ?>
                                                <button class="btn-check-match" onclick="toggleComparisonRow('<?php echo esc_js($match_unique_id); ?>', '<?php echo esc_js($room_number); ?>', 'suggested')">
                                                    <span class="material-symbols-outlined">search</span> Check Match
                                                </button>
                                            <?php endif; ?>
                                            <?php elseif ($has_booking): ?>
                                            <button class="btn-create-booking"
                                                    data-guest-info='<?php echo $guest_data_json; ?>'
                                                    onclick="toggleCreateBookingRow('<?php echo esc_js($room_number); ?>', '<?php echo esc_js($input_date); ?>', <?php echo intval($total_party_size); ?>, this)">
                                                <span class="material-symbols-outlined">add_circle</span> Create Booking
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <?php if (!empty($organized_rooms['grouped'])): ?>
                        <!-- Grouped Rooms -->
                        <?php foreach ($organized_rooms['grouped'] as $group_id => $group_data): ?>
                            <?php
                            // Fetch group details to get group-level notes
                            $group_details = $this->get_group_details($group_id);
                            $group_notes = array();
                            $group_reference_id = '';
                            
                            if ($group_details) {
                                // Get group reference ID
                                if (isset($group_details['id'])) {
                                    $group_reference_id = strval($group_details['id']);
                                } elseif (isset($group_details['reference_id'])) {
                                    $group_reference_id = strval($group_details['reference_id']);
                                }
                                
                                // Extract group-level notes (not booking notes)
                                if (isset($group_details['notes']) && is_array($group_details['notes'])) {
                                    foreach ($group_details['notes'] as $note) {
                                        if (isset($note['type_id']) && isset($note['content'])) {
                                            $note_type_id = strval($note['type_id']);
                                            $note_type_name = isset($note_types[$note_type_id]) ? $note_types[$note_type_id] : 'Note';
                                            $group_notes[] = array(
                                                'type' => $note_type_name,
                                                'content' => strval($note['content'])
                                            );
                                        }
                                    }
                                }
                            } else {
                                // Fallback: use group_id as reference if API call failed
                                $group_reference_id = strval($group_id);
                            }
                            
                            $group_notes_json = esc_attr(json_encode($group_notes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
                            ?>
                            <div class="group-section">
                                <h2 class="group-header has-group-tooltip" 
                                    data-group-id="<?php echo esc_attr($group_id); ?>"
                                    data-group-name="<?php echo esc_attr($group_data['name']); ?>"
                                    data-group-reference="<?php echo esc_attr($group_reference_id); ?>"
                                    data-room-count="<?php echo count($group_data['rooms']); ?>"
                                    data-notes="<?php echo $group_notes_json; ?>">
                                    <?php echo esc_html($group_data['name']); ?>
                                </h2>
                                <table class="booking-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Room</th>
                                            <th>Guest Name</th>
                                            <th style="width: 80px;">Occupancy</th>
                                            <th style="width: 150px;">Night Status</th>
                                            <th style="width: 200px;">Restaurant Booking</th>
                                            <th style="width: 140px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group_data['rooms'] as $room): ?>
                                            <?php
                                            $room_number = $room['site_name'];
                                            $room_category = isset($room['type_name']) ? $room['type_name'] : '';
                                            $has_booking = isset($bookings_by_room[$room_number]);
                                            
                                            if ($has_booking) {
                                                $booking = $bookings_by_room[$room_number];
                                                $guest_name = $this->get_primary_guest_name($booking);
                                                $booking_status = isset($booking['booking_status']) ? strtolower($booking['booking_status']) : '';
                                                $night_status = $this->get_night_status(
                                                    $input_date,
                                                    $booking['booking_arrival'],
                                                    $booking['booking_departure']
                                                );

                                                // Get occupancy info
                                                $adults = isset($booking['booking_adults']) ? intval($booking['booking_adults']) : 0;
                                                $children = isset($booking['booking_children']) ? intval($booking['booking_children']) : 0;
                                                $infants = isset($booking['booking_infants']) ? intval($booking['booking_infants']) : 0;
                                                
                                                // Build occupancy string
                                                $occupancy_parts = array($adults . 'A');
                                                if ($children > 0) $occupancy_parts[] = $children . 'C';
                                                if ($infants > 0) $occupancy_parts[] = $infants . 'I';
                                                $occupancy_string = implode(', ', $occupancy_parts);

                                                // Calculate total party size for restaurant (adults + children, excluding infants)
                                                $total_party_size = $adults + $children;
                                                
                                                // Get rate info for today
                                                $rate_type = '-';
                                                $rate_amount = '-';
                                                if (isset($booking['tariffs_quoted']) && is_array($booking['tariffs_quoted'])) {
                                                    foreach ($booking['tariffs_quoted'] as $tariff) {
                                                        if (isset($tariff['stay_date']) && $tariff['stay_date'] == $input_date) {
                                                            $rate_type = isset($tariff['label']) ? $tariff['label'] : '-';
                                                            $rate_amount = isset($tariff['calculated_amount']) ? '&pound;' . number_format($tariff['calculated_amount'], 2) : '-';
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                // Build tooltip content - use separate data attributes
                                                $tooltip_booking_id = isset($booking['booking_id']) ? $booking['booking_id'] : '-';
                                                $tooltip_rate_type = esc_attr($rate_type);
                                                $tooltip_rate = esc_attr($rate_amount);
                                                $tooltip_source = isset($booking['booking_source_name']) ? esc_attr($booking['booking_source_name']) : 'None';
                                                $tooltip_agent = isset($booking['travel_agent_name']) && !empty($booking['travel_agent_name']) ? esc_attr($booking['travel_agent_name']) : 'None';
                                                
                                                // Prepare notes with type names
                                                $tooltip_notes = array();
                                                if (isset($booking['notes']) && is_array($booking['notes'])) {
                                                    foreach ($booking['notes'] as $note) {
                                                        $note_type_id = isset($note['type_id']) ? $note['type_id'] : '';
                                                        $note_type_name = isset($note_types[$note_type_id]) ? $note_types[$note_type_id] : 'Note';
                                                        $tooltip_notes[] = array(
                                                            'type' => $note_type_name,
                                                            'content' => isset($note['content']) ? $note['content'] : ''
                                                        );
                                                    }
                                                }
                                                $tooltip_notes_json = esc_attr(json_encode($tooltip_notes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));

                                                // Prepare guest data for create booking form
                                                $guest_data = $this->prepare_guest_data_for_create_booking($booking, $input_date);
                                                $guest_data_json = esc_attr(json_encode($guest_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
                                            }
                                            ?>
                                            <tr data-room-id="<?php echo esc_attr($room_number); ?>">
                                                <td>
                                                    <div class="room-number"><?php echo esc_html($room_number); ?></div>
                                                    <?php if ($room_category): ?>
                                                        <span class="room-category"><?php echo esc_html($room_category); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($has_booking): ?>
                                                        <div class="guest-info-cell">
                                                            <a href="https://appeu.newbook.cloud/bookings_view/<?php echo esc_attr($tooltip_booking_id); ?>"
                                                               target="_blank"
                                                               rel="noopener noreferrer"
                                                               class="guest-name-link has-tooltip"
                                                               data-booking-id="<?php echo esc_attr($tooltip_booking_id); ?>"
                                                               data-rate-type="<?php echo $tooltip_rate_type; ?>"
                                                               data-rate="<?php echo $tooltip_rate; ?>"
                                                               data-source="<?php echo $tooltip_source; ?>"
                                                               data-agent="<?php echo $tooltip_agent; ?>"
                                                               data-notes="<?php echo $tooltip_notes_json; ?>">
                                                                <span class="guest-name">
                                                                    <?php echo esc_html($guest_name); ?>
                                                                </span>
                                                            </a>
                                                            <?php if ($booking_status): ?>
                                                                <span class="booking-status status-<?php echo esc_attr($booking_status); ?>">
                                                                    <?php echo esc_html(ucfirst($booking_status)); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="empty-cell">Vacant</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="occupancy-cell">
                                                    <?php if ($has_booking): ?>
                                                        <span class="occupancy"><?php echo esc_html($occupancy_string); ?></span>
                                                    <?php else: ?>
                                                        <span class="empty-cell">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($has_booking): ?>
                                                        <span class="night-status <?php echo esc_attr($night_status['class']); ?>">
                                                            <?php echo esc_html($night_status['status']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="night-status vacant">Vacant</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($matched_restaurant_bookings[$room_number])): ?>
                                                        <?php foreach ($matched_restaurant_bookings[$room_number] as $index => $rest_booking): ?>
                                                            <?php 
                                                            $is_confirmed = $rest_booking['match_info']['is_primary'];
                                                            $match_class = $is_confirmed ? 'confirmed-match' : 'suggested-match';
                                                            
                                                            // Prepare comparison data for both suggested AND confirmed matches
                                                            $comparison_data_attr = '';
                                                            if (isset($rest_booking['hotel_booking']) && isset($rest_booking['resos_booking'])) {
                                                                $comparison = $this->prepare_comparison_data($rest_booking['hotel_booking'], $rest_booking['resos_booking'], $input_date);
                                                                $comparison_data_attr = ' data-comparison=\'' . esc_attr(json_encode($comparison, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) . '\'';
                                                            }
                                                            
                                                            $unique_id = 'rest-' . $room_number . '-' . $index;

                                                            // Extract tooltip data from resos_booking
                                                            $tooltip_guest_name = isset($rest_booking['resos_booking']['guest']['name']) ? $rest_booking['resos_booking']['guest']['name'] : '';
                                                            $tooltip_phone = isset($rest_booking['resos_booking']['guest']['phone']) ? $rest_booking['resos_booking']['guest']['phone'] : '';
                                                            $tooltip_email = isset($rest_booking['resos_booking']['guest']['email']) ? $rest_booking['resos_booking']['guest']['email'] : '';
                                                            $tooltip_status = isset($rest_booking['resos_booking']['status']) ? $rest_booking['resos_booking']['status'] : 'request';
                                                            $tooltip_booking_id = isset($rest_booking['resos_booking']['_id']) ? $rest_booking['resos_booking']['_id'] : '';
                                                            $tooltip_restaurant_id = isset($rest_booking['resos_booking']['restaurantId']) ? $rest_booking['resos_booking']['restaurantId'] : '';

                                                            // Extract tables
                                                            $tooltip_tables = array();
                                                            if (isset($rest_booking['resos_booking']['tables']) && is_array($rest_booking['resos_booking']['tables'])) {
                                                                foreach ($rest_booking['resos_booking']['tables'] as $table) {
                                                                    if (isset($table['name'])) {
                                                                        $tooltip_tables[] = $table['name'];
                                                                    }
                                                                }
                                                            }
                                                            $tooltip_tables_str = !empty($tooltip_tables) ? implode(', ', $tooltip_tables) : '';

                                                            // Extract notes
                                                            $tooltip_notes = array();
                                                            if (isset($rest_booking['resos_booking']['restaurantNotes']) && is_array($rest_booking['resos_booking']['restaurantNotes'])) {
                                                                foreach ($rest_booking['resos_booking']['restaurantNotes'] as $note) {
                                                                    if (isset($note['restaurantNote'])) {
                                                                        $tooltip_notes[] = array('type' => 'internal', 'content' => $note['restaurantNote']);
                                                                    }
                                                                }
                                                            }
                                                            if (isset($rest_booking['resos_booking']['comments']) && is_array($rest_booking['resos_booking']['comments'])) {
                                                                foreach ($rest_booking['resos_booking']['comments'] as $comment) {
                                                                    if (isset($comment['comment']) && empty($comment['system'])) {
                                                                        $tooltip_notes[] = array('type' => 'guest', 'content' => $comment['comment']);
                                                                    }
                                                                }
                                                            }
                                                            $tooltip_notes_json = esc_attr(json_encode($tooltip_notes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
                                                            ?>
                                                            <div class="restaurant-booking has-comparison-tooltip <?php echo $match_class; ?> <?php echo !$is_confirmed ? 'expandable-match' : ''; ?>"
                                                                 <?php echo $comparison_data_attr; ?>
                                                                 data-room="<?php echo esc_attr($room_number); ?>"
                                                                 data-index="<?php echo esc_attr($index); ?>"
                                                                 data-unique-id="<?php echo esc_attr($unique_id); ?>"
                                                                 data-status="<?php echo esc_attr($tooltip_status); ?>"
                                                                 data-tooltip-guest-name="<?php echo esc_attr($tooltip_guest_name); ?>"
                                                                 data-tooltip-phone="<?php echo esc_attr($tooltip_phone); ?>"
                                                                 data-tooltip-email="<?php echo esc_attr($tooltip_email); ?>"
                                                                 data-tooltip-tables="<?php echo esc_attr($tooltip_tables_str); ?>"
                                                                 data-tooltip-notes="<?php echo $tooltip_notes_json; ?>"
                                                                 data-tooltip-booking-id="<?php echo esc_attr($tooltip_booking_id); ?>"
                                                                 data-tooltip-restaurant-id="<?php echo esc_attr($tooltip_restaurant_id); ?>"
                                                                 data-tooltip-date="<?php echo esc_attr($input_date); ?>">
                                                                <div class="booking-time">
                                                                    <?php echo esc_html($rest_booking['time']); ?>
                                                                    (<?php echo esc_html($rest_booking['people']); ?> pax)
                                                                </div>
                                                                <div class="match-indicator">
                                                                    <small>
                                                                        <span class="status-icon" data-status="<?php echo esc_attr($rest_booking['resos_booking']['status'] ?? 'request'); ?>"></span>
                                                                        <?php if ($is_confirmed): ?>
                                                                            &#10004; Booking # match
                                                                        <?php else: ?>
                                                                            Matched: <?php echo esc_html($rest_booking['match_info']['match_label']); ?>
                                                                        <?php endif; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="empty-cell">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="action-cell">
                                                    <?php
                                                    // Determine which button to show based on match status
                                                    $has_matches = isset($matched_restaurant_bookings[$room_number]) && !empty($matched_restaurant_bookings[$room_number]);

                                                    if ($has_matches):
                                                        $first_match = $matched_restaurant_bookings[$room_number][0];
                                                        $is_confirmed_match = $first_match['match_info']['is_primary'];
                                                        $match_unique_id = 'rest-' . $room_number . '-0';

                                                        // Check if there are suggested updates for confirmed matches
                                                        $has_updates = false;
                                                        if ($is_confirmed_match && isset($first_match['hotel_booking']) && isset($first_match['resos_booking'])) {
                                                            $comp_check = $this->prepare_comparison_data($first_match['hotel_booking'], $first_match['resos_booking'], $input_date);
                                                            $has_updates = !empty($comp_check['suggested_updates']);
                                                        }

                                                        if ($is_confirmed_match && $has_updates):
                                                    ?>
                                                        <button class="btn-check-updates" onclick="toggleComparisonRow('<?php echo esc_js($match_unique_id); ?>', '<?php echo esc_js($room_number); ?>', 'updates')">
                                                            <span class="material-symbols-outlined">update</span> Check Updates
                                                        </button>
                                                    <?php elseif ($is_confirmed_match && !$has_updates): ?>
                                                        <button class="btn-view-details" onclick="toggleComparisonRow('<?php echo esc_js($match_unique_id); ?>', '<?php echo esc_js($room_number); ?>', 'confirmed')">
                                                            <span class="material-symbols-outlined">visibility</span> View Details
                                                        </button>
                                                    <?php elseif (!$is_confirmed_match): ?>
                                                        <button class="btn-check-match" onclick="toggleComparisonRow('<?php echo esc_js($match_unique_id); ?>', '<?php echo esc_js($room_number); ?>', 'suggested')">
                                                            <span class="material-symbols-outlined">search</span> Check Match
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php elseif ($has_booking): ?>
                                                    <button class="btn-create-booking"
                                                            data-guest-info='<?php echo $guest_data_json; ?>'
                                                            onclick="toggleCreateBookingRow('<?php echo esc_js($room_number); ?>', '<?php echo esc_js($input_date); ?>', <?php echo intval($total_party_size); ?>, this)">
                                                        <span class="material-symbols-outlined">add_circle</span> Create Booking
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                <?php endif; ?>

                <!-- Plugin Version -->
                <div class="plugin-version">
                    Plugin v<?php echo self::VERSION; ?> | JS v<?php echo self::JS_VERSION; ?>
                </div>
            </div>
        </div>

        <!-- Store restaurant bookings data in a safe JSON script tag -->
        <script type="application/json" id="restaurant-bookings-data">
        <?php echo json_encode($all_restaurant_bookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        </script>

        <?php

        // Get the buffered content and return
        return ob_get_clean();
    }
}

// Initialize the plugin
new Hotel_Booking_Table();

// AJAX handler for testing API connection
add_action('wp_ajax_test_hotel_booking_api', 'test_hotel_booking_api_connection');

function test_hotel_booking_api_connection() {
    $username = get_option('hotel_booking_api_username');
    $password = get_option('hotel_booking_api_password');
    $api_key = get_option('hotel_booking_api_key');
    $region = get_option('hotel_booking_api_region', 'au');
    
    if (empty($username) || empty($password) || empty($api_key)) {
        wp_send_json_error(array('message' => 'API credentials not configured'));
        return;
    }
    
    $url = 'https://api.newbook.cloud/rest/sites_list';
    
    $data = array(
        'region' => $region,
        'api_key' => $api_key
    );
    
    $args = array(
        'method' => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ),
        'body' => json_encode($data)
    );
    
    $response = wp_remote_post($url, $args);
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code !== 200) {
        wp_send_json_error(array('message' => 'API returned status code ' . $response_code));
        return;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['success']) && $data['success'] === 'false') {
        $error_msg = isset($data['message']) ? $data['message'] : 'Unknown API error';
        wp_send_json_error(array('message' => $error_msg));
        return;
    }
    
    if (isset($data['data'])) {
        wp_send_json_success(array('rooms' => count($data['data'])));
    } else {
        wp_send_json_error(array('message' => 'Invalid API response format'));
    }
}