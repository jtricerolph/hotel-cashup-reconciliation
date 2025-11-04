<?php
/**
 * Plugin activation class
 *
 * Creates database tables and sets default options
 */
class HCR_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table 1: Cash Up Sessions
        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
        $sql_cash_ups = "CREATE TABLE IF NOT EXISTS $cash_ups_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_date date NOT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'draft',
            total_float_counted decimal(10,2) DEFAULT 0.00,
            total_cash_counted decimal(10,2) DEFAULT 0.00,
            notes text,
            machine_photo_id bigint(20) DEFAULT NULL,
            submitted_at datetime,
            submitted_by bigint(20),
            PRIMARY KEY (id),
            UNIQUE KEY session_date (session_date),
            KEY created_by (created_by),
            KEY status (status)
        ) $charset_collate;";

        // Table 2: Cash Denomination Counts
        $denominations_table = $wpdb->prefix . 'hcr_denominations';
        $sql_denominations = "CREATE TABLE IF NOT EXISTS $denominations_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cash_up_id bigint(20) NOT NULL,
            count_type varchar(20) NOT NULL DEFAULT 'takings',
            denomination_type varchar(20) NOT NULL,
            denomination_value decimal(10,2) NOT NULL,
            quantity int DEFAULT NULL,
            value_entered decimal(10,2) DEFAULT NULL,
            total_amount decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY cash_up_id (cash_up_id),
            KEY count_type (count_type),
            FOREIGN KEY (cash_up_id) REFERENCES $cash_ups_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Table 3: Card Machine Data
        $card_machines_table = $wpdb->prefix . 'hcr_card_machines';
        $sql_card_machines = "CREATE TABLE IF NOT EXISTS $card_machines_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cash_up_id bigint(20) NOT NULL,
            machine_name varchar(100) NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            amex_amount decimal(10,2) NOT NULL,
            visa_mc_amount decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY cash_up_id (cash_up_id),
            FOREIGN KEY (cash_up_id) REFERENCES $cash_ups_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Table 4: Payment Records from Newbook
        $payments_table = $wpdb->prefix . 'hcr_payment_records';
        $sql_payments = "CREATE TABLE IF NOT EXISTS $payments_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cash_up_id bigint(20) DEFAULT NULL,
            newbook_payment_id varchar(100),
            booking_id varchar(100),
            guest_name varchar(255),
            payment_date datetime NOT NULL,
            payment_type varchar(100),
            payment_method varchar(50),
            transaction_method varchar(50),
            card_type varchar(50),
            amount decimal(10,2) NOT NULL,
            tendered decimal(10,2),
            processed_by varchar(255),
            synced_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cash_up_id (cash_up_id),
            KEY newbook_payment_id (newbook_payment_id),
            KEY payment_date (payment_date),
            KEY transaction_method (transaction_method),
            KEY card_type (card_type),
            FOREIGN KEY (cash_up_id) REFERENCES $cash_ups_table(id) ON DELETE SET NULL
        ) $charset_collate;";

        // Table 5: Reconciliation Results
        $reconciliation_table = $wpdb->prefix . 'hcr_reconciliation';
        $sql_reconciliation = "CREATE TABLE IF NOT EXISTS $reconciliation_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cash_up_id bigint(20) NOT NULL,
            category varchar(50) NOT NULL,
            banked_amount decimal(10,2) NOT NULL,
            reported_amount decimal(10,2) NOT NULL,
            variance decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY cash_up_id (cash_up_id),
            KEY category (category),
            FOREIGN KEY (cash_up_id) REFERENCES $cash_ups_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Table 6: Daily Statistics
        $daily_stats_table = $wpdb->prefix . 'hcr_daily_stats';
        $sql_daily_stats = "CREATE TABLE IF NOT EXISTS $daily_stats_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_date date NOT NULL,
            gross_sales decimal(10,2) DEFAULT 0.00,
            debtors_creditors_balance decimal(10,2) DEFAULT 0.00,
            rooms_sold int DEFAULT 0,
            total_people int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            source varchar(50) DEFAULT 'manual',
            PRIMARY KEY (id),
            UNIQUE KEY business_date (business_date)
        ) $charset_collate;";

        // Table 7: Sales Breakdown
        $sales_breakdown_table = $wpdb->prefix . 'hcr_sales_breakdown';
        $sql_sales_breakdown = "CREATE TABLE IF NOT EXISTS $sales_breakdown_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_date date NOT NULL,
            category varchar(100) NOT NULL,
            net_amount decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY business_date (business_date),
            KEY category (category)
        ) $charset_collate;";

        // Table 8: Float Counts (Petty Cash and Change Tin)
        $float_counts_table = $wpdb->prefix . 'hcr_float_counts';
        $sql_float_counts = "CREATE TABLE IF NOT EXISTS $float_counts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            count_type varchar(20) NOT NULL,
            count_date datetime NOT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            total_counted decimal(10,2) DEFAULT 0.00,
            total_receipts decimal(10,2) DEFAULT 0.00,
            target_amount decimal(10,2) DEFAULT 0.00,
            variance decimal(10,2) DEFAULT 0.00,
            notes text,
            PRIMARY KEY (id),
            KEY count_type (count_type),
            KEY count_date (count_date),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Table 9: Float Denomination Counts
        $float_denominations_table = $wpdb->prefix . 'hcr_float_denominations';
        $sql_float_denominations = "CREATE TABLE IF NOT EXISTS $float_denominations_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            float_count_id bigint(20) NOT NULL,
            denomination_value decimal(10,2) NOT NULL,
            quantity int DEFAULT 0,
            total_amount decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY float_count_id (float_count_id),
            FOREIGN KEY (float_count_id) REFERENCES $float_counts_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Table 10: Float Receipts (for petty cash)
        $float_receipts_table = $wpdb->prefix . 'hcr_float_receipts';
        $sql_float_receipts = "CREATE TABLE IF NOT EXISTS $float_receipts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            float_count_id bigint(20) NOT NULL,
            receipt_value decimal(10,2) NOT NULL,
            receipt_description varchar(255),
            PRIMARY KEY (id),
            KEY float_count_id (float_count_id),
            FOREIGN KEY (float_count_id) REFERENCES $float_counts_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_cash_ups);
        dbDelta($sql_denominations);
        dbDelta($sql_card_machines);
        dbDelta($sql_payments);
        dbDelta($sql_reconciliation);
        dbDelta($sql_daily_stats);
        dbDelta($sql_sales_breakdown);
        dbDelta($sql_float_counts);
        dbDelta($sql_float_denominations);
        dbDelta($sql_float_receipts);

        // Set default options
        add_option('hcr_newbook_api_username', '');
        add_option('hcr_newbook_api_password', '');
        add_option('hcr_newbook_api_key', '');
        add_option('hcr_newbook_api_region', 'eu');
        add_option('hcr_hotel_id', '1');
        add_option('hcr_currency', 'GBP');

        // GBP denominations
        add_option('hcr_denominations', json_encode(array(
            'notes' => array(50.00, 20.00, 10.00, 5.00),
            'coins' => array(2.00, 1.00, 0.50, 0.20, 0.10, 0.05, 0.02, 0.01)
        )));

        add_option('hcr_enable_auto_sync', 'no');
        add_option('hcr_sync_frequency', 'daily');
        add_option('hcr_default_report_days', '7');
        add_option('hcr_variance_threshold', '10.00');

        // Float management settings
        add_option('hcr_petty_cash_float', '200.00'); // Default petty cash float amount

        // Change tin breakdown - default breakdown by denomination
        add_option('hcr_change_tin_breakdown', json_encode(array(
            '50.00' => 0,    // £50 notes
            '20.00' => 0,    // £20 notes
            '10.00' => 0,    // £10 notes
            '5.00' => 0,     // £5 notes
            '2.00' => 20.00, // £2 bags (1 bag = £20)
            '1.00' => 20.00, // £1 bags (1 bag = £20)
            '0.50' => 10.00, // 50p bags (1 bag = £10)
            '0.20' => 10.00, // 20p bags (1 bag = £10)
            '0.10' => 5.00,  // 10p bags (1 bag = £5)
            '0.05' => 5.00   // 5p bags (1 bag = £5)
        )));

        // Flush rewrite rules
        flush_rewrite_rules();

        // Run database migrations for version 1.1.0
        self::migrate_to_1_1_0();

        // Run database migrations for version 1.6.9
        self::migrate_to_1_6_9();

        error_log('HCR: Plugin activated and database tables created');
    }

    /**
     * Migrate database to version 1.1.0
     * Adds count_type column to denominations table and total_float_counted to cash_ups table
     */
    private static function migrate_to_1_1_0() {
        global $wpdb;

        // Check if count_type column exists in denominations table
        $denominations_table = $wpdb->prefix . 'hcr_denominations';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $denominations_table LIKE 'count_type'");

        if (empty($column_exists)) {
            // Add count_type column after cash_up_id
            $wpdb->query("ALTER TABLE $denominations_table ADD COLUMN count_type varchar(20) NOT NULL DEFAULT 'takings' AFTER cash_up_id");
            $wpdb->query("ALTER TABLE $denominations_table ADD KEY count_type (count_type)");
            error_log('HCR: Added count_type column to hcr_denominations table');
        }

        // Check if total_float_counted column exists in cash_ups table
        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
        $float_column_exists = $wpdb->get_results("SHOW COLUMNS FROM $cash_ups_table LIKE 'total_float_counted'");

        if (empty($float_column_exists)) {
            // Add total_float_counted column after status
            $wpdb->query("ALTER TABLE $cash_ups_table ADD COLUMN total_float_counted decimal(10,2) DEFAULT 0.00 AFTER status");
            error_log('HCR: Added total_float_counted column to hcr_cash_ups table');
        }
    }

    /**
     * Migrate database to version 1.6.9
     * Adds machine_photo_id column to cash_ups table for card machine receipt photos
     */
    private static function migrate_to_1_6_9() {
        global $wpdb;

        $cash_ups_table = $wpdb->prefix . 'hcr_cash_ups';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $cash_ups_table LIKE 'machine_photo_id'");

        if (empty($column_exists)) {
            // Add machine_photo_id column after notes
            $wpdb->query("ALTER TABLE $cash_ups_table ADD COLUMN machine_photo_id bigint(20) DEFAULT NULL AFTER notes");
            error_log('HCR: Added machine_photo_id column to hcr_cash_ups table');
        }
    }
}
