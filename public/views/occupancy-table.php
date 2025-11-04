<?php
/**
 * Public view for Occupancy Statistics Table
 */

if (!defined('ABSPATH')) {
    exit;
}

// Parse shortcode attributes (passed from parent)
$start_date = isset($atts['start_date']) ? $atts['start_date'] : date('Y-m-d', strtotime('monday this week'));
$num_days = isset($atts['days']) ? intval($atts['days']) : 7;
?>

<div class="hcr-public-cash-up-form hcr-occupancy-public">
    <h2>Occupancy Statistics</h2>

    <!-- Report Parameters -->
    <div style="background: #f9f9f9; padding: 11px; margin-bottom: 14px; border: 1px solid #ddd; border-radius: 3px;">
        <div class="hcr-form-row">
            <label for="occupancy_start_date_public"><strong>Start Date:</strong></label>
            <input type="date" id="occupancy_start_date_public" value="<?php echo esc_attr($start_date); ?>" style="margin-left: 7px;">
        </div>
        <div class="hcr-form-row">
            <label for="occupancy_num_days_public"><strong>Number of Days:</strong></label>
            <input type="number" id="occupancy_num_days_public" value="<?php echo esc_attr($num_days); ?>" min="1" max="90" style="margin-left: 7px; width: 80px;">
            <span class="hcr-help-text" style="margin-left: 7px;">(1-90 days)</span>
        </div>
        <div class="hcr-form-row">
            <button type="button" id="hcr-load-occupancy-public" class="hcr-button-primary">Load Occupancy Data</button>
            <span id="hcr-occupancy-status-public" style="margin-left: 11px;"></span>
        </div>
    </div>

    <!-- Occupancy Table -->
    <div id="hcr-occupancy-table-container-public" style="display: none;">
        <p class="hcr-help-text">Days in ascending order. Shows room occupancy, guest count, revenue metrics, and booking statistics.</p>

        <div style="overflow-x: auto;">
            <table class="hcr-denomination-table" id="hcr-occupancy-table-public" style="min-width: 100%;">
                <thead>
                    <tr id="hcr-occupancy-header-public">
                        <th>Metric</th>
                        <!-- Date columns populated by JavaScript -->
                    </tr>
                </thead>
                <tbody id="hcr-occupancy-data-public">
                    <!-- Populated by JavaScript -->
                </tbody>
                <tfoot>
                    <tr id="hcr-occupancy-averages-public" class="hcr-total-row">
                        <th>TOTAL (AVERAGE)</th>
                        <!-- Totals and averages populated by JavaScript -->
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var currentData = null;
    var earnedRevenue = null;
    var glAccounts = null;
    var occupancyByDate = {};
    var roomCategoryCounts = {};
    var dailyStats = {};

    // Format money
    function formatMoney(amount) {
        return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Load occupancy data
    function loadOccupancyData() {
        var startDate = $('#occupancy_start_date_public').val();
        var numDays = parseInt($('#occupancy_num_days_public').val());

        if (!startDate || numDays < 1 || numDays > 90) {
            alert('Please enter a valid start date and number of days (1-90).');
            return;
        }

        $('#hcr-occupancy-status-public').html('<span style="color: #0073aa;">Loading data...</span>');
        $('#hcr-load-occupancy-public').prop('disabled', true);
        $('#hcr-occupancy-table-container-public').hide();

        $.ajax({
            url: hcrPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_occupancy_report',
                nonce: hcrAdmin.nonce,
                start_date: startDate,
                num_days: numDays
            },
            success: function(response) {
                if (response.success && response.data) {
                    currentData = response.data.days || [];
                    earnedRevenue = response.data.earned_revenue || [];
                    glAccounts = response.data.gl_accounts || [];
                    occupancyByDate = response.data.occupancy_by_date || {};
                    roomCategoryCounts = response.data.room_category_counts || {};
                    dailyStats = response.data.daily_stats || {};

                    renderOccupancyTable();
                    $('#hcr-occupancy-status-public').html('<span style="color: #46b450;">✓ Data loaded</span>');
                    $('#hcr-occupancy-table-container-public').fadeIn();
                } else {
                    $('#hcr-occupancy-status-public').html('<span style="color: #dc3232;">Error: ' + (response.data ? response.data.message : 'Failed to load data') + '</span>');
                }
            },
            error: function() {
                $('#hcr-occupancy-status-public').html('<span style="color: #dc3232;">Error loading data. Please try again.</span>');
            },
            complete: function() {
                $('#hcr-load-occupancy-public').prop('disabled', false);
            }
        });
    }

    // Render occupancy table
    function renderOccupancyTable() {
        var header = $('#hcr-occupancy-header-public');
        var tbody = $('#hcr-occupancy-data-public');
        var footer = $('#hcr-occupancy-averages-public');

        // Clear existing content
        header.find('th:not(:first)').remove();
        tbody.empty();
        footer.find('th:not(:first), td').remove();

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

                if (!glGroupMap[glGroupId]) {
                    glGroupMap[glGroupId] = {
                        group_name: glGroupName
                    };
                }
            });
        }

        // Build accommodation revenue by date from earnedRevenue
        var accomRevenueByDate = {};
        if (earnedRevenue && Array.isArray(earnedRevenue)) {
            earnedRevenue.forEach(function(item) {
                var period = item.period ? item.period.substring(0, 10) : '';
                var glGroupId = item.gl_group_id;
                var amountNet = parseFloat(item.earned_revenue_ex || 0);

                if (!period || !glGroupId) return;

                var groupName = glGroupMap[glGroupId] ? glGroupMap[glGroupId].group_name : '';

                // Check if this is accommodation revenue (ACC group or contains "Accommodation")
                if (groupName.indexOf('ACC') === 0 || groupName.toLowerCase().indexOf('accommodation') !== -1) {
                    if (!accomRevenueByDate[period]) {
                        accomRevenueByDate[period] = 0;
                    }
                    accomRevenueByDate[period] += amountNet;
                }
            });
        }

        // Get total rooms available
        var totalRooms = 0;
        for (var catId in roomCategoryCounts) {
            totalRooms += roomCategoryCounts[catId].count || 0;
        }

        // Initialize totals
        var totals = {
            rooms: 0,
            occupancy: 0,
            netAccom: 0,
            revpar: 0,
            occupancyPct: 0,
            ggr: 0,
            leadTime: 0,
            count: 0
        };

        // Iterate through each date
        currentData.forEach(function(day) {
            var date = day.date;
            var row = $('<tr></tr>');

            // Date
            row.append('<td>' + date + '</td>');

            // Rooms Occupied with tooltip
            var roomsOccupied = occupancyByDate[date] ? occupancyByDate[date].total : 0;
            var roomsTooltip = 'Occupied: ' + roomsOccupied + '\n\nBy Category:';
            if (occupancyByDate[date]) {
                for (var catId in occupancyByDate[date].by_category) {
                    var count = occupancyByDate[date].by_category[catId];
                    var catName = roomCategoryCounts[catId] ? roomCategoryCounts[catId].name : 'Unknown';
                    roomsTooltip += '\n' + catName + ': ' + count + ' occupied';
                }
            }
            var roomsCell = $('<td class="hcr-has-tooltip">' + roomsOccupied + '</td>');
            roomsCell.attr('data-tooltip', roomsTooltip);
            row.append(roomsCell);

            // People Occupancy with tooltip
            var peopleOccupancy = dailyStats[date] ? dailyStats[date].totalPeople : 0;
            var adults = dailyStats[date] ? dailyStats[date].totalAdults : 0;
            var children = dailyStats[date] ? dailyStats[date].totalChildren : 0;
            var infants = dailyStats[date] ? dailyStats[date].totalInfants : 0;

            var occupancyTooltip = 'Total People: ' + peopleOccupancy +
                                  '\nAdults: ' + adults +
                                  '\nChildren: ' + children +
                                  '\nInfants: ' + infants +
                                  '\n\nBy Category:';
            if (dailyStats[date]) {
                for (var catId in dailyStats[date].byCategory) {
                    var cat = dailyStats[date].byCategory[catId];
                    var catName = cat.name || (roomCategoryCounts[catId] ? roomCategoryCounts[catId].name : 'Unknown');
                    occupancyTooltip += '\n' + catName + ': ' + cat.totalPeople +
                                       ' people (' + cat.adults + 'A, ' + cat.children + 'C, ' + cat.infants + 'I)';
                }
            }
            var occupancyCell = $('<td class="hcr-has-tooltip">' + peopleOccupancy + '</td>');
            occupancyCell.attr('data-tooltip', occupancyTooltip);
            row.append(occupancyCell);

            // Net Accommodation Revenue
            var netAccomRevenue = accomRevenueByDate[date] || 0;
            row.append('<td>£' + formatMoney(netAccomRevenue) + '</td>');

            // Average Net Accom
            var aveNetAccom = roomsOccupied > 0 ? netAccomRevenue / roomsOccupied : 0;
            row.append('<td>£' + formatMoney(aveNetAccom) + '</td>');

            // REVPAR
            var revpar = totalRooms > 0 ? netAccomRevenue / totalRooms : 0;
            row.append('<td>£' + formatMoney(revpar) + '</td>');

            // Occupancy % with tooltip
            var occupancyPct = totalRooms > 0 ? (roomsOccupied / totalRooms * 100) : 0;
            var occupancyPctTooltip = 'Overall Occupancy: ' + occupancyPct.toFixed(1) + '%\n(' + roomsOccupied + '/' + totalRooms + ' rooms)\n\nBy Category:';
            if (occupancyByDate[date]) {
                for (var catId in occupancyByDate[date].by_category) {
                    var catOccupied = occupancyByDate[date].by_category[catId];
                    var catTotalRooms = roomCategoryCounts[catId] ? roomCategoryCounts[catId].count : 0;
                    var catOccupancyPct = catTotalRooms > 0 ? (catOccupied / catTotalRooms * 100) : 0;
                    var catName = roomCategoryCounts[catId] ? roomCategoryCounts[catId].name : 'Unknown';
                    occupancyPctTooltip += '\n' + catName + ': ' + catOccupancyPct.toFixed(1) + '% (' + catOccupied + '/' + catTotalRooms + ' rooms)';
                }
            }
            var occupancyPctCell = $('<td class="hcr-has-tooltip">' + occupancyPct.toFixed(1) + '%</td>');
            occupancyPctCell.attr('data-tooltip', occupancyPctTooltip);
            row.append(occupancyPctCell);

            // GGR with tooltip
            var totalGuestRate = 0;
            var ggrRoomCount = 0;
            if (dailyStats[date]) {
                for (var catId in dailyStats[date].byCategory) {
                    totalGuestRate += dailyStats[date].byCategory[catId].totalRate;
                    ggrRoomCount += dailyStats[date].byCategory[catId].roomCount;
                }
            }
            var ggr = ggrRoomCount > 0 ? totalGuestRate / ggrRoomCount : 0;
            var ggrTooltip = 'Average Guest Rate: £' + formatMoney(ggr) + '\n\nBy Category:';
            if (dailyStats[date]) {
                for (var catId in dailyStats[date].byCategory) {
                    var cat = dailyStats[date].byCategory[catId];
                    var catAvg = cat.roomCount > 0 ? cat.totalRate / cat.roomCount : 0;
                    var catName = roomCategoryCounts[catId] ? roomCategoryCounts[catId].name : 'Unknown';
                    ggrTooltip += '\n' + catName + ': £' + formatMoney(catAvg) + ' (' + cat.roomCount + ' rooms)';
                }
            }
            var ggrCell = $('<td class="hcr-has-tooltip">£' + formatMoney(ggr) + '</td>');
            ggrCell.attr('data-tooltip', ggrTooltip);
            row.append(ggrCell);

            // Average Lead Time with tooltip
            var avgLeadTimeArriving = 0;
            var avgLeadTimeStaying = 0;
            if (dailyStats[date] && dailyStats[date].leadTimeArriving.count > 0) {
                avgLeadTimeArriving = Math.round(dailyStats[date].leadTimeArriving.totalDays / dailyStats[date].leadTimeArriving.count);
            }
            if (dailyStats[date] && dailyStats[date].leadTimeStaying.count > 0) {
                avgLeadTimeStaying = Math.round(dailyStats[date].leadTimeStaying.totalDays / dailyStats[date].leadTimeStaying.count);
            }

            // Build tooltip with both arriving and staying sections
            var leadTimeTooltip = 'ARRIVALS\nAverage Lead Time: ' + avgLeadTimeArriving + ' days';
            if (dailyStats[date] && dailyStats[date].leadTimeArriving.count > 0) {
                leadTimeTooltip += '\nTotal Arrivals: ' + dailyStats[date].leadTimeArriving.count + '\n\nBreakdown:';

                var categories = ['Walk In', 'Last Minute', 'Week', 'Fortnight', 'Month', '3 Months', '6 Months', '1 Year', 'Over 1 Year', 'Unknown'];
                categories.forEach(function(cat) {
                    var count = dailyStats[date].leadTimeArriving.categories[cat] || 0;
                    if (count > 0) {
                        leadTimeTooltip += '\n' + cat + ': ' + count + ' booking' + (count > 1 ? 's' : '');
                    }
                });
            }

            leadTimeTooltip += '\n\nSTAYING GUESTS\nAverage Lead Time: ' + avgLeadTimeStaying + ' days';
            if (dailyStats[date] && dailyStats[date].leadTimeStaying.count > 0) {
                leadTimeTooltip += '\nTotal Staying: ' + dailyStats[date].leadTimeStaying.count + '\n\nBreakdown:';

                var categories = ['Walk In', 'Last Minute', 'Week', 'Fortnight', 'Month', '3 Months', '6 Months', '1 Year', 'Over 1 Year', 'Unknown'];
                categories.forEach(function(cat) {
                    var count = dailyStats[date].leadTimeStaying.categories[cat] || 0;
                    if (count > 0) {
                        leadTimeTooltip += '\n' + cat + ': ' + count + ' booking' + (count > 1 ? 's' : '');
                    }
                });
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

        // Add totals row
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

    // Load data button
    $('#hcr-load-occupancy-public').on('click', function() {
        loadOccupancyData();
    });

    // Auto-load data on page load
    <?php if (isset($atts['autoload']) && $atts['autoload'] === 'true'): ?>
    loadOccupancyData();
    <?php endif; ?>
});
</script>

<style>
/* Tooltip styles matching backend multi-day report */
.hcr-has-tooltip {
    position: relative;
    cursor: help;
}

.hcr-has-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    padding: 10px 14px;
    background: rgba(0, 0, 0, 0.95);
    color: #fff;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-line;
    border-radius: 6px;
    z-index: 999999;
    min-width: 220px;
    max-width: 400px;
    text-align: left;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    pointer-events: none;
    font-weight: normal;
}

.hcr-has-tooltip:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 8px solid transparent;
    border-top-color: rgba(0, 0, 0, 0.95);
    z-index: 999999;
    pointer-events: none;
}

/* Ensure table and containers don't clip tooltips */
#hcr-occupancy-table-container-public {
    overflow: visible !important;
}

.hcr-occupancy-report-table-public {
    position: relative;
    overflow: visible !important;
}

.hcr-occupancy-report-table-public tbody,
.hcr-occupancy-report-table-public thead,
.hcr-occupancy-report-table-public tfoot {
    overflow: visible !important;
}

/* Add some padding to the table wrapper to prevent clipping */
.hcr-public-cash-up-form {
    overflow: visible !important;
    padding-bottom: 20px;
}
</style>
