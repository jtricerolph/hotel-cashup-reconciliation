<?php
/**
 * Database Update Script for Hotel Cash Up & Reconciliation
 *
 * Run this script to manually update database schema.
 * Access via: /wp-content/plugins/hotel-cash-up-reconciliation/dev-tools/update-database.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is logged in and is admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. You must be logged in as an administrator.');
}

global $wpdb;

echo '<h1>Hotel Cash Up & Reconciliation - Database Update</h1>';
echo '<p>Running database migrations...</p>';

// Migration for version 1.6.9 - Add machine_photo_id column
$cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $cash_ups_table LIKE 'machine_photo_id'");

if (empty($column_exists)) {
    echo '<p>Adding machine_photo_id column to ' . $cash_ups_table . '...</p>';
    $result = $wpdb->query("ALTER TABLE $cash_ups_table ADD COLUMN machine_photo_id bigint(20) DEFAULT NULL AFTER notes");

    if ($result !== false) {
        echo '<p style="color: green;">✓ Successfully added machine_photo_id column</p>';
        error_log('HCR: Added machine_photo_id column to hcr_cash_ups table');
    } else {
        echo '<p style="color: red;">✗ Failed to add machine_photo_id column: ' . $wpdb->last_error . '</p>';
    }
} else {
    echo '<p style="color: blue;">ℹ machine_photo_id column already exists</p>';
}

echo '<hr>';
echo '<p><strong>Database update complete!</strong></p>';
echo '<p><a href="' . admin_url('admin.php?page=hcr-cash-up') . '">← Back to Cash Up Form</a></p>';
