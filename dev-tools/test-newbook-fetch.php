<?php
/**
 * Test Newbook Payment Fetch
 * Run this to test fetching payment data from Newbook API
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Load Newbook API class
require_once dirname(dirname(__FILE__)) . '/includes/class-hcr-newbook-api.php';

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "TESTING NEWBOOK API PAYMENT FETCH\n";
echo str_repeat("=", 70) . "\n\n";

// Get date to test (default to today)
$test_date = isset($argv[1]) ? $argv[1] : date('Y-m-d');
echo "Testing date: {$test_date}\n\n";

// Initialize API
$api = new HCR_Newbook_API();

echo "Step 1: Testing API Connection\n";
echo str_repeat("-", 70) . "\n";
$connection_test = $api->test_connection();
if ($connection_test['success']) {
    echo "✓ Connection successful\n";
    echo "  Message: {$connection_test['message']}\n\n";
} else {
    echo "✗ Connection failed\n";
    echo "  Message: {$connection_test['message']}\n\n";
    echo "Please check your API credentials in Settings.\n";
    exit(1);
}

echo "Step 2: Fetching Payments for {$test_date}\n";
echo str_repeat("-", 70) . "\n";
$payments = $api->fetch_payments_by_date($test_date);

if ($payments === false) {
    echo "✗ Failed to fetch payments\n";
    echo "Check the error log for details:\n";
    echo "  tail -f wp-content/debug.log\n\n";
    exit(1);
}

echo "✓ Successfully fetched " . count($payments) . " payments\n\n";

if (count($payments) > 0) {
    echo "Step 3: Payment Details\n";
    echo str_repeat("-", 70) . "\n";

    foreach ($payments as $i => $payment) {
        echo "Payment " . ($i + 1) . ":\n";
        echo "  Payment ID: {$payment['payment_id']}\n";
        echo "  Guest: {$payment['guest_name']}\n";
        echo "  Amount: £" . number_format($payment['amount'], 2) . "\n";
        echo "  Type: {$payment['payment_type']}\n";
        echo "  Method: {$payment['payment_method']}\n";
        echo "  Transaction Method: {$payment['transaction_method']}\n";
        echo "  Card Type: {$payment['card_type']}\n";
        echo "\n";
    }

    echo "Step 4: Calculate Totals\n";
    echo str_repeat("-", 70) . "\n";
    $totals = $api->calculate_payment_totals($payments);

    echo "Cash Total: £" . number_format($totals['cash'], 2) . "\n";
    echo "Manual Visa/MC: £" . number_format($totals['manual_visa_mc'], 2) . "\n";
    echo "Manual Amex: £" . number_format($totals['manual_amex'], 2) . "\n";
    echo "Gateway Visa/MC: £" . number_format($totals['gateway_visa_mc'], 2) . "\n";
    echo "Gateway Amex: £" . number_format($totals['gateway_amex'], 2) . "\n";
    echo "BACS/Bank Transfer: £" . number_format($totals['bacs'], 2) . "\n";
    echo "\n";
    echo "Total Manual PDQ: £" . number_format($totals['manual_visa_mc'] + $totals['manual_amex'], 2) . "\n";
    echo "Total Gateway: £" . number_format($totals['gateway_visa_mc'] + $totals['gateway_amex'], 2) . "\n";
    echo "Grand Total: £" . number_format(array_sum($totals), 2) . "\n";
} else {
    echo "No payments found for this date.\n";
    echo "\nTips:\n";
    echo "  - Make sure the date format is YYYY-MM-DD\n";
    echo "  - Check if there are actual bookings/payments for this date in Newbook\n";
    echo "  - Verify the hotel_id setting matches your property\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Test completed\n";
echo str_repeat("=", 70) . "\n\n";

echo "Usage: php test-newbook-fetch.php [YYYY-MM-DD]\n";
echo "Example: php test-newbook-fetch.php 2025-11-01\n\n";
?>
