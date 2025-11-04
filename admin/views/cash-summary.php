<?php
/**
 * Admin view for Cash Count Summary
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
    <h1>Cash Count Summary</h1>

    <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>Generate Cash Summary Report</h2>

        <form id="hcr-cash-summary-form" style="margin-bottom: 20px;">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="date_from">From Date:</label></th>
                    <td>
                        <input type="date" id="date_from" name="date_from" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="date_to">To Date:</label></th>
                    <td>
                        <input type="date" id="date_to" name="date_to" value="<?php echo date('Y-m-d'); ?>" required>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary button-large">Generate Summary</button>
                <span id="load-status" style="margin-left: 15px;"></span>
            </p>
        </form>

        <div id="summary-results" style="display: none;">
            <h2>Summary Results</h2>
            <p id="summary-period"></p>
            <p class="description">Copy and paste into Excel.
                <strong>Excel-like selection:</strong> Click cell, then Shift+Click to select range. Drag to select multiple cells. Ctrl+C to copy.</p>

            <table class="hcr-report-table" id="summary-table" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th>Denomination</th>
                        <th style="text-align: right;">Total Quantity</th>
                        <th style="text-align: right;">Total Value</th>
                    </tr>
                </thead>
                <tbody id="summary-tbody">
                    <!-- Results will be populated here -->
                </tbody>
                <tfoot>
                    <tr style="background: #f9f9f9; font-weight: bold;">
                        <th>Grand Total:</th>
                        <th style="text-align: right;" id="total-quantity">0</th>
                        <th style="text-align: right;" id="total-value">£0.00</th>
                    </tr>
                </tfoot>
            </table>

            <div style="margin-top: 20px;">
                <button type="button" class="button" onclick="window.print();">Print Summary</button>
                <button type="button" id="transfer-to-safe-btn" class="button button-primary" style="margin-left: 10px;">Transfer to Safe</button>
            </div>
        </div>

        <div id="no-results" style="display: none; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; margin-top: 20px;">
            <p><strong>No cash up records found for the selected date range.</strong></p>
            <p>Please select a different date range and try again.</p>
        </div>
    </div>

    <!-- Selection Tooltip -->
    <div id="selection-tooltip" style="display: none; position: fixed; bottom: 20px; right: 20px; background: #f9f9f9; border: 2px solid #0078d4; padding: 10px 15px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 10000; font-size: 13px;">
        <div id="tooltip-content"></div>
    </div>
</div>

<style>
/* Excel-like cell selection */
.hcr-report-table th,
.hcr-report-table td {
    border: 1px solid #ccc;
    padding: 8px;
    cursor: cell;
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

.hcr-report-table td.hcr-cell-selected,
.hcr-report-table th.hcr-cell-selected {
    background-color: #cce4ff !important;
    outline: 2px solid #0078d4;
    outline-offset: -2px;
}

.hcr-report-table tbody td:hover {
    box-shadow: inset 0 0 0 1px #4a90e2;
}

@media print {
    .wrap h1,
    #hcr-cash-summary-form,
    .button {
        display: none;
    }

    #summary-results {
        display: block !important;
    }

    table {
        border-collapse: collapse;
    }

    table, th, td {
        border: 1px solid #000;
    }

    th, td {
        padding: 8px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {

    // Generate summary
    $('#hcr-cash-summary-form').on('submit', function(e) {
        e.preventDefault();

        var dateFrom = $('#date_from').val();
        var dateTo = $('#date_to').val();

        if (!dateFrom || !dateTo) {
            alert('Please select both From and To dates.');
            return;
        }

        // Validate date range
        if (new Date(dateFrom) > new Date(dateTo)) {
            alert('From date must be before or equal to To date.');
            return;
        }

        var data = {
            action: 'hcr_generate_cash_summary',
            date_from: dateFrom,
            date_to: dateTo,
            nonce: '<?php echo wp_create_nonce('hcr_cash_summary_nonce'); ?>'
        };

        $('#load-status').html('<span style="color: blue;">Generating summary...</span>');
        $('#summary-results').hide();
        $('#no-results').hide();

        $.post(ajaxurl, data, function(response) {
            $('#load-status').html('');

            if (response.success && response.data) {
                var denominations = response.data.denominations;
                var period = response.data.period;

                if (!denominations || Object.keys(denominations).length === 0) {
                    $('#no-results').show();
                    return;
                }

                // Update period display
                $('#summary-period').html('<strong>Period:</strong> ' + period.from + ' to ' + period.to);

                // Build table rows
                var html = '';
                var totalQuantity = 0;
                var totalValue = 0;

                // Define denomination order (same as PHP)
                var allDenoms = <?php echo json_encode($all_denominations); ?>;

                allDenoms.forEach(function(denom) {
                    var denomKey = denom.toFixed(2);

                    // Get quantity and value (default to 0 if not counted)
                    var qty = 0;
                    var value = 0;

                    if (denominations[denomKey]) {
                        qty = parseInt(denominations[denomKey].quantity) || 0;
                        value = parseFloat(denominations[denomKey].value) || 0;
                    }

                    totalQuantity += qty;
                    totalValue += value;

                    // Format denomination label
                    var denomLabel;
                    if (denom >= 1) {
                        denomLabel = '£' + denom.toFixed(0);
                    } else {
                        denomLabel = (denom * 100).toFixed(0) + 'p';
                    }

                    html += '<tr>' +
                        '<td>' + denomLabel + '</td>' +
                        '<td style="text-align: right;">' + qty.toLocaleString() + '</td>' +
                        '<td style="text-align: right;">£' + value.toFixed(2) + '</td>' +
                        '</tr>';
                });

                $('#summary-tbody').html(html);
                $('#total-quantity').text(totalQuantity.toLocaleString());
                $('#total-value').text('£' + totalValue.toFixed(2));

                $('#summary-results').show();

                // Initialize Excel-like selection after table render
                setTimeout(function() {
                    enhanceTableSelection();
                }, 100);
            } else {
                $('#no-results').show();
            }
        }).fail(function() {
            $('#load-status').html('<span style="color: red;">✗ Server error. Please try again.</span>');
        });
    });

    // Transfer to Safe button
    $('#transfer-to-safe-btn').on('click', function() {
        // Collect denomination quantities from the table
        var denominations = {};
        $('#summary-tbody tr').each(function() {
            var $row = $(this);
            var $cells = $row.children('td');
            if ($cells.length >= 2) {
                var denomText = $cells.eq(0).text().trim(); // e.g., "£50" or "20p"
                var qtyText = $cells.eq(1).text().trim().replace(/,/g, ''); // Remove commas
                var qty = parseInt(qtyText) || 0;

                // Parse denomination value
                var denomValue = 0;
                if (denomText.indexOf('£') === 0) {
                    // Note denomination
                    denomValue = parseFloat(denomText.substring(1));
                } else if (denomText.indexOf('p') > 0) {
                    // Coin denomination
                    denomValue = parseFloat(denomText.substring(0, denomText.length - 1)) / 100;
                }

                if (denomValue > 0 && qty > 0) {
                    denominations[denomValue.toFixed(2)] = qty;
                }
            }
        });

        // Build URL parameters - replace decimal points with underscores for URL safety
        var params = [];
        for (var denom in denominations) {
            var paramName = 'denom_' + denom.replace(/\./g, '_');  // Replace . with _
            params.push(paramName + '=' + encodeURIComponent(denominations[denom]));
        }
        params.push('transfer=1');

        // Navigate to Safe Cash page with parameters
        var safeCashUrl = '<?php echo admin_url('admin.php?page=hotel-cash-up-reconciliation-safe-cash'); ?>';
        if (params.length > 0) {
            safeCashUrl += '&' + params.join('&');
        }

        window.location.href = safeCashUrl;
    });

    // Excel-like cell selection
    var selectedCells = [];
    var selectionAnchor = null;
    var isSelecting = false;

    function enhanceTableSelection() {
        $('.hcr-report-table').off('mousedown mouseenter mouseup dblclick keydown');
        $(document).off('copy.hcr mouseup.hcr');

        // Mouse down starts selection
        $('.hcr-report-table').on('mousedown', 'td, th', function(e) {
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
        $('.hcr-report-table').on('mouseenter', 'td, th', function(e) {
            if (isSelecting && selectionAnchor) {
                selectCellRange(selectionAnchor, this);
            }
        });

        // Mouse up ends selection
        $(document).on('mouseup.hcr', function() {
            isSelecting = false;
        });

        // Double-click selects cell text
        $('.hcr-report-table').on('dblclick', 'td, th', function() {
            var selection = window.getSelection();
            var range = document.createRange();
            range.selectNodeContents(this);
            selection.removeAllRanges();
            selection.addRange(range);
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
});
</script>
