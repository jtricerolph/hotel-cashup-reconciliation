<?php
/**
 * Daily Cash Up Form View
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

$denominations = json_decode(get_option('hcr_denominations'), true);

// Check if date parameter is provided (from history edit link)
$default_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
$auto_load = isset($_GET['date']) ? 'true' : 'false';
?>

<div class="wrap hcr-cash-up-page">
    <h1>Daily Cash Up</h1>

    <!-- Date Selection Section -->
    <div id="hcr-date-selector" style="background: #fff; padding: 12px 15px; border: 1px solid #ccd0d4; border-radius: 3px; margin: 10px 0;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 0;">
            <label for="session_date" style="margin: 0; font-weight: 600;">Session Date:</label>
            <input type="date" id="session_date" name="session_date" value="<?php echo esc_attr($default_date); ?>" max="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required style="margin: 0;">
            <button type="button" id="hcr-check-date" class="button button-primary">Check Date</button>
        </div>
        <div id="hcr-date-actions" style="display:none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #dcdcde;">
            <p id="hcr-date-status-message" style="margin: 0 0 8px 0;"></p>
            <div style="display: flex; gap: 8px;">
                <button type="button" id="hcr-load-draft" class="button button-secondary" style="display:none;">Load Draft</button>
                <button type="button" id="hcr-view-final" class="button button-secondary" style="display:none;">View Final Submission</button>
                <button type="button" id="hcr-create-new" class="button button-primary" style="display:none;">Create New Entry</button>
            </div>
        </div>
    </div>

    <!-- Current Session Date Header -->
    <div id="hcr-session-header" style="display:none; background: #f0f0f1; padding: 10px 15px; margin: 10px 0; border-left: 4px solid #2271b1;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">
            <h2 style="margin: 0; font-size: 18px;">Cash Up for: <span id="hcr-current-date-display"></span></h2>
            <span id="hcr-session-status"></span>
        </div>
    </div>

    <div id="hcr-message" style="display:none;" class="notice"></div>

    <form id="hcr-cash-up-form" style="display:none;">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('hcr_admin_nonce'); ?>">
        <input type="hidden" id="hcr-auto-load" value="<?php echo esc_attr($auto_load); ?>">

        <!-- Cash Counting Section -->
        <h2>Cash Counting</h2>

        <div class="hcr-counting-grid">
            <!-- Till Float Count Section -->
            <div class="hcr-counting-section">
                <h3>Till Float Count</h3>
                <p>Count the float/change in the till drawer.</p>

                <table class="wp-list-table widefat fixed striped hcr-denomination-table">
                    <thead>
                        <tr>
                            <th>Denomination</th>
                            <th>Quantity</th>
                            <th>OR</th>
                            <th>Value</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="float-denominations-body">
                        <?php if (isset($denominations['notes'])): ?>
                            <tr class="hcr-section-header"><td colspan="5"><strong>Notes</strong></td></tr>
                            <?php foreach ($denominations['notes'] as $value): ?>
                                <tr class="float-denomination-row" data-type="note" data-value="<?php echo esc_attr(number_format($value, 2, '.', '')); ?>">
                                    <td>£<?php echo number_format($value, 2); ?></td>
                                    <td><input type="number" class="float-denom-quantity" min="0" step="1" placeholder="0"></td>
                                    <td>or</td>
                                    <td><input type="number" class="float-denom-value" min="0" step="0.01" placeholder="0.00"></td>
                                    <td class="float-denom-total">£0.00</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (isset($denominations['coins'])): ?>
                            <tr class="hcr-section-header"><td colspan="5"><strong>Coins</strong></td></tr>
                            <?php foreach ($denominations['coins'] as $value): ?>
                                <tr class="float-denomination-row" data-type="coin" data-value="<?php echo esc_attr(number_format($value, 2, '.', '')); ?>">
                                    <td>£<?php echo number_format($value, 2); ?></td>
                                    <td><input type="number" class="float-denom-quantity" min="0" step="1" placeholder="0"></td>
                                    <td>or</td>
                                    <td><input type="number" class="float-denom-value" min="0" step="0.01" placeholder="0.00"></td>
                                    <td class="float-denom-total">£0.00</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="hcr-total-row">
                            <th colspan="4">TOTAL FLOAT</th>
                            <th id="total-float-counted">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-row">
                            <th colspan="4">EXPECTED FLOAT</th>
                            <th id="expected-float">£<?php echo number_format(floatval(get_option('hcr_expected_till_float', '300.00')), 2); ?></th>
                        </tr>
                        <tr class="hcr-variance-row">
                            <th colspan="4">VARIANCE</th>
                            <th id="float-variance" class="hcr-variance-value">£0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Cash Takings Banked Section -->
            <div class="hcr-counting-section">
                <h3>Cash Takings Banked</h3>
                <p>Count the cash takings to be banked (excluding float).</p>

                <table class="wp-list-table widefat fixed striped hcr-denomination-table">
                    <thead>
                        <tr>
                            <th>Denomination</th>
                            <th>Quantity</th>
                            <th>OR</th>
                            <th>Value</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="takings-denominations-body">
                        <?php if (isset($denominations['notes'])): ?>
                            <tr class="hcr-section-header"><td colspan="5"><strong>Notes</strong></td></tr>
                            <?php foreach ($denominations['notes'] as $value): ?>
                                <tr class="takings-denomination-row" data-type="note" data-value="<?php echo esc_attr(number_format($value, 2, '.', '')); ?>">
                                    <td>£<?php echo number_format($value, 2); ?></td>
                                    <td><input type="number" class="takings-denom-quantity" min="0" step="1" placeholder="0"></td>
                                    <td>or</td>
                                    <td><input type="number" class="takings-denom-value" min="0" step="0.01" placeholder="0.00"></td>
                                    <td class="takings-denom-total">£0.00</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (isset($denominations['coins'])): ?>
                            <tr class="hcr-section-header"><td colspan="5"><strong>Coins</strong></td></tr>
                            <?php foreach ($denominations['coins'] as $value): ?>
                                <tr class="takings-denomination-row" data-type="coin" data-value="<?php echo esc_attr(number_format($value, 2, '.', '')); ?>">
                                    <td>£<?php echo number_format($value, 2); ?></td>
                                    <td><input type="number" class="takings-denom-quantity" min="0" step="1" placeholder="0"></td>
                                    <td>or</td>
                                    <td><input type="number" class="takings-denom-value" min="0" step="0.01" placeholder="0.00"></td>
                                    <td class="takings-denom-total">£0.00</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="hcr-total-row">
                            <th colspan="4">TOTAL CASH TAKINGS</th>
                            <th id="total-cash-counted">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-row" id="cash-takings-newbook-row" style="display:none;">
                            <th colspan="4">NEWBOOK CASH EXPECTED</th>
                            <th id="newbook-cash-expected">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-row" id="cash-takings-variance-row" style="display:none;">
                            <th colspan="4">VARIANCE</th>
                            <th id="cash-takings-variance" class="hcr-variance-value">£0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Card Machines Section -->
        <h2>Card Machines</h2>
        <p>Enter total and Amex amounts for each machine. Visa/Mastercard will be calculated automatically.</p>

        <div class="hcr-card-machines">
            <div class="hcr-machine-section">
                <h3>Front Desk Machine</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Total:</label></th>
                        <td>£<input type="number" id="front_desk_total" class="machine-total" data-machine="front_desk" min="0" step="0.01" value="0.00"></td>
                    </tr>
                    <tr>
                        <th><label>Amex:</label></th>
                        <td>£<input type="number" id="front_desk_amex" class="machine-amex" data-machine="front_desk" min="0" step="0.01" value="0.00"></td>
                    </tr>
                    <tr>
                        <th><label>Visa/Mastercard:</label></th>
                        <td><strong>£<span id="front_desk_visa_mc" class="machine-visa-mc">0.00</span></strong> (calculated)</td>
                    </tr>
                </table>
            </div>

            <div class="hcr-machine-section">
                <h3>Restaurant/Bar Machine</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Total:</label></th>
                        <td>£<input type="number" id="restaurant_total" class="machine-total" data-machine="restaurant" min="0" step="0.01" value="0.00"></td>
                    </tr>
                    <tr>
                        <th><label>Amex:</label></th>
                        <td>£<input type="number" id="restaurant_amex" class="machine-amex" data-machine="restaurant" min="0" step="0.01" value="0.00"></td>
                    </tr>
                    <tr>
                        <th><label>Visa/Mastercard:</label></th>
                        <td><strong>£<span id="restaurant_visa_mc" class="machine-visa-mc">0.00</span></strong> (calculated)</td>
                    </tr>
                </table>
            </div>

            <div class="hcr-machine-section hcr-combined-totals">
                <h3>Combined PDQ Totals</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Total:</label></th>
                        <td><strong>£<span id="combined_total">0.00</span></strong></td>
                    </tr>
                    <tr>
                        <th><label>Amex:</label></th>
                        <td><strong>£<span id="combined_amex">0.00</span></strong></td>
                    </tr>
                    <tr>
                        <th><label>Visa/Mastercard:</label></th>
                        <td><strong>£<span id="combined_visa_mc">0.00</span></strong> (calculated)</td>
                    </tr>
                </table>
            </div>

            <div class="hcr-machine-section hcr-machine-photo-section">
                <h3>Card Machine Z-Report Photo</h3>
                <table class="form-table">
                    <tr>
                        <td colspan="2">
                            <div id="hcr-photo-upload-area" style="display: block;">
                                <input type="file" id="hcr-machine-photo-input" accept="image/*" capture="environment" style="display: none;">
                                <button type="button" id="hcr-upload-photo-btn" class="button button-secondary">
                                    <span class="dashicons dashicons-camera" style="margin-top: 3px;"></span> Upload Photo
                                </button>
                                <span id="hcr-photo-upload-status" style="margin-left: 10px;"></span>
                            </div>
                            <div id="hcr-photo-display-area" style="display: none; margin-top: 10px;">
                                <img id="hcr-machine-photo-preview" src="" alt="Card Machine Z-Report" style="max-width: 120px; cursor: pointer; border: 2px solid #ddd; border-radius: 4px;">
                                <button type="button" id="hcr-remove-photo-btn" class="button button-secondary" style="display: block; margin-top: 10px;">
                                    <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Remove Photo
                                </button>
                            </div>
                            <input type="hidden" id="hcr-machine-photo-id" value="">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Photo Lightbox Modal -->
        <div id="hcr-photo-lightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; cursor: pointer;">
            <span id="hcr-lightbox-close" style="position: absolute; top: 20px; right: 35px; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
            <img id="hcr-lightbox-image" src="" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%; border: 2px solid #fff;">
        </div>

        <!-- Newbook Data Section -->
        <h2>Newbook Payment Data</h2>
        <p style="display: flex; justify-content: space-between; align-items: center;">
            <span>
                <button type="button" id="hcr-fetch-payments" class="button button-secondary">Fetch Newbook Payments</button>
                <span id="hcr-fetch-status"></span>
            </span>
            <button type="button" id="hcr-show-till-payments" class="button button-secondary" style="display: none; position: relative;">Show Till Payments</button>
        </p>

        <!-- Till Payments Tooltip -->
        <div id="hcr-till-payments-tooltip" style="display: none; position: absolute; background: white; border: 2px solid #2271b1; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); z-index: 1000; min-width: 320px; max-width: 400px; border-radius: 4px;">
            <h4 style="margin-top: 0; color: #2271b1;">Till System Payments</h4>
            <div id="hcr-till-payments-content"></div>
            <p style="font-size: 11px; color: #666; margin-bottom: 0; margin-top: 10px; border-top: 1px solid #ddd; padding-top: 8px;">
                <em>Variance based on restaurant/bar machine total and payments described as "Ticket:". Use of other machines for bar/restaurant will cause variance.</em>
            </p>
        </div>

        <!-- Reconciliation Table -->
        <div id="hcr-reconciliation-section" style="display:none;">
            <h3 id="hcr-reconciliation-title">Reconciliation Summary</h3>
            <table class="wp-list-table widefat fixed striped hcr-reconciliation-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Banked</th>
                        <th>Reported</th>
                        <th>Variance</th>
                    </tr>
                </thead>
                <tbody id="hcr-reconciliation-body">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Transaction Breakdown Section -->
        <div id="hcr-transaction-breakdown-section" style="display:none; margin-top: 20px;">
            <h3 style="cursor: pointer; user-select: none;" id="hcr-breakdown-toggle">
                <span class="dashicons dashicons-arrow-right" id="hcr-breakdown-arrow"></span>
                Show Transaction Breakdown from Newbook
            </h3>
            <div id="hcr-breakdown-content" style="display:none;">
                <div style="display: flex; gap: 20px; margin-top: 15px;">
                    <!-- Reception Payments Table -->
                    <div style="flex: 1;">
                        <h4>Reception Payments</h4>
                        <table class="wp-list-table widefat fixed striped" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Time</th>
                                    <th style="width: 15%;">Type</th>
                                    <th style="width: 45%;">Details</th>
                                    <th style="width: 20%; text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="hcr-reception-breakdown">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Restaurant/Bar Payments Table -->
                    <div style="flex: 1;">
                        <h4>Restaurant/Bar Payments</h4>
                        <table class="wp-list-table widefat fixed striped" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Time</th>
                                    <th style="width: 15%;">Type</th>
                                    <th style="width: 45%;">Details</th>
                                    <th style="width: 20%; text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="hcr-restaurant-breakdown">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes Section -->
        <h2>Notes</h2>
        <p>
            <textarea id="cash_up_notes" name="notes" rows="4" class="large-text"></textarea>
        </p>

        <!-- Save Status Indicator -->
        <div id="hcr-save-status" class="hcr-save-status-box" style="display: none;"></div>

        <!-- Action Buttons -->
        <p class="submit">
            <button type="submit" id="hcr-save-draft" name="save_draft" class="button button-secondary">Save as Draft</button>
            <button type="submit" id="hcr-submit-final" name="submit_final" class="button button-primary">Submit Final</button>
        </p>
    </form>
</div>
