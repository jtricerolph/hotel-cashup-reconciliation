<?php
/**
 * Core plugin class
 *
 * Orchestrates all plugin functionality and hooks
 */
class HCR_Core {

    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->version = HCR_VERSION;
        $this->plugin_name = 'hotel-cash-up-reconciliation';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_ajax_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once HCR_PLUGIN_DIR . 'admin/class-hcr-admin.php';
        require_once HCR_PLUGIN_DIR . 'public/class-hcr-public.php';
        require_once HCR_PLUGIN_DIR . 'includes/class-hcr-ajax.php';
        require_once HCR_PLUGIN_DIR . 'includes/class-hcr-newbook-api.php';
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        $plugin_admin = new HCR_Admin($this->get_plugin_name(), $this->get_version());

        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        add_action('admin_init', array($plugin_admin, 'register_settings'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks() {
        $plugin_public = new HCR_Public($this->get_plugin_name(), $this->get_version());

        add_action('wp_enqueue_scripts', array($plugin_public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($plugin_public, 'enqueue_scripts'));

        // Register shortcodes
        add_shortcode('hcr_cash_up_form', array($plugin_public, 'render_cash_up_form'));
    }

    /**
     * Register AJAX hooks
     */
    private function define_ajax_hooks() {
        $plugin_ajax = new HCR_Ajax();

        // Cash up actions
        add_action('wp_ajax_hcr_save_cash_up', array($plugin_ajax, 'handle_save_cash_up'));
        add_action('wp_ajax_hcr_load_cash_up', array($plugin_ajax, 'handle_load_cash_up'));
        add_action('wp_ajax_hcr_delete_cash_up', array($plugin_ajax, 'handle_delete_cash_up'));

        // Photo upload actions
        add_action('wp_ajax_hcr_upload_machine_photo', array($plugin_ajax, 'handle_upload_machine_photo'));
        add_action('wp_ajax_hcr_delete_machine_photo', array($plugin_ajax, 'handle_delete_machine_photo'));

        // Newbook data actions
        add_action('wp_ajax_hcr_fetch_newbook_payments', array($plugin_ajax, 'handle_fetch_newbook_payments'));
        add_action('wp_ajax_hcr_sync_daily_stats', array($plugin_ajax, 'handle_sync_daily_stats'));

        // Report actions
        add_action('wp_ajax_hcr_generate_multi_day_report', array($plugin_ajax, 'handle_generate_multi_day_report'));
        add_action('wp_ajax_hcr_fetch_debtors_creditors_data', array($plugin_ajax, 'handle_fetch_debtors_creditors_data'));
        add_action('wp_ajax_hcr_export_to_excel', array($plugin_ajax, 'handle_export_to_excel'));

        // Settings actions
        add_action('wp_ajax_hcr_test_connection', array($plugin_ajax, 'handle_test_connection'));
        add_action('wp_ajax_hcr_refresh_gl_accounts', array($plugin_ajax, 'handle_refresh_gl_accounts'));

        // Float management actions
        // Cash summary
        add_action('wp_ajax_hcr_generate_cash_summary', array($plugin_ajax, 'handle_generate_cash_summary'));

        // Petty cash
        add_action('wp_ajax_hcr_save_petty_cash_count', array($plugin_ajax, 'handle_save_petty_cash_count'));
        add_action('wp_ajax_hcr_load_petty_cash_counts', array($plugin_ajax, 'handle_load_petty_cash_counts'));
        add_action('wp_ajax_hcr_get_petty_cash_count', array($plugin_ajax, 'handle_get_petty_cash_count'));

        // Change tin
        add_action('wp_ajax_hcr_save_change_tin_count', array($plugin_ajax, 'handle_save_change_tin_count'));
        add_action('wp_ajax_hcr_load_change_tin_counts', array($plugin_ajax, 'handle_load_change_tin_counts'));
        add_action('wp_ajax_hcr_get_change_tin_count', array($plugin_ajax, 'handle_get_change_tin_count'));

        // Safe cash
        add_action('wp_ajax_hcr_save_safe_cash_count', array($plugin_ajax, 'handle_save_safe_cash_count'));
        add_action('wp_ajax_hcr_load_safe_cash_counts', array($plugin_ajax, 'handle_load_safe_cash_counts'));
        add_action('wp_ajax_hcr_get_safe_cash_count', array($plugin_ajax, 'handle_get_safe_cash_count'));
    }

    /**
     * Run the plugin
     */
    public function run() {
        // Plugin is running
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
