<?php
/**
 * Check if HCR database tables exist
 * Run this from the command line: php check-tables.php
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

global $wpdb;

$tables = array(
    'wp_hcr_cash_ups',
    'wp_hcr_denominations',
    'wp_hcr_card_machines',
    'wp_hcr_payment_records',
    'wp_hcr_reconciliation',
    'wp_hcr_daily_stats',
    'wp_hcr_sales_breakdown'
);

echo "Checking HCR database tables:\n";
echo str_repeat("=", 50) . "\n\n";

foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    echo sprintf("%-35s %s\n", $table, $status);

    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo sprintf("  └─ Rows: %d\n", $count);
    }
}

echo "\n" . str_repeat("=", 50) . "\n";

// Check API settings
echo "\nNewbook API Configuration:\n";
echo str_repeat("=", 50) . "\n";
$api_username = get_option('hcr_newbook_api_username');
$api_password = get_option('hcr_newbook_api_password');
$api_key = get_option('hcr_newbook_api_key');
$region = get_option('hcr_newbook_api_region');
$hotel_id = get_option('hcr_hotel_id');

echo "API Username: " . ($api_username ? '✓ Set' : '✗ Not set') . "\n";
echo "API Password: " . ($api_password ? '✓ Set' : '✗ Not set') . "\n";
echo "API Key: " . ($api_key ? '✓ Set' : '✗ Not set') . "\n";
echo "Region: " . ($region ? $region : 'Not set') . "\n";
echo "Hotel ID: " . ($hotel_id ? $hotel_id : 'Not set') . "\n";

echo "\n" . str_repeat("=", 50) . "\n";
?>
