<?php
/**
 * Multi-Day Report View
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

$default_days = get_option('hcr_default_report_days', 7);
?>

<div class="wrap hcr-multi-day-report-page">
    <h1>Multi-Day Report</h1>

    <div id="hcr-report-message" style="display:none;" class="notice"></div>

    <!-- Report Parameters -->
    <div class="hcr-report-params" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
        <h2>Report Parameters</h2>
        <table class="form-table">
            <tr>
                <th><label for="report_start_date">Start Date:</label></th>
                <td><input type="date" id="report_start_date" value="<?php echo date('Y-m-d', strtotime('monday this week')); ?>" required></td>
            </tr>
            <tr>
                <th><label for="report_num_days">Number of Days:</label></th>
                <td>
                    <input type="number" id="report_num_days" value="<?php echo esc_attr($default_days); ?>" min="1" max="365" required>
                    <span class="description">(1-365 days)</span>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" id="hcr-generate-report" class="button button-primary">Generate Report</button>
            <button type="button" id="hcr-print-report" class="button button-secondary" style="display:none;">Print Report</button>
            <span id="hcr-generate-status"></span>
        </p>
    </div>

    <!-- Report Output -->
    <div id="hcr-report-output" style="display:none;">

        <!-- Table 1: Daily Cash Up & Reconciliation Summary -->
        <div class="hcr-report-section">
            <h2>Daily Cash Up & Reconciliation Summary</h2>
            <p class="description">Days in ascending order. Copy and paste into Excel.
                <strong>Excel-like selection:</strong> Click cell, then Shift+Click to select range. Drag to select multiple cells. Ctrl+C to copy.</p>
            <p class="description" style="margin-top: 10px;">
                <strong>BANKED column colors:</strong>
                <span style="background: #e8f5e9; padding: 2px 8px; margin-left: 5px;">Green = Manual entry</span>
                <span style="background: #fff3e0; padding: 2px 8px; margin-left: 5px;">Orange = Auto-populated</span>
                <span style="background: #ffcccc; padding: 2px 8px; margin-left: 5px;">Red = Short (▼)</span>
                <span style="background: #ffe6b3; padding: 2px 8px; margin-left: 5px;">Amber = Over (▲)</span>
            </p>
            <p class="description" style="margin-top: 5px;">
                <strong>Note:</strong> The "AUDIT" row in the REPORTED table shows Daily Audit Summary values with variance against transaction flow values.
                V/MC and Amex columns span gateway and manual (PDQ) as the audit combines both methods.
            </p>

            <div class="hcr-tables-wrapper">
                <!-- BANKED Table -->
                <table class="hcr-report-table hcr-table-half" id="hcr-table-banked">
                    <thead>
                        <tr class="hcr-header-row-1">
                            <th colspan="8" style="background: #e8f5e9; border-bottom: 1px solid #999;">BANKED</th>
                        </tr>
                        <tr class="hcr-header-row-2">
                            <th style="background: #e8f5e9;">Date</th>
                            <th style="background: #e8f5e9;">Cash</th>
                            <th style="background: #fff3e0;">Gateway V/MC</th>
                            <th style="background: #e8f5e9;">PDQ V/MC</th>
                            <th style="background: #fff3e0;">Gateway Amex</th>
                            <th style="background: #e8f5e9;">PDQ Amex</th>
                            <th style="background: #fff3e0;">BACS</th>
                            <th style="background: #e8f5e9;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="hcr-banked-data">
                        <!-- Populated by JavaScript -->
                    </tbody>
                    <tfoot>
                        <tr class="hcr-totals-row">
                            <th>TOTALS</th>
                            <th id="total-banked-cash">£0.00</th>
                            <th id="total-banked-gateway-vmc" style="background: #ffedc9;">£0.00</th>
                            <th id="total-banked-pdq-vmc">£0.00</th>
                            <th id="total-banked-gateway-amex" style="background: #ffedc9;">£0.00</th>
                            <th id="total-banked-pdq-amex">£0.00</th>
                            <th id="total-banked-bacs" style="background: #ffedc9;">£0.00</th>
                            <th id="total-banked-all">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-totals-row">
                            <th>VARIANCE</th>
                            <th id="variance-total-cash">£0.00</th>
                            <th id="variance-total-gateway-vmc" style="background: #ffedc9;">£0.00</th>
                            <th id="variance-total-pdq-vmc">£0.00</th>
                            <th id="variance-total-gateway-amex" style="background: #ffedc9;">£0.00</th>
                            <th id="variance-total-pdq-amex">£0.00</th>
                            <th id="variance-total-bacs" style="background: #ffedc9;">£0.00</th>
                            <th id="variance-total-all">£0.00</th>
                        </tr>
                    </tfoot>
                </table>

                <!-- REPORTED Table -->
                <table class="hcr-report-table hcr-table-half" id="hcr-table-reported">
                    <thead>
                        <tr class="hcr-header-row-1">
                            <th colspan="9" style="background: #fff3e0; border-bottom: 1px solid #999;">REPORTED</th>
                        </tr>
                        <tr class="hcr-header-row-2">
                            <th style="background: #fff3e0;">Date</th>
                            <th style="background: #fff3e0;">Cash</th>
                            <th style="background: #fff3e0;">Gateway V/MC</th>
                            <th style="background: #fff3e0;">PDQ V/MC</th>
                            <th style="background: #fff3e0;">Gateway Amex</th>
                            <th style="background: #fff3e0;">PDQ Amex</th>
                            <th style="background: #fff3e0;">BACS</th>
                            <th style="background: #fff3e0;">Total</th>
                            <th style="background: #fff3e0;">Audit</th>
                        </tr>
                    </thead>
                    <tbody id="hcr-reported-data">
                        <!-- Populated by JavaScript -->
                    </tbody>
                    <tfoot>
                        <tr class="hcr-totals-row">
                            <th>TOTALS</th>
                            <th id="total-reported-cash">£0.00</th>
                            <th id="total-reported-gateway-vmc">£0.00</th>
                            <th id="total-reported-pdq-vmc">£0.00</th>
                            <th id="total-reported-gateway-amex">£0.00</th>
                            <th id="total-reported-pdq-amex">£0.00</th>
                            <th id="total-reported-bacs">£0.00</th>
                            <th id="total-reported-all">£0.00</th>
                            <th id="total-reported-audit">£0.00</th>
                        </tr>
                        <tr class="hcr-audit-verification-row">
                            <th>AUDIT</th>
                            <th id="audit-verify-cash">£0.00</th>
                            <th id="audit-verify-vmc" colspan="2">£0.00</th>
                            <th id="audit-verify-amex" colspan="2">£0.00</th>
                            <th id="audit-verify-bacs">£0.00</th>
                            <th id="audit-verify-total">£0.00</th>
                            <th id="audit-verify-audit-total">£0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Gross Sales Table -->
            <div style="margin-top: 20px;">
                <h3>Gross Sales (from Daily Audit)</h3>
                <table class="hcr-report-table" id="hcr-table-gross-sales" style="width: 50%;">
                    <thead>
                        <tr>
                            <th style="background: #f1f1f1;">Date</th>
                            <th style="background: #f1f1f1;">Gross Sales</th>
                        </tr>
                    </thead>
                    <tbody id="hcr-gross-sales-data">
                        <!-- Populated by JavaScript -->
                    </tbody>
                    <tfoot>
                        <tr class="hcr-totals-row">
                            <th>TOTAL</th>
                            <th id="total-gross-sales">£0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Table 2: Sales Breakdown (Net Values) -->
        <div class="hcr-report-section">
            <h2>Sales Breakdown (Net Values)</h2>
            <p class="description">Dates on Y-axis, GL categories on X-axis. Copy and paste into Excel.</p>
            <p class="description" style="margin-top: 5px;">
                <strong>Excel-like selection:</strong> Click cell, then Shift+Click to select a range vertically or horizontally.
                Drag mouse to select multiple cells. Press Ctrl+C to copy selected cells. Double-click to edit cell text.
            </p>
            <p class="description" style="margin-top: 5px;">
                <strong>Note:</strong> Column sorting and visibility can be customised in the <a href="<?php echo admin_url('admin.php?page=hcr-settings'); ?>">plugin settings</a>.
            </p>

            <table class="hcr-report-table" id="hcr-table-sales">
                <thead>
                    <tr id="hcr-sales-header">
                        <th>Date</th>
                        <!-- GL group category columns populated by JavaScript -->
                    </tr>
                </thead>
                <tbody id="hcr-sales-data">
                    <!-- Date rows populated by JavaScript -->
                </tbody>
                <tfoot>
                    <tr id="hcr-sales-totals" class="hcr-totals-row">
                        <th>TOTAL</th>
                        <!-- Category totals populated by JavaScript -->
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Table 3: Occupancy Statistics -->
        <div class="hcr-report-section">
            <h2>Occupancy Statistics</h2>
            <p class="description">Days on X-axis, metrics on Y-axis. Copy and paste into Excel.</p>

            <table class="hcr-report-table" id="hcr-table-occupancy">
                <thead>
                    <tr id="hcr-occupancy-header">
                        <th>Metric</th>
                        <!-- Date columns populated by JavaScript -->
                    </tr>
                </thead>
                <tbody id="hcr-occupancy-data">
                    <!-- Populated by JavaScript -->
                </tbody>
                <tfoot>
                    <tr id="hcr-occupancy-averages" class="hcr-totals-row">
                        <th>TOTAL (AVERAGE)</th>
                        <!-- Totals and averages populated by JavaScript -->
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Table 4: Creditors and Debtors -->
        <div class="hcr-report-section">
            <h2>Creditors and Debtors</h2>
            <p class="description">Daily account balances showing creditors (we owe), debtors (they owe us), and overall balance.</p>

            <table class="hcr-report-table" id="hcr-table-balances">
                <thead>
                    <tr id="hcr-balances-header">
                        <th>Date</th>
                        <th>Creditors</th>
                        <th>Debtors</th>
                        <th>Overall Balance</th>
                    </tr>
                </thead>
                <tbody id="hcr-balances-data">
                    <!-- Date rows populated by JavaScript -->
                </tbody>
                <tfoot>
                    <tr id="hcr-balances-totals" class="hcr-totals-row">
                        <th>Period Close</th>
                        <!-- Period closing balance populated by JavaScript -->
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Copy/Paste Friendly Tables -->
        <div class="hcr-report-section">
            <h2>Copy/Paste Format (for Spreadsheet)</h2>
            <p class="description">2-row format per date with Gateway Amex on second row. Select all and copy with Ctrl+C.</p>

            <!-- Reconciliation Copy/Paste Table -->
            <h3>Cash Up & Reconciliation</h3>
            <table class="hcr-report-table" id="hcr-table-copypaste" style="width: 100%; margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th colspan="7" style="background: #e8f5e9; border-bottom: 1px solid #999;">BANKED</th>
                        <th colspan="4" style="background: #fff3e0; border-bottom: 1px solid #999;">REPORTED</th>
                    </tr>
                    <tr>
                        <th style="background: #f1f1f1;">Date</th>
                        <th style="background: #f1f1f1;">Cash</th>
                        <th style="background: #f1f1f1;">Gateway V/MC</th>
                        <th style="background: #f1f1f1;">PDQ V/MC</th>
                        <th style="background: #f1f1f1;">PDQ Amex</th>
                        <th style="background: #f1f1f1;">BACS</th>
                        <th style="background: #f1f1f1;"></th>
                        <th style="background: #f1f1f1;">Cash</th>
                        <th style="background: #f1f1f1;">Total V/MC</th>
                        <th style="background: #f1f1f1;">Total BACS</th>
                        <th style="background: #f1f1f1;">Total Amex</th>
                    </tr>
                </thead>
                <tbody id="hcr-copypaste-data">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>

            <!-- Gross Sales Copy/Paste Table -->
            <h3>Gross Sales</h3>
            <table class="hcr-report-table" id="hcr-table-copypaste-grosssales" style="width: 50%;">
                <thead>
                    <tr>
                        <th style="background: #f1f1f1;">Date</th>
                        <th style="background: #f1f1f1;"></th>
                        <th style="background: #f1f1f1;">Gross Sales</th>
                    </tr>
                </thead>
                <tbody id="hcr-copypaste-grosssales-data">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>

    </div>

    <!-- Custom tooltip -->
    <div id="hcr-tooltip" class="hcr-custom-tooltip"></div>

    <!-- Selection Tooltip -->
    <div id="selection-tooltip" style="display: none; position: fixed; bottom: 20px; right: 20px; background: #f9f9f9; border: 2px solid #0078d4; padding: 10px 15px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 10000; font-size: 13px;">
        <div id="tooltip-content"></div>
    </div>
</div>

<style>
    .hcr-tables-wrapper {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
    }

    .hcr-report-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        background: #fff;
    }

    .hcr-table-half {
        width: 49%;
        margin-bottom: 0;
    }

    .hcr-report-table th,
    .hcr-report-table td {
        border: 1px solid #ccc;
        padding: 8px;
        text-align: right;
        cursor: cell;
        user-select: text;
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
    }

    /* Improve selection highlighting for spreadsheet-like behavior */
    .hcr-report-table td::selection,
    .hcr-report-table th::selection {
        background: #b3d7ff;
        color: #000;
    }

    .hcr-report-table td::-moz-selection,
    .hcr-report-table th::-moz-selection {
        background: #b3d7ff;
        color: #000;
    }

    /* Add hover effect for better cell targeting */
    .hcr-report-table tbody td:hover {
        box-shadow: inset 0 0 0 1px #4a90e2;
    }

    /* Excel-like cell selection highlighting */
    .hcr-report-table td.hcr-cell-selected,
    .hcr-report-table th.hcr-cell-selected {
        background-color: #cce4ff !important;
        outline: 2px solid #0078d4;
        outline-offset: -2px;
    }

    .hcr-report-table td.hcr-cell-selected:first-child,
    .hcr-report-table th.hcr-cell-selected:first-child {
        outline-width: 2px 2px 2px 3px;
    }

    .hcr-variance-inline {
        display: block;
        font-size: 10px;
        font-weight: normal;
        margin-top: 2px;
    }

    .hcr-variance-totals-row th {
        font-weight: bold;
        border-top: 2px solid #666;
        font-size: 11px;
        white-space: nowrap;
        line-height: 1.2;
        padding: 6px 8px;
    }

    .hcr-audit-verification-row th {
        font-weight: bold;
        border-top: 2px solid #666;
        font-size: 11px;
        white-space: nowrap;
        line-height: 1.2;
        padding: 6px 8px;
    }

    #hcr-table-sales thead th {
        font-size: 11px;
        white-space: nowrap;
    }

    .hcr-variance-balanced {
        color: #155724;
        background-color: #d4edda !important;
    }

    .hcr-variance-over {
        color: #856404;
        background-color: #fff3cd !important;
    }

    .hcr-variance-short {
        color: #721c24;
        background-color: #f8d7da !important;
    }

    .hcr-report-table th {
        background: #f1f1f1;
        font-weight: bold;
        text-align: center;
        color: #333;
    }

    .hcr-report-table th:first-child,
    .hcr-report-table td:first-child {
        text-align: left;
    }

    .hcr-report-table .hcr-totals-row {
        background: #e0e0e0;
        font-weight: bold;
        color: #333;
    }

    .hcr-report-table .hcr-header-row-1 th {
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .hcr-report-table .hcr-header-row-2 th {
        font-size: 11px;
        font-weight: 600;
    }

    .hcr-variance-positive {
        color: green;
    }

    .hcr-variance-negative {
        color: red;
    }

    .hcr-variance-zero {
        color: #666;
    }

    .hcr-report-section {
        margin-bottom: 40px;
    }

    /* Period Open/Close row styling */
    .hcr-period-row {
        background: #f5f5f5 !important;
        font-weight: bold !important;
    }

    .hcr-period-row td,
    .hcr-period-row th {
        border-top: 2px solid #0078d4 !important;
        border-left: 2px solid #0078d4 !important;
        border-right: 2px solid #0078d4 !important;
        border-bottom: 2px solid #0078d4 !important;
        padding: 10px 8px !important;
    }

    /* Custom tooltip styling */
    .hcr-custom-tooltip {
        position: absolute;
        background: #2c3e50;
        color: #fff;
        padding: 12px 16px;
        border-radius: 6px;
        font-size: 13px;
        line-height: 1.6;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        pointer-events: none;
        white-space: pre-line;
        max-width: 350px;
        display: none;
    }

    .hcr-custom-tooltip::before {
        content: '';
        position: absolute;
        top: -6px;
        left: 20px;
        width: 0;
        height: 0;
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-bottom: 6px solid #2c3e50;
    }

    .hcr-custom-tooltip strong {
        color: #3498db;
        display: block;
        margin-top: 8px;
        margin-bottom: 4px;
    }

    .hcr-custom-tooltip strong:first-child {
        margin-top: 0;
    }

    @media print {
        .wrap h1, .hcr-report-params, .button {
            display: none;
        }

        .hcr-report-table {
            page-break-inside: avoid;
        }

        .hcr-report-section {
            page-break-after: always;
        }

        .hcr-report-section:last-child {
            page-break-after: auto;
        }
    }
</style>

<script>
jQuery(document).ready(function($) {
    var reportData = null;
    var glAccounts = null;
    var earnedRevenue = null;
    var occupancyData = null;
    var bookingsData = null;
    var sitesData = null;
    var balancesByDate = null;
    var periodOpenBalance = null;
    var salesColumns = [];
    var disabledColumns = [];

    // Generate Report
    $('#hcr-generate-report').on('click', function() {
        var $btn = $(this);
        var $status = $('#hcr-generate-status');
        var $message = $('#hcr-report-message');
        var $output = $('#hcr-report-output');

        var startDate = $('#report_start_date').val();
        var numDays = parseInt($('#report_num_days').val());

        if (!startDate || numDays < 1 || numDays > 365) {
            $message.removeClass('notice-success').addClass('notice-error')
                .html('<p>Please enter a valid date and number of days (1-365).</p>').show();
            return;
        }

        $btn.prop('disabled', true).text('Generating...');
        $status.html('<span class="spinner is-active"></span>');
        $message.hide();

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_generate_multi_day_report',
                nonce: hcrAdmin.nonce,
                start_date: startDate,
                num_days: numDays
            },
            success: function(response) {
                if (response.success) {
                    reportData = response.data.report_data;
                    glAccounts = response.data.gl_accounts || [];
                    earnedRevenue = response.data.earned_revenue || [];
                    occupancyData = response.data.occupancy_data || [];
                    bookingsData = response.data.bookings_data || [];
                    sitesData = response.data.sites_data || [];
                    balancesByDate = response.data.balances_by_date || {};
                    periodOpenBalance = response.data.period_open_balance || null;
                    salesColumns = response.data.sales_columns || [];
                    disabledColumns = response.data.disabled_columns || [];
                    renderReport(reportData);
                    $output.show();
                    $('#hcr-print-report').show();
                    $message.removeClass('notice-error').addClass('notice-success')
                        .html('<p>Report generated successfully!</p>').show();

                    // Check if debtors/creditors data needs to be loaded (lazy loading)
                    if (periodOpenBalance && periodOpenBalance.loading) {
                        fetchDebtorsCreditors(startDate, numDays);
                    }
                } else {
                    $message.removeClass('notice-success').addClass('notice-error')
                        .html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                $message.removeClass('notice-success').addClass('notice-error')
                    .html('<p>An error occurred. Please try again.</p>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('Generate Report');
                $status.html('');
            }
        });
    });

    // Print Report
    $('#hcr-print-report').on('click', function() {
        window.print();
    });

    // Fetch debtors/creditors data (lazy loaded)
    function fetchDebtorsCreditors(startDate, numDays) {
        var $message = $('#hcr-message');

        // Show loading indicator in the balances table
        $('.hcr-balance-loading').text('Loading debtors/creditors data...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hcr_fetch_debtors_creditors_data',
                start_date: startDate,
                num_days: numDays,
                nonce: hcrAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the global variables with the fetched data
                    balancesByDate = response.data.balances_by_date || {};
                    periodOpenBalance = response.data.period_open_balance || null;

                    // Re-render the balances table with the new data
                    renderBalancesTable(reportData);

                    // Reinitialize Excel-like selection for the updated table
                    setTimeout(function() {
                        enhanceTableSelection();
                        initializeCustomTooltips();
                    }, 100);

                    $message.removeClass('notice-error').addClass('notice-success')
                        .html('<p>Debtors/Creditors data loaded successfully!</p>').show();
                    setTimeout(function() {
                        $message.fadeOut();
                    }, 3000);
                } else {
                    $('.hcr-balance-loading').text('Error loading debtors/creditors data');
                    $message.removeClass('notice-success').addClass('notice-error')
                        .html('<p>Error loading debtors/creditors: ' + (response.data.message || 'Unknown error') + '</p>').show();
                }
            },
            error: function() {
                $('.hcr-balance-loading').text('Error loading debtors/creditors data');
                $message.removeClass('notice-success').addClass('notice-error')
                    .html('<p>Network error while loading debtors/creditors data.</p>').show();
            }
        });
    }

    // Render report tables
    function renderReport(data) {
        renderReconciliationTable(data);
        renderGrossSalesTable(data);
        renderCopyPasteTable(data);
        renderCopyPasteGrossSalesTable(data);
        renderSalesTable(data);
        renderOccupancyTable(data);
        renderBalancesTable(data);

        // Initialize Excel-like selection after all tables are rendered
        setTimeout(function() {
            enhanceTableSelection();
            initializeCustomTooltips();
        }, 100);
    }

    // Render Table 1: Reconciliation
    function renderReconciliationTable(data) {
        var bankedTbody = $('#hcr-banked-data');
        var reportedTbody = $('#hcr-reported-data');
        bankedTbody.empty();
        reportedTbody.empty();

        var totals = {
            banked_cash: 0, banked_gateway_vmc: 0, banked_gateway_amex: 0,
            banked_pdq_vmc: 0, banked_pdq_amex: 0, banked_bacs: 0, banked_all: 0,
            reported_cash: 0, reported_gateway_vmc: 0, reported_gateway_amex: 0,
            reported_pdq_vmc: 0, reported_pdq_amex: 0, reported_bacs: 0, reported_all: 0, reported_audit: 0,
            variance_cash: 0, variance_gateway_vmc: 0, variance_gateway_amex: 0,
            variance_pdq_vmc: 0, variance_pdq_amex: 0, variance_bacs: 0, variance_all: 0, variance_audit: 0,
            audit_cash: 0, audit_vmc: 0, audit_amex: 0, audit_bacs: 0, audit_total: 0
        };

        data.forEach(function(day) {
            // Get reconciliation data
            var recon = getReconciliationData(day.reconciliation);

            // Extract audit data
            var auditData = extractAuditCashIncome(day.audit_data || []);
            var dailyAuditTotal = auditData.cash + auditData.visa + auditData.mastercard + auditData.amex + auditData.bacs;

            // BANKED side values
            var bankedCash = recon.cash_banked || 0;
            var bankedGatewayVMC = recon.gateway_vmc_banked || 0;
            var bankedGatewayAmex = recon.gateway_amex_banked || 0;
            var bankedPdqVMC = recon.pdq_vmc_banked || 0;
            var bankedPdqAmex = recon.pdq_amex_banked || 0;
            var bankedBacs = recon.bacs_banked || 0;
            var bankedTotal = bankedCash + bankedGatewayVMC + bankedGatewayAmex + bankedPdqVMC + bankedPdqAmex + bankedBacs;

            // REPORTED side values
            var reportedCash = recon.cash_reported || 0;
            var reportedGatewayVMC = recon.gateway_vmc_reported || 0;
            var reportedGatewayAmex = recon.gateway_amex_reported || 0;
            var reportedPdqVMC = recon.pdq_vmc_reported || 0;
            var reportedPdqAmex = recon.pdq_amex_reported || 0;
            var reportedBacs = recon.bacs_reported || 0;
            var reportedTotal = reportedCash + reportedGatewayVMC + reportedGatewayAmex + reportedPdqVMC + reportedPdqAmex + reportedBacs;

            // Calculate variances (banked - reported)
            var varianceCash = bankedCash - reportedCash;
            var varianceGatewayVMC = bankedGatewayVMC - reportedGatewayVMC;
            var varianceGatewayAmex = bankedGatewayAmex - reportedGatewayAmex;
            var variancePdqVMC = bankedPdqVMC - reportedPdqVMC;
            var variancePdqAmex = bankedPdqAmex - reportedPdqAmex;
            var varianceBacs = bankedBacs - reportedBacs;
            var varianceTotal = bankedTotal - reportedTotal;

            // Build BANKED row with variance highlighting (reordered: Gateway V/MC, PDQ V/MC, Gateway Amex, PDQ Amex)
            var bankedRow = $('<tr></tr>');
            bankedRow.append('<td>' + formatDate(day.date) + '</td>');
            bankedRow.append(formatBankedCell(bankedCash, varianceCash, '#f1f8f4'));
            bankedRow.append(formatBankedCell(bankedGatewayVMC, varianceGatewayVMC, '#fffbf5'));
            bankedRow.append(formatBankedCell(bankedPdqVMC, variancePdqVMC, '#f1f8f4'));
            bankedRow.append(formatBankedCell(bankedGatewayAmex, varianceGatewayAmex, '#fffbf5'));
            bankedRow.append(formatBankedCell(bankedPdqAmex, variancePdqAmex, '#f1f8f4'));
            bankedRow.append(formatBankedCell(bankedBacs, varianceBacs, '#fffbf5'));
            bankedRow.append(formatBankedCell(bankedTotal, varianceTotal, '#f1f8f4', true));
            bankedTbody.append(bankedRow);

            // Calculate audit variance (audit - reported)
            var auditVariance = dailyAuditTotal - reportedTotal;

            // Build REPORTED row (reordered: Gateway V/MC, PDQ V/MC, Gateway Amex, PDQ Amex)
            var reportedRow = $('<tr></tr>');
            reportedRow.append('<td>' + formatDate(day.date) + '</td>');
            reportedRow.append('<td style="background: #fffbf5;">£' + formatMoney(reportedCash) + '</td>');
            reportedRow.append('<td style="background: #fffbf5;">£' + formatMoney(reportedGatewayVMC) + '</td>');
            reportedRow.append('<td style="background: #fffbf5;">£' + formatMoney(reportedPdqVMC) + '</td>');
            reportedRow.append('<td style="background: #fffbf5;">£' + formatMoney(reportedGatewayAmex) + '</td>');
            reportedRow.append('<td style="background: #fffbf5;">£' + formatMoney(reportedPdqAmex) + '</td>');
            reportedRow.append('<td style="background: #fffbf5;">£' + formatMoney(reportedBacs) + '</td>');
            reportedRow.append('<td style="background: #fffbf5; font-weight: bold;">£' + formatMoney(reportedTotal) + '</td>');
            reportedRow.append(formatAuditCell(dailyAuditTotal, auditVariance, true));
            reportedTbody.append(reportedRow);

            // Update totals
            totals.banked_cash += bankedCash;
            totals.banked_gateway_vmc += bankedGatewayVMC;
            totals.banked_gateway_amex += bankedGatewayAmex;
            totals.banked_pdq_vmc += bankedPdqVMC;
            totals.banked_pdq_amex += bankedPdqAmex;
            totals.banked_bacs += bankedBacs;
            totals.banked_all += bankedTotal;

            totals.reported_cash += reportedCash;
            totals.reported_gateway_vmc += reportedGatewayVMC;
            totals.reported_gateway_amex += reportedGatewayAmex;
            totals.reported_pdq_vmc += reportedPdqVMC;
            totals.reported_pdq_amex += reportedPdqAmex;
            totals.reported_bacs += reportedBacs;
            totals.reported_all += reportedTotal;

            // Accumulate variances
            totals.variance_cash += varianceCash;
            totals.variance_gateway_vmc += varianceGatewayVMC;
            totals.variance_gateway_amex += varianceGatewayAmex;
            totals.variance_pdq_vmc += variancePdqVMC;
            totals.variance_pdq_amex += variancePdqAmex;
            totals.variance_bacs += varianceBacs;
            totals.variance_all += varianceTotal;

            // Accumulate audit totals
            totals.reported_audit += dailyAuditTotal;
            totals.variance_audit += auditVariance;
            totals.audit_cash += auditData.cash;
            totals.audit_vmc += (auditData.visa + auditData.mastercard);
            totals.audit_amex += auditData.amex;
            totals.audit_bacs += auditData.bacs;
            totals.audit_total += dailyAuditTotal;
        });

        // Update footer totals
        $('#total-banked-cash').text('£' + formatMoney(totals.banked_cash));
        $('#total-banked-gateway-vmc').text('£' + formatMoney(totals.banked_gateway_vmc));
        $('#total-banked-gateway-amex').text('£' + formatMoney(totals.banked_gateway_amex));
        $('#total-banked-pdq-vmc').text('£' + formatMoney(totals.banked_pdq_vmc));
        $('#total-banked-pdq-amex').text('£' + formatMoney(totals.banked_pdq_amex));
        $('#total-banked-bacs').text('£' + formatMoney(totals.banked_bacs));
        $('#total-banked-all').text('£' + formatMoney(totals.banked_all));

        $('#total-reported-cash').text('£' + formatMoney(totals.reported_cash));
        $('#total-reported-gateway-vmc').text('£' + formatMoney(totals.reported_gateway_vmc));
        $('#total-reported-gateway-amex').text('£' + formatMoney(totals.reported_gateway_amex));
        $('#total-reported-pdq-vmc').text('£' + formatMoney(totals.reported_pdq_vmc));
        $('#total-reported-pdq-amex').text('£' + formatMoney(totals.reported_pdq_amex));
        $('#total-reported-bacs').text('£' + formatMoney(totals.reported_bacs));
        $('#total-reported-all').text('£' + formatMoney(totals.reported_all));

        // Update audit total in REPORTED table (with variance formatting)
        updateAuditTotalCell($('#total-reported-audit'), totals.reported_audit, totals.variance_audit);

        // Update variance totals with color coding
        updateVarianceTotalCell($('#variance-total-cash'), totals.variance_cash);
        updateVarianceTotalCell($('#variance-total-gateway-vmc'), totals.variance_gateway_vmc);
        updateVarianceTotalCell($('#variance-total-gateway-amex'), totals.variance_gateway_amex);
        updateVarianceTotalCell($('#variance-total-pdq-vmc'), totals.variance_pdq_vmc);
        updateVarianceTotalCell($('#variance-total-pdq-amex'), totals.variance_pdq_amex);
        updateVarianceTotalCell($('#variance-total-bacs'), totals.variance_bacs);
        updateVarianceTotalCell($('#variance-total-all'), totals.variance_all);

        // Update audit verification row (compare audit totals vs reported totals)
        updateAuditVerificationCell($('#audit-verify-cash'), totals.audit_cash, totals.reported_cash);
        updateAuditVerificationCell($('#audit-verify-vmc'), totals.audit_vmc, totals.reported_gateway_vmc + totals.reported_pdq_vmc);
        updateAuditVerificationCell($('#audit-verify-amex'), totals.audit_amex, totals.reported_gateway_amex + totals.reported_pdq_amex);
        updateAuditVerificationCell($('#audit-verify-bacs'), totals.audit_bacs, totals.reported_bacs);
        updateAuditVerificationCell($('#audit-verify-total'), totals.audit_total, totals.reported_all);
        updateAuditVerificationCell($('#audit-verify-audit-total'), totals.audit_total, totals.audit_total);
    }

    // Render Gross Sales Table
    function renderGrossSalesTable(data) {
        var tbody = $('#hcr-gross-sales-data');
        tbody.empty();

        var totalGrossSales = 0;

        data.forEach(function(day) {
            // Extract gross sales from audit data (sum of all accrual_income items)
            var grossSales = extractGrossSales(day.audit_data || []);
            totalGrossSales += grossSales;

            var row = $('<tr></tr>');
            row.append('<td>' + formatDate(day.date) + '</td>');
            row.append('<td>£' + formatMoney(grossSales) + '</td>');
            tbody.append(row);
        });

        // Update total
        $('#total-gross-sales').text('£' + formatMoney(totalGrossSales));
    }

    // Render Copy/Paste Friendly Table
    function renderCopyPasteTable(data) {
        var tbody = $('#hcr-copypaste-data');
        tbody.empty();

        data.forEach(function(day) {
            // Get reconciliation data
            var recon = getReconciliationData(day.reconciliation);

            // BANKED side values
            var bankedCash = recon.cash_banked || 0;
            var bankedGatewayVMC = recon.gateway_vmc_banked || 0;
            var bankedGatewayAmex = recon.gateway_amex_banked || 0;
            var bankedPdqVMC = recon.pdq_vmc_banked || 0;
            var bankedPdqAmex = recon.pdq_amex_banked || 0;
            var bankedBacs = recon.bacs_banked || 0;
            var bankedTotal = bankedCash + bankedGatewayVMC + bankedGatewayAmex + bankedPdqVMC + bankedPdqAmex + bankedBacs;

            // REPORTED side values
            var reportedCash = recon.cash_reported || 0;
            var reportedGatewayVMC = recon.gateway_vmc_reported || 0;
            var reportedGatewayAmex = recon.gateway_amex_reported || 0;
            var reportedPdqVMC = recon.pdq_vmc_reported || 0;
            var reportedPdqAmex = recon.pdq_amex_reported || 0;
            var reportedBacs = recon.bacs_reported || 0;
            var reportedTotal = reportedCash + reportedGatewayVMC + reportedGatewayAmex + reportedPdqVMC + reportedPdqAmex + reportedBacs;

            // Calculate totals for reported columns
            var totalVMC = reportedGatewayVMC + reportedPdqVMC;
            var totalAmex = reportedGatewayAmex + reportedPdqAmex;

            // Row 1: Date, BANKED values (with Cash first, except Gateway Amex), blank column, then REPORTED values (with Cash first)
            var row1 = $('<tr></tr>');
            row1.append('<td>' + formatDate(day.date) + '</td>');
            row1.append('<td>£' + formatMoney(bankedCash) + '</td>');
            row1.append('<td>£' + formatMoney(bankedGatewayVMC) + '</td>');
            row1.append('<td>£' + formatMoney(bankedPdqVMC) + '</td>');
            row1.append('<td>£' + formatMoney(bankedPdqAmex) + '</td>');
            row1.append('<td>£' + formatMoney(bankedBacs) + '</td>');
            row1.append('<td></td>'); // Blank spacing column
            row1.append('<td>£' + formatMoney(reportedCash) + '</td>');
            row1.append('<td>£' + formatMoney(totalVMC) + '</td>');
            row1.append('<td>£' + formatMoney(reportedBacs) + '</td>');
            row1.append('<td>£' + formatMoney(totalAmex) + '</td>');
            tbody.append(row1);

            // Row 2: Gateway Amex on second row (third column of BANKED), rest blank
            var row2 = $('<tr style="background: #fafafa;"></tr>');
            row2.append('<td></td>'); // Empty date cell
            row2.append('<td></td>'); // Empty Cash (banked)
            row2.append('<td>£' + formatMoney(bankedGatewayAmex) + '</td>'); // Gateway Amex
            row2.append('<td></td>'); // Empty PDQ V/MC
            row2.append('<td></td>'); // Empty PDQ Amex
            row2.append('<td></td>'); // Empty BACS
            row2.append('<td></td>'); // Blank spacing column
            row2.append('<td></td>'); // Empty Cash (reported)
            row2.append('<td></td>'); // Empty Total V/MC
            row2.append('<td></td>'); // Empty Total BACS
            row2.append('<td></td>'); // Empty Total Amex
            tbody.append(row2);
        });
    }

    // Render Copy/Paste Gross Sales Table (double-row format)
    function renderCopyPasteGrossSalesTable(data) {
        var tbody = $('#hcr-copypaste-grosssales-data');
        tbody.empty();

        data.forEach(function(day) {
            // Get gross sales for this day
            var grossSales = extractGrossSales(day.audit_data || []);

            // Row 1: Date, blank, Gross Sales value
            var row1 = $('<tr></tr>');
            row1.append('<td>' + formatDate(day.date) + '</td>');
            row1.append('<td></td>'); // Blank column
            row1.append('<td>£' + formatMoney(grossSales) + '</td>');
            tbody.append(row1);

            // Row 2: All blank cells for double-row spacing
            var row2 = $('<tr style="background: #fafafa;"></tr>');
            row2.append('<td></td>'); // Empty date
            row2.append('<td></td>'); // Empty blank column
            row2.append('<td></td>'); // Empty gross sales
            tbody.append(row2);
        });
    }

    // Helper function to update variance total cells with color coding
    function updateVarianceTotalCell($element, variance) {
        var sign = variance >= 0 ? '+' : '';
        var arrow = '';

        // Remove existing variance classes
        $element.removeClass('hcr-variance-short hcr-variance-over hcr-variance-balanced');

        if (Math.abs(variance) >= 0.005) {
            if (variance < 0) {
                $element.addClass('hcr-variance-short');
                arrow = '▼ ';
            } else {
                $element.addClass('hcr-variance-over');
                arrow = '▲ ';
            }
        } else {
            $element.addClass('hcr-variance-balanced');
        }

        $element.text(arrow + sign + '£' + formatMoney(Math.abs(variance)));
    }

    // Render Table 2: Sales Breakdown (with custom columns from settings)
    function renderSalesTable(data) {
        var header = $('#hcr-sales-header');
        var tbody = $('#hcr-sales-data');
        var footer = $('#hcr-sales-totals');

        // Clear existing
        header.find('th:not(:first)').remove();
        tbody.empty();
        footer.find('th:not(:first), td').remove(); // Remove both th and td elements

        // Use custom columns from settings (already sorted and filtered by backend)
        if (!salesColumns || salesColumns.length === 0) {
            tbody.append('<tr><td colspan="100%">No sales categories configured. Go to Settings to configure.</td></tr>');
            return;
        }

        // Build header with custom columns
        salesColumns.forEach(function(column) {
            var headerHtml = '<div style="font-weight: bold;">' + column.display_name + '</div>' +
                           '<div style="font-size: 0.85em; color: #666; font-weight: normal;">' + column.gl_code + '</div>';
            header.append('<th>' + headerHtml + '</th>');
        });
        header.append('<th>TOTAL NET</th>');
        header.append('<th>VAT</th>');
        header.append('<th>GROSS TOTAL</th>');
        header.append('<th>AUDIT</th>');

        // Track column totals
        var columnTotals = {};
        salesColumns.forEach(function(column) {
            columnTotals[column.gl_code] = 0;
        });

        var grandTotals = { net: 0, vat: 0, gross: 0, audit: 0 };
        var hasAuditMismatch = false;

        // Create rows for each date
        data.forEach(function(day) {
            var row = $('<tr></tr>');
            row.append('<td>' + formatDate(day.date) + '</td>');

            var salesBreakdown = day.sales_breakdown || [];
            var salesAudit = day.sales_audit || {};

            // Create lookup map for sales breakdown by gl_code
            var breakdownMap = {};
            salesBreakdown.forEach(function(item) {
                breakdownMap[item.gl_code] = item;
            });

            // Add columns in order
            salesColumns.forEach(function(column) {
                var item = breakdownMap[column.gl_code] || { net_amount: 0 };
                var netAmount = parseFloat(item.net_amount || 0);
                row.append('<td>£' + formatMoney(netAmount) + '</td>');
                columnTotals[column.gl_code] += netAmount;
            });

            // Add totals columns (from pre-calculated audit data)
            var displayedNet = parseFloat(salesAudit.displayed_net || 0);
            var displayedVat = parseFloat(salesAudit.displayed_vat || 0);
            var displayedGross = parseFloat(salesAudit.displayed_gross || 0);

            row.append('<td style="font-weight: bold;">£' + formatMoney(displayedNet) + '</td>');
            row.append('<td style="font-weight: bold;">£' + formatMoney(displayedVat) + '</td>');
            row.append('<td style="font-weight: bold;">£' + formatMoney(displayedGross) + '</td>');

            // AUDIT column - compare displayed gross vs audit gross sales
            var auditGrossSales = extractGrossSales(day.audit_data || []);
            var auditVariance = displayedGross - auditGrossSales;
            row.append(formatAuditCell(auditGrossSales, auditVariance, true));

            // Check for audit mismatch
            if (salesAudit.mismatch) {
                hasAuditMismatch = true;
            }

            tbody.append(row);

            // Accumulate grand totals
            grandTotals.net += displayedNet;
            grandTotals.vat += displayedVat;
            grandTotals.gross += displayedGross;
            grandTotals.audit += auditGrossSales;
        });

        // Add totals row
        salesColumns.forEach(function(column) {
            footer.append('<th>£' + formatMoney(columnTotals[column.gl_code]) + '</th>');
        });
        footer.append('<th>£' + formatMoney(grandTotals.net) + '</th>');
        footer.append('<th>£' + formatMoney(grandTotals.vat) + '</th>');
        footer.append('<th>£' + formatMoney(grandTotals.gross) + '</th>');

        var grandAuditVariance = grandTotals.gross - grandTotals.audit;
        var auditTotalCell = formatAuditCell(grandTotals.audit, grandAuditVariance, true);
        footer.append(auditTotalCell);

        // Remove any previously added warnings and messages
        $('#hcr-table-sales').siblings('.notice-warning').remove();
        $('#hcr-table-sales').siblings('[style*="border-left: 3px solid #999"]').remove();

        // Show audit warning if there's a mismatch (missing GL accounts)
        if (hasAuditMismatch) {
            var warningHtml = '<div class="notice notice-warning" style="margin-top: 10px; padding: 10px;">' +
                '<strong>⚠ Warning:</strong> Displayed totals do not match raw Newbook totals. ' +
                'This may indicate new GL accounts in Newbook that are not configured in Settings. ' +
                '<a href="admin.php?page=hotel-cash-up-reconciliation-settings">Configure Sales Categories</a>' +
                '</div>';
            $('#hcr-table-sales').after(warningHtml);
        }

        // Show disabled columns section if any
        if (disabledColumns && disabledColumns.length > 0) {
            var disabledHtml = '<div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #999;">' +
                '<strong>Disabled Categories (not shown in table):</strong><br>' +
                '<span style="color: #666;">';

            disabledColumns.forEach(function(column, index) {
                if (index > 0) disabledHtml += ', ';
                disabledHtml += column.display_name + ' (' + column.gl_code + ')';
            });

            disabledHtml += '</span><br>' +
                '<small>Enable these in <a href="admin.php?page=hotel-cash-up-reconciliation-settings">Settings</a> to include them in reports.</small>' +
                '</div>';

            $('#hcr-table-sales').after(disabledHtml);
        }
    }

    // Render Table 3: Occupancy
    function renderOccupancyTable(data) {
        var header = $('#hcr-occupancy-header');
        var tbody = $('#hcr-occupancy-data');
        var footer = $('#hcr-occupancy-averages');

        // Clear existing content
        header.find('th:not(:first)').remove();
        tbody.empty();
        footer.find('th:not(:first), td').remove(); // Remove both th and td elements

        // Add column headers
        header.append('<th>Rooms</th>');
        header.append('<th>Occupancy</th>');
        header.append('<th>Net Accom</th>');
        header.append('<th>Ave Net Accom</th>');
        header.append('<th>REVPAR</th>');
        header.append('<th>Occupancy %</th>');
        header.append('<th>GGR</th>');
        header.append('<th>Avg Lead Time</th>');

        // Build GL group mapping for accommodation revenue lookup
        var glGroupMap = {};
        if (glAccounts && Array.isArray(glAccounts)) {
            glAccounts.forEach(function(account) {
                var glGroupId = account.gl_group_id;
                var glGroupName = account.gl_group_name || '';

                // Use first occurrence of each group (they should all have same name)
                if (!glGroupMap[glGroupId]) {
                    glGroupMap[glGroupId] = {
                        group_name: glGroupName
                    };
                }
            });
        }

        // Build accommodation revenue by date from earnedRevenue
        // Note: earnedRevenue is now aggregated by gl_group_id (changed in PHP)
        var accomRevenueByDate = {};
        if (earnedRevenue && Array.isArray(earnedRevenue)) {
            earnedRevenue.forEach(function(item) {
                var period = item.period ? item.period.substring(0, 10) : '';
                var glGroupId = item.gl_group_id;
                var amountNet = parseFloat(item.earned_revenue_ex || 0);

                if (!period || !glGroupId) return;

                var glInfo = glGroupMap[glGroupId];
                if (!glInfo) return;

                var glGroupName = glInfo.group_name;

                // Check if this is accommodation revenue (ACC group or contains "Accommodation")
                if (glGroupName.indexOf('ACC') === 0 || glGroupName.toLowerCase().indexOf('accommodation') !== -1) {
                    if (!accomRevenueByDate[period]) {
                        accomRevenueByDate[period] = 0;
                    }
                    accomRevenueByDate[period] += amountNet;
                }
            });
        }

        // Calculate total rooms from sites data (excluding overflow)
        var totalRooms = 0;
        var roomCategoryCounts = {};

        if (sitesData && Array.isArray(sitesData)) {
            sitesData.forEach(function(site) {
                var categoryName = site.category_name || '';
                if (categoryName.toLowerCase().indexOf('overflow') === -1) {
                    totalRooms++;
                    roomCategoryCounts[site.category_id] = roomCategoryCounts[site.category_id] || {
                        name: categoryName,
                        count: 0
                    };
                    roomCategoryCounts[site.category_id].count++;
                }
            });
        }

        // Build category ID to name map from occupancy data
        var categoryNames = {};
        if (occupancyData && Array.isArray(occupancyData)) {
            occupancyData.forEach(function(category) {
                categoryNames[category.category_id] = category.category_name;
            });
        }

        // Process bookings to calculate daily statistics
        var dailyStats = {};

        if (bookingsData && Array.isArray(bookingsData)) {
            bookingsData.forEach(function(booking) {
                var arrival = new Date(booking.booking_arrival.substring(0, 10));
                var departure = new Date(booking.booking_departure.substring(0, 10));
                var categoryId = booking.category_id;
                var categoryName = booking.category_name;

                // Calculate lead time (days between placed and arrival)
                var leadTimeDays = 0;
                var leadTimeCategory = 'Unknown';
                if (booking.booking_placed) {
                    var placed = new Date(booking.booking_placed.substring(0, 10));
                    leadTimeDays = Math.floor((arrival - placed) / (1000 * 60 * 60 * 24));

                    // Categorize lead time (mutually exclusive categories)
                    if (leadTimeDays <= 1) {
                        leadTimeCategory = 'Walk In';
                    } else if (leadTimeDays <= 3) {
                        leadTimeCategory = 'Last Minute';
                    } else if (leadTimeDays <= 7) {
                        leadTimeCategory = 'Week';
                    } else if (leadTimeDays <= 14) {
                        leadTimeCategory = 'Fortnight';
                    } else if (leadTimeDays <= 31) {
                        leadTimeCategory = 'Month';
                    } else if (leadTimeDays <= 90) {
                        leadTimeCategory = '3 Months';
                    } else if (leadTimeDays <= 180) {
                        leadTimeCategory = '6 Months';
                    } else if (leadTimeDays <= 365) {
                        leadTimeCategory = '1 Year';
                    } else {
                        leadTimeCategory = 'Over 1 Year';
                    }
                }

                // For each day the booking stays (excluding departure day)
                var currentDate = new Date(arrival);
                while (currentDate < departure) {
                    var dateStr = currentDate.toISOString().substring(0, 10);

                    if (!dailyStats[dateStr]) {
                        dailyStats[dateStr] = {
                            totalAdults: 0,
                            totalChildren: 0,
                            totalInfants: 0,
                            totalPeople: 0,
                            roomCount: 0,
                            leadTimeArriving: {
                                totalDays: 0,
                                count: 0,
                                categories: {
                                    'Walk In': 0,
                                    'Last Minute': 0,
                                    'Week': 0,
                                    'Fortnight': 0,
                                    'Month': 0,
                                    '3 Months': 0,
                                    '6 Months': 0,
                                    '1 Year': 0,
                                    'Over 1 Year': 0,
                                    'Unknown': 0
                                }
                            },
                            leadTimeStaying: {
                                totalDays: 0,
                                count: 0,
                                categories: {
                                    'Walk In': 0,
                                    'Last Minute': 0,
                                    'Week': 0,
                                    'Fortnight': 0,
                                    'Month': 0,
                                    '3 Months': 0,
                                    '6 Months': 0,
                                    '1 Year': 0,
                                    'Over 1 Year': 0,
                                    'Unknown': 0
                                }
                            },
                            byCategory: {}
                        };
                    }

                    // Add occupancy counts
                    var adults = parseInt(booking.booking_adults) || 0;
                    var children = parseInt(booking.booking_children) || 0;
                    var infants = parseInt(booking.booking_infants) || 0;

                    dailyStats[dateStr].totalAdults += adults;
                    dailyStats[dateStr].totalChildren += children;
                    dailyStats[dateStr].totalInfants += infants;
                    dailyStats[dateStr].totalPeople += adults + children + infants;
                    dailyStats[dateStr].roomCount++;

                    // Add lead time data
                    // For arriving: only count on arrival date
                    if (dateStr === arrival.toISOString().substring(0, 10)) {
                        dailyStats[dateStr].leadTimeArriving.totalDays += leadTimeDays;
                        dailyStats[dateStr].leadTimeArriving.count++;
                        dailyStats[dateStr].leadTimeArriving.categories[leadTimeCategory]++;
                    }

                    // For staying: count on every date they're staying
                    dailyStats[dateStr].leadTimeStaying.totalDays += leadTimeDays;
                    dailyStats[dateStr].leadTimeStaying.count++;
                    dailyStats[dateStr].leadTimeStaying.categories[leadTimeCategory]++;

                    // Track by category
                    if (!dailyStats[dateStr].byCategory[categoryId]) {
                        dailyStats[dateStr].byCategory[categoryId] = {
                            name: categoryName,
                            adults: 0,
                            children: 0,
                            infants: 0,
                            totalPeople: 0,
                            roomCount: 0,
                            totalRate: 0
                        };
                    }

                    dailyStats[dateStr].byCategory[categoryId].adults += adults;
                    dailyStats[dateStr].byCategory[categoryId].children += children;
                    dailyStats[dateStr].byCategory[categoryId].infants += infants;
                    dailyStats[dateStr].byCategory[categoryId].totalPeople += adults + children + infants;
                    dailyStats[dateStr].byCategory[categoryId].roomCount++;

                    // Find rate for this specific date from tariffs_quoted
                    if (booking.tariffs_quoted && Array.isArray(booking.tariffs_quoted)) {
                        booking.tariffs_quoted.forEach(function(tariff) {
                            if (tariff.stay_date === dateStr) {
                                var rate = parseFloat(tariff.calculated_amount) || 0;
                                dailyStats[dateStr].byCategory[categoryId].totalRate += rate;
                            }
                        });
                    }

                    currentDate.setDate(currentDate.getDate() + 1);
                }
            });
        }

        // Build occupancy data by date from occupancy API
        var occupancyByDate = {};
        if (occupancyData && Array.isArray(occupancyData)) {
            occupancyData.forEach(function(category) {
                var categoryId = category.category_id;
                var categoryName = category.category_name;

                for (var date in category.occupancy) {
                    if (!occupancyByDate[date]) {
                        occupancyByDate[date] = {
                            totalOccupied: 0,
                            totalMaintenance: 0,
                            byCategory: {}
                        };
                    }

                    var dayData = category.occupancy[date];
                    occupancyByDate[date].totalOccupied += parseInt(dayData.occupied) || 0;
                    occupancyByDate[date].totalMaintenance += parseInt(dayData.maintenance) || 0;

                    occupancyByDate[date].byCategory[categoryId] = {
                        name: categoryName,
                        occupied: parseInt(dayData.occupied) || 0,
                        maintenance: parseInt(dayData.maintenance) || 0,
                        available: parseInt(dayData.available) || 0
                    };
                }
            });
        }

        // Build the table rows
        var totals = {
            rooms: 0,
            occupancy: 0,
            netAccom: 0,
            aveNetAccom: 0,
            revpar: 0,
            occupancyPct: 0,
            ggr: 0,
            leadTime: 0,
            count: 0
        };

        data.forEach(function(day) {
            var date = day.date;
            var row = $('<tr></tr>');
            row.append('<td>' + formatDate(date) + '</td>');

            // Rooms Occupied (from occupancy API)
            var roomsOccupied = occupancyByDate[date] ? occupancyByDate[date].totalOccupied : 0;
            var roomsMaintenance = occupancyByDate[date] ? occupancyByDate[date].totalMaintenance : 0;

            // Build tooltip for rooms
            var roomsTooltip = 'Occupied: ' + roomsOccupied + '\nMaintenance: ' + roomsMaintenance + '\n\nBy Category:';
            if (occupancyByDate[date]) {
                for (var catId in occupancyByDate[date].byCategory) {
                    var cat = occupancyByDate[date].byCategory[catId];
                    roomsTooltip += '\n' + cat.name + ': ' + cat.occupied + ' occupied';
                }
            }
            var roomsCell = $('<td class="hcr-has-tooltip">' + roomsOccupied + '</td>');
            roomsCell.attr('data-tooltip', roomsTooltip);
            row.append(roomsCell);

            // People Occupancy (from bookings data)
            var peopleOccupancy = dailyStats[date] ? dailyStats[date].totalPeople : 0;
            var adults = dailyStats[date] ? dailyStats[date].totalAdults : 0;
            var children = dailyStats[date] ? dailyStats[date].totalChildren : 0;
            var infants = dailyStats[date] ? dailyStats[date].totalInfants : 0;

            // Calculate average lead time (for arriving bookings - shown in column)
            var avgLeadTimeArriving = 0;
            if (dailyStats[date] && dailyStats[date].leadTimeArriving.count > 0) {
                avgLeadTimeArriving = Math.round(dailyStats[date].leadTimeArriving.totalDays / dailyStats[date].leadTimeArriving.count);
            }

            // Calculate average lead time (for all bookings staying)
            var avgLeadTimeStaying = 0;
            if (dailyStats[date] && dailyStats[date].leadTimeStaying.count > 0) {
                avgLeadTimeStaying = Math.round(dailyStats[date].leadTimeStaying.totalDays / dailyStats[date].leadTimeStaying.count);
            }

            // Build tooltip for occupancy
            var occupancyTooltip = 'Total People: ' + peopleOccupancy + '\nAdults: ' + adults + '\nChildren: ' + children + '\nInfants: ' + infants + '\n\nBy Category:';
            if (dailyStats[date]) {
                for (var catId in dailyStats[date].byCategory) {
                    var cat = dailyStats[date].byCategory[catId];
                    occupancyTooltip += '\n' + cat.name + ': ' + cat.totalPeople + ' people (' + cat.adults + 'A, ' + cat.children + 'C, ' + cat.infants + 'I)';
                }
            }

            var occupancyCell = $('<td class="hcr-has-tooltip">' + peopleOccupancy + '</td>');
            occupancyCell.attr('data-tooltip', occupancyTooltip);
            row.append(occupancyCell);

            // Net Accommodation Revenue (from earned revenue - ACC category)
            var netAccomRevenue = accomRevenueByDate[date] || 0;
            row.append('<td>£' + formatMoney(netAccomRevenue) + '</td>');

            // Average Net Accom (net revenue / rooms occupied)
            var aveNetAccom = roomsOccupied > 0 ? netAccomRevenue / roomsOccupied : 0;
            row.append('<td>£' + formatMoney(aveNetAccom) + '</td>');

            // REVPAR (net revenue / total rooms)
            var revpar = totalRooms > 0 ? netAccomRevenue / totalRooms : 0;
            row.append('<td>£' + formatMoney(revpar) + '</td>');

            // Occupancy % (occupied rooms / total rooms * 100)
            var occupancyPct = totalRooms > 0 ? (roomsOccupied / totalRooms * 100) : 0;

            // Build tooltip for occupancy % breakdown by category
            var occupancyPctTooltip = 'Overall Occupancy: ' + occupancyPct.toFixed(1) + '%\n(' + roomsOccupied + '/' + totalRooms + ' rooms)\n\nBy Category:';
            if (occupancyByDate[date]) {
                for (var catId in occupancyByDate[date].byCategory) {
                    var cat = occupancyByDate[date].byCategory[catId];
                    var catTotalRooms = roomCategoryCounts[catId] ? roomCategoryCounts[catId].count : 0;
                    var catOccupancyPct = catTotalRooms > 0 ? (cat.occupied / catTotalRooms * 100) : 0;
                    occupancyPctTooltip += '\n' + cat.name + ': ' + catOccupancyPct.toFixed(1) + '% (' + cat.occupied + '/' + catTotalRooms + ' rooms)';
                }
            }

            var occupancyPctCell = $('<td class="hcr-has-tooltip">' + occupancyPct.toFixed(1) + '%</td>');
            occupancyPctCell.attr('data-tooltip', occupancyPctTooltip);
            row.append(occupancyPctCell);

            // GGR (average quoted rate from bookings)
            var totalGuestRate = 0;
            var ggrRoomCount = 0;
            if (dailyStats[date]) {
                for (var catId in dailyStats[date].byCategory) {
                    totalGuestRate += dailyStats[date].byCategory[catId].totalRate;
                    ggrRoomCount += dailyStats[date].byCategory[catId].roomCount;
                }
            }
            var ggr = ggrRoomCount > 0 ? totalGuestRate / ggrRoomCount : 0;

            // Build tooltip for GGR
            var ggrTooltip = 'Average Guest Rate: £' + formatMoney(ggr) + '\n\nBy Category:';
            if (dailyStats[date]) {
                for (var catId in dailyStats[date].byCategory) {
                    var cat = dailyStats[date].byCategory[catId];
                    var catAvg = cat.roomCount > 0 ? cat.totalRate / cat.roomCount : 0;
                    ggrTooltip += '\n' + cat.name + ': £' + formatMoney(catAvg) + ' (' + cat.roomCount + ' rooms)';
                }
            }
            var ggrCell = $('<td class="hcr-has-tooltip">£' + formatMoney(ggr) + '</td>');
            ggrCell.attr('data-tooltip', ggrTooltip);
            row.append(ggrCell);

            // Add Average Lead Time column with breakdown tooltip
            var leadTimeTooltip = '';
            var categories = ['Walk In', 'Last Minute', 'Week', 'Fortnight', 'Month', '3 Months', '6 Months', '1 Year', 'Over 1 Year'];

            if (dailyStats[date]) {
                // Arriving bookings section
                leadTimeTooltip = 'ARRIVALS\nAverage Lead Time: ' + avgLeadTimeArriving + ' days';
                if (dailyStats[date].leadTimeArriving.count > 0) {
                    leadTimeTooltip += '\nTotal Arrivals: ' + dailyStats[date].leadTimeArriving.count + '\n\nBreakdown:';
                    categories.forEach(function(cat) {
                        var count = dailyStats[date].leadTimeArriving.categories[cat];
                        if (count > 0) {
                            leadTimeTooltip += '\n' + cat + ': ' + count + ' booking' + (count > 1 ? 's' : '');
                        }
                    });
                    if (dailyStats[date].leadTimeArriving.categories['Unknown'] > 0) {
                        leadTimeTooltip += '\nUnknown: ' + dailyStats[date].leadTimeArriving.categories['Unknown'] + ' booking(s)';
                    }
                } else {
                    leadTimeTooltip += '\nNo arrivals this day';
                }

                // Staying bookings section
                leadTimeTooltip += '\n\nSTAYING GUESTS\nAverage Lead Time: ' + avgLeadTimeStaying + ' days';
                if (dailyStats[date].leadTimeStaying.count > 0) {
                    leadTimeTooltip += '\nTotal Staying: ' + dailyStats[date].leadTimeStaying.count + '\n\nBreakdown:';
                    categories.forEach(function(cat) {
                        var count = dailyStats[date].leadTimeStaying.categories[cat];
                        if (count > 0) {
                            leadTimeTooltip += '\n' + cat + ': ' + count + ' booking' + (count > 1 ? 's' : '');
                        }
                    });
                    if (dailyStats[date].leadTimeStaying.categories['Unknown'] > 0) {
                        leadTimeTooltip += '\nUnknown: ' + dailyStats[date].leadTimeStaying.categories['Unknown'] + ' booking(s)';
                    }
                } else {
                    leadTimeTooltip += '\nNo staying bookings';
                }
            } else {
                leadTimeTooltip = 'No bookings with lead time data';
            }

            var leadTimeCell = $('<td class="hcr-has-tooltip">' + avgLeadTimeArriving + ' days</td>');
            leadTimeCell.attr('data-tooltip', leadTimeTooltip);
            row.append(leadTimeCell);

            tbody.append(row);

            // Accumulate totals
            totals.rooms += roomsOccupied;
            totals.occupancy += peopleOccupancy;
            totals.netAccom += netAccomRevenue;
            totals.revpar += revpar;
            totals.occupancyPct += occupancyPct;
            totals.ggr += ggr;
            totals.leadTime += avgLeadTimeArriving;
            totals.count++;
        });

        // Add totals row with averages in brackets
        var avgRooms = totals.count > 0 ? Math.round(totals.rooms / totals.count) : 0;
        var avgOccupancy = totals.count > 0 ? Math.round(totals.occupancy / totals.count) : 0;
        var avgNetAccom = totals.rooms > 0 ? totals.netAccom / totals.rooms : 0;
        var avgRevpar = totals.count > 0 ? totals.revpar / totals.count : 0;
        var avgOccupancyPct = totals.count > 0 ? totals.occupancyPct / totals.count : 0;
        var avgGgr = totals.count > 0 ? totals.ggr / totals.count : 0;
        var avgLeadTime = totals.count > 0 ? Math.round(totals.leadTime / totals.count) : 0;

        footer.append('<th>' + totals.rooms + ' (' + avgRooms + ')</th>');
        footer.append('<th>' + totals.occupancy + ' (' + avgOccupancy + ')</th>');
        footer.append('<th>£' + formatMoney(totals.netAccom) + '</th>');
        footer.append('<th>(£' + formatMoney(avgNetAccom) + ')</th>');
        footer.append('<th>(£' + formatMoney(avgRevpar) + ')</th>');
        footer.append('<th>(' + avgOccupancyPct.toFixed(1) + '%)</th>');
        footer.append('<th>(£' + formatMoney(avgGgr) + ')</th>');
        footer.append('<th>(' + avgLeadTime + ' days)</th>');
    }

    // Render Table 4: Creditors and Debtors
    function renderBalancesTable(data) {
        var tbody = $('#hcr-balances-data');
        var footer = $('#hcr-balances-totals');

        tbody.empty();
        footer.empty();
        footer.removeClass('hcr-period-row hcr-period-close-row');

        if (!balancesByDate || Object.keys(balancesByDate).length === 0) {
            tbody.append('<tr><td colspan="4">No balance data available</td></tr>');
            return;
        }

        var lastDayCreditors = 0;
        var lastDayDebtors = 0;
        var lastDayOverall = 0;

        // Render Period Open row (balance from day before start date)
        if (periodOpenBalance) {
            var openRow = $('<tr class="hcr-period-row hcr-period-open-row"></tr>');
            openRow.append('<td>Period Open</td>');

            // Check if data is still loading
            if (periodOpenBalance.loading) {
                openRow.append('<td colspan="3" class="hcr-balance-loading" style="text-align: center; font-style: italic; color: #666;">Loading...</td>');
            } else {
                var openCreditors = periodOpenBalance.creditors || 0;
                var openDebtors = periodOpenBalance.debtors || 0;
                var openOverall = periodOpenBalance.overall || 0;

                // Creditors displayed as negative (we owe this amount) - using £-X.XX format for Excel compatibility
                openRow.append('<td>£-' + formatMoney(Math.abs(openCreditors)) + '</td>');
                openRow.append('<td>£' + formatMoney(openDebtors) + '</td>');

                // Style overall balance based on positive/negative
                var openOverallCell = $('<td></td>');
                if (openOverall > 0) {
                    openOverallCell.text('£' + formatMoney(openOverall));
                    openOverallCell.css('color', '#155724'); // Green for positive
                } else if (openOverall < 0) {
                    // Use £-X.XX format for better Excel copy/paste compatibility
                    openOverallCell.text('£-' + formatMoney(Math.abs(openOverall)));
                    openOverallCell.css('color', '#721c24'); // Red for negative
                } else {
                    openOverallCell.text('£0.00');
                }
                openRow.append(openOverallCell);
            }

            tbody.append(openRow);
        }

        // Render data rows (one per date)
        data.forEach(function(day, index) {
            var date = day.date;
            var balanceData = balancesByDate[date];

            if (!balanceData) {
                return;
            }

            var row = $('<tr></tr>');
            row.append('<td>' + formatDate(date) + '</td>');

            // Check if data is still loading
            if (balanceData.loading) {
                row.append('<td colspan="3" class="hcr-balance-loading" style="text-align: center; font-style: italic; color: #666;">Loading...</td>');
            } else {
                var creditors = balanceData.creditors || 0;
                var debtors = balanceData.debtors || 0;
                var overall = balanceData.overall || 0;

                // Track the last day's values for period close
                if (index === data.length - 1) {
                    lastDayCreditors = creditors;
                    lastDayDebtors = debtors;
                    lastDayOverall = overall;
                }

                // Creditors displayed as negative (we owe this amount) - using £-X.XX format for Excel compatibility
                row.append('<td>£-' + formatMoney(Math.abs(creditors)) + '</td>');
                row.append('<td>£' + formatMoney(debtors) + '</td>');

                // Style overall balance based on positive/negative
                var overallCell = $('<td></td>');
                if (overall > 0) {
                    overallCell.text('£' + formatMoney(overall));
                    overallCell.css('color', '#155724'); // Green for positive
                } else if (overall < 0) {
                    // Use £-X.XX format for better Excel copy/paste compatibility
                    overallCell.text('£-' + formatMoney(Math.abs(overall)));
                    overallCell.css('color', '#721c24'); // Red for negative
                } else {
                    overallCell.text('£0.00');
                }
                row.append(overallCell);
            }

            tbody.append(row);
        });

        // Render period close row (last day's values)
        footer.addClass('hcr-period-row hcr-period-close-row');
        footer.html('<th>Period Close</th>');
        // Creditors displayed as negative (we owe this amount) - using £-X.XX format for Excel compatibility
        footer.append('<th>£-' + formatMoney(Math.abs(lastDayCreditors)) + '</th>');
        footer.append('<th>£' + formatMoney(lastDayDebtors) + '</th>');

        var closeOverallCell = $('<th></th>');
        if (lastDayOverall > 0) {
            closeOverallCell.text('£' + formatMoney(lastDayOverall));
            closeOverallCell.css('color', '#155724'); // Green for positive
        } else if (lastDayOverall < 0) {
            // Use £-X.XX format for better Excel copy/paste compatibility
            closeOverallCell.text('£-' + formatMoney(Math.abs(lastDayOverall)));
            closeOverallCell.css('color', '#721c24'); // Red for negative
        } else {
            closeOverallCell.text('£0.00');
        }
        footer.append(closeOverallCell);
    }

    // Helper functions
    function getReconciliationData(reconciliation) {
        var data = {
            cash_banked: 0, cash_reported: 0,
            pdq_vmc_banked: 0, pdq_vmc_reported: 0,
            pdq_amex_banked: 0, pdq_amex_reported: 0,
            gateway_vmc_banked: 0, gateway_vmc_reported: 0,
            gateway_amex_banked: 0, gateway_amex_reported: 0,
            bacs_banked: 0, bacs_reported: 0
        };

        if (!reconciliation) return data;

        reconciliation.forEach(function(item) {
            var category = item.category;
            var banked = parseFloat(item.banked_amount);
            var reported = parseFloat(item.reported_amount);

            if (category === 'cash') {
                data.cash_banked = banked;
                data.cash_reported = reported;
            } else if (category === 'pdq_visa_mc') {
                data.pdq_vmc_banked = banked;
                data.pdq_vmc_reported = reported;
            } else if (category === 'pdq_amex') {
                data.pdq_amex_banked = banked;
                data.pdq_amex_reported = reported;
            } else if (category === 'gateway_visa_mc') {
                data.gateway_vmc_banked = banked;
                data.gateway_vmc_reported = reported;
            } else if (category === 'gateway_amex') {
                data.gateway_amex_banked = banked;
                data.gateway_amex_reported = reported;
            } else if (category === 'bacs') {
                data.bacs_banked = banked;
                data.bacs_reported = reported;
            }
        });

        return data;
    }

    function getSalesBreakdownAmount(breakdown, category) {
        if (!breakdown) return 0;

        var item = breakdown.find(function(b) {
            return b.category === category;
        });

        return item ? parseFloat(item.net_amount) : 0;
    }

    function formatBankedCell(amount, variance, baseColor, isBold) {
        var bgColor = baseColor;
        var varianceHtml = '';
        var fontWeight = isBold ? 'font-weight: bold;' : '';

        // Determine background color and variance display based on variance
        // Use 0.005 threshold to match cash-up logic (anything that rounds to 1p or more)
        if (Math.abs(variance) >= 0.005) {
            if (variance < 0) {
                // Negative variance (banked < reported) - Red background
                bgColor = '#ffcccc';
                varianceHtml = '<span class="hcr-variance-inline">▼ £-' + formatMoney(Math.abs(variance)) + '</span>';
            } else {
                // Positive variance (banked > reported) - Amber background
                bgColor = '#ffe6b3';
                varianceHtml = '<span class="hcr-variance-inline">▲ £+' + formatMoney(variance) + '</span>';
            }
        }

        return '<td style="background: ' + bgColor + '; ' + fontWeight + '">£' + formatMoney(amount) + varianceHtml + '</td>';
    }

    function formatAuditCell(amount, variance, isBold) {
        var bgColor = '#d4edda'; // Green when balanced
        var varianceHtml = '';
        var fontWeight = isBold ? 'font-weight: bold;' : '';

        // Determine background color and variance display based on variance
        // Use 0.005 threshold to match cash-up logic (anything that rounds to 1p or more)
        if (Math.abs(variance) >= 0.005) {
            if (variance < 0) {
                // Negative variance (audit < reported) - Red background
                bgColor = '#ffcccc';
                varianceHtml = '<span class="hcr-variance-inline">▼ £-' + formatMoney(Math.abs(variance)) + '</span>';
            } else {
                // Positive variance (audit > reported) - Amber background
                bgColor = '#ffe6b3';
                varianceHtml = '<span class="hcr-variance-inline">▲ £+' + formatMoney(variance) + '</span>';
            }
        }

        return '<td style="background: ' + bgColor + '; ' + fontWeight + '">£' + formatMoney(amount) + varianceHtml + '</td>';
    }

    function formatDate(dateStr) {
        var date = new Date(dateStr);
        var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return days[date.getDay()] + ' ' + date.getDate() + '/' + (date.getMonth() + 1);
    }

    function formatMoney(amount) {
        return parseFloat(amount).toFixed(2);
    }

    function getVarianceClass(variance) {
        if (variance > 0) return 'hcr-variance-positive';
        if (variance < 0) return 'hcr-variance-negative';
        return 'hcr-variance-zero';
    }

    // Extract cash_income amounts from audit data
    function extractAuditCashIncome(auditData) {
        var result = {
            cash: 0,
            visa: 0,
            mastercard: 0,
            amex: 0,
            bacs: 0
        };

        if (!auditData || !Array.isArray(auditData)) {
            return result;
        }

        auditData.forEach(function(item) {
            if (item.report_type !== 'cash_income') {
                return;
            }

            // Amounts in audit data are negative (like transaction_flow)
            // Negate them to get positive revenue values
            var amount = -parseFloat(item.amount || 0);
            var paymentType = (item.payment_type || '').toLowerCase();

            if (paymentType === 'cash') {
                result.cash += amount;
            } else if (paymentType === 'visa') {
                result.visa += amount;
            } else if (paymentType === 'mastercard') {
                result.mastercard += amount;
            } else if (paymentType === 'amex') {
                result.amex += amount;
            } else if (paymentType === 'bacs' || paymentType === 'eft' || paymentType === 'bank transfer') {
                result.bacs += amount;
            }
        });

        return result;
    }

    // Extract gross sales from audit data (sum of all accrual_income items)
    function extractGrossSales(auditData) {
        var total = 0;

        if (!auditData || !Array.isArray(auditData)) {
            return total;
        }

        auditData.forEach(function(item) {
            if (item.report_type === 'accrual_income') {
                // Accrual income amounts are positive in the sample data
                // Just sum them directly
                total += parseFloat(item.amount || 0);
            }
        });

        return total;
    }

    // Update audit verification cell with variance styling (inline variance like banked cells)
    function updateAuditVerificationCell($element, auditAmount, reportedAmount) {
        var variance = auditAmount - reportedAmount;
        var varianceHtml = '';
        var bgColor = '#fffbf5';

        // Remove existing variance classes
        $element.removeClass('hcr-variance-short hcr-variance-over hcr-variance-balanced');

        if (Math.abs(variance) >= 0.005) {
            if (variance < 0) {
                $element.addClass('hcr-variance-short');
                bgColor = '#ffcccc';
                varianceHtml = '<span class="hcr-variance-inline">▼ £-' + formatMoney(Math.abs(variance)) + '</span>';
            } else {
                $element.addClass('hcr-variance-over');
                bgColor = '#ffe6b3';
                varianceHtml = '<span class="hcr-variance-inline">▲ £+' + formatMoney(variance) + '</span>';
            }
        } else {
            $element.addClass('hcr-variance-balanced');
            bgColor = '#d4edda';
        }

        $element.css('background-color', bgColor);
        $element.html('£' + formatMoney(auditAmount) + varianceHtml);
    }

    // Update audit total cell with variance styling (same as verification cells)
    function updateAuditTotalCell($element, auditAmount, variance) {
        var varianceHtml = '';
        var bgColor = '#ffedc9';

        // Remove existing variance classes
        $element.removeClass('hcr-variance-short hcr-variance-over hcr-variance-balanced');

        if (Math.abs(variance) >= 0.005) {
            if (variance < 0) {
                $element.addClass('hcr-variance-short');
                bgColor = '#ffcccc';
                varianceHtml = '<span class="hcr-variance-inline">▼ £-' + formatMoney(Math.abs(variance)) + '</span>';
            } else {
                $element.addClass('hcr-variance-over');
                bgColor = '#ffe6b3';
                varianceHtml = '<span class="hcr-variance-inline">▲ £+' + formatMoney(variance) + '</span>';
            }
        } else {
            $element.addClass('hcr-variance-balanced');
            bgColor = '#d4edda';
        }

        $element.css('background-color', bgColor);
        $element.html('£' + formatMoney(auditAmount) + varianceHtml);
    }

    // Excel-like spreadsheet cell selection system
    var selectedCells = [];
    var selectionAnchor = null;
    var isSelecting = false;

    function enhanceTableSelection() {
        // Remove previous handlers to avoid duplicates
        $('.hcr-report-table').off('mousedown mouseenter mouseup');
        $(document).off('copy.hcr keydown.hcr');

        // Mouse down starts selection
        $('.hcr-report-table').on('mousedown', 'td, th', function(e) {
            var $cell = $(this);

            if (e.shiftKey && selectionAnchor) {
                // Shift+click extends selection
                e.preventDefault();
                selectCellRange(selectionAnchor, this);
            } else {
                // Regular click starts new selection
                clearSelection();
                selectionAnchor = this;
                isSelecting = true;
                $cell.addClass('hcr-cell-selected');
                selectedCells = [this];
            }
        });

        // Mouse enter while selecting extends selection
        $('.hcr-report-table').on('mouseenter', 'td, th', function(e) {
            if (isSelecting && selectionAnchor) {
                selectCellRange(selectionAnchor, this);
            }
        });

        // Mouse up ends selection
        $(document).on('mouseup', function() {
            isSelecting = false;
        });

        // Double-click selects cell text for editing
        $('.hcr-report-table').on('dblclick', 'td, th', function() {
            var selection = window.getSelection();
            var range = document.createRange();
            range.selectNodeContents(this);
            selection.removeAllRanges();
            selection.addRange(range);
        });

        // Keyboard navigation with arrow keys
        $('.hcr-report-table').on('keydown', 'td, th', function(e) {
            var $cell = $(this);
            var $row = $cell.parent();
            var cellIndex = $cell.index();
            var $newCell = null;

            switch(e.which) {
                case 37: // Left arrow
                    $newCell = $cell.prev('td, th');
                    break;
                case 38: // Up arrow
                    var $prevRow = $row.prev('tr');
                    if ($prevRow.length) {
                        $newCell = $prevRow.children().eq(cellIndex);
                    }
                    break;
                case 39: // Right arrow
                    $newCell = $cell.next('td, th');
                    break;
                case 40: // Down arrow
                    var $nextRow = $row.next('tr');
                    if ($nextRow.length) {
                        $newCell = $nextRow.children().eq(cellIndex);
                    }
                    break;
                default:
                    return; // Allow other keys to work normally
            }

            if ($newCell && $newCell.length) {
                e.preventDefault();
                clearSelection();
                $newCell.focus();
                $newCell.addClass('hcr-cell-selected');
                selectedCells = [$newCell[0]];
                selectionAnchor = $newCell[0];
            }
        });

        // Make cells focusable
        $('.hcr-report-table td, .hcr-report-table th').attr('tabindex', '0');

        // Handle copy (Ctrl+C / Cmd+C)
        $(document).on('copy.hcr', function(e) {
            if (selectedCells.length > 0 && $(selectedCells[0]).closest('.hcr-report-table').length) {
                e.preventDefault();
                copySelectedCellsToClipboard(e.originalEvent);
            }
        });

        function selectCellRange(start, end) {
            clearSelection();

            var $table = $(start).closest('table');
            var $start = $(start);
            var $end = $(end);

            // Get all rows in the table (thead + tbody + tfoot)
            var $allRows = $table.find('tr');

            // Get row indices relative to ALL rows in the table, not just within section
            var startRow = $allRows.index($start.parent());
            var startCol = $start.index();
            var endRow = $allRows.index($end.parent());
            var endCol = $end.index();

            // Normalize so start is top-left
            var minRow = Math.min(startRow, endRow);
            var maxRow = Math.max(startRow, endRow);
            var minCol = Math.min(startCol, endCol);
            var maxCol = Math.max(startCol, endCol);

            // Select all cells in the range
            for (var row = minRow; row <= maxRow; row++) {
                var $row = $allRows.eq(row);
                var $cells = $row.children('td, th');

                for (var col = minCol; col <= maxCol; col++) {
                    var cell = $cells.eq(col)[0];
                    if (cell) {
                        $(cell).addClass('hcr-cell-selected');
                        selectedCells.push(cell);
                    }
                }
            }

            updateSelectionTooltip();
        }

        function clearSelection() {
            $('.hcr-cell-selected').removeClass('hcr-cell-selected');
            selectedCells = [];
            $('#selection-tooltip').hide();
        }

        function copySelectedCellsToClipboard(event) {
            if (selectedCells.length === 0) return;

            // Get table and all rows for proper row indexing
            var $table = $(selectedCells[0]).closest('table');
            var $allRows = $table.find('tr');

            // Group cells by row (using row index relative to entire table, not section)
            var cellsByRow = {};
            selectedCells.forEach(function(cell) {
                var $cell = $(cell);
                var rowIndex = $allRows.index($cell.parent());
                if (!cellsByRow[rowIndex]) {
                    cellsByRow[rowIndex] = [];
                }

                // Get cell text without variance indicators
                var cellText;
                if ($cell.find('.hcr-variance-inline').length > 0) {
                    // Clone cell, remove variance spans, get clean text
                    var $clone = $cell.clone();
                    $clone.find('.hcr-variance-inline').remove();
                    cellText = $clone.text().trim();
                } else {
                    // No variance, get text as normal
                    cellText = $cell.text().trim();
                }

                // Extract raw numeric value if cell contains a number
                // Remove currency symbols (£), commas, percentage signs, and other formatting
                // Keep negative signs and decimal points
                var numericMatch = cellText.match(/[-]?[\d,]+\.?\d*/);
                if (numericMatch) {
                    // Found a number - extract and clean it
                    var rawNumber = numericMatch[0].replace(/,/g, ''); // Remove commas
                    cellText = rawNumber;
                }
                // If no number found, keep the original text (for dates, text cells, etc.)

                cellsByRow[rowIndex].push({
                    col: $cell.index(),
                    text: cellText
                });
            });

            // Build clipboard data
            var rows = Object.keys(cellsByRow).sort(function(a, b) { return a - b; });
            var rowTexts = [];

            rows.forEach(function(rowIndex) {
                var cells = cellsByRow[rowIndex].sort(function(a, b) { return a.col - b.col; });
                var rowText = cells.map(function(c) { return c.text; }).join('\t');
                rowTexts.push(rowText);
            });

            // Join rows with newlines (no trailing newline)
            var clipboardText = rowTexts.join('\n');

            // Copy to clipboard
            if (event.clipboardData) {
                event.clipboardData.setData('text/plain', clipboardText);
            }
        }

        function updateSelectionTooltip() {
            if (selectedCells.length === 0) {
                $('#selection-tooltip').hide();
                return;
            }

            var count = selectedCells.length;
            var sum = 0;
            var numericCount = 0;

            selectedCells.forEach(function(cell) {
                var $cell = $(cell);
                var text = $cell.text().trim();

                // Remove variance indicators if present
                if ($cell.find('.hcr-variance-inline').length > 0) {
                    var $clone = $cell.clone();
                    $clone.find('.hcr-variance-inline').remove();
                    text = $clone.text().trim();
                }

                // Remove currency symbols, commas, percentage signs and parse
                var value = parseFloat(text.replace(/[£,%]/g, '').replace(/,/g, ''));
                if (!isNaN(value)) {
                    sum += value;
                    numericCount++;
                }
            });

            var tooltipText = '<strong>Selected:</strong> ' + count + ' cell' + (count !== 1 ? 's' : '');
            if (numericCount > 0) {
                tooltipText += ' | <strong>Sum:</strong> £' + sum.toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (numericCount > 1) {
                    var avg = sum / numericCount;
                    tooltipText += ' | <strong>Avg:</strong> £' + avg.toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            }

            $('#tooltip-content').html(tooltipText);
            $('#selection-tooltip').show();
        }
    }

    // Custom tooltip system
    function initializeCustomTooltips() {
        var $tooltip = $('#hcr-tooltip');

        // Remove previous handlers to avoid duplicates
        $('.hcr-has-tooltip').off('mouseenter.tooltip mouseleave.tooltip mousemove.tooltip');

        // Show tooltip on hover
        $('.hcr-has-tooltip').on('mouseenter.tooltip', function(e) {
            var $cell = $(this);
            var tooltipText = $cell.attr('data-tooltip');
            if (!tooltipText) return;

            // Format tooltip text with proper line breaks and styling
            var formattedText = tooltipText
                .split('\n\n')
                .map(function(section) {
                    var lines = section.split('\n');
                    if (lines.length > 0) {
                        // First line of each section is a header
                        return '<strong>' + lines[0] + '</strong>\n' + lines.slice(1).join('\n');
                    }
                    return section;
                })
                .join('\n\n');

            $tooltip.html(formattedText.replace(/\n/g, '<br>'));
            $tooltip.css('display', 'block');

            // Position tooltip relative to cell
            updateTooltipPosition($cell);
        });

        // Hide tooltip on mouse leave
        $('.hcr-has-tooltip').on('mouseleave.tooltip', function() {
            $tooltip.css('display', 'none');
        });

        function updateTooltipPosition($cell) {
            var cellOffset = $cell.offset();
            var cellWidth = $cell.outerWidth();
            var cellHeight = $cell.outerHeight();
            var tooltipWidth = $tooltip.outerWidth();
            var tooltipHeight = $tooltip.outerHeight();
            var windowWidth = $(window).width();
            var windowHeight = $(window).height();
            var scrollTop = $(window).scrollTop();
            var scrollLeft = $(window).scrollLeft();

            // Default: position below and centered on cell
            var left = cellOffset.left + (cellWidth / 2) - (tooltipWidth / 2);
            var top = cellOffset.top + cellHeight + 10;

            // Adjust horizontally if off screen
            if (left < scrollLeft + 10) {
                left = scrollLeft + 10;
            } else if (left + tooltipWidth > scrollLeft + windowWidth - 10) {
                left = scrollLeft + windowWidth - tooltipWidth - 10;
            }

            // If tooltip would go below viewport, position above cell instead
            if (top + tooltipHeight > scrollTop + windowHeight - 10) {
                top = cellOffset.top - tooltipHeight - 10;
            }

            // Update arrow position based on cell center
            var arrowLeft = cellOffset.left + (cellWidth / 2) - left - 6;
            $tooltip.find('::before').css('left', arrowLeft + 'px');

            $tooltip.css({
                left: left + 'px',
                top: top + 'px'
            });
        }
    }

});
</script>
