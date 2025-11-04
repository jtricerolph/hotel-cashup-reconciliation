<?php
/**
 * Admin view for Change Tin Float counts
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

<div class="wrap">
    <h1>Change Tin Float</h1>

    <!-- New Count Form -->
    <div class="hcr-change-tin-form" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>New Change Tin Count</h2>

        <form id="hcr-change-tin-count-form">
            <!-- Date/Time -->
            <div style="margin-bottom: 20px;">
                <label for="count_date"><strong>Count Date/Time:</strong></label><br>
                <input type="datetime-local" id="count_date" name="count_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required style="padding: 5px;">
            </div>

            <!-- Bag/Note Count Table -->
            <h3>Change Tin Count</h3>
            <table class="widefat" style="margin-bottom: 20px;">
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
                                   class="change-tin-qty"
                                   data-denomination="<?php echo esc_attr($denom); ?>"
                                   data-bag-value="<?php echo esc_attr($bag_values[$denom]); ?>"
                                   data-target="<?php echo esc_attr($target); ?>"
                                   min="0"
                                   value="0"
                                   style="width: 100%;">
                        </td>
                        <td class="change-tin-total">£0.00</td>
                        <td>£<?php echo number_format($target, 2); ?></td>
                        <td class="change-tin-topup" style="font-weight: bold;">-</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f9f9f9; font-weight: bold;">
                        <th>Total:</th>
                        <th></th>
                        <th id="total-counted">£0.00</th>
                        <th id="total-target">£<?php echo number_format($total_target, 2); ?></th>
                        <th id="total-variance" style="font-weight: bold;">£0.00</th>
                    </tr>
                </tfoot>
            </table>

            <!-- Notes -->
            <div style="margin-bottom: 20px;">
                <label for="count_notes"><strong>Notes:</strong></label><br>
                <textarea id="count_notes" name="count_notes" rows="3" style="width: 100%; max-width: 800px;"></textarea>
            </div>

            <!-- Save Button -->
            <button type="submit" class="button button-primary button-large">Save Count</button>
            <span id="save-status" style="margin-left: 15px;"></span>
        </form>
    </div>

    <!-- History Section -->
    <div class="hcr-change-tin-history" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>Count History</h2>

        <table class="widefat" id="change-tin-history-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Total Counted</th>
                    <th>Target</th>
                    <th>Variance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="history-tbody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">Loading...</td>
                </tr>
            </tbody>
        </table>

        <div style="text-align: center; margin-top: 15px;">
            <button type="button" id="load-more-btn" class="button" style="display: none;">Load More (10)</button>
        </div>
    </div>
</div>

<!-- View Count Modal -->
<div id="view-count-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: #fff; margin: 50px auto; padding: 20px; width: 90%; max-width: 900px; max-height: 80%; overflow-y: auto; border-radius: 5px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Count Details</h2>
            <button type="button" class="button" id="close-modal-btn">&times; Close</button>
        </div>
        <div id="modal-content">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<style>
.variance-positive {
    color: green;
}
.variance-negative {
    color: red;
}
.variance-balanced {
    color: gray;
}
.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 5px;
}
.status-over {
    background-color: green;
}
.status-short {
    background-color: red;
}
.status-balanced {
    background-color: green;
}
.topup-positive {
    color: green;
}
.topup-negative {
    color: orange;
}
</style>

<script>
jQuery(document).ready(function($) {
    var totalTarget = <?php echo $total_target; ?>;
    var loadedRecordsCount = 0;
    var recordsPerPage = 10;

    // Calculate totals and top-ups
    function calculateTotals() {
        var totalCounted = 0;

        $('.change-tin-qty').each(function() {
            var qty = parseFloat($(this).val()) || 0;
            var bagValue = parseFloat($(this).data('bag-value'));
            var target = parseFloat($(this).data('target'));
            var total = qty * bagValue;

            // Update total value
            $(this).closest('tr').find('.change-tin-total').text('£' + total.toFixed(2));

            totalCounted += total;

            // Calculate top-up suggestion
            var difference = total - target;
            var topupCell = $(this).closest('tr').find('.change-tin-topup');
            var denomination = parseFloat($(this).data('denomination'));

            if (Math.abs(difference) < 0.01) {
                // Balanced
                topupCell.text('Balanced').removeClass('topup-positive topup-negative').css('color', 'gray');
            } else if (difference < 0) {
                // Need to add (get from bank)
                var needed = Math.abs(difference);

                if (bagValue > 5.00) {
                    // It's a bag (£10 or £20 bags) - show value
                    topupCell.text('+£' + needed.toFixed(2) + ' from bank').removeClass('topup-negative').addClass('topup-positive');
                } else {
                    // It's notes or small bags - show value
                    topupCell.text('+£' + needed.toFixed(2) + ' from bank').removeClass('topup-negative').addClass('topup-positive');
                }
            } else {
                // Over - need to bank (give to bank)
                topupCell.text('-£' + difference.toFixed(2) + ' to bank').removeClass('topup-positive').addClass('topup-negative');
            }
        });

        $('#total-counted').text('£' + totalCounted.toFixed(2));

        // Calculate total variance
        var totalVariance = totalCounted - totalTarget;
        var varianceText = '£' + Math.abs(totalVariance).toFixed(2);

        if (totalVariance > 0) {
            varianceText = '+' + varianceText;
            $('#total-variance').removeClass('variance-negative variance-balanced').addClass('variance-positive');
        } else if (totalVariance < 0) {
            varianceText = '-' + varianceText;
            $('#total-variance').removeClass('variance-positive variance-balanced').addClass('variance-negative');
        } else {
            $('#total-variance').removeClass('variance-positive variance-negative').addClass('variance-balanced');
        }

        $('#total-variance').text(varianceText);
    }

    // Recalculate on input change
    $(document).on('input', '.change-tin-qty', function() {
        calculateTotals();
    });

    // Select all text on click for easy replacement
    $(document).on('click', '.change-tin-qty', function() {
        $(this).select();
    });

    // Save count
    $('#hcr-change-tin-count-form').on('submit', function(e) {
        e.preventDefault();

        // Gather denomination data
        var denominations = [];
        $('.change-tin-qty').each(function() {
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

        // Get totals
        var totalCounted = parseFloat($('#total-counted').text().replace('£', ''));
        var variance = totalCounted - totalTarget;

        var data = {
            action: 'hcr_save_change_tin_count',
            count_date: $('#count_date').val(),
            denominations: denominations,
            total_counted: totalCounted,
            target_amount: totalTarget,
            variance: variance,
            notes: $('#count_notes').val(),
            nonce: '<?php echo wp_create_nonce('hcr_change_tin_nonce'); ?>'
        };

        $('#save-status').html('<span style="color: blue;">Saving...</span>');

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#save-status').html('<span style="color: green;">✓ Count saved successfully!</span>');

                // Reset form
                $('.change-tin-qty').val(0);
                $('#count_notes').val('');
                $('#count_date').val('<?php echo date('Y-m-d\TH:i'); ?>');
                calculateTotals();

                // Reload history
                loadHistory(false);

                setTimeout(function() {
                    $('#save-status').html('');
                }, 3000);
            } else {
                $('#save-status').html('<span style="color: red;">✗ Error: ' + (response.data || 'Unknown error') + '</span>');
            }
        }).fail(function() {
            $('#save-status').html('<span style="color: red;">✗ Server error. Please try again.</span>');
        });
    });

    // Load history
    function loadHistory(append) {
        append = append || false;

        var data = {
            action: 'hcr_load_change_tin_counts',
            offset: append ? loadedRecordsCount : 0,
            limit: recordsPerPage,
            nonce: '<?php echo wp_create_nonce('hcr_change_tin_nonce'); ?>'
        };

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data.counts) {
                var counts = response.data.counts;
                var html = '';

                if (counts.length === 0 && !append) {
                    html = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No counts found.</td></tr>';
                } else {
                    counts.forEach(function(count) {
                        var variance = parseFloat(count.variance);
                        var varianceClass = variance > 0 ? 'variance-positive' : (variance < 0 ? 'variance-negative' : 'variance-balanced');
                        // Status: green only if balanced (0), red for any variance
                        var statusClass = Math.abs(variance) < 0.01 ? 'status-balanced' : 'status-short';
                        var varianceText = (variance > 0 ? '+' : '') + '£' + Math.abs(variance).toFixed(2);

                        html += '<tr>' +
                            '<td>' + count.count_date + '</td>' +
                            '<td>£' + parseFloat(count.total_counted).toFixed(2) + '</td>' +
                            '<td>£' + parseFloat(count.target_amount).toFixed(2) + '</td>' +
                            '<td class="' + varianceClass + '">' + varianceText + '</td>' +
                            '<td><span class="status-indicator ' + statusClass + '"></span></td>' +
                            '<td>' +
                                '<button type="button" class="button view-count-btn" data-count-id="' + count.id + '">View</button> ' +
                                '<button type="button" class="button button-primary load-for-edit-btn" data-count-id="' + count.id + '">Preload Count</button>' +
                            '</td>' +
                            '</tr>';
                    });
                }

                if (append) {
                    $('#history-tbody').append(html);
                } else {
                    $('#history-tbody').html(html);
                    loadedRecordsCount = 0;
                }

                loadedRecordsCount += counts.length;

                // Show/hide "Load More" button
                if (response.data.has_more) {
                    $('#load-more-btn').show();
                } else {
                    $('#load-more-btn').hide();
                }
            }
        });
    }

    // Load More button
    $('#load-more-btn').on('click', function() {
        loadHistory(true);
    });

    // View count details
    $(document).on('click', '.view-count-btn', function() {
        var countId = $(this).data('count-id');

        var data = {
            action: 'hcr_get_change_tin_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_change_tin_nonce'); ?>'
        };

        $('#modal-content').html('<p>Loading...</p>');
        $('#view-count-modal').show();

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data) {
                var count = response.data;
                var variance = parseFloat(count.variance);
                var varianceClass = variance > 0 ? 'variance-positive' : (variance < 0 ? 'variance-negative' : 'variance-balanced');
                var varianceText = (variance > 0 ? '+' : '') + '£' + Math.abs(variance).toFixed(2);

                var html = '<div>';
                html += '<p><strong>Date/Time:</strong> ' + count.count_date + '</p>';
                html += '<p><strong>Created By:</strong> ' + (count.created_by_name || 'Unknown') + '</p>';

                // Denominations
                html += '<h3>Change Tin Breakdown</h3>';
                html += '<table class="widefat">';
                html += '<thead><tr><th>Denomination</th><th>Qty</th><th>Total</th></tr></thead><tbody>';

                if (count.denominations && count.denominations.length > 0) {
                    count.denominations.forEach(function(denom) {
                        var denomValue = parseFloat(denom.denomination_value);
                        var denomLabel = denomValue >= 1 ? '£' + denomValue.toFixed(0) : (denomValue * 100).toFixed(0) + 'p';
                        html += '<tr>' +
                            '<td>' + denomLabel + '</td>' +
                            '<td>' + denom.quantity + '</td>' +
                            '<td>£' + parseFloat(denom.total_amount).toFixed(2) + '</td>' +
                            '</tr>';
                    });
                }

                html += '<tr style="background: #f9f9f9;"><th colspan="2">Total Counted:</th><th>£' + parseFloat(count.total_counted).toFixed(2) + '</th></tr>';
                html += '</tbody></table>';

                // Summary
                html += '<h3>Summary</h3>';
                html += '<table class="widefat" style="max-width: 400px; background: #f9f9f9;">';
                html += '<tr><th>Total Counted:</th><td>£' + parseFloat(count.total_counted).toFixed(2) + '</td></tr>';
                html += '<tr><th>Target Amount:</th><td>£' + parseFloat(count.target_amount).toFixed(2) + '</td></tr>';
                html += '<tr><th>Variance:</th><td class="' + varianceClass + '">' + varianceText + '</td></tr>';
                html += '</table>';

                // Notes
                if (count.notes) {
                    html += '<h3>Notes</h3>';
                    html += '<p>' + count.notes + '</p>';
                }

                html += '</div>';

                $('#modal-content').html(html);
            } else {
                $('#modal-content').html('<p style="color: red;">Error loading count details.</p>');
            }
        });
    });

    // Load count for editing
    $(document).on('click', '.load-for-edit-btn', function() {
        var countId = $(this).data('count-id');
        var $button = $(this);

        var data = {
            action: 'hcr_get_change_tin_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_change_tin_nonce'); ?>'
        };

        $button.text('Loading...').prop('disabled', true);

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data) {
                var count = response.data;

                // Reset form first
                $('.change-tin-qty').val(0);

                // Set date/time to current time (not from the saved count)
                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var currentDateTime = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
                $('#count_date').val(currentDateTime);

                // Populate denominations
                if (count.denominations && count.denominations.length > 0) {
                    count.denominations.forEach(function(denom) {
                        var denomValue = parseFloat(denom.denomination_value);

                        // Find input by comparing denomination values as floats
                        $('.change-tin-qty').each(function() {
                            var inputDenom = parseFloat($(this).data('denomination'));
                            if (Math.abs(inputDenom - denomValue) < 0.001) {
                                $(this).val(denom.quantity);
                            }
                        });
                    });
                }

                // Populate notes
                $('#count_notes').val(count.notes || '');

                // Recalculate totals
                calculateTotals();

                // Show success message
                $('#save-status').html('<span style="color: green;">✓ Count preloaded. Modify as needed and save to create a new count.</span>');
                setTimeout(function() {
                    $('#save-status').html('');
                }, 5000);

                // Scroll to form
                $('html, body').animate({
                    scrollTop: $('.hcr-change-tin-form').offset().top - 50
                }, 500);
            } else {
                alert('Error loading count. Please try again.');
            }

            $button.text('Preload Count').prop('disabled', false);
        }).fail(function() {
            alert('Server error. Please try again.');
            $button.text('Preload Count').prop('disabled', false);
        });
    });

    // Close modal
    $('#close-modal-btn').on('click', function() {
        $('#view-count-modal').hide();
    });

    // Close modal on outside click
    $('#view-count-modal').on('click', function(e) {
        if (e.target.id === 'view-count-modal') {
            $(this).hide();
        }
    });

    // Initial load
    loadHistory(false);
    calculateTotals();
});
</script>
