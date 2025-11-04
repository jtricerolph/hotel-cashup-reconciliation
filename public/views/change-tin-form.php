<?php
/**
 * Public view for Change Tin Float count form
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get change tin breakdown from settings
$breakdown = get_option('hcr_change_tin_breakdown', '');

// Handle both string (JSON) and array formats
if (is_string($breakdown) && !empty($breakdown)) {
    $breakdown = json_decode($breakdown, true);
}

if (empty($breakdown) || !is_array($breakdown)) {
    $breakdown = array(
        '50.00' => 0,
        '20.00' => 0,
        '10.00' => 0,
        '5.00' => 0,
        '2.00' => 20.00,
        '1.00' => 20.00,
        '0.50' => 10.00,
        '0.20' => 10.00,
        '0.10' => 5.00,
        '0.05' => 5.00
    );
}

// Calculate total target
$total_target = array_sum($breakdown);

// Define bag values for each denomination
$bag_values = array(
    '50.00' => 50.00,  // £50 notes - £50 each
    '20.00' => 20.00,  // £20 notes - £20 each
    '10.00' => 10.00,  // £10 notes - £10 each
    '5.00' => 5.00,    // £5 notes - £5 each
    '2.00' => 20.00,   // £2 bags = £20 per bag
    '1.00' => 20.00,   // £1 bags = £20 per bag
    '0.50' => 10.00,   // 50p bags = £10 per bag
    '0.20' => 10.00,   // 20p bags = £10 per bag
    '0.10' => 5.00,    // 10p bags = £5 per bag
    '0.05' => 5.00     // 5p bags = £5 per bag
);

// Labels for each denomination
$denom_labels = array(
    '50.00' => '£50 notes',
    '20.00' => '£20 notes',
    '10.00' => '£10 notes',
    '5.00' => '£5 notes',
    '2.00' => '£2 bags',
    '1.00' => '£1 bags',
    '0.50' => '50p bags',
    '0.20' => '20p bags',
    '0.10' => '10p bags',
    '0.05' => '5p bags'
);
?>

<div class="hcr-public-cash-up-form hcr-change-tin-public">
    <h2>Change Tin Count</h2>

    <form id="hcr-change-tin-count-form-public">
        <!-- Date/Time -->
        <div class="hcr-form-row">
            <label for="count_date_public_ct"><strong>Count Date/Time:</strong></label><br>
            <input type="datetime-local" id="count_date_public_ct" name="count_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required style="padding: 5px;">
        </div>

        <!-- Bag/Note Count Table -->
        <h3>Change Tin Count</h3>
        <table class="hcr-denomination-table">
            <thead>
                <tr>
                    <th>Denomination</th>
                    <th style="width: 120px;">Bags/Notes</th>
                    <th style="width: 120px;">Total Value</th>
                    <th style="width: 120px;">Target</th>
                    <th style="width: 150px;">Top-up Suggestion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakdown as $denom => $target): ?>
                <tr>
                    <td>
                        <?php echo esc_html($denom_labels[$denom]); ?>
                        <?php if ($bag_values[$denom] > 1): ?>
                            <span style="color: #666; font-size: 0.9em;">(£<?php echo number_format($bag_values[$denom], 2); ?> per bag)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="number"
                               class="change-tin-qty-public"
                               data-denomination="<?php echo esc_attr($denom); ?>"
                               data-bag-value="<?php echo esc_attr($bag_values[$denom]); ?>"
                               data-target="<?php echo esc_attr($target); ?>"
                               min="0"
                               value="0">
                    </td>
                    <td class="change-tin-total-public">£0.00</td>
                    <td>£<?php echo number_format($target, 2); ?></td>
                    <td class="change-tin-topup-public" style="font-weight: bold;">-</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="hcr-total-row">
                    <th>Total:</th>
                    <th></th>
                    <th id="total-counted-public-ct">£0.00</th>
                    <th id="total-target-public-ct">£<?php echo number_format($total_target, 2); ?></th>
                    <th id="total-variance-public-ct" style="font-weight: bold;">£0.00</th>
                </tr>
            </tfoot>
        </table>

        <!-- Notes -->
        <div style="margin: 14px 0;">
            <label for="count_notes_public_ct"><strong>Notes:</strong></label><br>
            <textarea id="count_notes_public_ct" name="count_notes" rows="3"></textarea>
        </div>

        <!-- Save Button -->
        <div class="hcr-form-actions">
            <button type="submit" class="hcr-button-primary">Save Count</button>
            <span id="save-status-public-ct" style="margin-left: 15px;"></span>
        </div>
    </form>
</div>

<style>
.topup-positive-public {
    color: #46b450;
}
.topup-negative-public {
    color: #f0b429;
}
</style>

<script>
jQuery(document).ready(function($) {
    var totalTarget = <?php echo $total_target; ?>;

    // Calculate totals and top-ups
    function calculateTotals() {
        var totalCounted = 0;

        $('.change-tin-qty-public').each(function() {
            var qty = parseFloat($(this).val()) || 0;
            var bagValue = parseFloat($(this).data('bag-value'));
            var target = parseFloat($(this).data('target'));
            var total = qty * bagValue;

            // Update total value
            $(this).closest('tr').find('.change-tin-total-public').text('£' + total.toFixed(2));

            totalCounted += total;

            // Calculate top-up suggestion
            var difference = total - target;
            var topupCell = $(this).closest('tr').find('.change-tin-topup-public');

            if (Math.abs(difference) < 0.01) {
                // Balanced
                topupCell.text('Balanced').removeClass('topup-positive-public topup-negative-public').css('color', 'gray');
            } else if (difference < 0) {
                // Need to add (get from bank)
                var needed = Math.abs(difference);
                topupCell.text('+£' + needed.toFixed(2) + ' from bank').removeClass('topup-negative-public').addClass('topup-positive-public');
            } else {
                // Over - need to bank (give to bank)
                topupCell.text('-£' + difference.toFixed(2) + ' to bank').removeClass('topup-positive-public').addClass('topup-negative-public');
            }
        });

        $('#total-counted-public-ct').text('£' + totalCounted.toFixed(2));

        // Calculate total variance
        var totalVariance = totalCounted - totalTarget;
        var varianceText = '£' + Math.abs(totalVariance).toFixed(2);

        if (totalVariance > 0) {
            varianceText = '+' + varianceText;
            $('#total-variance-public-ct').removeClass('hcr-variance-short hcr-variance-balanced').addClass('hcr-variance-over');
        } else if (totalVariance < 0) {
            varianceText = '-' + varianceText;
            $('#total-variance-public-ct').removeClass('hcr-variance-over hcr-variance-balanced').addClass('hcr-variance-short');
        } else {
            $('#total-variance-public-ct').removeClass('hcr-variance-over hcr-variance-short').addClass('hcr-variance-balanced');
        }

        $('#total-variance-public-ct').text(varianceText);
    }

    // Recalculate on input change
    $(document).on('input', '.change-tin-qty-public', function() {
        calculateTotals();
    });

    // Select all text on click for easy replacement
    $(document).on('click', '.change-tin-qty-public', function() {
        $(this).select();
    });

    // Save count
    $('#hcr-change-tin-count-form-public').on('submit', function(e) {
        e.preventDefault();

        // Gather denomination data
        var denominations = [];
        $('.change-tin-qty-public').each(function() {
            var qty = parseInt($(this).val()) || 0;
            if (qty > 0) {
                var denom = parseFloat($(this).data('denomination'));
                var bagValue = parseFloat($(this).data('bag-value'));
                denominations.push({
                    denomination: denom,
                    quantity: qty,
                    total: qty * bagValue
                });
            }
        });

        // Calculate totals
        var totalCounted = 0;
        denominations.forEach(function(d) {
            totalCounted += d.total;
        });

        var variance = totalCounted - totalTarget;

        var data = {
            action: 'hcr_save_change_tin_count',
            nonce: hcrPublic.nonce,
            count_date: $('#count_date_public_ct').val(),
            denominations: JSON.stringify(denominations),
            total_counted: totalCounted,
            target_amount: totalTarget,
            variance: variance,
            notes: $('#count_notes_public_ct').val()
        };

        $('#save-status-public-ct').html('<span style="color: #0073aa;">Saving...</span>');
        $('button[type="submit"]').prop('disabled', true);

        $.post(hcrPublic.ajaxUrl, data, function(response) {
            if (response.success) {
                $('#save-status-public-ct').html('<span style="color: #46b450;">✓ Saved successfully!</span>');

                // Reset form after 2 seconds
                setTimeout(function() {
                    $('.change-tin-qty-public').val(0);
                    $('#count_notes_public_ct').val('');
                    $('#count_date_public_ct').val('<?php echo date('Y-m-d\TH:i'); ?>');
                    calculateTotals();
                    $('#save-status-public-ct').html('');
                }, 2000);
            } else {
                $('#save-status-public-ct').html('<span style="color: #dc3232;">Error: ' + response.data.message + '</span>');
            }
        }).fail(function() {
            $('#save-status-public-ct').html('<span style="color: #dc3232;">Error saving count. Please try again.</span>');
        }).always(function() {
            $('button[type="submit"]').prop('disabled', false);
        });
    });

    // Initial calculation
    calculateTotals();
});
</script>
