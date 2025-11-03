<?php
/**
 * Newbook API Integration Class
 *
 * Handles all communication with Newbook PMS API
 */
class HCR_Newbook_API {

    private $api_base_url = 'https://api.newbook.cloud/rest/';
    private $username;
    private $password;
    private $api_key;
    private $region;
    private $hotel_id;

    public function __construct() {
        $this->username = get_option('hcr_newbook_api_username');
        $this->password = get_option('hcr_newbook_api_password');
        $this->api_key = get_option('hcr_newbook_api_key');
        $this->region = get_option('hcr_newbook_api_region', 'uk');
        $this->hotel_id = get_option('hcr_hotel_id', '1');
    }

    /**
     * Make authenticated API call to Newbook
     */
    private function call_api($endpoint, $data = array()) {
        if (empty($this->username) || empty($this->password) || empty($this->api_key)) {
            error_log('HCR: Newbook API credentials not configured');
            return false;
        }

        $url = $this->api_base_url . $endpoint;

        // Add required parameters
        $data['region'] = $this->region;
        $data['api_key'] = $this->api_key;

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
            ),
            'body' => json_encode($data)
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            error_log('HCR: Newbook API request failed: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('HCR: Newbook API returned error code: ' . $response_code);
            error_log('HCR: Response body: ' . $response_body);
            return false;
        }

        $data_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('HCR: Failed to parse Newbook API response: ' . json_last_error_msg());
            return false;
        }

        return $data_response;
    }

    /**
     * Fetch payments for a specific date using transaction flow report
     * This includes both payments and refunds for accurate reconciliation
     *
     * @param string $date Date to fetch payments for
     * @param bool $return_raw_data If true, returns both payments and raw transaction data
     * @return array|false Payment data or false on failure
     */
    public function fetch_payments_by_date($date, $return_raw_data = false) {
        $period_from = $date . ' 00:00:00';
        $period_to = $date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'data_offset' => 0,
            'data_limit' => 1000
        );

        $response = $this->call_api('reports_transaction_flow', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No payment data returned from Newbook');
            return false;
        }

        $payments = array();

        foreach ($response['data'] as $transaction) {
            // Skip non-payment items (but include refunds as they reduce totals)
            $item_type = $transaction['item_type'] ?? '';
            if ($item_type !== 'payments_raised' && $item_type !== 'refunds_raised') {
                continue;
            }

            // In Newbook, payments are negative and refunds are positive (account perspective)
            // For reconciliation, we want payments positive and refunds negative (revenue perspective)
            // So we simply negate the value
            $amount = -floatval($transaction['item_amount'] ?? 0);

            $payments[] = array(
                'payment_id' => $transaction['item_id'] ?? '',
                'booking_id' => $transaction['booking_id'] ?? '',
                'guest_name' => $transaction['account_for_name'] ?? '',
                'payment_date' => $transaction['item_date'] ?? $date,
                'payment_type' => $transaction['payment_type'] ?? '',
                'payment_method' => '',
                'transaction_method' => $transaction['payment_transaction_method'] ?? 'manual',
                'card_type' => $this->identify_card_type($transaction),
                'amount' => $amount,
                'tendered' => 0,
                'processed_by' => '',
                'item_type' => $item_type
            );
        }

        // If raw data is requested, return both payments and raw transaction data
        if ($return_raw_data) {
            return array(
                'payments' => $payments,
                'raw_data' => $response
            );
        }

        return $payments;
    }

    /**
     * Fetch payments for a date range using transaction flow report
     * Returns payments grouped by date for efficient multi-day report generation
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array|false Array of payments grouped by date, or false on failure
     */
    public function fetch_payments_by_date_range($start_date, $end_date) {
        $period_from = $start_date . ' 00:00:00';
        $period_to = $end_date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'data_offset' => 0,
            'data_limit' => 5000  // Increased limit for multi-day data
        );

        $response = $this->call_api('reports_transaction_flow', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No payment data returned from Newbook for date range');
            return false;
        }

        // Group payments by date
        $payments_by_date = array();

        foreach ($response['data'] as $transaction) {
            // Skip non-payment items (but include refunds as they reduce totals)
            $item_type = $transaction['item_type'] ?? '';
            if ($item_type !== 'payments_raised' && $item_type !== 'refunds_raised') {
                continue;
            }

            // Extract date from item_date (format: YYYY-MM-DD HH:MM:SS)
            $item_date = $transaction['item_date'] ?? '';
            $date = substr($item_date, 0, 10); // Get just YYYY-MM-DD

            if (empty($date)) {
                continue;
            }

            // In Newbook, payments are negative and refunds are positive (account perspective)
            // For reconciliation, we want payments positive and refunds negative (revenue perspective)
            // So we simply negate the value
            $amount = -floatval($transaction['item_amount'] ?? 0);

            $card_type = $this->identify_card_type($transaction);
            $transaction_method = $transaction['payment_transaction_method'] ?? 'manual';

            // Log refunds for debugging
            $payment = array(
                'payment_id' => $transaction['item_id'] ?? '',
                'booking_id' => $transaction['booking_id'] ?? '',
                'guest_name' => $transaction['account_for_name'] ?? '',
                'payment_date' => $item_date,
                'payment_type' => $transaction['payment_type'] ?? '',
                'payment_method' => '',
                'transaction_method' => $transaction['payment_transaction_method'] ?? 'manual',
                'card_type' => $card_type,
                'amount' => $amount,
                'tendered' => 0,
                'processed_by' => '',
                'item_type' => $item_type
            );

            // Initialize date array if not exists
            if (!isset($payments_by_date[$date])) {
                $payments_by_date[$date] = array();
            }

            $payments_by_date[$date][] = $payment;
        }

        return $payments_by_date;
    }

    /**
     * Fetch daily audit summary for a specific date
     *
     * @param string $date Date to fetch audit data for (YYYY-MM-DD)
     * @return array|false Audit data or false on failure
     */
    public function fetch_daily_audit_summary($date) {
        $period_from = $date . ' 00:00:00';
        $period_to = $date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to
        );

        $response = $this->call_api('reports_daily_audit_summary', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No audit data returned from Newbook for date ' . $date);
            return false;
        }

        return $response['data'];
    }

    /**
     * Identify card type from transaction data
     */
    private function identify_card_type($transaction) {
        // Handle both old 'type' field and new 'payment_type' field
        $type = isset($transaction['payment_type']) ? strtolower($transaction['payment_type']) : '';
        if (empty($type)) {
            $type = isset($transaction['type']) ? strtolower($transaction['type']) : '';
        }

        $method = isset($transaction['method']) ? strtolower($transaction['method']) : '';
        $transaction_method = isset($transaction['payment_transaction_method']) ? strtolower($transaction['payment_transaction_method']) : '';
        $combined = $type . ' ' . $method;

        // Cash must be identified first
        if (strpos($combined, 'cash') !== false) {
            return 'cash';
        }

        // BACS/Bank transfers
        if (strpos($combined, 'eft') !== false || strpos($combined, 'bacs') !== false ||
            strpos($combined, 'bank transfer') !== false || strpos($combined, 'banktransfer') !== false ||
            strpos($combined, 'direct debit') !== false) {
            return 'bacs';
        }

        // Amex - must be explicitly identified
        if (strpos($combined, 'amex') !== false || strpos($combined, 'american express') !== false) {
            return 'amex';
        }

        // Visa/Mastercard - must be explicitly identified
        if (strpos($combined, 'visa') !== false || strpos($combined, 'mastercard') !== false ||
            strpos($combined, 'master card') !== false || strpos($combined, 'mc') !== false) {
            return 'visa_mc';
        }

        // For gateway/automated transactions, if we haven't identified the card type by now,
        // we should still try to categorize it rather than marking as 'other'
        if ($transaction_method === 'automated' || $transaction_method === 'gateway' || $transaction_method === 'cc_gateway') {
            // If payment_type contains any card-related keywords, it's likely a card payment
            if (strpos($combined, 'card') !== false || strpos($combined, 'credit') !== false || strpos($combined, 'debit') !== false) {
                return 'visa_mc';
            }

            // If payment_type is empty or doesn't match any known type, default to visa_mc
            // since gateway transactions are almost always card payments (Visa/MC more common than Amex)
            return 'visa_mc';
        }

        // Log unidentified payment types for debugging (non-gateway transactions only)
        if (!empty($type)) {
            error_log('HCR: Unidentified payment type: "' . $type . '" (method: "' . $method . '", transaction_method: "' . $transaction_method . '") - Transaction: ' . json_encode($transaction));
        }

        return 'other';
    }

    /**
     * Calculate payment totals for reconciliation
     */
    public function calculate_payment_totals($payments) {
        $totals = array(
            'cash' => 0,
            'manual_visa_mc' => 0,
            'manual_amex' => 0,
            'gateway_visa_mc' => 0,
            'gateway_amex' => 0,
            'bacs' => 0
        );

        foreach ($payments as $payment) {
            $amount = floatval($payment['amount']);
            $transaction_method = $payment['transaction_method'];
            $card_type = $payment['card_type'];

            if ($card_type === 'cash') {
                $totals['cash'] += $amount;
            } elseif ($card_type === 'bacs') {
                // BACS is always gateway/automated, not physical
                $totals['bacs'] += $amount;
            } elseif ($transaction_method === 'manual') {
                if ($card_type === 'amex') {
                    $totals['manual_amex'] += $amount;
                } elseif ($card_type === 'visa_mc') {
                    $totals['manual_visa_mc'] += $amount;
                }
            } elseif ($transaction_method === 'automated' || $transaction_method === 'gateway' || $transaction_method === 'cc_gateway') {
                if ($card_type === 'amex') {
                    $totals['gateway_amex'] += $amount;
                } elseif ($card_type === 'visa_mc') {
                    $totals['gateway_visa_mc'] += $amount;
                }
            }
        }

        return $totals;
    }

    /**
     * Parse till system transactions from Newbook transaction data
     * Extracts transactions where method is "manual" and item_description follows pattern:
     * "Ticket: {number} - {payment_type}"
     *
     * @param array $transaction_data Raw transaction data from Newbook API
     * @return array Grouped by payment type with count and total
     */
    public function parse_till_system_transactions($transaction_data) {
        $till_payments = array();

        if (!isset($transaction_data['data']) || !is_array($transaction_data['data'])) {
            return array();
        }

        $manual_count = 0;
        $matched_count = 0;

        foreach ($transaction_data['data'] as $transaction) {
            // Only process manual transactions
            $method = $transaction['payment_transaction_method'] ?? '';
            if ($method !== 'manual') {
                continue;
            }

            $manual_count++;

            // Get item description
            $description = $transaction['item_description'] ?? '';

            // Parse "Ticket: {number} - {payment_type}" pattern
            // Pattern: Ticket: followed by digits, then " - ", then payment type name
            if (preg_match('/^Ticket:\s*(\d+)\s*-\s*(.+)$/i', $description, $matches)) {
                $matched_count++;
                $ticket_number = $matches[1];
                $payment_type = trim($matches[2]);
                $amount = abs(floatval($transaction['item_amount'] ?? 0));

                // Skip if amount is 0
                if ($amount == 0) {
                    continue;
                }

                // Initialize payment type array if not exists
                if (!isset($till_payments[$payment_type])) {
                    $till_payments[$payment_type] = array(
                        'payment_type' => $payment_type,
                        'quantity' => 0,
                        'total_value' => 0.00
                    );
                }

                // Increment count and add to total
                $till_payments[$payment_type]['quantity']++;
                $till_payments[$payment_type]['total_value'] += $amount;
            }
        }

        // Convert associative array to indexed array for JSON response
        $result = array_values($till_payments);

        return $result;
    }

    /**
     * Categorize payment type into groups (Cash, BACS, Card)
     */
    private function categorize_payment_type($payment_type) {
        $type_lower = strtolower($payment_type);

        if (strpos($type_lower, 'cash') !== false) {
            return 'Cash';
        } elseif (strpos($type_lower, 'bacs') !== false ||
                  strpos($type_lower, 'eft') !== false ||
                  strpos($type_lower, 'bank transfer') !== false ||
                  strpos($type_lower, 'direct debit') !== false) {
            return 'BACS';
        } else {
            // Everything else (including all card types) is grouped as 'Card'
            return 'Card';
        }
    }

    /**
     * Parse transaction breakdown for display
     * Separates transactions into reception (manual/gateway) and restaurant/bar (till)
     * Groups by payment type category (Cash, BACS, Card)
     *
     * @param array $transaction_data Raw transaction data from Newbook API
     * @return array Separated transactions grouped by payment type
     */
    public function parse_transaction_breakdown($transaction_data) {
        $reception_manual = array();
        $reception_gateway = array();
        $restaurant_bar = array();

        if (!isset($transaction_data['data']) || !is_array($transaction_data['data'])) {
            error_log('HCR: No transaction data available for breakdown parsing');
            return array(
                'reception_manual' => array(),
                'reception_gateway' => array(),
                'restaurant_bar' => array()
            );
        }

        foreach ($transaction_data['data'] as $transaction) {
            // Only process payments and refunds
            $item_type = $transaction['item_type'] ?? '';
            if ($item_type !== 'payments_raised' && $item_type !== 'refunds') {
                continue;
            }

            $description = $transaction['item_description'] ?? '';
            $method = $transaction['payment_transaction_method'] ?? '';
            $payment_type = $transaction['payment_type'] ?? '';
            $amount = floatval($transaction['item_amount'] ?? 0);
            $time = $transaction['item_date'] ?? '';
            $booking_id = $transaction['booking_id'] ?? '';
            $account_name = $transaction['account_for_name'] ?? '';

            // Categorize payment type
            $category = $this->categorize_payment_type($payment_type);

            // Check if this is a till/ticket payment
            $is_ticket = preg_match('/^Ticket:\s*(\d+)\s*-\s*(.+)$/i', $description, $matches);

            $transaction_item = array(
                'time' => $time,
                'payment_type' => $payment_type,
                'details' => '',
                'amount' => $amount
            );

            if ($is_ticket) {
                // Restaurant/Bar payment
                $ticket_number = $matches[1];
                $transaction_item['details'] = 'Ticket: ' . $ticket_number;

                // Group by payment type category
                if (!isset($restaurant_bar[$category])) {
                    $restaurant_bar[$category] = array();
                }
                $restaurant_bar[$category][] = $transaction_item;
            } else {
                // Reception payment
                // Format details
                if (!empty($booking_id) && !empty($account_name)) {
                    $transaction_item['details'] = '#' . $booking_id . ' - ' . $account_name;
                } elseif (!empty($booking_id)) {
                    $transaction_item['details'] = '#' . $booking_id;
                } elseif (!empty($account_name)) {
                    $transaction_item['details'] = $account_name;
                } else {
                    $transaction_item['details'] = $description;
                }

                // Categorize as manual or gateway and group by payment type
                if ($method === 'manual') {
                    if (!isset($reception_manual[$category])) {
                        $reception_manual[$category] = array();
                    }
                    $reception_manual[$category][] = $transaction_item;
                } elseif (in_array($method, array('cc_gateway', 'gateway', 'automated'))) {
                    if (!isset($reception_gateway[$category])) {
                        $reception_gateway[$category] = array();
                    }
                    $reception_gateway[$category][] = $transaction_item;
                }
            }
        }

        return array(
            'reception_manual' => $reception_manual,
            'reception_gateway' => $reception_gateway,
            'restaurant_bar' => $restaurant_bar
        );
    }

    /**
     * Fetch GL account list for revenue grouping
     *
     * @return array|false GL account data with groups or false on failure
     */
    public function fetch_gl_account_list() {
        $response = $this->call_api('gl_account_list', array());

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No GL account data returned from Newbook');
            return false;
        }

        return $response['data'];
    }

    /**
     * Fetch earned revenue for a date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @param string $period_increment Increment type ('day', 'week', 'month', etc.)
     * @return array|false Earned revenue data or false on failure
     */
    public function fetch_earned_revenue($start_date, $end_date, $period_increment = 'day') {
        $period_from = $start_date . ' 00:00:00';
        $period_to = $end_date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'period_increment' => $period_increment
        );

        $response = $this->call_api('reports_earned_revenue', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No earned revenue data returned from Newbook');
            return false;
        }

        return $response['data'];
    }

    /**
     * Fetch occupancy report for a date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array|false Occupancy data by category or false on failure
     */
    public function fetch_occupancy($start_date, $end_date) {
        $period_from = $start_date . ' 00:00:00';
        $period_to = $end_date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to
        );

        $response = $this->call_api('reports_occupancy', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No occupancy data returned from Newbook');
            return false;
        }

        return $response['data'];
    }

    /**
     * Fetch bookings list for a date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array|false Bookings data or false on failure
     */
    public function fetch_bookings_list($start_date, $end_date) {
        $period_from = $start_date . ' 00:00:00';
        $period_to = $end_date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'list_type' => 'staying'
        );

        $response = $this->call_api('bookings_list', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No bookings data returned from Newbook');
            return false;
        }

        return $response['data'];
    }

    /**
     * Fetch sites list
     *
     * @return array|false Sites data or false on failure
     */
    public function fetch_sites_list() {
        $response = $this->call_api('sites_list', array());

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No sites data returned from Newbook');
            return false;
        }

        return $response['data'];
    }

    /**
     * Fetch gross sales for a date
     */
    public function fetch_gross_sales($date) {
        // Placeholder - needs real Newbook endpoint
        // This would call a Newbook reporting endpoint
        // For now, return 0 as placeholder
        return 0.00;
    }

    /**
     * Fetch sales breakdown by category
     */
    public function fetch_sales_breakdown($date) {
        // Placeholder - needs real Newbook endpoint
        // This would return an array like:
        // array('Accommodation' => 1500.00, 'Food' => 800.00, 'Beverage' => 600.00, 'Other' => 100.00)
        return array(
            'Accommodation' => 0.00,
            'Food' => 0.00,
            'Beverage' => 0.00,
            'Other' => 0.00
        );
    }

    /**
     * Fetch occupancy statistics
     */
    public function fetch_occupancy_stats($date) {
        // Placeholder - needs real Newbook endpoint
        $period_from = $date . ' 00:00:00';
        $period_to = $date . ' 23:59:59';

        $data = array(
            'period_from' => $period_from,
            'period_to' => $period_to,
            'list_type' => 'staying'
        );

        $response = $this->call_api('bookings_list', $data);

        if (!$response || !isset($response['data'])) {
            return array('rooms_sold' => 0, 'total_people' => 0);
        }

        $rooms_sold = count($response['data']);
        $total_people = 0;

        foreach ($response['data'] as $booking) {
            $total_people += intval($booking['guests'] ?? 1);
        }

        return array(
            'rooms_sold' => $rooms_sold,
            'total_people' => $total_people
        );
    }

    /**
     * Fetch debtors/creditors balance for a specific date
     * Returns array with creditors, debtors, and overall balance
     */
    public function fetch_debtors_creditors_balance($date) {
        $data = array(
            'period_from' => $date
        );

        $response = $this->call_api('reports_balances_dated', $data);

        if (!$response || !isset($response['data'])) {
            error_log('HCR: No balance data returned for ' . $date);
            return array(
                'creditors' => 0.00,
                'debtors' => 0.00,
                'overall' => 0.00,
                'accounts' => array()
            );
        }

        $creditors = 0.00;
        $debtors = 0.00;
        $accounts = array();

        foreach ($response['data'] as $account) {
            $balance = floatval($account['account_balance'] ?? 0);

            // Negative balance = creditor (we owe them)
            if ($balance < 0) {
                $creditors += abs($balance);
            }
            // Positive balance = debtor (they owe us)
            else if ($balance > 0) {
                $debtors += $balance;
            }

            $accounts[] = array(
                'name' => $account['account_for_name'] ?? 'Unknown',
                'balance' => $balance
            );
        }

        $overall = $debtors - $creditors;

        return array(
            'creditors' => $creditors,
            'debtors' => $debtors,
            'overall' => $overall,
            'accounts' => $accounts
        );
    }

    /**
     * Fetch all GL accounts from Newbook
     * Returns associative array of gl_group_id => display_name
     */
    public function fetch_gl_accounts() {
        $response = $this->call_api('gl_account_list', array());

        if (!$response) {
            error_log('HCR: No response from gl_account_list API');
            return false;
        }

        if (!isset($response['data'])) {
            error_log('HCR: gl_account_list response missing data key. Response: ' . print_r($response, true));
            return false;
        }

        if (empty($response['data'])) {
            error_log('HCR: gl_account_list returned empty data array');
            // Return empty array rather than false - this is valid (just no GL accounts configured)
            return array();
        }

        $gl_accounts = array();

        // Extract GL accounts and their group names
        foreach ($response['data'] as $item) {
            // Use gl_group_name as the display name (e.g., "ACC - Accommodation")
            if (isset($item['gl_group_id']) && isset($item['gl_group_name'])) {
                $gl_group_id = trim($item['gl_group_id']);
                $gl_group_name = trim($item['gl_group_name']);

                // Parse name to extract just the category (remove prefix like "ACC - ")
                $display_name = $gl_group_name;
                if (strpos($gl_group_name, ' - ') !== false) {
                    $parts = explode(' - ', $gl_group_name, 2);
                    $display_name = isset($parts[1]) ? trim($parts[1]) : $gl_group_name;
                }

                if (!empty($gl_group_id) && !isset($gl_accounts[$gl_group_id])) {
                    $gl_accounts[$gl_group_id] = $display_name;
                }
            }
        }

        error_log('HCR: Successfully fetched ' . count($gl_accounts) . ' GL groups from Newbook');
        return $gl_accounts;
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        $response = $this->call_api('sites_list', array());

        if ($response && isset($response['data'])) {
            return array(
                'success' => true,
                'message' => 'Connected successfully. Found ' . count($response['data']) . ' site(s).'
            );
        }

        return array(
            'success' => false,
            'message' => 'Connection failed. Please check your API credentials.'
        );
    }
}
