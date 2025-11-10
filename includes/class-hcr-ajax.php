<?php
/**
 * AJAX handlers class
 *
 * Handles all AJAX requests for the plugin
 */
class HCR_Ajax {

    /**
     * Handle save cash up (draft or final)
     */
    public function handle_save_cash_up() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        // Check user permissions
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        global $wpdb;

        // Sanitize inputs
        $session_date = sanitize_text_field($_POST['session_date']);
        $status = sanitize_text_field($_POST['status']); // 'draft' or 'final'
        $notes = sanitize_textarea_field($_POST['notes']);
        $machine_photo_id = isset($_POST['machine_photo_id']) && !empty($_POST['machine_photo_id']) ? intval($_POST['machine_photo_id']) : null;

        // Decode JSON strings from FormData
        $denominations = isset($_POST['denominations']) ? json_decode(stripslashes($_POST['denominations']), true) : array();
        $card_machines = isset($_POST['card_machines']) ? json_decode(stripslashes($_POST['card_machines']), true) : array();

        // Fallback to direct array if not JSON encoded (for backwards compatibility)
        if (!is_array($denominations)) {
            $denominations = isset($_POST['denominations']) ? $_POST['denominations'] : array();
        }
        if (!is_array($card_machines)) {
            $card_machines = isset($_POST['card_machines']) ? $_POST['card_machines'] : array();
        }

        // Validate required fields
        if (empty($session_date)) {
            wp_send_json_error(array('message' => 'Session date is required.'));
            return;
        }

        // Check if cash up already exists for this date
        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $cash_ups_table WHERE session_date = %s",
            $session_date
        ));

        $cash_up_id = null;
        $total_float_counted = 0;
        $total_cash_counted = 0;

        // Calculate totals - separate float from takings
        foreach ($denominations as $denom) {
            $count_type = isset($denom['count_type']) ? $denom['count_type'] : 'takings';
            if ($count_type === 'float') {
                $total_float_counted += floatval($denom['total_amount']);
            } else {
                $total_cash_counted += floatval($denom['total_amount']);
            }
        }

        if ($existing) {
            // Update existing cash up
            $cash_up_id = $existing->id;

            $update_data = array(
                'updated_at' => current_time('mysql'),
                'status' => $status,
                'total_float_counted' => $total_float_counted,
                'total_cash_counted' => $total_cash_counted,
                'notes' => $notes,
                'machine_photo_id' => $machine_photo_id
            );

            $format_array = array('%s', '%s', '%f', '%f', '%s', '%d');

            if ($status === 'final') {
                $update_data['submitted_at'] = current_time('mysql');
                $update_data['submitted_by'] = get_current_user_id();
                $format_array = array('%s', '%s', '%f', '%f', '%s', '%d', '%s', '%d');
            }

            $result = $wpdb->update(
                $cash_ups_table,
                $update_data,
                array('id' => $cash_up_id),
                $format_array,
                array('%d')
            );

            if ($result === false) {
                error_log('HCR: Failed to update cash up: ' . $wpdb->last_error);
                wp_send_json_error(array('message' => 'Failed to update cash up.'));
                return;
            }

            // Delete existing denominations and card machines for this cash up
            $wpdb->delete($wpdb->prefix . 'hcr_denominations', array('cash_up_id' => $cash_up_id), array('%d'));
            $wpdb->delete($wpdb->prefix . 'hcr_card_machines', array('cash_up_id' => $cash_up_id), array('%d'));
        } else {
            // Create new cash up
            $insert_data = array(
                'session_date' => $session_date,
                'created_by' => get_current_user_id(),
                'status' => $status,
                'total_float_counted' => $total_float_counted,
                'total_cash_counted' => $total_cash_counted,
                'notes' => $notes,
                'machine_photo_id' => $machine_photo_id
            );

            $format_array = array('%s', '%d', '%s', '%f', '%f', '%s', '%d');

            if ($status === 'final') {
                $insert_data['submitted_at'] = current_time('mysql');
                $insert_data['submitted_by'] = get_current_user_id();
                $format_array = array('%s', '%d', '%s', '%f', '%f', '%s', '%d', '%s', '%d');
            }

            $result = $wpdb->insert(
                $cash_ups_table,
                $insert_data,
                $format_array
            );

            if ($result === false) {
                error_log('HCR: Failed to insert cash up: ' . $wpdb->last_error);
                wp_send_json_error(array('message' => 'Failed to save cash up.'));
                return;
            }

            $cash_up_id = $wpdb->insert_id;
        }

        // Insert denominations
        $denominations_table = $wpdb->prefix . 'hcr_denominations';
        foreach ($denominations as $denom) {
            $count_type = isset($denom['count_type']) ? sanitize_text_field($denom['count_type']) : 'takings';
            $wpdb->insert(
                $denominations_table,
                array(
                    'cash_up_id' => $cash_up_id,
                    'count_type' => $count_type,
                    'denomination_type' => sanitize_text_field($denom['type']),
                    'denomination_value' => floatval($denom['value']),
                    'quantity' => !empty($denom['quantity']) ? intval($denom['quantity']) : null,
                    'value_entered' => !empty($denom['value_entered']) ? floatval($denom['value_entered']) : null,
                    'total_amount' => floatval($denom['total_amount'])
                ),
                array('%d', '%s', '%s', '%f', '%d', '%f', '%f')
            );
        }

        // Insert card machine data
        $card_machines_table = $wpdb->prefix . 'hcr_card_machines';
        foreach ($card_machines as $machine) {
            $wpdb->insert(
                $card_machines_table,
                array(
                    'cash_up_id' => $cash_up_id,
                    'machine_name' => sanitize_text_field($machine['name']),
                    'total_amount' => floatval($machine['total']),
                    'amex_amount' => floatval($machine['amex']),
                    'visa_mc_amount' => floatval($machine['visa_mc'])
                ),
                array('%d', '%s', '%f', '%f', '%f')
            );
        }

        // Handle file uploads for receipt photos
        if (isset($_FILES['receipt_photos']) && !empty($_FILES['receipt_photos']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');

            $attachments_table = $wpdb->prefix . 'hcr_cash_count_attachments';
            $upload_overrides = array('test_form' => false);
            $uploaded_files = array();

            // Process each uploaded file
            foreach ($_FILES['receipt_photos']['name'] as $key => $filename) {
                if ($_FILES['receipt_photos']['error'][$key] === UPLOAD_ERR_OK) {
                    // Validate file size (5MB max)
                    if ($_FILES['receipt_photos']['size'][$key] > 5 * 1024 * 1024) {
                        error_log('HCR: File too large: ' . $filename);
                        continue;
                    }

                    // Validate file type
                    $file_type = $_FILES['receipt_photos']['type'][$key];
                    $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'application/pdf');
                    if (!in_array($file_type, $allowed_types)) {
                        error_log('HCR: Invalid file type: ' . $file_type);
                        continue;
                    }

                    // Prepare file array for wp_handle_upload
                    $file = array(
                        'name' => $_FILES['receipt_photos']['name'][$key],
                        'type' => $_FILES['receipt_photos']['type'][$key],
                        'tmp_name' => $_FILES['receipt_photos']['tmp_name'][$key],
                        'error' => $_FILES['receipt_photos']['error'][$key],
                        'size' => $_FILES['receipt_photos']['size'][$key]
                    );

                    // Upload file
                    $uploaded_file = wp_handle_upload($file, $upload_overrides);

                    if (isset($uploaded_file['file'])) {
                        // Insert file record into attachments table
                        $wpdb->insert(
                            $attachments_table,
                            array(
                                'cash_up_id' => $cash_up_id,
                                'file_name' => sanitize_file_name($filename),
                                'file_path' => $uploaded_file['url'],
                                'file_type' => $file_type,
                                'file_size' => $_FILES['receipt_photos']['size'][$key],
                                'uploaded_by' => get_current_user_id()
                            ),
                            array('%d', '%s', '%s', '%s', '%d', '%d')
                        );

                        // Store uploaded file info (match format from database query)
                        $uploaded_files[] = array(
                            'id' => $wpdb->insert_id,
                            'file_name' => $filename,
                            'file_path' => $uploaded_file['url'],
                            'file_type' => $file_type
                        );
                    } else {
                        error_log('HCR: File upload failed: ' . print_r($uploaded_file, true));
                    }
                }
            }
        }

        // Get all attachments for this cash up (including newly uploaded ones)
        $all_attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hcr_cash_count_attachments WHERE cash_up_id = %d ORDER BY uploaded_at DESC",
            $cash_up_id
        ), ARRAY_A);

        wp_send_json_success(array(
            'message' => $status === 'final' ? 'Cash up submitted successfully!' : 'Cash up saved as draft.',
            'cash_up_id' => $cash_up_id,
            'uploaded_files' => isset($uploaded_files) ? $uploaded_files : array(),
            'attachments' => $all_attachments
        ));
    }

    /**
     * Handle load existing cash up
     */
    public function handle_load_cash_up() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        global $wpdb;

        $session_date = sanitize_text_field($_POST['session_date']);

        // Get cash up
        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
        $cash_up = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $cash_ups_table WHERE session_date = %s",
            $session_date
        ));

        if (!$cash_up) {
            wp_send_json_error(array('message' => 'No cash up found for this date.'));
            return;
        }

        // Get denominations
        $denominations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hcr_denominations WHERE cash_up_id = %d ORDER BY denomination_value DESC",
            $cash_up->id
        ), ARRAY_A);

        // Get card machines
        $card_machines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hcr_card_machines WHERE cash_up_id = %d",
            $cash_up->id
        ), ARRAY_A);

        // Get reconciliation data if exists
        $reconciliation = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hcr_reconciliation WHERE cash_up_id = %d",
            $cash_up->id
        ), ARRAY_A);

        // Get photo URL if exists
        $photo_url = null;
        if ($cash_up->machine_photo_id) {
            $photo_url = wp_get_attachment_url($cash_up->machine_photo_id);
        }

        // Get attached receipt photos
        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hcr_cash_count_attachments WHERE cash_up_id = %d ORDER BY uploaded_at DESC",
            $cash_up->id
        ), ARRAY_A);

        wp_send_json_success(array(
            'cash_up' => $cash_up,
            'denominations' => $denominations,
            'card_machines' => $card_machines,
            'reconciliation' => $reconciliation,
            'photo_url' => $photo_url,
            'attachments' => $attachments
        ));
    }

    /**
     * Handle delete cash up
     */
    public function handle_delete_cash_up() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        global $wpdb;

        $cash_up_id = intval($_POST['cash_up_id']);

        // Check if cash up exists and is not final
        $cash_up = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hcr_cash_ups WHERE id = %d",
            $cash_up_id
        ));

        if (!$cash_up) {
            wp_send_json_error(array('message' => 'Cash up not found.'));
            return;
        }

        if ($cash_up->status === 'final') {
            wp_send_json_error(array('message' => 'Cannot delete a final cash up.'));
            return;
        }

        // Delete cash up (cascades to related tables)
        $result = $wpdb->delete(
            $wpdb->prefix . 'hcr_cash_ups',
            array('id' => $cash_up_id),
            array('%d')
        );

        if ($result === false) {
            error_log('HCR: Failed to delete cash up: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Failed to delete cash up.'));
            return;
        }

        wp_send_json_success(array('message' => 'Cash up deleted successfully.'));
    }

    /**
     * Handle bulk finalize cash ups
     */
    public function handle_bulk_finalize_cash_ups() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        global $wpdb;

        $cash_up_ids = isset($_POST['cash_up_ids']) ? array_map('intval', $_POST['cash_up_ids']) : array();

        if (empty($cash_up_ids)) {
            wp_send_json_error(array('message' => 'No cash ups selected.'));
            return;
        }

        $success_count = 0;
        $error_count = 0;
        $errors = array();

        foreach ($cash_up_ids as $cash_up_id) {
            // Check if cash up exists and is draft
            $cash_up = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hcr_cash_ups WHERE id = %d",
                $cash_up_id
            ));

            if (!$cash_up) {
                $error_count++;
                $errors[] = "Cash up ID {$cash_up_id} not found.";
                continue;
            }

            if ($cash_up->status !== 'draft') {
                $error_count++;
                $errors[] = "Cash up for {$cash_up->session_date} is not a draft.";
                continue;
            }

            // Update to final status
            $result = $wpdb->update(
                $wpdb->prefix . 'hcr_cash_ups',
                array(
                    'status' => 'final',
                    'submitted_at' => current_time('mysql')
                ),
                array('id' => $cash_up_id),
                array('%s', '%s'),
                array('%d')
            );

            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Failed to finalize cash up for {$cash_up->session_date}.";
            }
        }

        // Build response message
        $message = '';
        if ($success_count > 0) {
            $message .= $success_count . ' cash up' . ($success_count !== 1 ? 's' : '') . ' saved as final. ';
        }
        if ($error_count > 0) {
            $message .= $error_count . ' failed. ';
        }

        if ($error_count > 0 && $success_count === 0) {
            wp_send_json_error(array('message' => trim($message), 'errors' => $errors));
        } else {
            wp_send_json_success(array('message' => trim($message), 'success_count' => $success_count, 'error_count' => $error_count));
        }
    }

    /**
     * Handle fetch Newbook payments
     */
    public function handle_fetch_newbook_payments() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $session_date = sanitize_text_field($_POST['session_date']);

        // Use Newbook API to fetch payments with raw transaction data
        $api = new HCR_Newbook_API();
        $payment_data = $api->fetch_payments_by_date($session_date, true);

        if ($payment_data === false) {
            wp_send_json_error(array('message' => 'Failed to fetch payments from Newbook. Check API settings.'));
            return;
        }

        $payments = $payment_data['payments'];
        $raw_data = $payment_data['raw_data'];

        // Store payments in database
        global $wpdb;
        $payments_table = $wpdb->prefix . 'hcr_payment_records';

        // Delete existing payments for this date
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $payments_table WHERE DATE(payment_date) = %s",
            $session_date
        ));

        // Insert new payments
        foreach ($payments as $payment) {
            $wpdb->insert(
                $payments_table,
                array(
                    'newbook_payment_id' => $payment['payment_id'],
                    'booking_id' => $payment['booking_id'],
                    'guest_name' => $payment['guest_name'],
                    'payment_date' => $payment['payment_date'],
                    'payment_type' => $payment['payment_type'],
                    'payment_method' => $payment['payment_method'],
                    'transaction_method' => $payment['transaction_method'],
                    'card_type' => $payment['card_type'],
                    'amount' => $payment['amount'],
                    'tendered' => $payment['tendered'],
                    'processed_by' => $payment['processed_by']
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s')
            );
        }

        // Calculate totals for reconciliation
        $totals = $api->calculate_payment_totals($payments);

        // Parse till system transactions
        $till_payments = $api->parse_till_system_transactions($raw_data);

        // Parse transaction breakdown for display
        $transaction_breakdown = $api->parse_transaction_breakdown($raw_data);

        wp_send_json_success(array(
            'message' => 'Payments fetched successfully.',
            'count' => count($payments),
            'totals' => $totals,
            'till_payments' => $till_payments,
            'transaction_breakdown' => $transaction_breakdown
        ));
    }

    /**
     * Handle sync daily stats from Newbook
     */
    public function handle_sync_daily_stats() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $business_date = sanitize_text_field($_POST['business_date']);

        $api = new HCR_Newbook_API();

        // Fetch various stats from Newbook
        $gross_sales = $api->fetch_gross_sales($business_date);
        $occupancy = $api->fetch_occupancy_stats($business_date);
        $debtors_creditors = $api->fetch_debtors_creditors_balance($business_date);
        $sales_breakdown = $api->fetch_sales_breakdown($business_date);

        global $wpdb;

        // Update or insert daily stats
        $stats_table = $wpdb->prefix . 'hcr_daily_stats';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stats_table WHERE business_date = %s",
            $business_date
        ));

        $stats_data = array(
            'business_date' => $business_date,
            'gross_sales' => $gross_sales,
            'debtors_creditors_balance' => $debtors_creditors,
            'rooms_sold' => $occupancy['rooms_sold'],
            'total_people' => $occupancy['total_people'],
            'source' => 'newbook_auto'
        );

        if ($existing) {
            $wpdb->update($stats_table, $stats_data, array('id' => $existing->id));
        } else {
            $wpdb->insert($stats_table, $stats_data);
        }

        // Update sales breakdown
        $breakdown_table = $wpdb->prefix . 'hcr_sales_breakdown';
        $wpdb->delete($breakdown_table, array('business_date' => $business_date));

        foreach ($sales_breakdown as $category => $amount) {
            $wpdb->insert(
                $breakdown_table,
                array(
                    'business_date' => $business_date,
                    'category' => $category,
                    'net_amount' => $amount
                ),
                array('%s', '%s', '%f')
            );
        }

        wp_send_json_success(array('message' => 'Daily stats synced successfully.'));
    }

    /**
     * Handle generate multi-day report
     */
    public function handle_generate_multi_day_report() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $start_date = sanitize_text_field($_POST['start_date']);
        $num_days = intval($_POST['num_days']);

        if ($num_days < 1 || $num_days > 365) {
            wp_send_json_error(array('message' => 'Number of days must be between 1 and 365.'));
            return;
        }

        global $wpdb;

        $report_data = array();

        // Generate array of dates
        $dates = array();
        for ($i = 0; $i < $num_days; $i++) {
            $dates[] = date('Y-m-d', strtotime($start_date . ' + ' . $i . ' days'));
        }

        // Calculate end date
        $end_date = $dates[count($dates) - 1];

        // Initialize Newbook API for fetching fresh data
        $api = new HCR_Newbook_API();

        // Fetch ALL payment data for entire date range in ONE API call
        $payments_by_date = $api->fetch_payments_by_date_range($start_date, $end_date);

        if ($payments_by_date === false) {
            error_log('HCR: Failed to fetch payment data from Newbook');
            $payments_by_date = array();
        }

        // Fetch audit data for all dates
        $audit_by_date = array();
        foreach ($dates as $date) {
            $audit_data = $api->fetch_daily_audit_summary($date);
            if ($audit_data !== false) {
                $audit_by_date[$date] = $audit_data;
            } else {
                $audit_by_date[$date] = array();
            }
        }

        // Fetch GL account list for revenue grouping
        $gl_accounts = $api->fetch_gl_account_list();
        if ($gl_accounts === false) {
            error_log('HCR: Failed to fetch GL account list');
            $gl_accounts = array();
        }

        // Fetch earned revenue for entire date range
        $earned_revenue = $api->fetch_earned_revenue($start_date, $end_date, 'day');
        if ($earned_revenue === false) {
            error_log('HCR: Failed to fetch earned revenue data');
            $earned_revenue = array();
        }

        // Create mapping of GL account code to GL group ID
        $account_to_group_map = array();
        if (!empty($gl_accounts)) {
            foreach ($gl_accounts as $account) {
                if (isset($account['gl_account_code']) && isset($account['gl_group_id'])) {
                    $account_to_group_map[$account['gl_account_code']] = $account['gl_group_id'];
                }
            }
        }

        // Aggregate earned revenue by GL group and period
        $aggregated_revenue = array();
        if (!empty($earned_revenue)) {
            foreach ($earned_revenue as $item) {
                $period = $item['period'] ?? '';
                $account_code = $item['gl_account_code'] ?? '';

                // Find the GL group ID for this account
                $group_id = isset($account_to_group_map[$account_code]) ? $account_to_group_map[$account_code] : null;

                if (!empty($period) && !empty($group_id)) {
                    $key = $period . '_' . $group_id;

                    if (!isset($aggregated_revenue[$key])) {
                        $aggregated_revenue[$key] = array(
                            'period' => $period,
                            'gl_group_id' => $group_id,
                            'earned_revenue_ex' => 0,
                            'earned_revenue_tax' => 0,
                            'earned_revenue' => 0
                        );
                    }

                    // Aggregate the amounts
                    $aggregated_revenue[$key]['earned_revenue_ex'] += floatval($item['earned_revenue_ex'] ?? 0);
                    $aggregated_revenue[$key]['earned_revenue_tax'] += floatval($item['earned_revenue_tax'] ?? 0);
                    $aggregated_revenue[$key]['earned_revenue'] += floatval($item['earned_revenue'] ?? 0);
                }
            }
        }

        // Replace earned_revenue with aggregated data
        $earned_revenue = array_values($aggregated_revenue);

        // Fetch occupancy data for entire date range
        $occupancy_data = $api->fetch_occupancy($start_date, $end_date);
        if ($occupancy_data === false) {
            error_log('HCR: Failed to fetch occupancy data');
            $occupancy_data = array();
        }

        // Fetch bookings list for entire date range
        $bookings_data = $api->fetch_bookings_list($start_date, $end_date);
        if ($bookings_data === false) {
            error_log('HCR: Failed to fetch bookings data');
            $bookings_data = array();
        }

        // Fetch sites list
        $sites_data = $api->fetch_sites_list();
        if ($sites_data === false) {
            error_log('HCR: Failed to fetch sites data');
            $sites_data = array();
        }

        // Skip debtors/creditors balance fetching - will be loaded lazily via separate AJAX call
        // This significantly speeds up initial report load
        $period_open_balance = array(
            'creditors' => 0.00,
            'debtors' => 0.00,
            'overall' => 0.00,
            'accounts' => array(),
            'loading' => true  // Flag to indicate data not yet loaded
        );

        $balances_by_date = array();
        foreach ($dates as $date) {
            $balances_by_date[$date] = array(
                'creditors' => 0.00,
                'debtors' => 0.00,
                'overall' => 0.00,
                'accounts' => array(),
                'loading' => true  // Flag to indicate data not yet loaded
            );
        }

        // Load sales breakdown column settings
        $column_settings = get_option('hcr_sales_breakdown_columns', array());
        if (empty($column_settings)) {
            // Set defaults if not configured
            $column_settings = array(
                array('gl_code' => 'ACCOMMODATION', 'display_name' => 'Accommodation', 'enabled' => true, 'sort_order' => 1),
                array('gl_code' => 'FOOD', 'display_name' => 'Food', 'enabled' => true, 'sort_order' => 2),
                array('gl_code' => 'BEVERAGE', 'display_name' => 'Beverage', 'enabled' => true, 'sort_order' => 3),
                array('gl_code' => 'OTHER', 'display_name' => 'Other', 'enabled' => true, 'sort_order' => 4)
            );
        }

        // Sort by sort_order
        usort($column_settings, function($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });

        // Get enabled and disabled columns
        $enabled_columns = array_filter($column_settings, function($col) { return $col['enabled']; });
        $disabled_columns = array_filter($column_settings, function($col) { return !$col['enabled']; });

        // Process each date
        foreach ($dates as $date) {
            // Get payments for this specific date
            $fresh_payments = isset($payments_by_date[$date]) ? $payments_by_date[$date] : array();

            // Get audit data for this date
            $audit_data = isset($audit_by_date[$date]) ? $audit_by_date[$date] : array();

            // Calculate totals from fresh API data, separating gateway from manual (PDQ)
            $payment_totals = array(
                'cash' => 0,
                'gateway_visa_mc' => 0,
                'gateway_amex' => 0,
                'manual_visa_mc' => 0,
                'manual_amex' => 0,
                'bacs' => 0
            );

            if (!empty($fresh_payments)) {
                $other_count = 0;
                $other_amount = 0;

                foreach ($fresh_payments as $payment) {
                    $amount = floatval($payment['amount']);
                    $transaction_method = $payment['transaction_method'];
                    $card_type = $payment['card_type'];

                    // Skip payments with unidentified card type
                    if ($card_type === 'other') {
                        $other_count++;
                        $other_amount += $amount;
                        continue;
                    }

                    // Cash from ANY method/source
                    if ($card_type === 'cash') {
                        $payment_totals['cash'] += $amount;
                    }
                    // BACS can be used on both sides
                    elseif ($card_type === 'bacs') {
                        $payment_totals['bacs'] += $amount;
                    }
                    // Gateway payments (automated/cc_gateway)
                    elseif ($transaction_method === 'automated' || $transaction_method === 'gateway' || $transaction_method === 'cc_gateway') {
                        if ($card_type === 'amex') {
                            $payment_totals['gateway_amex'] += $amount;
                        } elseif ($card_type === 'visa_mc') {
                            $payment_totals['gateway_visa_mc'] += $amount;
                        }
                    }
                    // Manual payments (PDQ)
                    elseif ($transaction_method === 'manual') {
                        if ($card_type === 'amex') {
                            $payment_totals['manual_amex'] += $amount;
                        } elseif ($card_type === 'visa_mc') {
                            $payment_totals['manual_visa_mc'] += $amount;
                        }
                    }
                }

                // Log summary for this date
            }

            // Get cash up record
            $cash_up = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hcr_cash_ups WHERE session_date = %s",
                $date
            ));

            // Build reconciliation data
            $reconciliation = array();

            // Get banked amounts from cash up (if exists)
            $banked_cash = 0;
            $banked_pdq_visa_mc = 0;
            $banked_pdq_amex = 0;

            if ($cash_up) {
                $banked_cash = floatval($cash_up->total_cash_counted);

                // Get card machine totals for this cash up
                $card_machines = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hcr_card_machines WHERE cash_up_id = %d",
                    $cash_up->id
                ), ARRAY_A);

                // Calculate PDQ totals from card machines
                foreach ($card_machines as $machine) {
                    $banked_pdq_visa_mc += floatval($machine['visa_mc_amount']);
                    $banked_pdq_amex += floatval($machine['amex_amount']);
                }
            }

            // Build reconciliation array with proper separation
            // Cash
            $reconciliation[] = array(
                'category' => 'cash',
                'banked_amount' => $banked_cash,
                'reported_amount' => floatval($payment_totals['cash'])
            );

            // Gateway Visa/MC (can be used for both banked and reported)
            $reconciliation[] = array(
                'category' => 'gateway_visa_mc',
                'banked_amount' => floatval($payment_totals['gateway_visa_mc']),
                'reported_amount' => floatval($payment_totals['gateway_visa_mc'])
            );

            // Gateway Amex (can be used for both banked and reported)
            $reconciliation[] = array(
                'category' => 'gateway_amex',
                'banked_amount' => floatval($payment_totals['gateway_amex']),
                'reported_amount' => floatval($payment_totals['gateway_amex'])
            );

            // PDQ Visa/MC (manual method on reported side)
            $reconciliation[] = array(
                'category' => 'pdq_visa_mc',
                'banked_amount' => $banked_pdq_visa_mc,
                'reported_amount' => floatval($payment_totals['manual_visa_mc'])
            );

            // PDQ Amex (manual method on reported side)
            $reconciliation[] = array(
                'category' => 'pdq_amex',
                'banked_amount' => $banked_pdq_amex,
                'reported_amount' => floatval($payment_totals['manual_amex'])
            );

            // BACS (can be used on both sides)
            $reconciliation[] = array(
                'category' => 'bacs',
                'banked_amount' => floatval($payment_totals['bacs']),
                'reported_amount' => floatval($payment_totals['bacs'])
            );

            // Calculate daily stats from fresh Newbook data
            $daily_stats = null;
            if (!empty($fresh_payments)) {
                $total_sales = 0;
                foreach ($fresh_payments as $payment) {
                    $total_sales += floatval($payment['amount']);
                }

                if ($total_sales > 0) {
                    $daily_stats = (object) array(
                        'business_date' => $date,
                        'gross_sales' => $total_sales,
                        'debtors_creditors_balance' => 0.00, // Placeholder - would need separate Newbook endpoint
                        'transaction_count' => count($fresh_payments)
                    );
                }
            }

            // Sales breakdown from earned revenue data
            $sales_breakdown = array();
            $raw_total_net = 0.00;
            $raw_total_vat = 0.00;
            $raw_total_gross = 0.00;
            $displayed_total_net = 0.00;
            $displayed_total_vat = 0.00;
            $displayed_total_gross = 0.00;

            // Build sales breakdown based on enabled columns
            foreach ($enabled_columns as $column) {
                $gl_code = $column['gl_code'];
                $display_name = $column['display_name'];
                $is_placeholder = isset($column['is_placeholder']) && $column['is_placeholder'];

                // Find matching data in earned_revenue (skip for placeholders)
                $net = 0.00;
                $vat = 0.00;
                $gross = 0.00;

                if (!$is_placeholder && !empty($earned_revenue)) {
                    foreach ($earned_revenue as $revenue_item) {
                        // Check if period (date field) matches
                        $revenue_period = $revenue_item['period'] ?? '';
                        $revenue_gl_group_id = $revenue_item['gl_group_id'] ?? '';

                        if ($revenue_period === $date && strtoupper($revenue_gl_group_id) === strtoupper($gl_code)) {
                            // Use aggregated earned revenue amounts
                            $net = floatval($revenue_item['earned_revenue_ex'] ?? 0);
                            $vat = floatval($revenue_item['earned_revenue_tax'] ?? 0);
                            $gross = floatval($revenue_item['earned_revenue'] ?? 0);
                            break;
                        }
                    }
                }

                $sales_breakdown[] = array(
                    'gl_code' => $gl_code,
                    'category' => $display_name,
                    'net_amount' => $net,
                    'vat_amount' => $vat,
                    'gross_amount' => $gross
                );

                $displayed_total_net += $net;
                $displayed_total_vat += $vat;
                $displayed_total_gross += $gross;
            }

            // Calculate raw totals from ALL earned revenue for this date (for audit)
            if (!empty($earned_revenue)) {
                foreach ($earned_revenue as $revenue_item) {
                    $revenue_period = $revenue_item['period'] ?? '';
                    if ($revenue_period === $date) {
                        $raw_total_net += floatval($revenue_item['earned_revenue_ex'] ?? 0);
                        $raw_total_vat += floatval($revenue_item['earned_revenue_tax'] ?? 0);
                        $raw_total_gross += floatval($revenue_item['earned_revenue'] ?? 0);
                    }
                }
            }

            // Check for audit mismatch
            $audit_mismatch = abs($raw_total_gross - $displayed_total_gross) > 0.01;

            $report_data[] = array(
                'date' => $date,
                'cash_up' => $cash_up,
                'reconciliation' => $reconciliation,
                'daily_stats' => $daily_stats,
                'sales_breakdown' => $sales_breakdown,
                'audit_data' => $audit_data,
                'sales_audit' => array(
                    'displayed_net' => $displayed_total_net,
                    'displayed_vat' => $displayed_total_vat,
                    'displayed_gross' => $displayed_total_gross,
                    'raw_net' => $raw_total_net,
                    'raw_vat' => $raw_total_vat,
                    'raw_gross' => $raw_total_gross,
                    'mismatch' => $audit_mismatch
                )
            );
        }

        wp_send_json_success(array(
            'report_data' => $report_data,
            'gl_accounts' => $gl_accounts,
            'earned_revenue' => $earned_revenue,
            'occupancy_data' => $occupancy_data,
            'bookings_data' => $bookings_data,
            'sites_data' => $sites_data,
            'balances_by_date' => $balances_by_date,
            'period_open_balance' => $period_open_balance,
            'sales_columns' => array_values($enabled_columns),
            'disabled_columns' => array_values($disabled_columns)
        ));
    }

    /**
     * Handle export to Excel
     */
    public function handle_export_to_excel() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        // This would use a library like PHPSpreadsheet
        // For now, return CSV format
        wp_send_json_success(array('message' => 'Excel export functionality to be implemented.'));
    }

    /**
     * Handle fetch debtors/creditors balance data (lazy loaded)
     */
    public function handle_fetch_debtors_creditors_data() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $start_date = sanitize_text_field($_POST['start_date']);
        $num_days = intval($_POST['num_days']);

        if ($num_days < 1 || $num_days > 365) {
            wp_send_json_error(array('message' => 'Number of days must be between 1 and 365.'));
            return;
        }

        // Generate array of dates
        $dates = array();
        for ($i = 0; $i < $num_days; $i++) {
            $dates[] = date('Y-m-d', strtotime($start_date . ' + ' . $i . ' days'));
        }

        // Initialize Newbook API
        $api = new HCR_Newbook_API();

        // Fetch balance data for the day BEFORE the start date (for Period Open)
        $day_before_start = date('Y-m-d', strtotime($start_date . ' - 1 day'));
        $period_open_balance = $api->fetch_debtors_creditors_balance($day_before_start);
        if ($period_open_balance === false) {
            $period_open_balance = array(
                'creditors' => 0.00,
                'debtors' => 0.00,
                'overall' => 0.00,
                'accounts' => array()
            );
        }

        // Fetch balance data for each date
        $balances_by_date = array();
        foreach ($dates as $date) {
            $balance_data = $api->fetch_debtors_creditors_balance($date);
            if ($balance_data !== false) {
                $balances_by_date[$date] = $balance_data;
            } else {
                $balances_by_date[$date] = array(
                    'creditors' => 0.00,
                    'debtors' => 0.00,
                    'overall' => 0.00,
                    'accounts' => array()
                );
            }
        }

        wp_send_json_success(array(
            'balances_by_date' => $balances_by_date,
            'period_open_balance' => $period_open_balance
        ));
    }

    /**
     * Handle test Newbook API connection
     */
    public function handle_test_connection() {
        check_ajax_referer('hcr_admin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $api = new HCR_Newbook_API();
        $result = $api->test_connection();

        if ($result['success']) {
            wp_send_json_success(array('message' => 'Connection successful! ' . $result['message']));
        } else {
            wp_send_json_error(array('message' => 'Connection failed: ' . $result['message']));
        }
    }

    /**
     * Handle upload card machine photo
     */
    public function handle_upload_machine_photo() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        // Check if file was uploaded
        if (empty($_FILES['photo'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
            return;
        }

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        $file_type = $_FILES['photo']['type'];

        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error(array('message' => 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.'));
            return;
        }

        // Validate file size (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB in bytes
        if ($_FILES['photo']['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File too large. Maximum size is 10MB.'));
            return;
        }

        // Handle the upload using WordPress media handler
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('photo', 0);

        if (is_wp_error($attachment_id)) {
            error_log('HCR: Photo upload error: ' . $attachment_id->get_error_message());
            wp_send_json_error(array('message' => 'Upload failed: ' . $attachment_id->get_error_message()));
            return;
        }

        // Get the uploaded file URL
        $photo_url = wp_get_attachment_url($attachment_id);

        wp_send_json_success(array(
            'message' => 'Photo uploaded successfully.',
            'attachment_id' => $attachment_id,
            'photo_url' => $photo_url
        ));
    }

    /**
     * Handle delete card machine photo
     */
    public function handle_delete_machine_photo() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $attachment_id = intval($_POST['attachment_id']);

        if (empty($attachment_id)) {
            wp_send_json_error(array('message' => 'No attachment ID provided.'));
            return;
        }

        // Delete the attachment
        $result = wp_delete_attachment($attachment_id, true);

        if ($result === false) {
            error_log('HCR: Failed to delete photo attachment ID: ' . $attachment_id);
            wp_send_json_error(array('message' => 'Failed to delete photo.'));
            return;
        }

        wp_send_json_success(array('message' => 'Photo deleted successfully.'));
    }

    /**
     * Refresh GL accounts from Newbook and merge with settings
     */
    public function handle_refresh_gl_accounts() {
        // Security check
        check_ajax_referer('hcr_refresh_gl_accounts', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
            return;
        }

        // Get GL accounts from Newbook
        $api = new HCR_Newbook_API();
        $gl_accounts = $api->fetch_gl_accounts();

        if ($gl_accounts === false) {
            wp_send_json_error(array('message' => 'Failed to fetch GL accounts from Newbook.'));
            return;
        }

        // Get existing column settings
        $existing_columns = get_option('hcr_sales_breakdown_columns', array());

        // Build updated columns array
        $updated_columns = array();
        $new_count = 0;
        $removed_count = 0;
        $updated_count = 0;

        // First, keep existing columns that still exist in Newbook (preserve enabled status and sort order)
        foreach ($existing_columns as $column) {
            $gl_code = $column['gl_code'];

            if (isset($gl_accounts[$gl_code])) {
                // Column still exists in Newbook - keep it with updated display name
                $updated_columns[] = array(
                    'gl_code' => $gl_code,
                    'display_name' => $gl_accounts[$gl_code], // Update display name from Newbook
                    'enabled' => $column['enabled'],
                    'sort_order' => $column['sort_order']
                );
                $updated_count++;

                // Remove from gl_accounts so we know it's been processed
                unset($gl_accounts[$gl_code]);
            } else {
                // Column no longer exists in Newbook - remove it
                $removed_count++;
            }
        }

        // Add any remaining GL accounts from Newbook as new disabled columns
        $max_sort_order = !empty($updated_columns) ? max(array_column($updated_columns, 'sort_order')) : 0;

        foreach ($gl_accounts as $gl_group_id => $display_name) {
            $updated_columns[] = array(
                'gl_code' => $gl_group_id,
                'display_name' => $display_name,
                'enabled' => false,
                'sort_order' => ++$max_sort_order
            );
            $new_count++;
        }

        // Save updated columns
        update_option('hcr_sales_breakdown_columns', $updated_columns);

        // Build response message
        $messages = array();
        if ($new_count > 0) {
            $messages[] = "Added {$new_count} new GL account(s)";
        }
        if ($removed_count > 0) {
            $messages[] = "Removed {$removed_count} old GL account(s)";
        }
        if ($updated_count > 0) {
            $messages[] = "Updated {$updated_count} existing GL account(s)";
        }

        if (empty($messages)) {
            $message = 'No changes needed.';
        } else {
            $message = implode('. ', $messages) . '.';
        }

        wp_send_json_success(array('message' => $message));
    }

    /**
     * Handle generate cash summary
     */
    public function handle_generate_cash_summary() {
        check_ajax_referer('hcr_cash_summary_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);

        if (empty($date_from) || empty($date_to)) {
            wp_send_json_error('Date range is required.');
            return;
        }

        // Query denominations from cash ups in date range where count_type = 'takings'
        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
        $denominations_table = $wpdb->prefix . 'hcr_denominations';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT d.denomination_value, SUM(d.quantity) as total_quantity, SUM(d.total_amount) as total_value
             FROM $denominations_table d
             INNER JOIN $cash_ups_table c ON d.cash_up_id = c.id
             WHERE c.session_date >= %s AND c.session_date <= %s
             AND d.count_type = 'takings'
             GROUP BY d.denomination_value
             ORDER BY d.denomination_value DESC",
            $date_from,
            $date_to
        ), ARRAY_A);

        // Build denominations array keyed by denomination value
        $denominations = array();
        foreach ($results as $row) {
            $denom_key = number_format(floatval($row['denomination_value']), 2, '.', '');
            $denominations[$denom_key] = array(
                'quantity' => intval($row['total_quantity']),
                'value' => floatval($row['total_value'])
            );
        }

        wp_send_json_success(array(
            'denominations' => $denominations,
            'period' => array(
                'from' => date('d/m/Y', strtotime($date_from)),
                'to' => date('d/m/Y', strtotime($date_to))
            )
        ));
    }

    /**
     * Handle save petty cash count
     */
    public function handle_save_petty_cash_count() {
        check_ajax_referer('hcr_petty_cash_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $count_date = sanitize_text_field($_POST['count_date']);
        $denominations = isset($_POST['denominations']) ? $_POST['denominations'] : array();
        $receipts = isset($_POST['receipts']) ? $_POST['receipts'] : array();
        $total_counted = floatval($_POST['total_counted']);
        $total_receipts = floatval($_POST['total_receipts']);
        $target_amount = floatval($_POST['target_amount']);
        $variance = floatval($_POST['variance']);
        $notes = sanitize_textarea_field($_POST['notes']);

        // Insert float count record
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $result = $wpdb->insert(
            $float_counts_table,
            array(
                'count_type' => 'petty_cash',
                'count_date' => $count_date,
                'created_by' => get_current_user_id(),
                'total_counted' => $total_counted,
                'total_receipts' => $total_receipts,
                'target_amount' => $target_amount,
                'variance' => $variance,
                'notes' => $notes
            ),
            array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s')
        );

        if ($result === false) {
            error_log('HCR: Failed to insert petty cash count: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save count.');
            return;
        }

        $count_id = $wpdb->insert_id;

        // Insert denominations
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        foreach ($denominations as $denom) {
            $wpdb->insert(
                $float_denominations_table,
                array(
                    'float_count_id' => $count_id,
                    'denomination_value' => floatval($denom['denomination']),
                    'quantity' => intval($denom['quantity']),
                    'total_amount' => floatval($denom['total'])
                ),
                array('%d', '%f', '%d', '%f')
            );
        }

        // Insert receipts
        $float_receipts_table = $wpdb->prefix . 'hcr_float_receipts';
        foreach ($receipts as $receipt) {
            $wpdb->insert(
                $float_receipts_table,
                array(
                    'float_count_id' => $count_id,
                    'receipt_value' => floatval($receipt['amount']),
                    'receipt_description' => sanitize_text_field($receipt['description'])
                ),
                array('%d', '%f', '%s')
            );
        }

        wp_send_json_success(array('message' => 'Count saved successfully.', 'count_id' => $count_id));
    }

    /**
     * Handle load petty cash counts
     */
    public function handle_load_petty_cash_counts() {
        check_ajax_referer('hcr_petty_cash_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_counts_table
             WHERE count_type = 'petty_cash'
             ORDER BY count_date DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);

        // Format dates
        foreach ($counts as &$count) {
            $count['count_date'] = date('d/m/Y H:i:s', strtotime($count['count_date']));
        }

        // Check if there are more records
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $float_counts_table WHERE count_type = 'petty_cash'");
        $has_more = ($offset + count($counts)) < $total_count;

        wp_send_json_success(array('counts' => $counts, 'has_more' => $has_more, 'total_count' => $total_count));
    }

    /**
     * Handle get petty cash count details
     */
    public function handle_get_petty_cash_count() {
        check_ajax_referer('hcr_petty_cash_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $count_id = intval($_POST['count_id']);

        // Get count record
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $count = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $float_counts_table WHERE id = %d",
            $count_id
        ), ARRAY_A);

        if (!$count) {
            wp_send_json_error('Count not found.');
            return;
        }

        // Get denominations
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        $denominations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_denominations_table WHERE float_count_id = %d ORDER BY denomination_value DESC",
            $count_id
        ), ARRAY_A);

        // Get receipts
        $float_receipts_table = $wpdb->prefix . 'hcr_float_receipts';
        $receipts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_receipts_table WHERE float_count_id = %d",
            $count_id
        ), ARRAY_A);

        // Get created by user name
        $user = get_userdata($count['created_by']);
        $count['created_by_name'] = $user ? $user->display_name : 'Unknown';

        // Format date
        $count['count_date'] = date('d/m/Y H:i:s', strtotime($count['count_date']));

        // Add denominations and receipts to count
        $count['denominations'] = $denominations;
        $count['receipts'] = $receipts;

        wp_send_json_success($count);
    }

    /**
     * Handle save change tin count
     */
    public function handle_save_change_tin_count() {
        check_ajax_referer('hcr_change_tin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $count_date = sanitize_text_field($_POST['count_date']);
        $denominations = isset($_POST['denominations']) ? $_POST['denominations'] : array();
        $total_counted = floatval($_POST['total_counted']);
        $target_amount = floatval($_POST['target_amount']);
        $variance = floatval($_POST['variance']);
        $notes = sanitize_textarea_field($_POST['notes']);

        // Insert float count record
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $result = $wpdb->insert(
            $float_counts_table,
            array(
                'count_type' => 'change_tin',
                'count_date' => $count_date,
                'created_by' => get_current_user_id(),
                'total_counted' => $total_counted,
                'total_receipts' => 0.00, // No receipts for change tin
                'target_amount' => $target_amount,
                'variance' => $variance,
                'notes' => $notes
            ),
            array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s')
        );

        if ($result === false) {
            error_log('HCR: Failed to insert change tin count: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save count.');
            return;
        }

        $count_id = $wpdb->insert_id;

        // Insert denominations
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        foreach ($denominations as $denom) {
            $wpdb->insert(
                $float_denominations_table,
                array(
                    'float_count_id' => $count_id,
                    'denomination_value' => floatval($denom['denomination']),
                    'quantity' => intval($denom['quantity']),
                    'total_amount' => floatval($denom['total'])
                ),
                array('%d', '%f', '%d', '%f')
            );
        }

        wp_send_json_success(array('message' => 'Count saved successfully.', 'count_id' => $count_id));
    }

    /**
     * Handle load change tin counts
     */
    public function handle_load_change_tin_counts() {
        check_ajax_referer('hcr_change_tin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_counts_table
             WHERE count_type = 'change_tin'
             ORDER BY count_date DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);

        // Format dates
        foreach ($counts as &$count) {
            $count['count_date'] = date('d/m/Y H:i:s', strtotime($count['count_date']));
        }

        // Check if there are more records
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $float_counts_table WHERE count_type = 'change_tin'");
        $has_more = ($offset + count($counts)) < $total_count;

        wp_send_json_success(array('counts' => $counts, 'has_more' => $has_more, 'total_count' => $total_count));
    }

    /**
     * Handle get change tin count details
     */
    public function handle_get_change_tin_count() {
        check_ajax_referer('hcr_change_tin_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $count_id = intval($_POST['count_id']);

        // Get count record
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $count = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $float_counts_table WHERE id = %d",
            $count_id
        ), ARRAY_A);

        if (!$count) {
            wp_send_json_error('Count not found.');
            return;
        }

        // Get denominations
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        $denominations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_denominations_table WHERE float_count_id = %d ORDER BY denomination_value DESC",
            $count_id
        ), ARRAY_A);

        // Get change tin breakdown from settings to include target amounts
        $breakdown = get_option('hcr_change_tin_breakdown', '');
        if (is_string($breakdown) && !empty($breakdown)) {
            $breakdown = json_decode($breakdown, true);
        }
        if (empty($breakdown) || !is_array($breakdown)) {
            $breakdown = array(
                '50.00' => 0,
                '20.00' => 0,
                '10.00' => 0,
                '5.00' => 0,
                '2.00' => 20.00,
                '1.00' => 20.00,
                '0.50' => 10.00,
                '0.20' => 10.00,
                '0.10' => 5.00,
                '0.05' => 5.00
            );
        }

        // Add target amount to each denomination
        foreach ($denominations as &$denom) {
            $denom_key = number_format(floatval($denom['denomination_value']), 2, '.', '');
            $denom['target'] = isset($breakdown[$denom_key]) ? floatval($breakdown[$denom_key]) : 0.00;
        }

        // Get created by user name
        $user = get_userdata($count['created_by']);
        $count['created_by_name'] = $user ? $user->display_name : 'Unknown';

        // Format date
        $count['count_date'] = date('d/m/Y H:i:s', strtotime($count['count_date']));

        // Add denominations to count
        $count['denominations'] = $denominations;

        wp_send_json_success($count);
    }

    /**
     * Handle save safe cash count
     */
    public function handle_save_safe_cash_count() {
        check_ajax_referer('hcr_safe_cash_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $count_date = sanitize_text_field($_POST['count_date']);
        $denominations = isset($_POST['denominations']) ? $_POST['denominations'] : array();
        $total_counted = floatval($_POST['total_counted']);
        $notes = sanitize_textarea_field($_POST['notes']);

        // Insert float count record
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $result = $wpdb->insert(
            $float_counts_table,
            array(
                'count_type' => 'safe_cash',
                'count_date' => $count_date,
                'created_by' => get_current_user_id(),
                'total_counted' => $total_counted,
                'total_receipts' => 0.00, // No receipts for safe cash
                'target_amount' => 0.00, // No target for safe cash
                'variance' => 0.00, // No variance for safe cash
                'notes' => $notes
            ),
            array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s')
        );

        if ($result === false) {
            error_log('HCR: Failed to insert safe cash count: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save count.');
            return;
        }

        $count_id = $wpdb->insert_id;

        // Insert denominations
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        foreach ($denominations as $denom) {
            $wpdb->insert(
                $float_denominations_table,
                array(
                    'float_count_id' => $count_id,
                    'denomination_value' => floatval($denom['denomination']),
                    'quantity' => intval($denom['quantity']),
                    'total_amount' => floatval($denom['total'])
                ),
                array('%d', '%f', '%d', '%f')
            );
        }

        wp_send_json_success(array('message' => 'Count saved successfully.', 'count_id' => $count_id));
    }

    /**
     * Handle load safe cash counts
     */
    public function handle_load_safe_cash_counts() {
        check_ajax_referer('hcr_safe_cash_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_counts_table
             WHERE count_type = 'safe_cash'
             ORDER BY count_date DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);

        // Format dates
        foreach ($counts as &$count) {
            $count['count_date'] = date('d/m/Y H:i:s', strtotime($count['count_date']));
        }

        // Check if there are more records
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $float_counts_table WHERE count_type = 'safe_cash'");
        $has_more = ($offset + count($counts)) < $total_count;

        wp_send_json_success(array('counts' => $counts, 'has_more' => $has_more, 'total_count' => $total_count));
    }

    /**
     * Handle get safe cash count details
     */
    public function handle_get_safe_cash_count() {
        check_ajax_referer('hcr_safe_cash_nonce', 'nonce');

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied.');
            return;
        }

        global $wpdb;

        $count_id = intval($_POST['count_id']);

        // Get count record
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $count = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $float_counts_table WHERE id = %d",
            $count_id
        ), ARRAY_A);

        if (!$count) {
            wp_send_json_error('Count not found.');
            return;
        }

        // Get denominations
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        $denominations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $float_denominations_table WHERE float_count_id = %d ORDER BY denomination_value DESC",
            $count_id
        ), ARRAY_A);

        // Get created by user name
        $user = get_userdata($count['created_by']);
        $count['created_by_name'] = $user ? $user->display_name : 'Unknown';

        // Format date
        $count['count_date'] = date('d/m/Y H:i:s', strtotime($count['count_date']));

        // Add denominations to count
        $count['denominations'] = $denominations;

        wp_send_json_success($count);
    }

    /**
     * Handle delete attachment
     */
    public function handle_delete_attachment() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        // Check user permissions
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        global $wpdb;
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error(array('message' => 'Invalid attachment ID.'));
            return;
        }

        $attachments_table = $wpdb->prefix . 'hcr_cash_count_attachments';

        // Get attachment details before deleting
        $attachment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $attachments_table WHERE id = %d",
            $attachment_id
        ), ARRAY_A);

        if (!$attachment) {
            wp_send_json_error(array('message' => 'Attachment not found.'));
            return;
        }

        // Delete the file from the filesystem
        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $attachment['file_path']);
        if (file_exists($file_path)) {
            wp_delete_file($file_path);
        }

        // Delete from database
        $result = $wpdb->delete(
            $attachments_table,
            array('id' => $attachment_id),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to delete attachment.'));
            return;
        }

        wp_send_json_success(array('message' => 'Photo deleted successfully.'));
    }

    /**
     * Handle purge receipt photos
     */
    public function handle_purge_receipt_photos() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        // Check user permissions (only administrators can purge)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can purge photos.'));
            return;
        }

        global $wpdb;
        $purge_date = isset($_POST['purge_date']) ? sanitize_text_field($_POST['purge_date']) : '';

        if (!$purge_date) {
            wp_send_json_error(array('message' => 'Invalid date provided.'));
            return;
        }

        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $purge_date);
        if (!$date_obj) {
            wp_send_json_error(array('message' => 'Invalid date format.'));
            return;
        }

        // Get all attachments from cash ups before the purge date
        $attachments_table = $wpdb->prefix . 'hcr_cash_count_attachments';
        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';

        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM $attachments_table a
            INNER JOIN $cash_ups_table c ON a.cash_up_id = c.id
            WHERE c.session_date < %s
            ORDER BY a.id ASC",
            $purge_date
        ), ARRAY_A);

        if (empty($attachments)) {
            wp_send_json_success(array(
                'message' => 'No receipt photos found before ' . date('d/m/Y', strtotime($purge_date)) . '.',
                'purge_date' => $purge_date,
                'deleted_count' => 0
            ));
            return;
        }

        $deleted_count = 0;
        $failed_count = 0;
        $upload_dir = wp_upload_dir();

        foreach ($attachments as $attachment) {
            // Delete the physical file
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $attachment['file_path']);

            $file_deleted = false;
            if (file_exists($file_path)) {
                $file_deleted = wp_delete_file($file_path);
            } else {
                // File doesn't exist, still delete from database
                $file_deleted = true;
            }

            // Delete from database
            if ($file_deleted) {
                $result = $wpdb->delete(
                    $attachments_table,
                    array('id' => $attachment['id']),
                    array('%d')
                );

                if ($result !== false) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                    error_log('HCR: Failed to delete attachment record from database: ID ' . $attachment['id']);
                }
            } else {
                $failed_count++;
                error_log('HCR: Failed to delete file: ' . $file_path);
            }
        }

        // Update last purge date option
        update_option('hcr_last_photo_purge_date', $purge_date);

        // Log the purge action
        error_log('HCR: Photo purge completed. Deleted: ' . $deleted_count . ', Failed: ' . $failed_count . ', Date: ' . $purge_date . ', User: ' . get_current_user_id());

        $message = 'Successfully deleted ' . $deleted_count . ' receipt photo(s) from submissions before ' . date('d/m/Y', strtotime($purge_date)) . '.';
        if ($failed_count > 0) {
            $message .= ' ' . $failed_count . ' photo(s) could not be deleted.';
        }

        wp_send_json_success(array(
            'message' => $message,
            'purge_date' => $purge_date,
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count
        ));
    }

    /**
     * Handle occupancy report (for public occupancy table shortcode)
     * Returns data in format specifically for occupancy statistics display
     */
    public function handle_occupancy_report() {
        // Verify nonce - accept both admin and public nonces
        $nonce_valid = false;
        if (isset($_POST['nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'hcr_admin_nonce') ||
                          wp_verify_nonce($_POST['nonce'], 'hcr_public_nonce');
        }

        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return;
        }

        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
            return;
        }

        $start_date = sanitize_text_field($_POST['start_date']);
        $num_days = intval($_POST['num_days']);

        if ($num_days < 1 || $num_days > 90) {
            wp_send_json_error(array('message' => 'Number of days must be between 1 and 90.'));
            return;
        }

        // Generate array of dates
        $dates = array();
        for ($i = 0; $i < $num_days; $i++) {
            $dates[] = date('Y-m-d', strtotime($start_date . ' + ' . $i . ' days'));
        }
        $end_date = $dates[count($dates) - 1];

        // Initialize Newbook API
        $api = new HCR_Newbook_API();

        // Fetch occupancy data
        $occupancy_data = $api->fetch_occupancy($start_date, $end_date);
        if ($occupancy_data === false) {
            $occupancy_data = array();
        }

        // Fetch bookings data for statistics
        $bookings_data = $api->fetch_bookings_list($start_date, $end_date);
        if ($bookings_data === false) {
            $bookings_data = array();
        }

        // Fetch earned revenue for accommodation
        $earned_revenue = $api->fetch_earned_revenue($start_date, $end_date, 'day');
        if ($earned_revenue === false) {
            $earned_revenue = array();
        }

        // Fetch GL accounts to identify accommodation revenue
        $gl_accounts = $api->fetch_gl_account_list();
        if ($gl_accounts === false) {
            $gl_accounts = array();
        }

        // Fetch sites list for actual room inventory (excluding overflow)
        $sites_data = $api->fetch_sites_list();
        if ($sites_data === false) {
            $sites_data = array();
        }

        // Create mapping of GL account code to GL group ID (like backend does)
        $account_to_group_map = array();
        if (!empty($gl_accounts)) {
            foreach ($gl_accounts as $account) {
                if (isset($account['gl_account_code']) && isset($account['gl_group_id'])) {
                    $account_to_group_map[$account['gl_account_code']] = $account['gl_group_id'];
                }
            }
        }

        // Aggregate earned revenue by GL group and period (like backend does)
        $aggregated_revenue = array();
        if (!empty($earned_revenue)) {
            foreach ($earned_revenue as $item) {
                $period = $item['period'] ?? '';
                $account_code = $item['gl_account_code'] ?? '';

                // Find the GL group ID for this account
                $group_id = isset($account_to_group_map[$account_code]) ? $account_to_group_map[$account_code] : null;

                if (!empty($period) && !empty($group_id)) {
                    $key = $period . '_' . $group_id;

                    if (!isset($aggregated_revenue[$key])) {
                        $aggregated_revenue[$key] = array(
                            'period' => $period,
                            'gl_group_id' => $group_id,
                            'earned_revenue_ex' => 0,
                            'earned_revenue_tax' => 0,
                            'earned_revenue' => 0
                        );
                    }

                    // Aggregate the amounts
                    $aggregated_revenue[$key]['earned_revenue_ex'] += floatval($item['earned_revenue_ex'] ?? 0);
                    $aggregated_revenue[$key]['earned_revenue_tax'] += floatval($item['earned_revenue_tax'] ?? 0);
                    $aggregated_revenue[$key]['earned_revenue'] += floatval($item['earned_revenue'] ?? 0);
                }
            }
        }

        // Convert aggregated revenue back to array for JSON response
        $earned_revenue = array_values($aggregated_revenue);

        // Initialize room category names from occupancy data
        $room_category_names = array();
        if (!empty($occupancy_data)) {
            foreach ($occupancy_data as $item) {
                $category_id = $item['category_id'] ?? 'unknown';
                $room_category_names[$category_id] = $item['category_name'] ?? 'Unknown';
            }
        }

        // Process bookings data to calculate occupancy and daily statistics
        $occupancy_by_date = array();
        $daily_stats = array();
        $max_rooms_by_category = array();  // Track maximum concurrent bookings per category
        $overflow_bookings_excluded = 0;
        $total_bookings_processed = 0;

        if (!empty($bookings_data)) {
            foreach ($bookings_data as $booking) {
                // Use correct field names from Newbook API
                $arrival_date = isset($booking['booking_arrival']) ? substr($booking['booking_arrival'], 0, 10) : '';
                $departure_date = isset($booking['booking_departure']) ? substr($booking['booking_departure'], 0, 10) : '';
                $category_name = $booking['category_name'] ?? '';

                if (empty($arrival_date) || empty($departure_date)) continue;

                // Exclude overflow rooms from occupancy calculations (like backend does)
                if (stripos($category_name, 'overflow') !== false) {
                    $overflow_bookings_excluded++;
                    continue;
                }

                $total_bookings_processed++;

                // Calculate staying dates for this booking
                $current_date = $arrival_date;
                while (strtotime($current_date) < strtotime($departure_date)) {
                    if (in_array($current_date, $dates)) {
                        // Initialize occupancy_by_date for this date
                        if (!isset($occupancy_by_date[$current_date])) {
                            $occupancy_by_date[$current_date] = array('total' => 0, 'by_category' => array());
                        }

                        // Count this room as occupied
                        $occupancy_by_date[$current_date]['total']++;

                        $category_id = $booking['category_id'] ?? 'unknown';
                        if (!isset($occupancy_by_date[$current_date]['by_category'][$category_id])) {
                            $occupancy_by_date[$current_date]['by_category'][$category_id] = 0;
                        }
                        $occupancy_by_date[$current_date]['by_category'][$category_id]++;

                        // Initialize daily_stats for this date
                        if (!isset($daily_stats[$current_date])) {
                            $daily_stats[$current_date] = array(
                                'totalPeople' => 0,
                                'totalAdults' => 0,
                                'totalChildren' => 0,
                                'totalInfants' => 0,
                                'byCategory' => array(),
                                'leadTimeArriving' => array(
                                    'totalDays' => 0,
                                    'count' => 0,
                                    'categories' => array(
                                        'Walk In' => 0,
                                        'Last Minute' => 0,
                                        'Week' => 0,
                                        'Fortnight' => 0,
                                        'Month' => 0,
                                        '3 Months' => 0,
                                        '6 Months' => 0,
                                        '1 Year' => 0,
                                        'Over 1 Year' => 0,
                                        'Unknown' => 0
                                    )
                                ),
                                'leadTimeStaying' => array(
                                    'totalDays' => 0,
                                    'count' => 0,
                                    'categories' => array(
                                        'Walk In' => 0,
                                        'Last Minute' => 0,
                                        'Week' => 0,
                                        'Fortnight' => 0,
                                        'Month' => 0,
                                        '3 Months' => 0,
                                        '6 Months' => 0,
                                        '1 Year' => 0,
                                        'Over 1 Year' => 0,
                                        'Unknown' => 0
                                    )
                                )
                            );
                        }

                        // Count people
                        $adults = intval($booking['booking_adults'] ?? 0);
                        $children = intval($booking['booking_children'] ?? 0);
                        $infants = intval($booking['booking_infants'] ?? 0);
                        $total_people = $adults + $children + $infants;

                        $daily_stats[$current_date]['totalPeople'] += $total_people;
                        $daily_stats[$current_date]['totalAdults'] += $adults;
                        $daily_stats[$current_date]['totalChildren'] += $children;
                        $daily_stats[$current_date]['totalInfants'] += $infants;

                        // Track rates by category
                        if (!isset($daily_stats[$current_date]['byCategory'][$category_id])) {
                            $category_name_full = $booking['category_name'] ?? 'Unknown';
                            $daily_stats[$current_date]['byCategory'][$category_id] = array(
                                'name' => $category_name_full,
                                'totalRate' => 0,
                                'roomCount' => 0,
                                'totalPeople' => 0,
                                'adults' => 0,
                                'children' => 0,
                                'infants' => 0
                            );
                        }

                        // Find rate for this specific date from tariffs_quoted (like backend does)
                        // Note: Backend uses forEach which adds ALL matching tariffs, so we do the same
                        $daily_rate = 0;
                        if (isset($booking['tariffs_quoted']) && is_array($booking['tariffs_quoted'])) {
                            foreach ($booking['tariffs_quoted'] as $tariff) {
                                if (isset($tariff['stay_date']) && substr($tariff['stay_date'], 0, 10) === $current_date) {
                                    $daily_rate += floatval($tariff['calculated_amount'] ?? 0);
                                    // NO break - add all matching tariffs like backend does
                                }
                            }
                        }

                        $daily_stats[$current_date]['byCategory'][$category_id]['totalRate'] += $daily_rate;
                        $daily_stats[$current_date]['byCategory'][$category_id]['roomCount']++;
                        $daily_stats[$current_date]['byCategory'][$category_id]['totalPeople'] += $total_people;
                        $daily_stats[$current_date]['byCategory'][$category_id]['adults'] += $adults;
                        $daily_stats[$current_date]['byCategory'][$category_id]['children'] += $children;
                        $daily_stats[$current_date]['byCategory'][$category_id]['infants'] += $infants;

                        // Helper function to categorize lead time
                        $categorize_lead_time = function($days) {
                            if ($days < 0) return 'Unknown';
                            if ($days <= 1) return 'Walk In';
                            if ($days <= 3) return 'Last Minute';
                            if ($days <= 7) return 'Week';
                            if ($days <= 14) return 'Fortnight';
                            if ($days <= 30) return 'Month';
                            if ($days <= 90) return '3 Months';
                            if ($days <= 180) return '6 Months';
                            if ($days <= 365) return '1 Year';
                            return 'Over 1 Year';
                        };

                        // Calculate lead time for arriving bookings (only on arrival date)
                        if ($current_date === $arrival_date) {
                            $placed_date = isset($booking['booking_placed']) ? substr($booking['booking_placed'], 0, 10) : '';
                            if (!empty($placed_date)) {
                                $lead_time_days = (strtotime($arrival_date) - strtotime($placed_date)) / (60 * 60 * 24);
                                if ($lead_time_days >= 0) {
                                    $daily_stats[$current_date]['leadTimeArriving']['totalDays'] += $lead_time_days;
                                    $daily_stats[$current_date]['leadTimeArriving']['count']++;

                                    $category = $categorize_lead_time($lead_time_days);
                                    $daily_stats[$current_date]['leadTimeArriving']['categories'][$category]++;
                                }
                            } else {
                                // No booking placed date
                                $daily_stats[$current_date]['leadTimeArriving']['categories']['Unknown']++;
                            }
                        }

                        // Calculate lead time for staying bookings (all days during stay)
                        $placed_date = isset($booking['booking_placed']) ? substr($booking['booking_placed'], 0, 10) : '';
                        if (!empty($placed_date)) {
                            $lead_time_days = (strtotime($arrival_date) - strtotime($placed_date)) / (60 * 60 * 24);
                            if ($lead_time_days >= 0) {
                                $daily_stats[$current_date]['leadTimeStaying']['totalDays'] += $lead_time_days;
                                $daily_stats[$current_date]['leadTimeStaying']['count']++;

                                $category = $categorize_lead_time($lead_time_days);
                                $daily_stats[$current_date]['leadTimeStaying']['categories'][$category]++;
                            }
                        } else {
                            // No booking placed date
                            $daily_stats[$current_date]['leadTimeStaying']['categories']['Unknown']++;
                        }

                        // Track maximum rooms per category (for capacity calculation)
                        if (!isset($max_rooms_by_category[$category_id])) {
                            $max_rooms_by_category[$category_id] = 0;
                        }
                        if (isset($occupancy_by_date[$current_date]['by_category'][$category_id])) {
                            $max_rooms_by_category[$category_id] = max(
                                $max_rooms_by_category[$category_id],
                                $occupancy_by_date[$current_date]['by_category'][$category_id]
                            );
                        }
                    }

                    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                }
            }
        }

        // Calculate room_category_counts from sites data (excluding overflow)
        // This matches the backend multi-day report logic
        $room_category_counts = array();
        if (!empty($sites_data)) {
            foreach ($sites_data as $site) {
                $category_id = $site['category_id'] ?? 'unknown';
                $category_name = $site['category_name'] ?? 'Unknown';

                // Exclude overflow rooms from occupancy calculations
                if (stripos($category_name, 'overflow') === false) {
                    if (!isset($room_category_counts[$category_id])) {
                        $room_category_counts[$category_id] = array(
                            'name' => $category_name,
                            'count' => 0
                        );
                    }
                    $room_category_counts[$category_id]['count']++;
                }
            }
        }

        // Log GL accounts and aggregated revenue for debugging
        if (!empty($gl_accounts) && count($gl_accounts) > 0) {
            error_log('HCR Occupancy: Total GL accounts: ' . count($gl_accounts));

            // Check for accommodation-related GL groups
            $acc_groups = array_filter($gl_accounts, function($acc) {
                $name = $acc['gl_group_name'] ?? '';
                return (strpos($name, 'ACC') === 0) || (stripos($name, 'accommodation') !== false);
            });
            error_log('HCR Occupancy: Found ' . count($acc_groups) . ' accommodation GL groups out of ' . count($gl_accounts) . ' total');

            if (count($acc_groups) > 0) {
                $acc_group_names = array_map(function($acc) {
                    return ($acc['gl_group_id'] ?? 'no-id') . ': ' . ($acc['gl_group_name'] ?? 'no-name');
                }, array_slice(array_values($acc_groups), 0, 3));
                error_log('HCR Occupancy: Sample ACC groups: ' . json_encode($acc_group_names));
            }
        }

        // Log aggregated earned revenue for debugging
        if (!empty($earned_revenue) && count($earned_revenue) > 0) {
            error_log('HCR Occupancy: Total aggregated revenue records: ' . count($earned_revenue));
            error_log('HCR Occupancy: First aggregated revenue: ' . json_encode($earned_revenue[0]));

            // Check if any revenue matches accommodation groups
            $acc_revenue_count = 0;
            foreach ($earned_revenue as $rev) {
                $gl_group_id = $rev['gl_group_id'] ?? '';
                foreach ($gl_accounts as $acc) {
                    if (($acc['gl_group_id'] ?? '') == $gl_group_id) {
                        $name = $acc['gl_group_name'] ?? '';
                        if ((strpos($name, 'ACC') === 0) || (stripos($name, 'accommodation') !== false)) {
                            $acc_revenue_count++;
                        }
                        break;
                    }
                }
            }
            error_log('HCR Occupancy: Found ' . $acc_revenue_count . ' accommodation revenue records');
        }

        // Log room counts and booking exclusions for debugging
        $total_rooms = 0;
        foreach ($room_category_counts as $cat_id => $cat_info) {
            $total_rooms += $cat_info['count'];
        }
        error_log('HCR Occupancy: Total rooms (excluding overflow): ' . $total_rooms);
        error_log('HCR Occupancy: Room categories: ' . json_encode($room_category_counts));
        error_log('HCR Occupancy: Bookings processed: ' . $total_bookings_processed . ', Overflow bookings excluded: ' . $overflow_bookings_excluded);

        // Log byCategory data for GGR verification (all dates)
        foreach ($dates as $check_date) {
            if (isset($daily_stats[$check_date]) && isset($daily_stats[$check_date]['byCategory'])) {
                error_log('HCR Occupancy GGR: Date ' . $check_date . ' - Categories: ' . json_encode(array_keys($daily_stats[$check_date]['byCategory'])));

                $date_total_rate = 0;
                $date_room_count = 0;
                foreach ($daily_stats[$check_date]['byCategory'] as $cat_id => $cat_data) {
                    $cat_name = $cat_data['name'] ?? 'Unknown';
                    $total_rate = $cat_data['totalRate'] ?? 0;
                    $room_count = $cat_data['roomCount'] ?? 0;
                    $date_total_rate += $total_rate;
                    $date_room_count += $room_count;
                    error_log('HCR Occupancy GGR:   ' . $cat_name . ' (ID: ' . $cat_id . ') - Total: £' . number_format($total_rate, 2) . ', Rooms: ' . $room_count);
                }

                $calculated_ggr = $date_room_count > 0 ? $date_total_rate / $date_room_count : 0;
                error_log('HCR Occupancy GGR: ' . $check_date . ' TOTAL - £' . number_format($date_total_rate, 2) . ' / ' . $date_room_count . ' rooms = £' . number_format($calculated_ggr, 2) . ' per room');
            }
        }

        // Build days array
        $days = array();
        foreach ($dates as $date) {
            $days[] = array('date' => $date);
        }

        wp_send_json_success(array(
            'days' => $days,
            'earned_revenue' => $earned_revenue,
            'gl_accounts' => $gl_accounts,
            'occupancy_by_date' => $occupancy_by_date,
            'room_category_counts' => $room_category_counts,
            'daily_stats' => $daily_stats,
            'sites_data' => $sites_data  // Include for front-end reference
        ));
    }
}
