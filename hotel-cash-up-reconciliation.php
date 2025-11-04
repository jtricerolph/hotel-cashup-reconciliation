<?php
/**
 * Plugin Name: Hotel Cash Up & Reconciliation Reporting
 * Plugin URI: https://yourwebsite.com
 * Description: Daily cash counting, payment reconciliation, and multi-day reporting for hotel operations integrated with Newbook PMS
 * Version: 2.1.3
 * Author: Your Hotel
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hotel-cash-up-reconciliation
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('HCR_VERSION', '2.1.3');
define('HCR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HCR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HCR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation
 */
function activate_hotel_cash_up_reconciliation() {
    require_once HCR_PLUGIN_DIR . 'includes/class-hcr-activator.php';
    HCR_Activator::activate();
}

/**
 * Plugin deactivation
 */
function deactivate_hotel_cash_up_reconciliation() {
    require_once HCR_PLUGIN_DIR . 'includes/class-hcr-deactivator.php';
    HCR_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hotel_cash_up_reconciliation');
register_deactivation_hook(__FILE__, 'deactivate_hotel_cash_up_reconciliation');

/**
 * Load core plugin class
 */
require HCR_PLUGIN_DIR . 'includes/class-hcr-core.php';

/**
 * Begin plugin execution
 */
function run_hotel_cash_up_reconciliation() {
    $plugin = new HCR_Core();
    $plugin->run();
}
run_hotel_cash_up_reconciliation();
