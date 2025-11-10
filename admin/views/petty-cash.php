<?php
/**
 * Admin view for Petty Cash Float counts
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

<div class="wrap">
    <h1>Petty Cash Float</h1>

    <!-- New Count Form -->
    <div class="hcr-petty-cash-form" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>New Petty Cash Count</h2>

        <form id="hcr-petty-cash-count-form">
            <!-- Date/Time -->
            <div style="margin-bottom: 20px;">
                <label for="count_date"><strong>Count Date/Time:</strong></label><br>
                <input type="datetime-local" id="count_date" name="count_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required style="padding: 5px;">
            </div>

            <!-- Denomination Count Table -->
            <h3>Cash Counted</h3>
            <table class="widefat" style="max-width: 600px; margin-bottom: 20px;">
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
                                   class="petty-cash-qty"
                                   data-denomination="<?php echo esc_attr($denom); ?>"
                                   min="0"
                                   value="0"
                                   style="width: 100px;">
                        </td>
                        <td class="petty-cash-denom-total">£0.00</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Total Cash Counted:</th>
                        <th id="total-cash-counted">£0.00</th>
                    </tr>
                </tfoot>
            </table>

            <!-- Receipts Section -->
            <h3>Receipts</h3>
            <div style="margin-bottom: 10px;">
                <button type="button" id="add-receipt-btn" class="button">+ Add Receipt</button>
            </div>

            <table class="widefat" id="receipts-table" style="max-width: 600px; margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="width: 120px;">Amount (£)</th>
                        <th>Description</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="receipts-tbody">
                    <!-- Receipt rows will be added here dynamically -->
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total Receipts:</th>
                        <th colspan="2" id="total-receipts">£0.00</th>
                    </tr>
                </tfoot>
            </table>

            <!-- Summary Totals -->
            <table class="widefat" style="max-width: 600px; margin-bottom: 20px; background: #f9f9f9;">
                <tbody>
                    <tr>
                        <th style="width: 60%;">Grand Total (Cash + Receipts):</th>
                        <td id="grand-total" style="font-weight: bold;">£0.00</td>
                    </tr>
                    <tr>
                        <th>Target Amount:</th>
                        <td>£<?php echo number_format($petty_cash_target, 2); ?></td>
                    </tr>
                    <tr id="variance-row">
                        <th>Variance:</th>
                        <td id="variance-amount" style="font-weight: bold;">£0.00</td>
                    </tr>
                </tbody>
            </table>

            <!-- Notes -->
            <div style="margin-bottom: 20px;">
                <label for="count_notes"><strong>Notes:</strong></label><br>
                <textarea id="count_notes" name="count_notes" rows="3" style="width: 100%; max-width: 600px;"></textarea>
            </div>

            <!-- Save Button -->
            <button type="submit" class="button button-primary button-large">Save Count</button>
            <span id="save-status" style="margin-left: 15px;"></span>
        </form>
    </div>

    <!-- Selection Tooltip -->
    <div id="selection-tooltip" style="display: none; position: fixed; bottom: 20px; right: 20px; background: #f9f9f9; border: 2px solid #0078d4; padding: 10px 15px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 10000; font-size: 13px;">
        <div id="tooltip-content"></div>
    </div>

    <!-- History Section -->
    <div class="hcr-petty-cash-history" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>Count History</h2>

        <table class="widefat" id="petty-cash-history-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Total Counted</th>
                    <th>Total Receipts</th>
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
    <div id="modal-inner" style="background-color: #fff; margin: 50px auto; padding: 20px; width: 90%; max-width: 800px; max-height: 80%; overflow-y: auto; border-radius: 5px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;" class="no-print">
            <h2>Count Details</h2>
            <div>
                <button type="button" class="button button-primary" id="print-modal-btn" style="margin-right: 10px;">
                    <span class="dashicons dashicons-printer" style="vertical-align: middle; margin-right: 5px;"></span>Print
                </button>
                <button type="button" class="button" id="close-modal-btn">&times; Close</button>
            </div>
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

/* Excel-like cell selection */
.widefat td.hcr-cell-selected,
.widefat th.hcr-cell-selected {
    background-color: #cce4ff !important;
    outline: 2px solid #0078d4;
    outline-offset: -2px;
}

.widefat td:hover {
    box-shadow: inset 0 0 0 1px #4a90e2;
}

/* Print styles - not needed since we open a new window for printing */
</style>

<script>
jQuery(document).ready(function($) {
    var targetAmount = <?php echo $petty_cash_target; ?>;
    var loadedRecordsCount = 0;
    var recordsPerPage = 10;

    // Calculate totals
    function calculateTotals() {
        var totalCash = 0;

        // Calculate cash total
        $('.petty-cash-qty').each(function() {
            var qty = parseFloat($(this).val()) || 0;
            var denom = parseFloat($(this).data('denomination'));
            var total = qty * denom;

            $(this).closest('tr').find('.petty-cash-denom-total').text('£' + total.toFixed(2));
            totalCash += total;
        });

        $('#total-cash-counted').text('£' + totalCash.toFixed(2));

        // Calculate receipts total
        var totalReceipts = 0;
        $('.receipt-amount').each(function() {
            totalReceipts += parseFloat($(this).val()) || 0;
        });

        $('#total-receipts').text('£' + totalReceipts.toFixed(2));

        // Calculate grand total
        var grandTotal = totalCash + totalReceipts;
        $('#grand-total').text('£' + grandTotal.toFixed(2));

        // Calculate variance
        var variance = grandTotal - targetAmount;
        var varianceText = '£' + Math.abs(variance).toFixed(2);

        if (variance > 0) {
            varianceText = '+' + varianceText;
            $('#variance-amount').removeClass('variance-negative variance-balanced').addClass('variance-positive');
        } else if (variance < 0) {
            varianceText = '-' + varianceText;
            $('#variance-amount').removeClass('variance-positive variance-balanced').addClass('variance-negative');
        } else {
            $('#variance-amount').removeClass('variance-positive variance-negative').addClass('variance-balanced');
        }

        $('#variance-amount').text(varianceText);
    }

    // Add receipt row
    function addReceiptRow(amount, description) {
        amount = amount || '';
        description = description || '';

        var row = '<tr class="receipt-row">' +
            '<td><input type="number" class="receipt-amount" step="0.01" min="0" value="' + amount + '" style="width: 100%;"></td>' +
            '<td><input type="text" class="receipt-description" value="' + description + '" style="width: 100%;"></td>' +
            '<td><button type="button" class="button remove-receipt-btn">Remove</button></td>' +
            '</tr>';

        $('#receipts-tbody').append(row);
        calculateTotals();
    }

    // Add receipt button
    $('#add-receipt-btn').on('click', function() {
        addReceiptRow();
    });

    // Remove receipt button
    $(document).on('click', '.remove-receipt-btn', function() {
        $(this).closest('tr').remove();
        calculateTotals();
    });

    // Recalculate on input change
    $(document).on('input', '.petty-cash-qty, .receipt-amount', function() {
        calculateTotals();
    });

    // Select all text on click for easy replacement
    $(document).on('click', '.petty-cash-qty, .receipt-amount', function() {
        $(this).select();
    });

    // Save count
    $('#hcr-petty-cash-count-form').on('submit', function(e) {
        e.preventDefault();

        // Gather denomination data
        var denominations = [];
        $('.petty-cash-qty').each(function() {
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
        $('.receipt-row').each(function() {
            var amount = parseFloat($(this).find('.receipt-amount').val()) || 0;
            var description = $(this).find('.receipt-description').val().trim();
            if (amount > 0) {
                receipts.push({
                    amount: amount,
                    description: description
                });
            }
        });

        // Get totals
        var totalCash = parseFloat($('#total-cash-counted').text().replace('£', ''));
        var totalReceipts = parseFloat($('#total-receipts').text().replace('£', ''));
        var grandTotal = parseFloat($('#grand-total').text().replace('£', ''));
        var variance = grandTotal - targetAmount;

        var data = {
            action: 'hcr_save_petty_cash_count',
            count_date: $('#count_date').val(),
            denominations: denominations,
            receipts: receipts,
            total_counted: totalCash,
            total_receipts: totalReceipts,
            target_amount: targetAmount,
            variance: variance,
            notes: $('#count_notes').val(),
            nonce: '<?php echo wp_create_nonce('hcr_petty_cash_nonce'); ?>'
        };

        $('#save-status').html('<span style="color: blue;">Saving...</span>');

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#save-status').html('<span style="color: green;">✓ Count saved successfully!</span>');

                // Reset form
                $('.petty-cash-qty').val(0);
                $('#receipts-tbody').empty();
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
            action: 'hcr_load_petty_cash_counts',
            offset: append ? loadedRecordsCount : 0,
            limit: recordsPerPage,
            nonce: '<?php echo wp_create_nonce('hcr_petty_cash_nonce'); ?>'
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
                            '<td>£' + parseFloat(count.total_receipts).toFixed(2) + '</td>' +
                            '<td class="' + varianceClass + '">' + varianceText + '</td>' +
                            '<td><span class="status-indicator ' + statusClass + '"></span></td>' +
                            '<td>' +
                                '<button type="button" class="button view-count-btn" data-count-id="' + count.id + '">View</button> ' +
                                '<button type="button" class="button load-receipts-btn" data-count-id="' + count.id + '">Load Receipts</button> ' +
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
            action: 'hcr_get_petty_cash_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_petty_cash_nonce'); ?>'
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
                html += '<h3>Cash Counted</h3>';
                html += '<table class="widefat" style="max-width: 400px;">';
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

                html += '<tr><th colspan="2">Total Cash:</th><th>£' + parseFloat(count.total_counted).toFixed(2) + '</th></tr>';
                html += '</tbody></table>';

                // Receipts
                if (count.receipts && count.receipts.length > 0) {
                    html += '<h3>Receipts</h3>';
                    html += '<table class="widefat" style="max-width: 400px;">';
                    html += '<thead><tr><th>Amount</th><th>Description</th></tr></thead><tbody>';

                    count.receipts.forEach(function(receipt) {
                        html += '<tr>' +
                            '<td>£' + parseFloat(receipt.receipt_value).toFixed(2) + '</td>' +
                            '<td>' + (receipt.receipt_description || '') + '</td>' +
                            '</tr>';
                    });

                    html += '<tr><th>Total Receipts:</th><th>£' + parseFloat(count.total_receipts).toFixed(2) + '</th></tr>';
                    html += '</tbody></table>';
                }

                // Summary
                html += '<h3>Summary</h3>';
                html += '<table class="widefat" style="max-width: 400px; background: #f9f9f9;">';
                html += '<tr><th>Grand Total:</th><td>£' + (parseFloat(count.total_counted) + parseFloat(count.total_receipts)).toFixed(2) + '</td></tr>';
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
            action: 'hcr_get_petty_cash_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_petty_cash_nonce'); ?>'
        };

        $button.text('Loading...').prop('disabled', true);

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data) {
                var count = response.data;

                // Reset form first
                $('.petty-cash-qty').val(0);
                $('#receipts-tbody').empty();

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
                        $('.petty-cash-qty').each(function() {
                            var inputDenom = parseFloat($(this).data('denomination'));
                            if (Math.abs(inputDenom - denomValue) < 0.001) {
                                $(this).val(denom.quantity);
                            }
                        });
                    });
                }

                // Populate receipts
                if (count.receipts && count.receipts.length > 0) {
                    count.receipts.forEach(function(receipt) {
                        addReceiptRow(
                            parseFloat(receipt.receipt_value).toFixed(2),
                            receipt.receipt_description || ''
                        );
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
                    scrollTop: $('.hcr-petty-cash-form').offset().top - 50
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

    // Load receipts from previous count
    $(document).on('click', '.load-receipts-btn', function() {
        var countId = $(this).data('count-id');
        var $button = $(this);

        var data = {
            action: 'hcr_get_petty_cash_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_petty_cash_nonce'); ?>'
        };

        $button.text('Loading...').prop('disabled', true);

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data && response.data.receipts) {
                var receipts = response.data.receipts;

                if (receipts.length === 0) {
                    alert('This count has no receipts to load.');
                    $button.text('Load Receipts').prop('disabled', false);
                    return;
                }

                // Clear existing receipts
                $('#receipts-tbody').empty();

                // Add receipts from the selected count
                receipts.forEach(function(receipt) {
                    addReceiptRow(
                        parseFloat(receipt.receipt_value).toFixed(2),
                        receipt.receipt_description || ''
                    );
                });

                // Show success message
                $('#save-status').html('<span style="color: green;">✓ Loaded ' + receipts.length + ' receipt(s) from previous count.</span>');
                setTimeout(function() {
                    $('#save-status').html('');
                }, 3000);

                // Scroll to receipts section
                $('html, body').animate({
                    scrollTop: $('#receipts-table').offset().top - 100
                }, 500);
            } else {
                alert('Error loading receipts. Please try again.');
            }

            $button.text('Load Receipts').prop('disabled', false);
        }).fail(function() {
            alert('Server error. Please try again.');
            $button.text('Load Receipts').prop('disabled', false);
        });
    });

    // Print modal
    $('#print-modal-btn').on('click', function() {
        // Get the modal content
        var content = $('#modal-content').html();

        // Open a new window for printing
        var printWindow = window.open('', '_blank', 'width=800,height=600');

        // Write the HTML document
        printWindow.document.write('<!DOCTYPE html>');
        printWindow.document.write('<html><head><title>Print Petty Cash Count</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; font-size: 10px; margin: 10mm; }');
        printWindow.document.write('h3 { font-size: 12px; margin: 8px 0 5px 0; }');
        printWindow.document.write('p { margin: 3px 0; font-size: 10px; }');
        printWindow.document.write('table.widefat { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }');
        printWindow.document.write('table.widefat th, table.widefat td { border: 1px solid #000; padding: 4px 6px; text-align: left; }');
        printWindow.document.write('table.widefat th { background: #f0f0f0; font-weight: bold; }');
        printWindow.document.write('table.widefat td:nth-child(2), table.widefat td:nth-child(3) { text-align: right; }');
        printWindow.document.write('.variance-positive { color: green; }');
        printWindow.document.write('.variance-negative { color: red; }');
        printWindow.document.write('.variance-balanced { color: gray; }');
        printWindow.document.write('@media print { body { margin: 8mm; } }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();

        // Wait for content to load, then print
        printWindow.onload = function() {
            printWindow.print();
            // Optional: Close window after printing (commented out to let user review)
            // printWindow.onafterprint = function() { printWindow.close(); };
        };
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

    // Excel-like cell selection for denomination count table
    var selectedCells = [];
    var selectionAnchor = null;
    var isSelecting = false;

    function enhanceTableSelection() {
        $('.hcr-petty-cash-form table.widefat').off('mousedown mouseenter mouseup dblclick');
        $(document).off('copy.pettycash mouseup.pettycash');

        // Mouse down starts selection
        $('.hcr-petty-cash-form table.widefat').on('mousedown', 'td, th', function(e) {
            var $cell = $(this);
            if (e.shiftKey && selectionAnchor) {
                e.preventDefault();
                selectCellRange(selectionAnchor, this);
            } else {
                clearSelection();
                selectionAnchor = this;
                isSelecting = true;
                $cell.addClass('hcr-cell-selected');
                selectedCells = [this];
                updateSelectionTooltip();
            }
        });

        // Mouse enter while selecting extends selection
        $('.hcr-petty-cash-form table.widefat').on('mouseenter', 'td, th', function(e) {
            if (isSelecting && selectionAnchor) {
                selectCellRange(selectionAnchor, this);
            }
        });

        // Mouse up ends selection
        $(document).on('mouseup.pettycash', function() {
            isSelecting = false;
        });

        // Double-click selects cell text
        $('.hcr-petty-cash-form table.widefat').on('dblclick', 'td, th', function() {
            var selection = window.getSelection();
            var range = document.createRange();
            range.selectNodeContents(this);
            selection.removeAllRanges();
            selection.addRange(range);
        });

        // Make cells focusable
        $('.hcr-petty-cash-form table.widefat td, .hcr-petty-cash-form table.widefat th').attr('tabindex', '0');

        // Handle copy (Ctrl+C / Cmd+C)
        $(document).on('copy.pettycash', function(e) {
            if (selectedCells.length > 0 && $(selectedCells[0]).closest('.hcr-petty-cash-form table.widefat').length) {
                e.preventDefault();
                copySelectedCellsToClipboard(e.originalEvent);
            }
        });
    }

    function selectCellRange(start, end) {
        clearSelection();
        var $table = $(start).closest('table');
        var $start = $(start);
        var $end = $(end);
        var $allRows = $table.find('tr');

        var startRow = $allRows.index($start.parent());
        var startCol = $start.index();
        var endRow = $allRows.index($end.parent());
        var endCol = $end.index();

        var minRow = Math.min(startRow, endRow);
        var maxRow = Math.max(startRow, endRow);
        var minCol = Math.min(startCol, endCol);
        var maxCol = Math.max(startCol, endCol);

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

    function updateSelectionTooltip() {
        if (selectedCells.length === 0) {
            $('#selection-tooltip').hide();
            return;
        }

        var count = selectedCells.length;
        var sum = 0;
        var numericCount = 0;

        selectedCells.forEach(function(cell) {
            var text = $(cell).text().trim();
            // Remove currency symbols and commas, then parse
            var value = parseFloat(text.replace(/[£,]/g, ''));
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

    function copySelectedCellsToClipboard(event) {
        if (selectedCells.length === 0) return;

        var $table = $(selectedCells[0]).closest('table');
        var $allRows = $table.find('tr');

        var cellsByRow = {};
        selectedCells.forEach(function(cell) {
            var $cell = $(cell);
            var rowIndex = $allRows.index($cell.parent());
            if (!cellsByRow[rowIndex]) {
                cellsByRow[rowIndex] = [];
            }
            cellsByRow[rowIndex].push({
                col: $cell.index(),
                text: $cell.text().trim()
            });
        });

        var rows = Object.keys(cellsByRow).sort(function(a, b) { return a - b; });
        var clipboardText = '';

        rows.forEach(function(rowIndex) {
            var cells = cellsByRow[rowIndex].sort(function(a, b) { return a.col - b.col; });
            var rowText = cells.map(function(c) { return c.text; }).join('\t');
            clipboardText += rowText + '\n';
        });

        if (event.clipboardData) {
            event.clipboardData.setData('text/plain', clipboardText);
        }
    }

    // Initialize Excel-like selection
    enhanceTableSelection();
});
</script>
