<?php
/**
 * Cash Up History View
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

global $wpdb;

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Default to last 30 days if no filters are set
$has_filters = isset($_GET['date_from']) || isset($_GET['date_to']) || isset($_GET['status']);
if (!$has_filters) {
    $date_from = date('Y-m-d', strtotime('-30 days'));
    $date_to = date('Y-m-d');
} else {
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Build base query for counting total results
$count_query = "SELECT COUNT(DISTINCT cu.id)
                FROM {$wpdb->prefix}hcr_cash_ups cu
                WHERE 1=1";

// Build query for fetching results with total variance and breakdown by payment type
$query = "SELECT cu.*,
                 u.display_name as created_by_name,
                 COALESCE(SUM(r.variance), 0) as total_variance,
                 COALESCE(SUM(CASE WHEN r.category = 'Cash' THEN r.variance ELSE 0 END), 0) as cash_variance,
                 COALESCE(SUM(CASE WHEN r.category IN ('PDQ Visa/MC', 'PDQ Amex', 'Gateway Visa/MC', 'Gateway Amex') THEN r.variance ELSE 0 END), 0) as card_variance,
                 COALESCE(SUM(CASE WHEN r.category = 'BACS/Bank Transfer' THEN r.variance ELSE 0 END), 0) as bacs_variance
          FROM {$wpdb->prefix}hcr_cash_ups cu
          LEFT JOIN {$wpdb->prefix}users u ON cu.created_by = u.ID
          LEFT JOIN {$wpdb->prefix}hcr_reconciliation r ON cu.id = r.cash_up_id
          WHERE 1=1";

$query_params = array();

if (!empty($status_filter)) {
    $query .= " AND cu.status = %s";
    $count_query .= " AND cu.status = %s";
    $query_params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND cu.session_date >= %s";
    $count_query .= " AND cu.session_date >= %s";
    $query_params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND cu.session_date <= %s";
    $count_query .= " AND cu.session_date <= %s";
    $query_params[] = $date_to;
}

// Get total count for pagination
if (!empty($query_params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
} else {
    $total_items = $wpdb->get_var($count_query);
}

$total_pages = ceil($total_items / $per_page);

// Add GROUP BY, ORDER BY, and LIMIT to main query
$query .= " GROUP BY cu.id
            ORDER BY cu.session_date DESC
            LIMIT %d OFFSET %d";

$query_params[] = $per_page;
$query_params[] = $offset;

if (!empty($query_params)) {
    $cash_ups = $wpdb->get_results($wpdb->prepare($query, $query_params));
} else {
    $cash_ups = $wpdb->get_results($query);
}
?>

<div class="wrap hcr-history-page">
    <h1>Cash Up History</h1>

    <!-- Filters -->
    <div class="hcr-filters" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
        <?php if (!$has_filters): ?>
            <p class="description" style="margin-top: 0;">Showing last 30 days by default. Use filters below to view other date ranges.</p>
        <?php endif; ?>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">

            <label>Status:
                <select name="status">
                    <option value="">All</option>
                    <option value="draft" <?php selected($status_filter, 'draft'); ?>>Draft</option>
                    <option value="final" <?php selected($status_filter, 'final'); ?>>Final</option>
                </select>
            </label>

            <label>From:
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
            </label>

            <label>To:
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
            </label>

            <button type="submit" class="button">Filter</button>
            <a href="?page=<?php echo esc_attr($_GET['page']); ?>" class="button">Clear</a>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="hcr-bulk-actions-bar" style="background: #fff; padding: 10px 15px; margin-bottom: 10px; border: 1px solid #ccc; display: none;">
        <button type="button" id="hcr-bulk-finalize-btn" class="button button-primary">
            <span class="dashicons dashicons-yes" style="vertical-align: middle; margin-top: 3px;"></span> Save Selected as Final
        </button>
        <span id="hcr-selection-count" style="margin-left: 10px; font-weight: 600;"></span>
    </div>

    <!-- Results Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 2.5em;"><input type="checkbox" id="hcr-select-all-drafts"></th>
                <th>Date</th>
                <th>Status</th>
                <th>Cash Total</th>
                <th>Total Variance</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cash_ups)): ?>
                <tr>
                    <td colspan="9">No cash ups found.</td>
                </tr>
            <?php else: ?>
                <?php
                    // Helper function for variance breakdown display
                    if (!function_exists('hcr_format_variance_breakdown')) {
                        function hcr_format_variance_breakdown($amount, $label) {
                            if (abs($amount) < 0.01) return ''; // Skip if zero
                            $sign = $amount >= 0 ? '+' : '';
                            $arrow = $amount >= 0 ? '↑' : '↓';
                            return sprintf('%s: %s£%s %s', $label, $sign, number_format(abs($amount), 2), $arrow);
                        }
                    }
                ?>
                <?php foreach ($cash_ups as $cash_up): ?>
                    <?php
                        $total_variance = floatval($cash_up->total_variance);
                        $cash_variance = floatval($cash_up->cash_variance);
                        $card_variance = floatval($cash_up->card_variance);
                        $bacs_variance = floatval($cash_up->bacs_variance);

                        $variance_class = '';
                        if ($total_variance > 0) {
                            $variance_class = 'hcr-variance-positive';
                        } elseif ($total_variance < 0) {
                            $variance_class = 'hcr-variance-negative';
                        } else {
                            $variance_class = 'hcr-variance-zero';
                        }
                        $variance_sign = $total_variance >= 0 ? '+' : '';
                    ?>
                    <tr>
                        <td>
                            <?php if ($cash_up->status === 'draft'): ?>
                                <input type="checkbox" class="hcr-draft-checkbox" data-id="<?php echo esc_attr($cash_up->id); ?>" data-date="<?php echo esc_attr($cash_up->session_date); ?>">
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('D, j M Y', strtotime($cash_up->session_date))); ?></td>
                        <td>
                            <span class="hcr-status-badge hcr-status-<?php echo esc_attr($cash_up->status); ?>">
                                <?php echo esc_html(ucfirst($cash_up->status)); ?>
                            </span>
                        </td>
                        <td>£<?php echo number_format($cash_up->total_cash_counted, 2); ?></td>
                        <td class="<?php echo esc_attr($variance_class); ?>" style="font-weight: 600;">
                            <div style="margin-bottom: 2px;">
                                <?php echo $variance_sign; ?>£<?php echo number_format(abs($total_variance), 2); ?>
                            </div>
                            <div style="font-size: 8px; font-weight: normal; line-height: 1.3;">
                                <?php
                                    $breakdown = array();
                                    if (abs($cash_variance) >= 0.01) {
                                        $breakdown[] = hcr_format_variance_breakdown($cash_variance, 'Cash');
                                    }
                                    if (abs($card_variance) >= 0.01) {
                                        $breakdown[] = hcr_format_variance_breakdown($card_variance, 'Card');
                                    }
                                    if (abs($bacs_variance) >= 0.01) {
                                        $breakdown[] = hcr_format_variance_breakdown($bacs_variance, 'BACS');
                                    }
                                    if (!empty($breakdown)) {
                                        echo implode('<br>', $breakdown);
                                    }
                                ?>
                            </div>
                        </td>
                        <td><?php echo esc_html($cash_up->created_by_name); ?></td>
                        <td><?php echo esc_html(date('d/m/Y H:i', strtotime($cash_up->created_at))); ?></td>
                        <td><?php echo $cash_up->submitted_at ? esc_html(date('d/m/Y H:i', strtotime($cash_up->submitted_at))) : '-'; ?></td>
                        <td>
                            <?php if ($cash_up->status === 'draft'): ?>
                                <a href="?page=hotel-cash-up-reconciliation&date=<?php echo esc_attr($cash_up->session_date); ?>" class="button button-small">Edit</a>
                                <button class="button button-small hcr-delete-cash-up" data-id="<?php echo esc_attr($cash_up->id); ?>">Delete</button>
                            <?php else: ?>
                                <a href="?page=hotel-cash-up-reconciliation&date=<?php echo esc_attr($cash_up->session_date); ?>" class="button button-small">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo number_format_i18n($total_items); ?> items</span>
                <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'type' => 'plain'
                    ));
                    if ($page_links) {
                        echo '<span class="pagination-links">' . $page_links . '</span>';
                    }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Update selection count and show/hide bulk actions bar
    function updateSelectionUI() {
        var selectedCount = $('.hcr-draft-checkbox:checked').length;

        if (selectedCount > 0) {
            $('#hcr-bulk-actions-bar').show();
            $('#hcr-selection-count').text(selectedCount + ' draft' + (selectedCount !== 1 ? 's' : '') + ' selected');
        } else {
            $('#hcr-bulk-actions-bar').hide();
        }
    }

    // Select all drafts checkbox
    $('#hcr-select-all-drafts').on('change', function() {
        $('.hcr-draft-checkbox').prop('checked', $(this).is(':checked'));
        updateSelectionUI();
    });

    // Individual checkbox change
    $(document).on('change', '.hcr-draft-checkbox', function() {
        // Update "select all" checkbox state
        var totalDrafts = $('.hcr-draft-checkbox').length;
        var selectedDrafts = $('.hcr-draft-checkbox:checked').length;
        $('#hcr-select-all-drafts').prop('checked', totalDrafts > 0 && totalDrafts === selectedDrafts);

        updateSelectionUI();
    });

    // Bulk finalize button
    $('#hcr-bulk-finalize-btn').on('click', function() {
        var selectedIds = [];
        $('.hcr-draft-checkbox:checked').each(function() {
            selectedIds.push($(this).data('id'));
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one draft to finalize.');
            return;
        }

        var confirmMsg = 'Are you sure you want to save ' + selectedIds.length + ' draft' + (selectedIds.length !== 1 ? 's' : '') + ' as final?\n\nThis action cannot be undone.';
        if (!confirm(confirmMsg)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hcr_bulk_finalize_cash_ups',
                nonce: '<?php echo wp_create_nonce('hcr_admin_nonce'); ?>',
                cash_up_ids: selectedIds
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'An error occurred. Please try again.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-top: 3px;"></span> Save Selected as Final');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-top: 3px;"></span> Save Selected as Final');
            }
        });
    });

    // Delete cash up
    $('.hcr-delete-cash-up').on('click', function() {
        if (!confirm('Are you sure you want to delete this cash up?')) {
            return;
        }

        var $btn = $(this);
        var cashUpId = $btn.data('id');

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_delete_cash_up',
                nonce: hcrAdmin.nonce,
                cash_up_id: cashUpId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    });
});
</script>
