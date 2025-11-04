<?php
/**
 * Admin view for Safe Cash counts
 */

if (!defined('ABSPATH')) {
    exit;
}

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
?>

<div class="wrap">
    <h1>Safe Cash</h1>

    <!-- New Count Form -->
    <div class="hcr-safe-cash-form" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>Safe Cash Count</h2>

        <form id="hcr-safe-cash-count-form">
            <!-- Date/Time -->
            <div style="margin-bottom: 20px;">
                <label for="count_date"><strong>Count Date/Time:</strong></label><br>
                <input type="datetime-local" id="count_date" name="count_date" value="<?php echo date('Y-m-d\TH:i:s'); ?>" step="1" required style="padding: 5px;" tabindex="1">
            </div>

            <!-- Denomination Count Table -->
            <h3>Safe Cash Breakdown</h3>
            <p class="description">Note: Use negative quantities to exchange small denominations with the till for higher denominations.</p>
            <table class="widefat hcr-safe-table" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th>Denomination</th>
                        <th colspan="3" style="text-align: center; background: #f0f0f0;">Current in Safe</th>
                        <th colspan="3" style="text-align: center; background: #e8f4f8;">Adding to Safe</th>
                        <th colspan="3" style="text-align: center; background: #e8f8e8;">New Total</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th style="background: #f0f0f0;">Qty (Input)</th>
                        <th style="background: #f0f0f0;">Qty</th>
                        <th style="background: #f0f0f0;">Value</th>
                        <th style="background: #e8f4f8;">Qty (Input)</th>
                        <th style="background: #e8f4f8;">Qty</th>
                        <th style="background: #e8f4f8;">Value</th>
                        <th style="background: #e8f8e8;">Qty</th>
                        <th style="background: #e8f8e8;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tabindex = 2; // Start at 2 (date/time is 1)
                    foreach ($all_denominations as $denom):
                    ?>
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
                        <!-- Current in Safe -->
                        <td style="background: #f9f9f9;">
                            <input type="number"
                                   class="safe-current-qty"
                                   data-denomination="<?php echo esc_attr($denom); ?>"
                                   value="0"
                                   readonly
                                   tabindex="-1"
                                   style="width: 80px; background: #f0f0f0;">
                        </td>
                        <td class="safe-current-qty-display excel-cell" style="background: #f9f9f9; text-align: right;">0</td>
                        <td class="safe-current-value excel-cell" style="background: #f9f9f9; text-align: right;">£0.00</td>

                        <!-- Adding to Safe -->
                        <td>
                            <input type="number"
                                   class="safe-adding-qty"
                                   data-denomination="<?php echo esc_attr($denom); ?>"
                                   value="0"
                                   tabindex="<?php echo $tabindex++; ?>"
                                   style="width: 80px;">
                        </td>
                        <td class="safe-adding-qty-display excel-cell" style="text-align: right;">0</td>
                        <td class="safe-adding-value excel-cell" style="text-align: right;">£0.00</td>

                        <!-- New Total -->
                        <td class="safe-new-qty excel-cell" style="background: #f0f9f0; font-weight: bold; text-align: right;">0</td>
                        <td class="safe-new-value excel-cell" style="background: #f0f9f0; font-weight: bold; text-align: right;">£0.00</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f9f9f9; font-weight: bold;">
                        <th>Totals:</th>
                        <th style="background: #f0f0f0;"></th>
                        <th style="background: #f0f0f0;"></th>
                        <th id="total-current-value" class="excel-cell" style="background: #f0f0f0; text-align: right;">£0.00</th>
                        <th style="background: #e8f4f8;"></th>
                        <th style="background: #e8f4f8;"></th>
                        <th id="total-adding-value" class="excel-cell" style="background: #e8f4f8; text-align: right;">£0.00</th>
                        <th style="background: #e8f8e8;"></th>
                        <th id="total-new-value" class="excel-cell" style="background: #e8f8e8; text-align: right;">£0.00</th>
                    </tr>
                </tfoot>
            </table>

            <!-- Notes -->
            <div style="margin-bottom: 20px;">
                <label for="count_notes"><strong>Notes:</strong></label><br>
                <textarea id="count_notes" name="count_notes" rows="3" style="width: 100%; max-width: 800px;" tabindex="<?php echo 2 + count($all_denominations); ?>"></textarea>
            </div>

            <!-- Save Button -->
            <button type="submit" class="button button-primary button-large">Save Count to Safe</button>
            <button type="button" id="take-to-bank-btn" class="button button-large" style="margin-left: 10px;">Take to Bank</button>
            <span id="save-status" style="margin-left: 15px;"></span>
        </form>
    </div>

    <!-- History Section -->
    <div class="hcr-safe-cash-history" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>Count History</h2>

        <table class="widefat" id="safe-cash-history-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Total in Safe</th>
                    <th>Total Banked</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="history-tbody">
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">Loading...</td>
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
    <div style="background-color: #fff; margin: 50px auto; padding: 20px; width: 90%; max-width: 800px; max-height: 80%; overflow-y: auto; border-radius: 5px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;" class="no-print">
            <h2>Count Details</h2>
            <button type="button" class="button" id="close-modal-btn">&times; Close</button>
        </div>
        <div id="modal-content">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<style>
/* Excel-like cell selection */
.excel-cell {
    cursor: cell;
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

.excel-cell.hcr-cell-selected {
    background-color: #cce4ff !important;
    outline: 2px solid #0078d4;
    outline-offset: -2px;
}

.hcr-safe-table .excel-cell:hover {
    box-shadow: inset 0 0 0 1px #4a90e2;
}

@media print {
    /* Hide everything except modal content */
    body * {
        visibility: hidden;
    }

    #modal-print-content,
    #modal-print-content * {
        visibility: visible;
    }

    #modal-print-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
    }

    /* Hide modal overlay and close button */
    #view-count-modal {
        position: static !important;
        background: none !important;
    }

    .no-print {
        display: none !important;
    }

    /* Style table for print */
    .modal-denom-table {
        border-collapse: collapse;
        width: auto !important;
        max-width: 500px;
    }

    .modal-denom-table th,
    .modal-denom-table td {
        border: 1px solid #000 !important;
        padding: 8px !important;
    }

    .modal-denom-table thead th {
        background: #f0f0f0 !important;
        font-weight: bold;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var loadedRecordsCount = 0;
    var recordsPerPage = 10;

    // Calculate totals
    function calculateTotals() {
        var totalCurrentValue = 0;
        var totalAddingValue = 0;
        var totalNewValue = 0;

        $('.safe-current-qty').each(function() {
            var row = $(this).closest('tr');
            var denom = parseFloat($(this).data('denomination'));

            // Current in Safe
            var currentQty = parseInt($(this).val()) || 0;
            var currentValue = currentQty * denom;
            row.find('.safe-current-qty-display').text(currentQty);
            row.find('.safe-current-value').text('£' + currentValue.toFixed(2));
            totalCurrentValue += currentValue;

            // Adding to Safe
            var addingQty = parseInt(row.find('.safe-adding-qty').val()) || 0;
            var addingValue = addingQty * denom;
            row.find('.safe-adding-qty-display').text(addingQty);
            row.find('.safe-adding-value').text('£' + addingValue.toFixed(2));
            totalAddingValue += addingValue;

            // New Total
            var newQty = currentQty + addingQty;
            var newValue = newQty * denom;
            row.find('.safe-new-qty').text(newQty);
            row.find('.safe-new-value').text('£' + newValue.toFixed(2));
            totalNewValue += newValue;
        });

        // Update totals row (only values, not quantities)
        $('#total-current-value').text('£' + totalCurrentValue.toFixed(2));
        $('#total-adding-value').text('£' + totalAddingValue.toFixed(2));
        $('#total-new-value').text('£' + totalNewValue.toFixed(2));
    }

    // Recalculate on input change
    $(document).on('input', '.safe-adding-qty', function() {
        calculateTotals();
    });

    // Select all text on click for easy replacement
    $(document).on('click', '.safe-adding-qty', function() {
        $(this).select();
    });

    // Save count
    $('#hcr-safe-cash-count-form').on('submit', function(e) {
        e.preventDefault();

        // Gather denomination data (save NEW TOTAL quantities and values)
        var denominations = [];
        $('.safe-current-qty').each(function() {
            var row = $(this).closest('tr');
            var denom = parseFloat($(this).data('denomination'));
            var newQty = parseInt(row.find('.safe-new-qty').text()) || 0;

            // Save all non-zero quantities (including negative values for exchanges)
            if (newQty !== 0) {
                denominations.push({
                    denomination: denom,
                    quantity: newQty,
                    total: newQty * denom
                });
            }
        });

        var totalNewValue = parseFloat($('#total-new-value').text().replace('£', '').replace(/,/g, ''));

        var data = {
            action: 'hcr_save_safe_cash_count',
            count_date: $('#count_date').val(),
            denominations: denominations,
            total_counted: totalNewValue,
            notes: $('#count_notes').val(),
            nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
        };

        $('#save-status').html('<span style="color: blue;">Saving...</span>');

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#save-status').html('<span style="color: green;">✓ Count saved successfully!</span>');

                // Reset adding section
                $('.safe-adding-qty').val(0);
                $('#count_notes').val('');

                // Set count_date to current time with seconds
                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                $('#count_date').val(year + '-' + month + '-' + day + 'T' + hours + ':' + minutes + ':' + seconds);

                // Move new totals to current
                $('.safe-current-qty').each(function() {
                    var row = $(this).closest('tr');
                    var newQty = row.find('.safe-new-qty').text();
                    $(this).val(newQty);
                });

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

    // Take to Bank button
    $('#take-to-bank-btn').on('click', function() {
        // Confirm action
        if (!confirm('This will record the current safe total as banked and reset the safe to £0.00. Continue?')) {
            return;
        }

        var countDate = $('#count_date').val();
        var notes = $('#count_notes').val();

        // Get current safe total (from "Current in Safe" column)
        var currentTotal = 0;
        $('.safe-current-qty').each(function() {
            var qty = parseInt($(this).val()) || 0;
            var denom = parseFloat($(this).data('denomination'));
            currentTotal += qty * denom;
        });

        if (currentTotal === 0) {
            alert('There is no cash in the safe to bank.');
            return;
        }

        // Gather denomination data from CURRENT section
        var denominations = [];
        $('.safe-current-qty').each(function() {
            var qty = parseInt($(this).val()) || 0;
            var denom = parseFloat($(this).data('denomination'));

            if (qty > 0) {
                denominations.push({
                    denomination: denom,
                    quantity: qty,
                    total: qty * denom
                });
            }
        });

        var data = {
            action: 'hcr_save_safe_cash_count',
            count_date: countDate,
            denominations: denominations,
            total_counted: currentTotal,
            notes: '[BANKED] ' + notes, // Mark as bank action
            nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
        };

        $('#save-status').html('<span style="color: blue;">Banking cash...</span>');

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#save-status').html('<span style="color: green;">✓ Cash banked successfully. Safe reset to £0.00.</span>');

                // Reset all inputs to 0
                $('.safe-current-qty').val(0);
                $('.safe-adding-qty').val(0);
                $('#count_notes').val('');

                // Reset date/time
                $('#count_date').val('<?php echo date('Y-m-d\TH:i'); ?>');

                calculateTotals();

                // Reload history
                loadHistory(false);

                setTimeout(function() {
                    $('#save-status').html('');
                }, 5000);
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
            action: 'hcr_load_safe_cash_counts',
            offset: append ? loadedRecordsCount : 0,
            limit: recordsPerPage,
            nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
        };

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data.counts) {
                var counts = response.data.counts;
                var html = '';

                if (counts.length === 0 && !append) {
                    html = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No counts found.</td></tr>';
                } else {
                    counts.forEach(function(count) {
                        var isBank = count.notes && count.notes.indexOf('[BANKED]') === 0;
                        var safeAmount = isBank ? '£0.00' : '£' + parseFloat(count.total_counted).toFixed(2);
                        var bankedAmount = isBank ? '£' + parseFloat(count.total_counted).toFixed(2) : '—';

                        html += '<tr>' +
                            '<td>' + count.count_date + '</td>' +
                            '<td>' + safeAmount + '</td>' +
                            '<td>' + bankedAmount + '</td>' +
                            '<td>' +
                                '<button type="button" class="button view-count-btn" data-count-id="' + count.id + '">View</button> ' +
                                (isBank ? '' : '<button type="button" class="button button-primary load-for-edit-btn" data-count-id="' + count.id + '">Preload Count</button>') +
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
            action: 'hcr_get_safe_cash_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
        };

        $('#modal-content').html('<p>Loading...</p>');
        $('#view-count-modal').show();

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data) {
                var count = response.data;
                var isBank = count.notes && count.notes.indexOf('[BANKED]') === 0;
                var displayNotes = isBank ? count.notes.replace('[BANKED] ', '') : count.notes;

                var html = '<div id="modal-print-content">';
                html += '<p><strong>Date/Time:</strong> ' + count.count_date + '</p>';
                html += '<p><strong>Created By:</strong> ' + (count.created_by_name || 'Unknown') + '</p>';

                // Denominations - show ALL denominations
                html += '<h3>' + (isBank ? 'Taken to Bank' : 'Safe Cash Breakdown') + '</h3>';
                html += '<table class="widefat modal-denom-table">';
                html += '<thead><tr><th>Denomination</th><th>Qty</th><th>Total</th></tr></thead><tbody>';

                // Build denomination map from count data
                var denomMap = {};
                if (count.denominations && count.denominations.length > 0) {
                    count.denominations.forEach(function(denom) {
                        denomMap[parseFloat(denom.denomination_value).toFixed(2)] = {
                            quantity: parseInt(denom.quantity),
                            total: parseFloat(denom.total_amount)
                        };
                    });
                }

                // Show all denominations
                var allDenoms = <?php echo json_encode($all_denominations); ?>;
                var grandTotal = 0;

                allDenoms.forEach(function(denom) {
                    var denomKey = denom.toFixed(2);
                    var qty = denomMap[denomKey] ? denomMap[denomKey].quantity : 0;
                    var total = denomMap[denomKey] ? denomMap[denomKey].total : 0;
                    var denomLabel = denom >= 1 ? '£' + denom.toFixed(0) : (denom * 100).toFixed(0) + 'p';

                    grandTotal += total;

                    html += '<tr>' +
                        '<td>' + denomLabel + '</td>' +
                        '<td>' + qty + '</td>' +
                        '<td>£' + total.toFixed(2) + '</td>' +
                        '</tr>';
                });

                html += '<tr style="background: #f9f9f9;"><th colspan="2">' + (isBank ? 'Total Banked:' : 'Total in Safe:') + '</th><th>£' + grandTotal.toFixed(2) + '</th></tr>';
                html += '</tbody></table>';

                // Notes
                if (displayNotes) {
                    html += '<h3>Notes</h3>';
                    html += '<p>' + displayNotes + '</p>';
                }

                html += '<div class="modal-buttons no-print" style="margin-top: 20px;">';
                html += '<button type="button" class="button" onclick="window.print();">Print</button>';
                html += '</div>';

                html += '</div>';

                $('#modal-content').html(html);
            } else {
                $('#modal-content').html('<p style="color: red;">Error loading count details.</p>');
            }
        });
    });

    // Load count for editing (preload into current section)
    $(document).on('click', '.load-for-edit-btn', function() {
        var countId = $(this).data('count-id');
        var $button = $(this);

        var data = {
            action: 'hcr_get_safe_cash_count',
            count_id: countId,
            nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
        };

        $button.text('Loading...').prop('disabled', true);

        $.post(ajaxurl, data, function(response) {
            if (response.success && response.data) {
                var count = response.data;

                // Reset form first
                $('.safe-current-qty').val(0);
                $('.safe-adding-qty').val(0);

                // Set date/time to current time
                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var currentDateTime = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
                $('#count_date').val(currentDateTime);

                // Populate CURRENT section with saved denominations
                if (count.denominations && count.denominations.length > 0) {
                    count.denominations.forEach(function(denom) {
                        var denomValue = parseFloat(denom.denomination_value);

                        // Find input by comparing denomination values as floats
                        $('.safe-current-qty').each(function() {
                            var inputDenom = parseFloat($(this).data('denomination'));
                            if (Math.abs(inputDenom - denomValue) < 0.001) {
                                $(this).val(denom.quantity);
                            }
                        });
                    });
                }

                // Clear notes (fresh count)
                $('#count_notes').val('');

                // Recalculate totals
                calculateTotals();

                // Show success message
                $('#save-status').html('<span style="color: green;">✓ Count preloaded to Current in Safe. Add new cash and save.</span>');
                setTimeout(function() {
                    $('#save-status').html('');
                }, 5000);

                // Scroll to form
                $('html, body').animate({
                    scrollTop: $('.hcr-safe-cash-form').offset().top - 50
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

    // Excel-like cell selection for safe cash table
    var selectedCells = [];
    var selectionAnchor = null;
    var isSelecting = false;

    function enhanceTableSelection() {
        $('.hcr-safe-table').off('mousedown mouseenter mouseup dblclick');
        $(document).off('copy.safecash mouseup.safecash');

        // Mouse down starts selection
        $('.hcr-safe-table').on('mousedown', '.excel-cell', function(e) {
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
            }
        });

        // Mouse enter while selecting extends selection
        $('.hcr-safe-table').on('mouseenter', '.excel-cell', function(e) {
            if (isSelecting && selectionAnchor) {
                selectCellRange(selectionAnchor, this);
            }
        });

        // Mouse up ends selection
        $(document).on('mouseup.safecash', function() {
            isSelecting = false;
        });

        // Double-click selects cell text
        $('.hcr-safe-table').on('dblclick', '.excel-cell', function() {
            var selection = window.getSelection();
            var range = document.createRange();
            range.selectNodeContents(this);
            selection.removeAllRanges();
            selection.addRange(range);
        });

        // Make cells focusable
        $('.hcr-safe-table .excel-cell').attr('tabindex', '0');

        // Handle copy (Ctrl+C / Cmd+C)
        $(document).on('copy.safecash', function(e) {
            if (selectedCells.length > 0 && $(selectedCells[0]).closest('.hcr-safe-table').length) {
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
                if (cell && $(cell).hasClass('excel-cell')) {
                    $(cell).addClass('hcr-cell-selected');
                    selectedCells.push(cell);
                }
            }
        }
    }

    function clearSelection() {
        $('.hcr-cell-selected').removeClass('hcr-cell-selected');
        selectedCells = [];
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

    // Check for transfer from cash count summary
    function checkTransferParameters() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('transfer') === '1') {
            // Load denomination parameters into "Adding to Safe" inputs
            var loadedCount = 0;
            $('.safe-adding-qty').each(function() {
                var $input = $(this);
                var denom = parseFloat($input.data('denomination'));
                var paramName = 'denom_' + denom.toFixed(2).replace(/\./g, '_');  // Replace . with _ to match URL format
                var qty = urlParams.get(paramName);
                if (qty) {
                    $input.val(parseInt(qty));
                    loadedCount++;
                }
            });

            // Recalculate totals immediately to show the "Adding to Safe" values
            calculateTotals();

            // Load the last safe cash count into "Current in Safe"
            $.post(ajaxurl, {
                action: 'hcr_load_safe_cash_counts',
                offset: 0,
                limit: 1,
                nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
            }, function(response) {
                if (response.success && response.data.counts && response.data.counts.length > 0) {
                    var lastCount = response.data.counts[0];

                    // Get denominations for the last count
                    $.post(ajaxurl, {
                        action: 'hcr_get_safe_cash_count',
                        count_id: lastCount.id,
                        nonce: '<?php echo wp_create_nonce('hcr_safe_cash_nonce'); ?>'
                    }, function(countResponse) {
                        if (countResponse.success && countResponse.data) {
                            var count = countResponse.data;

                            // Build denomination map
                            var denomMap = {};
                            if (count.denominations && count.denominations.length > 0) {
                                count.denominations.forEach(function(denom) {
                                    var denomKey = parseFloat(denom.denomination_value).toFixed(2);
                                    denomMap[denomKey] = parseInt(denom.quantity);
                                });
                            }

                            // Load into "Current in Safe" inputs
                            $('.safe-current-qty').each(function() {
                                var $input = $(this);
                                var denom = parseFloat($input.data('denomination'));
                                var denomKey = denom.toFixed(2);
                                var qty = denomMap[denomKey] || 0;
                                $input.val(qty);
                            });

                            // Recalculate totals to show combined values
                            calculateTotals();

                            // Show a message
                            alert('Safe cash preloaded with last count. Cash count summary loaded into "Adding to Safe" section.');
                        }
                    });
                } else {
                    // Still show message even if no previous count
                    alert('Cash count summary loaded into "Adding to Safe" section.');
                }
            });

            // Clean URL (remove parameters)
            window.history.replaceState({}, document.title, window.location.pathname + '?page=hotel-cash-up-reconciliation-safe-cash');
        }
    }

    // Initial load
    loadHistory(false);
    calculateTotals();
    checkTransferParameters();
});
</script>
