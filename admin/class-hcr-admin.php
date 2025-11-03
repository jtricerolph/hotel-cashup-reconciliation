<?php
/**
 * Admin functionality class
 *
 * Handles admin menu, settings, and admin pages
 */
class HCR_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            HCR_PLUGIN_URL . 'assets/css/hcr-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            HCR_PLUGIN_URL . 'assets/js/hcr-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            $this->version,
            false
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            $this->plugin_name,
            'hcrAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hcr_admin_nonce'),
                'denominations' => json_decode(get_option('hcr_denominations'), true),
                'expectedTillFloat' => floatval(get_option('hcr_expected_till_float', '300.00'))
            )
        );
    }

    /**
     * Add plugin admin menu
     */
    public function add_plugin_admin_menu() {
        // Main menu page
        add_menu_page(
            'Cash Up & Reconciliation',           // Page title
            'Cash Up',                             // Menu title
            'edit_posts',                          // Capability
            $this->plugin_name,                    // Menu slug
            array($this, 'display_cash_up_form'),  // Callback
            'dashicons-money-alt',                 // Icon
            30                                     // Position
        );

        // Rename first submenu to "Daily Cash Up"
        add_submenu_page(
            $this->plugin_name,
            'Daily Cash Up',
            'Daily Cash Up',
            'edit_posts',
            $this->plugin_name,
            array($this, 'display_cash_up_form')
        );

        // History submenu
        add_submenu_page(
            $this->plugin_name,
            'Cash Up History',
            'History',
            'edit_posts',
            $this->plugin_name . '-history',
            array($this, 'display_history_page')
        );

        // Multi-Day Report submenu
        add_submenu_page(
            $this->plugin_name,
            'Multi-Day Report',
            'Multi-Day Report',
            'edit_posts',
            $this->plugin_name . '-multi-day-report',
            array($this, 'display_multi_day_report')
        );

        // Settings submenu
        add_submenu_page(
            $this->plugin_name,
            'Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Newbook API settings
        register_setting('hcr_settings_group', 'hcr_newbook_api_username', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_newbook_api_password', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_newbook_api_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_newbook_api_region', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_hotel_id', array('sanitize_callback' => 'sanitize_text_field'));

        // General settings
        register_setting('hcr_settings_group', 'hcr_currency', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_enable_auto_sync', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_sync_frequency', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('hcr_settings_group', 'hcr_default_report_days', array('sanitize_callback' => 'intval'));
        register_setting('hcr_settings_group', 'hcr_variance_threshold', array('sanitize_callback' => 'floatval'));
        register_setting('hcr_settings_group', 'hcr_expected_till_float', array('sanitize_callback' => 'floatval'));

        // Sales breakdown column settings
        register_setting('hcr_settings_group', 'hcr_sales_breakdown_columns', array('sanitize_callback' => array($this, 'sanitize_sales_columns')));

        // Add settings sections
        add_settings_section(
            'hcr_api_section',
            'Newbook API Configuration',
            array($this, 'api_section_callback'),
            'hcr_settings'
        );

        add_settings_section(
            'hcr_general_section',
            'General Settings',
            array($this, 'general_section_callback'),
            'hcr_settings'
        );

        add_settings_section(
            'hcr_sales_breakdown_section',
            'Sales Breakdown Column Settings',
            array($this, 'sales_breakdown_section_callback'),
            'hcr_settings'
        );

        // Add settings fields - API
        add_settings_field(
            'hcr_newbook_api_username',
            'API Username',
            array($this, 'username_field_callback'),
            'hcr_settings',
            'hcr_api_section'
        );

        add_settings_field(
            'hcr_newbook_api_password',
            'API Password',
            array($this, 'password_field_callback'),
            'hcr_settings',
            'hcr_api_section'
        );

        add_settings_field(
            'hcr_newbook_api_key',
            'API Key',
            array($this, 'api_key_field_callback'),
            'hcr_settings',
            'hcr_api_section'
        );

        add_settings_field(
            'hcr_newbook_api_region',
            'Region',
            array($this, 'region_field_callback'),
            'hcr_settings',
            'hcr_api_section'
        );

        add_settings_field(
            'hcr_hotel_id',
            'Hotel ID',
            array($this, 'hotel_id_field_callback'),
            'hcr_settings',
            'hcr_api_section'
        );

        // Add settings fields - General
        add_settings_field(
            'hcr_default_report_days',
            'Default Report Days',
            array($this, 'default_report_days_field_callback'),
            'hcr_settings',
            'hcr_general_section'
        );

        add_settings_field(
            'hcr_enable_auto_sync',
            'Enable Auto Sync',
            array($this, 'auto_sync_field_callback'),
            'hcr_settings',
            'hcr_general_section'
        );

        add_settings_field(
            'hcr_sync_frequency',
            'Sync Frequency',
            array($this, 'sync_frequency_field_callback'),
            'hcr_settings',
            'hcr_general_section'
        );

        add_settings_field(
            'hcr_expected_till_float',
            'Expected Till Float',
            array($this, 'expected_till_float_field_callback'),
            'hcr_settings',
            'hcr_general_section'
        );

        // Sales breakdown column field
        add_settings_field(
            'hcr_sales_breakdown_columns',
            'Sales Categories',
            array($this, 'sales_breakdown_columns_field_callback'),
            'hcr_settings',
            'hcr_sales_breakdown_section'
        );
    }

    // Section callbacks
    public function api_section_callback() {
        echo '<p>Configure your Newbook PMS API credentials below.</p>';
    }

    public function general_section_callback() {
        echo '<p>General plugin settings.</p>';
    }

    // Field callbacks
    public function username_field_callback() {
        $value = get_option('hcr_newbook_api_username', '');
        echo '<input type="text" name="hcr_newbook_api_username" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Newbook API username</p>';
    }

    public function password_field_callback() {
        $value = get_option('hcr_newbook_api_password', '');
        echo '<input type="password" name="hcr_newbook_api_password" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Newbook API password</p>';
    }

    public function api_key_field_callback() {
        $value = get_option('hcr_newbook_api_key', '');
        echo '<input type="text" name="hcr_newbook_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Newbook API key</p>';
    }

    public function region_field_callback() {
        $value = get_option('hcr_newbook_api_region', 'eu');
        $regions = array('au' => 'Australia', 'ap' => 'Asia Pacific', 'eu' => 'Europe', 'us' => 'United States');
        echo '<select name="hcr_newbook_api_region">';
        foreach ($regions as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($value, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Newbook API region</p>';
    }

    public function hotel_id_field_callback() {
        $value = get_option('hcr_hotel_id', '1');
        echo '<input type="text" name="hcr_hotel_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your hotel ID in Newbook</p>';
    }

    public function default_report_days_field_callback() {
        $value = get_option('hcr_default_report_days', '7');
        echo '<input type="number" name="hcr_default_report_days" value="' . esc_attr($value) . '" min="1" max="365" />';
        echo '<p class="description">Default number of days for multi-day reports (1-365)</p>';
    }

    public function auto_sync_field_callback() {
        $value = get_option('hcr_enable_auto_sync', 'no');
        echo '<input type="checkbox" name="hcr_enable_auto_sync" value="yes" ' . checked($value, 'yes', false) . ' />';
        echo '<label>Enable automatic sync with Newbook</label>';
    }

    public function sync_frequency_field_callback() {
        $value = get_option('hcr_sync_frequency', 'daily');
        $frequencies = array('hourly' => 'Hourly', 'daily' => 'Daily');
        echo '<select name="hcr_sync_frequency">';
        foreach ($frequencies as $freq => $label) {
            echo '<option value="' . esc_attr($freq) . '" ' . selected($value, $freq, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">How often to sync data from Newbook (if auto-sync enabled)</p>';
    }

    public function expected_till_float_field_callback() {
        $value = get_option('hcr_expected_till_float', '300.00');
        echo '<input type="number" name="hcr_expected_till_float" value="' . esc_attr($value) . '" step="0.01" min="0" class="regular-text" />';
        echo '<p class="description">Expected till float amount (in GBP). Variance will be calculated against this value.</p>';
    }

    public function sales_breakdown_section_callback() {
        echo '<p>Configure which sales categories appear in multi-day reports and their display order.</p>';
    }

    public function sales_breakdown_columns_field_callback() {
        $columns = $this->get_sales_breakdown_columns();
        ?>
        <div id="hcr-sales-columns-container">
            <button type="button" id="hcr-refresh-gl-accounts" class="button button-secondary" style="margin-bottom: 15px;">
                Refresh from Newbook
            </button>
            <span id="hcr-refresh-status" style="margin-left: 10px;"></span>

            <table class="wp-list-table widefat fixed striped" id="hcr-sales-columns-table">
                <thead>
                    <tr>
                        <th style="width: 30px;"></th>
                        <th style="width: 150px;">GL Code</th>
                        <th>Display Name</th>
                        <th style="width: 80px; text-align: center;">Enabled</th>
                        <th style="width: 100px; text-align: center;">Placeholder</th>
                    </tr>
                </thead>
                <tbody id="hcr-sales-columns-tbody">
                    <?php foreach ($columns as $index => $column):
                        $is_placeholder = isset($column['is_placeholder']) && $column['is_placeholder'];
                    ?>
                    <tr data-index="<?php echo esc_attr($index); ?>" style="cursor: move;">
                        <td style="text-align: center; cursor: move;">
                            <span class="dashicons dashicons-menu" style="color: #999;"></span>
                            <input type="hidden"
                                   name="hcr_sales_breakdown_columns[<?php echo esc_attr($index); ?>][sort_order]"
                                   value="<?php echo esc_attr($column['sort_order']); ?>"
                                   class="sort-order-field" />
                        </td>
                        <td>
                            <input type="text"
                                   name="hcr_sales_breakdown_columns[<?php echo esc_attr($index); ?>][gl_code]"
                                   value="<?php echo esc_attr($column['gl_code']); ?>"
                                   class="regular-text gl-code-field"
                                   <?php echo $is_placeholder ? '' : 'readonly'; ?> />
                        </td>
                        <td>
                            <input type="text"
                                   name="hcr_sales_breakdown_columns[<?php echo esc_attr($index); ?>][display_name]"
                                   value="<?php echo esc_attr($column['display_name']); ?>"
                                   class="regular-text" />
                        </td>
                        <td style="text-align: center;">
                            <input type="checkbox"
                                   name="hcr_sales_breakdown_columns[<?php echo esc_attr($index); ?>][enabled]"
                                   value="1"
                                   <?php checked($column['enabled'], true); ?> />
                        </td>
                        <td style="text-align: center;">
                            <input type="checkbox"
                                   name="hcr_sales_breakdown_columns[<?php echo esc_attr($index); ?>][is_placeholder]"
                                   value="1"
                                   class="placeholder-checkbox"
                                   <?php checked($is_placeholder, true); ?> />
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 10px;">
                <button type="button" id="hcr-add-placeholder" class="button">+ Add Placeholder Column</button>
            </p>

            <p class="description">
                <strong>Drag and drop</strong> rows to reorder categories. Click "Refresh from Newbook" to detect new GL accounts.
                New accounts will be added as disabled. <strong>Placeholder columns</strong> show as blank spacers (£0.00) in reports - use these to align with your Excel template layout.
            </p>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle GL Code readonly based on placeholder checkbox
            $(document).on('change', '.placeholder-checkbox', function() {
                var $row = $(this).closest('tr');
                var $glCodeField = $row.find('.gl-code-field');
                if ($(this).is(':checked')) {
                    $glCodeField.prop('readonly', false).css('background-color', '#fff');
                } else {
                    $glCodeField.prop('readonly', true).css('background-color', '#f0f0f0');
                }
            });

            // Add placeholder column
            $('#hcr-add-placeholder').on('click', function() {
                var $tbody = $('#hcr-sales-columns-tbody');
                var nextIndex = $tbody.find('tr').length;
                var nextSortOrder = nextIndex + 1;

                var newRow = '<tr data-index="' + nextIndex + '" style="cursor: move;">' +
                    '<td style="text-align: center; cursor: move;">' +
                        '<span class="dashicons dashicons-menu" style="color: #999;"></span>' +
                        '<input type="hidden" name="hcr_sales_breakdown_columns[' + nextIndex + '][sort_order]" value="' + nextSortOrder + '" class="sort-order-field" />' +
                    '</td>' +
                    '<td>' +
                        '<input type="text" name="hcr_sales_breakdown_columns[' + nextIndex + '][gl_code]" value="SPACER' + nextIndex + '" class="regular-text gl-code-field" />' +
                    '</td>' +
                    '<td>' +
                        '<input type="text" name="hcr_sales_breakdown_columns[' + nextIndex + '][display_name]" value="Spacer ' + nextIndex + '" class="regular-text" />' +
                    '</td>' +
                    '<td style="text-align: center;">' +
                        '<input type="checkbox" name="hcr_sales_breakdown_columns[' + nextIndex + '][enabled]" value="1" checked />' +
                    '</td>' +
                    '<td style="text-align: center;">' +
                        '<input type="checkbox" name="hcr_sales_breakdown_columns[' + nextIndex + '][is_placeholder]" value="1" class="placeholder-checkbox" checked />' +
                    '</td>' +
                '</tr>';

                $tbody.append(newRow);

                // Re-initialize sortable
                $tbody.sortable('refresh');
            });

            // Refresh GL accounts button
            $('#hcr-refresh-gl-accounts').on('click', function() {
                var button = $(this);
                var status = $('#hcr-refresh-status');

                button.prop('disabled', true);
                status.html('<span style="color: #999;">Fetching GL accounts from Newbook...</span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hcr_refresh_gl_accounts',
                        nonce: '<?php echo wp_create_nonce('hcr_refresh_gl_accounts'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">✗ Failed to connect to server</span>');
                        button.prop('disabled', false);
                    }
                });
            });

            // Make table sortable with drag and drop
            $('#hcr-sales-columns-tbody').sortable({
                handle: 'td:first-child',
                placeholder: 'ui-state-highlight',
                helper: function(e, tr) {
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function(index) {
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                stop: function(event, ui) {
                    // Update sort order values after drag
                    $('#hcr-sales-columns-tbody tr').each(function(index) {
                        $(this).find('.sort-order-field').val(index + 1);
                    });
                }
            });
        });
        </script>
        <style>
        #hcr-sales-columns-tbody .ui-state-highlight {
            height: 50px;
            background-color: #ffffcc;
        }
        #hcr-sales-columns-tbody tr:hover {
            background-color: #f0f0f0;
        }
        </style>
        <?php
    }

    /**
     * Get sales breakdown columns with defaults
     */
    private function get_sales_breakdown_columns() {
        $columns = get_option('hcr_sales_breakdown_columns', array());

        // If empty, set defaults
        if (empty($columns)) {
            $columns = array(
                array(
                    'gl_code' => 'ACCOMMODATION',
                    'display_name' => 'Accommodation',
                    'enabled' => true,
                    'sort_order' => 1
                ),
                array(
                    'gl_code' => 'FOOD',
                    'display_name' => 'Food',
                    'enabled' => true,
                    'sort_order' => 2
                ),
                array(
                    'gl_code' => 'BEVERAGE',
                    'display_name' => 'Beverage',
                    'enabled' => true,
                    'sort_order' => 3
                ),
                array(
                    'gl_code' => 'OTHER',
                    'display_name' => 'Other',
                    'enabled' => true,
                    'sort_order' => 4
                )
            );
            update_option('hcr_sales_breakdown_columns', $columns);
        }

        // Sort by sort_order
        usort($columns, function($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });

        return $columns;
    }

    /**
     * Sanitize sales breakdown columns
     */
    public function sanitize_sales_columns($input) {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();
        foreach ($input as $column) {
            $sanitized[] = array(
                'gl_code' => sanitize_text_field($column['gl_code']),
                'display_name' => sanitize_text_field($column['display_name']),
                'enabled' => isset($column['enabled']) ? true : false,
                'sort_order' => intval($column['sort_order'])
            );
        }

        return $sanitized;
    }

    // Page display methods
    public function display_cash_up_form() {
        if (!current_user_can('edit_posts')) {
            wp_die('Access denied');
        }
        include_once HCR_PLUGIN_DIR . 'admin/views/cash-up-form.php';
    }

    public function display_history_page() {
        if (!current_user_can('edit_posts')) {
            wp_die('Access denied');
        }
        include_once HCR_PLUGIN_DIR . 'admin/views/cash-up-history.php';
    }

    public function display_multi_day_report() {
        if (!current_user_can('edit_posts')) {
            wp_die('Access denied');
        }
        include_once HCR_PLUGIN_DIR . 'admin/views/multi-day-report.php';
    }

    public function display_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        include_once HCR_PLUGIN_DIR . 'admin/views/admin-settings.php';
    }
}
