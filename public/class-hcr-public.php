<?php
/**
 * Public-facing functionality class
 *
 * Handles front-end shortcodes and public pages
 */
class HCR_Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            HCR_PLUGIN_URL . 'assets/css/hcr-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        // Enqueue admin JavaScript (contains all calculation logic)
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            HCR_PLUGIN_URL . 'assets/js/hcr-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Enqueue public JavaScript
        wp_enqueue_script(
            $this->plugin_name,
            HCR_PLUGIN_URL . 'assets/js/hcr-public.js',
            array('jquery', $this->plugin_name . '-admin'),
            $this->version,
            false
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            $this->plugin_name,
            'hcrPublic',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hcr_public_nonce'),
                'denominations' => json_decode(get_option('hcr_denominations'), true),
                'expectedTillFloat' => floatval(get_option('hcr_expected_till_float', '300.00'))
            )
        );

        // Also localize the admin script for public use
        wp_localize_script(
            $this->plugin_name . '-admin',
            'hcrAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hcr_admin_nonce'),
                'expectedTillFloat' => floatval(get_option('hcr_expected_till_float', '300.00'))
            )
        );
    }

    /**
     * Render cash up form shortcode
     */
    public function render_cash_up_form($atts) {
        // Check user permissions
        if (!is_user_logged_in()) {
            return '<p class="hcr-error">You must be logged in to access this form.</p>';
        }

        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'date' => date('Y-m-d')
        ), $atts);

        // Use output buffering to capture template
        ob_start();
        include HCR_PLUGIN_DIR . 'public/views/cash-up-form.php';
        return ob_get_clean();
    }

    /**
     * Render petty cash count form shortcode
     */
    public function render_petty_cash_form($atts) {
        // Check user permissions
        if (!is_user_logged_in()) {
            return '<p class="hcr-error">You must be logged in to access this form.</p>';
        }

        // Parse shortcode attributes
        $atts = shortcode_atts(array(), $atts);

        // Use output buffering to capture template
        ob_start();
        include HCR_PLUGIN_DIR . 'public/views/petty-cash-form.php';
        return ob_get_clean();
    }

    /**
     * Render change tin count form shortcode
     */
    public function render_change_tin_form($atts) {
        // Check user permissions
        if (!is_user_logged_in()) {
            return '<p class="hcr-error">You must be logged in to access this form.</p>';
        }

        // Parse shortcode attributes
        $atts = shortcode_atts(array(), $atts);

        // Use output buffering to capture template
        ob_start();
        include HCR_PLUGIN_DIR . 'public/views/change-tin-form.php';
        return ob_get_clean();
    }

    /**
     * Render occupancy statistics table shortcode
     */
    public function render_occupancy_table($atts) {
        // Check user permissions
        if (!is_user_logged_in()) {
            return '<p class="hcr-error">You must be logged in to access this report.</p>';
        }

        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'start_date' => date('Y-m-d', strtotime('monday this week')),
            'days' => 7,
            'autoload' => 'false'
        ), $atts);

        // Use output buffering to capture template
        ob_start();
        include HCR_PLUGIN_DIR . 'public/views/occupancy-table.php';
        return ob_get_clean();
    }
}
