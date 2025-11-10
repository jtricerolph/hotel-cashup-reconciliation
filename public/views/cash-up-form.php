<?php
/**
 * Public Cash Up Form View (Shortcode)
 *
 * This is a simplified version of the admin form for front-end use
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

$denominations = json_decode(get_option('hcr_denominations'), true);
$preselected_date = isset($atts['date']) ? $atts['date'] : date('Y-m-d');
?>

<div class="hcr-public-cash-up-form">
    <h2>Daily Cash Up</h2>

    <!-- Date Selection Section -->
    <div id="hcr-public-date-selector" style="background: #fff; padding: 12px 15px; border: 1px solid #ccd0d4; border-radius: 3px; margin: 10px 0;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 0;">
            <label for="public_session_date" style="margin: 0; font-weight: 600;">Session Date:</label>
            <input type="date" id="public_session_date" name="session_date" value="<?php echo esc_attr($preselected_date); ?>" required style="margin: 0;">
            <button type="button" id="hcr-public-check-date" class="hcr-button-primary">Check Date</button>
        </div>
        <div id="hcr-public-date-actions" style="display:none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #dcdcde;">
            <p id="hcr-public-date-status-message" style="margin: 0 0 8px 0;"></p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" id="hcr-public-load-draft" class="hcr-button-secondary" style="display:none;">Load Draft</button>
                <button type="button" id="hcr-public-view-final" class="hcr-button-secondary" style="display:none;">View Final Submission</button>
                <button type="button" id="hcr-public-create-new" class="hcr-button-primary" style="display:none;">Create New Entry</button>
            </div>
        </div>
    </div>

    <!-- Current Session Date Header -->
    <div id="hcr-public-session-header" style="display:none; background: #f0f0f1; padding: 10px 15px; margin: 10px 0; border-left: 4px solid #2271b1;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
            <h3 style="margin: 0; font-size: 18px;">Cash Up for: <span id="hcr-public-current-date-display"></span></h3>
            <span id="hcr-public-session-status"></span>
        </div>
    </div>

    <div class="hcr-message" id="hcr-public-message" style="display:none;"></div>

    <form id="hcr-public-cash-up-form" style="display:none;">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('hcr_public_nonce'); ?>">

        <div class="hcr-counting-grid">
            <!-- Till Float Count -->
            <div class="hcr-counting-section">
                <h3 id="hcr-section-till-float" class="hcr-section-header">Till Float Count</h3>
                <p class="hcr-help-text">Count the float/change in the till drawer.</p>

                <table class="hcr-denomination-table">
                    <thead>
                        <tr>
                            <th>Denomination</th>
                            <th>Quantity</th>
                            <th>OR</th>
                            <th>Value</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="public-float-denominations-body">
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
                            <th id="public-total-float-counted">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-row">
                            <th colspan="4">EXPECTED FLOAT</th>
                            <th id="public-expected-float">£<?php echo number_format(floatval(get_option('hcr_expected_till_float', '300.00')), 2); ?></th>
                        </tr>
                        <tr class="hcr-variance-row">
                            <th colspan="4">VARIANCE</th>
                            <th id="public-float-variance" class="hcr-variance-value">£0.00</th>
                        </tr>
                    </tfoot>
                </table>
                <button type="button" class="hcr-next-section-btn" data-target="hcr-section-takings">NEXT ›</button>
            </div>

            <!-- Cash Takings Banked -->
            <div class="hcr-counting-section">
                <h3 id="hcr-section-takings" class="hcr-section-header">Cash Takings Banked</h3>
                <p class="hcr-help-text">Count cash takings to be banked (excluding float).</p>

                <table class="hcr-denomination-table">
                    <thead>
                        <tr>
                            <th>Denomination</th>
                            <th>Quantity</th>
                            <th>OR</th>
                            <th>Value</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="public-takings-denominations-body">
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
                            <th id="public-total-cash-counted">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-row" id="public-cash-takings-newbook-row" style="display:none;">
                            <th colspan="4">NEWBOOK CASH EXPECTED</th>
                            <th id="public-newbook-cash-expected">£0.00</th>
                        </tr>
                        <tr class="hcr-variance-row" id="public-cash-takings-variance-row" style="display:none;">
                            <th colspan="4">VARIANCE</th>
                            <th id="public-cash-takings-variance" class="hcr-variance-value">£0.00</th>
                        </tr>
                    </tfoot>
                </table>
                <button type="button" class="hcr-next-section-btn" data-target="hcr-section-card-machines">NEXT ›</button>
            </div>
        </div>

        <!-- Card Machines -->
        <h3 id="hcr-section-card-machines" class="hcr-section-header">Card Machines</h3>

        <div class="hcr-machines-grid">
            <div class="hcr-machine-box">
                <h4>Front Desk</h4>
                <label>Total: £<input type="number" id="public_front_desk_total" class="machine-total" data-machine="front_desk" min="0" step="0.01" value="0.00"></label>
                <label>Amex: £<input type="number" id="public_front_desk_amex" class="machine-amex" data-machine="front_desk" min="0" step="0.01" value="0.00"></label>
                <p>Visa/MC: <strong>£<span id="public_front_desk_visa_mc">0.00</span></strong></p>
            </div>

            <div class="hcr-machine-box">
                <h4>Restaurant/Bar</h4>
                <label>Total: £<input type="number" id="public_restaurant_total" class="machine-total" data-machine="restaurant" min="0" step="0.01" value="0.00"></label>
                <label>Amex: £<input type="number" id="public_restaurant_amex" class="machine-amex" data-machine="restaurant" min="0" step="0.01" value="0.00"></label>
                <p>Visa/MC: <strong>£<span id="public_restaurant_visa_mc">0.00</span></strong></p>
            </div>

            <div class="hcr-machine-box hcr-combined-totals">
                <h4><strong>Combined PDQ Totals</strong></h4>
                <p>Total: <strong>£<span id="public_combined_total">0.00</span></strong></p>
                <p>Amex: <strong>£<span id="public_combined_amex">0.00</span></strong></p>
                <p>Visa/MC: <strong>£<span id="public_combined_visa_mc">0.00</span></strong></p>
            </div>

            <div class="hcr-machine-box hcr-machine-photo-section">
                <h4><strong>Card Machine Z-Report Photo</strong></h4>
                <div id="hcr-public-photo-upload-area" style="display: block;">
                    <input type="file" id="hcr-public-machine-photo-input" accept="image/*" capture="environment" style="display: none;">
                    <button type="button" id="hcr-public-upload-photo-btn" class="hcr-button-secondary">
                        📷 Upload Photo
                    </button>
                    <span id="hcr-public-photo-upload-status" style="margin-left: 10px; font-size: 12px;"></span>
                </div>
                <div id="hcr-public-photo-display-area" style="display: none; margin-top: 10px;">
                    <img id="hcr-public-machine-photo-preview" src="" alt="Card Machine Z-Report" style="max-width: 120px; cursor: pointer; border: 2px solid #ddd; border-radius: 4px;">
                    <button type="button" id="hcr-public-remove-photo-btn" class="hcr-button-secondary" style="display: block; margin-top: 10px;">
                        🗑️ Remove Photo
                    </button>
                </div>
                <input type="hidden" id="hcr-public-machine-photo-id" value="">
            </div>
        </div>

        <!-- Photo Lightbox Modal -->
        <div id="hcr-public-photo-lightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; cursor: pointer;">
            <span id="hcr-public-lightbox-close" style="position: absolute; top: 20px; right: 35px; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
            <img id="hcr-public-lightbox-image" src="" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%; border: 2px solid #fff;">
        </div>

        <!-- Newbook Data Section -->
        <h3 id="hcr-section-newbook" class="hcr-section-header">Newbook Payment Data</h3>
        <p style="display: flex; justify-content: space-between; align-items: center;">
            <span>
                <button type="button" id="hcr-fetch-payments" class="hcr-button-secondary">Fetch Newbook Payments</button>
                <span id="hcr-fetch-status"></span>
            </span>
            <button type="button" id="hcr-show-till-payments" class="hcr-button-secondary" style="display: none; position: relative;">Show Till Payments</button>
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
            <h4 id="hcr-reconciliation-title">Reconciliation Summary</h4>
            <table class="hcr-denomination-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Banked</th>
                        <th>Reported</th>
                        <th>Variance</th>
                        <th>Total Variance</th>
                    </tr>
                </thead>
                <tbody id="hcr-reconciliation-body">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Transaction Breakdown Section -->
        <div id="hcr-transaction-breakdown-section" style="display:none; margin-top: 20px;">
            <h4 style="cursor: pointer; user-select: none;" id="hcr-breakdown-toggle">
                <span class="dashicons dashicons-arrow-right" id="hcr-breakdown-arrow"></span>
                Show Transaction Breakdown from Newbook
            </h4>
            <div id="hcr-breakdown-content" style="display:none;">
                <div style="display: flex; gap: 20px; margin-top: 15px;">
                    <!-- Reception Payments Table -->
                    <div style="flex: 1;">
                        <h5>Reception Payments</h5>
                        <table class="hcr-denomination-table" style="font-size: 13px;">
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
                        <h5>Restaurant/Bar Payments</h5>
                        <table class="hcr-denomination-table" style="font-size: 13px;">
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

        <!-- Notes -->
        <h3 id="hcr-section-notes" class="hcr-section-header">Notes</h3>
        <textarea id="public_cash_up_notes" name="notes" rows="3" placeholder="Any notes or comments..."></textarea>

        <!-- Receipt Photos -->
        <h3>Receipt Photos</h3>
        <p class="hcr-help-text">Upload photos of receipts or transactions causing variances for accounts to investigate later. Multiple files allowed (max 5MB each, JPG/PNG/PDF).</p>
        <div id="hcr-public-receipt-photos-section">
            <input type="file" id="hcr-public-receipt-photos-input" name="receipt_photos[]" multiple accept="image/*,application/pdf" capture="environment" style="display: none;">
            <button type="button" id="hcr-public-upload-receipt-photos-btn" class="hcr-button-secondary">
                <span class="dashicons dashicons-camera"></span> Upload Receipt Photos
            </button>
            <div id="hcr-public-receipt-photos-preview" class="hcr-photos-grid" style="margin-top: 10px;"></div>
        </div>

        <!-- Save Status Indicator -->
        <div id="hcr-public-save-status" class="hcr-save-status-box" style="display: none;"></div>

        <!-- Submit Buttons -->
        <div class="hcr-form-actions">
            <button type="submit" id="hcr-public-save-draft" class="hcr-button-primary">Save as Draft</button>
        </div>
    </form>
</div>
