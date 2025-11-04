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

// Build query
$query = "SELECT cu.*, u.display_name as created_by_name
          FROM {$wpdb->prefix}hcr_cash_ups cu
          LEFT JOIN {$wpdb->prefix}users u ON cu.created_by = u.ID
          WHERE 1=1";

$query_params = array();

if (!empty($status_filter)) {
    $query .= " AND cu.status = %s";
    $query_params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND cu.session_date >= %s";
    $query_params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND cu.session_date <= %s";
    $query_params[] = $date_to;
}

$query .= " ORDER BY cu.session_date DESC";

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

    <!-- Results Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Cash Total</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cash_ups)): ?>
                <tr>
                    <td colspan="7">No cash ups found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($cash_ups as $cash_up): ?>
                    <tr>
                        <td><?php echo esc_html(date('D, j M Y', strtotime($cash_up->session_date))); ?></td>
                        <td>
                            <span class="hcr-status-badge hcr-status-<?php echo esc_attr($cash_up->status); ?>">
                                <?php echo esc_html(ucfirst($cash_up->status)); ?>
                            </span>
                        </td>
                        <td>£<?php echo number_format($cash_up->total_cash_counted, 2); ?></td>
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
</div>

<script>
jQuery(document).ready(function($) {
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
