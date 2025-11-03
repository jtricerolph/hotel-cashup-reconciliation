<?php
/**
 * Plugin deactivation class
 *
 * Handles cleanup on plugin deactivation
 */
class HCR_Deactivator {

    public static function deactivate() {
        // Clear any scheduled cron jobs
        wp_clear_scheduled_hook('hcr_daily_sync');

        // Flush rewrite rules
        flush_rewrite_rules();

        error_log('HCR: Plugin deactivated');
    }
}
