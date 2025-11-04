<?php
/**
 * Public view for Petty Cash Float count form
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get petty cash float target from settings
$petty_cash_target = floatval(get_option('hcr_petty_cash_float', 200.00));

// Get denominations
$denominations_json = get_option('hcr_denominations', '');
$denominations = json_decode($denominations_json, true);

$all_denominations = array();
if (isset($denominations['notes']) && is_array($denominations['notes'])) {
    $all_denominations = array_merge($all_denominations, $denominations['notes']);
}
if (isset($denominations['coins']) && is_array($denominations['coins'])) {
    $all_denominations = array_merge($all_denominations, $denominations['coins']);
}

// Sort in descending order
rsort($all_denominations);

// Filter to only 5p and above (exclude 2p and 1p)
$all_denominations = array_filter($all_denominations, function($denom) {
    return $denom >= 0.05;
});
?>

<div class="hcr-public-cash-up-form hcr-petty-cash-public">
    <h2>Petty Cash Count</h2>

    <form id="hcr-petty-cash-count-form-public">
        <!-- Date/Time -->
        <div class="hcr-form-row">
            <label for="count_date_public"><strong>Count Date/Time:</strong></label><br>
            <input type="datetime-local" id="count_date_public" name="count_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required style="padding: 5px;">
        </div>

        <!-- Denomination Count Table -->
        <h3>Cash Counted</h3>
        <table class="hcr-denomination-table">
            <thead>
                <tr>
                    <th>Denomination</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_denominations as $denom): ?>
                <tr>
                    <td>
                        <?php
                        if ($denom >= 1) {
                            echo '£' . number_format($denom, 0);
                        } else {
                            echo (intval($denom * 100)) . 'p';
                        }
                        ?>
                    </td>
                    <td>
                        <input type="number"
                               class="petty-cash-qty-public"
                               data-denomination="<?php echo esc_attr($denom); ?>"
                               min="0"
                               value="0">
                    </td>
                    <td class="petty-cash-denom-total-public">£0.00</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="hcr-total-row">
                    <th colspan="2">Total Cash Counted:</th>
                    <th id="total-cash-counted-public">£0.00</th>
                </tr>
            </tfoot>
        </table>

        <!-- Receipts Section -->
        <h3>Receipts</h3>
        <div style="margin-bottom: 10px;">
            <button type="button" id="add-receipt-btn-public" class="hcr-button-secondary">+ Add Receipt</button>
        </div>

        <table class="hcr-denomination-table" id="receipts-table-public">
            <thead>
                <tr>
                    <th style="width: 120px;">Amount (£)</th>
                    <th>Description</th>
                    <th style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody id="receipts-tbody-public">
                <!-- Receipt rows will be added here dynamically -->
            </tbody>
            <tfoot>
                <tr class="hcr-total-row">
                    <th>Total Receipts:</th>
                    <th colspan="2" id="total-receipts-public">£0.00</th>
                </tr>
            </tfoot>
        </table>

        <!-- Summary Totals -->
        <table class="hcr-denomination-table" style="background: #f9f9f9; margin-top: 14px;">
            <tbody>
                <tr>
                    <th style="width: 60%;">Grand Total (Cash + Receipts):</th>
                    <td id="grand-total-public" style="font-weight: bold;">£0.00</td>
                </tr>
                <tr>
                    <th>Target Amount:</th>
                    <td>£<?php echo number_format($petty_cash_target, 2); ?></td>
                </tr>
                <tr id="variance-row-public">
                    <th>Variance:</th>
                    <td id="variance-amount-public" style="font-weight: bold;">£0.00</td>
                </tr>
            </tbody>
        </table>

        <!-- Notes -->
        <div style="margin: 14px 0;">
            <label for="count_notes_public"><strong>Notes:</strong></label><br>
            <textarea id="count_notes_public" name="count_notes" rows="3"></textarea>
        </div>

        <!-- Save Button -->
        <div class="hcr-form-actions">
            <button type="submit" class="hcr-button-primary">Save Count</button>
            <span id="save-status-public" style="margin-left: 15px;"></span>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var targetAmount = <?php echo $petty_cash_target; ?>;

    // Calculate totals
    function calculateTotals() {
        var totalCash = 0;

        // Calculate cash total
        $('.petty-cash-qty-public').each(function() {
            var qty = parseFloat($(this).val()) || 0;
            var denom = parseFloat($(this).data('denomination'));
            var total = qty * denom;

            $(this).closest('tr').find('.petty-cash-denom-total-public').text('£' + total.toFixed(2));
            totalCash += total;
        });

        $('#total-cash-counted-public').text('£' + totalCash.toFixed(2));

        // Calculate receipts total
        var totalReceipts = 0;
        $('.receipt-amount-public').each(function() {
            totalReceipts += parseFloat($(this).val()) || 0;
        });

        $('#total-receipts-public').text('£' + totalReceipts.toFixed(2));

        // Calculate grand total
        var grandTotal = totalCash + totalReceipts;
        $('#grand-total-public').text('£' + grandTotal.toFixed(2));

        // Calculate variance
        var variance = grandTotal - targetAmount;
        var varianceText = '£' + Math.abs(variance).toFixed(2);

        if (variance > 0) {
            varianceText = '+' + varianceText;
            $('#variance-amount-public').removeClass('hcr-variance-short hcr-variance-balanced').addClass('hcr-variance-over');
        } else if (variance < 0) {
            varianceText = '-' + varianceText;
            $('#variance-amount-public').removeClass('hcr-variance-over hcr-variance-balanced').addClass('hcr-variance-short');
        } else {
            $('#variance-amount-public').removeClass('hcr-variance-over hcr-variance-short').addClass('hcr-variance-balanced');
        }

        $('#variance-amount-public').text(varianceText);
    }

    // Add receipt row
    function addReceiptRow(amount, description) {
        amount = amount || '';
        description = description || '';

        var row = '<tr class="receipt-row-public">' +
            '<td><input type="number" class="receipt-amount-public" step="0.01" min="0" value="' + amount + '" style="width: 100%;"></td>' +
            '<td><input type="text" class="receipt-description-public" value="' + description + '" style="width: 100%;"></td>' +
            '<td><button type="button" class="hcr-button-secondary remove-receipt-btn-public">Remove</button></td>' +
            '</tr>';

        $('#receipts-tbody-public').append(row);
        calculateTotals();
    }

    // Add receipt button
    $('#add-receipt-btn-public').on('click', function() {
        addReceiptRow();
    });

    // Remove receipt button
    $(document).on('click', '.remove-receipt-btn-public', function() {
        $(this).closest('tr').remove();
        calculateTotals();
    });

    // Recalculate on input change
    $(document).on('input', '.petty-cash-qty-public, .receipt-amount-public', function() {
        calculateTotals();
    });

    // Select all text on click for easy replacement
    $(document).on('click', '.petty-cash-qty-public, .receipt-amount-public', function() {
        $(this).select();
    });

    // Save count
    $('#hcr-petty-cash-count-form-public').on('submit', function(e) {
        e.preventDefault();

        // Gather denomination data
        var denominations = [];
        $('.petty-cash-qty-public').each(function() {
            var qty = parseInt($(this).val()) || 0;
            if (qty > 0) {
                denominations.push({
                    denomination: parseFloat($(this).data('denomination')),
                    quantity: qty,
                    total: qty * parseFloat($(this).data('denomination'))
                });
            }
        });

        // Gather receipts data
        var receipts = [];
        $('.receipt-amount-public').each(function() {
            var amount = parseFloat($(this).val()) || 0;
            if (amount > 0) {
                var description = $(this).closest('tr').find('.receipt-description-public').val();
                receipts.push({
                    amount: amount,
                    description: description
                });
            }
        });

        // Calculate totals
        var totalCash = 0;
        denominations.forEach(function(d) {
            totalCash += d.total;
        });

        var totalReceipts = 0;
        receipts.forEach(function(r) {
            totalReceipts += r.amount;
        });

        var grandTotal = totalCash + totalReceipts;
        var variance = grandTotal - targetAmount;

        var data = {
            action: 'hcr_save_petty_cash_count',
            nonce: hcrPublic.nonce,
            count_date: $('#count_date_public').val(),
            denominations: JSON.stringify(denominations),
            receipts: JSON.stringify(receipts),
            total_counted: totalCash,
            total_receipts: totalReceipts,
            target_amount: targetAmount,
            variance: variance,
            notes: $('#count_notes_public').val()
        };

        $('#save-status-public').html('<span style="color: #0073aa;">Saving...</span>');
        $('button[type="submit"]').prop('disabled', true);

        $.post(hcrPublic.ajaxUrl, data, function(response) {
            if (response.success) {
                $('#save-status-public').html('<span style="color: #46b450;">✓ Saved successfully!</span>');

                // Reset form after 2 seconds
                setTimeout(function() {
                    $('.petty-cash-qty-public').val(0);
                    $('#receipts-tbody-public').empty();
                    $('#count_notes_public').val('');
                    $('#count_date_public').val('<?php echo date('Y-m-d\TH:i'); ?>');
                    calculateTotals();
                    $('#save-status-public').html('');
                }, 2000);
            } else {
                $('#save-status-public').html('<span style="color: #dc3232;">Error: ' + response.data.message + '</span>');
            }
        }).fail(function() {
            $('#save-status-public').html('<span style="color: #dc3232;">Error saving count. Please try again.</span>');
        }).always(function() {
            $('button[type="submit"]').prop('disabled', false);
        });
    });

    // Initial calculation
    calculateTotals();
});
</script>
