<?php
/**
 * Debug Newbook API Response
 * Shows the raw API response to understand the data structure
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "DEBUGGING NEWBOOK API RAW RESPONSE\n";
echo str_repeat("=", 70) . "\n\n";

$test_date = isset($argv[1]) ? $argv[1] : date('Y-m-d');
echo "Testing date: {$test_date}\n\n";

// Get API credentials
$username = get_option('hcr_newbook_api_username');
$password = get_option('hcr_newbook_api_password');
$api_key = get_option('hcr_newbook_api_key');
$region = get_option('hcr_newbook_api_region', 'eu');

$period_from = $test_date . ' 00:00:00';
$period_to = $test_date . ' 23:59:59';

// Test reports_transaction_flow endpoint
echo "Calling: reports_transaction_flow\n";
echo str_repeat("-", 70) . "\n";

$data = array(
    'period_from' => $period_from,
    'period_to' => $period_to,
    'data_offset' => 0,
    'data_limit' => 50,
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

$response = wp_remote_post('https://api.newbook.cloud/rest/reports_transaction_flow', $args);

if (is_wp_error($response)) {
    echo "Error: " . $response->get_error_message() . "\n";
    exit(1);
}

$response_code = wp_remote_retrieve_response_code($response);
$response_body = wp_remote_retrieve_body($response);

echo "Response Code: {$response_code}\n\n";

$json = json_decode($response_body, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Parse Error: " . json_last_error_msg() . "\n";
    echo "Raw Response:\n";
    echo $response_body . "\n";
    exit(1);
}

echo "Decoded Response Structure:\n";
echo str_repeat("-", 70) . "\n";
print_r($json);

// Show first few records if available
if (isset($json['data']) && is_array($json['data']) && count($json['data']) > 0) {
    echo "\n\nFirst Transaction Detail:\n";
    echo str_repeat("-", 70) . "\n";
    print_r($json['data'][0]);

    echo "\n\nAvailable Fields:\n";
    echo str_repeat("-", 70) . "\n";
    echo implode(", ", array_keys($json['data'][0])) . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";
?>
