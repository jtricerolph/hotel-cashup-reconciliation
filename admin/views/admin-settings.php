<?php
/**
 * Settings page view
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap hcr-settings-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('hcr_settings_group');
        do_settings_sections('hcr_settings');
        submit_button('Save Settings');
        ?>
    </form>

    <hr>

    <h2>Test Newbook API Connection</h2>
    <p>Click the button below to test your Newbook API connection with the current settings.</p>
    <button type="button" id="hcr-test-connection" class="button button-secondary">Test Connection</button>
    <div id="hcr-connection-result" style="margin-top: 10px;"></div>

    <hr>

    <h2>Receipt Photo Management</h2>
    <p>Purge old receipt photos from cash up submissions to free up server storage space.</p>
    <p><strong>Warning:</strong> This will permanently delete receipt photos and cannot be undone. Only photos from cash up submissions before the selected date will be deleted.</p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="hcr-purge-date">Purge Photos Before</label>
            </th>
            <td>
                <?php
                $last_purge_date = get_option('hcr_last_photo_purge_date', '');
                $default_date = $last_purge_date ? $last_purge_date : date('Y-m-d', strtotime('-6 months'));
                ?>
                <input type="date" id="hcr-purge-date" value="<?php echo esc_attr($default_date); ?>" max="<?php echo date('Y-m-d'); ?>" />
                <p class="description">Select a date. All receipt photos from cash up submissions before this date will be deleted.</p>
                <?php if ($last_purge_date): ?>
                    <p class="description" style="color: #666;">Last purge: <?php echo date('d/m/Y', strtotime($last_purge_date)); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <button type="button" id="hcr-purge-photos" class="button button-secondary" style="background-color: #d63638; color: #fff; border-color: #d63638;">
                    <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Purge Receipt Photos
                </button>
                <div id="hcr-purge-result" style="margin-top: 10px;"></div>
            </td>
        </tr>
    </table>

</div>

<script>
jQuery(document).ready(function($) {
    // Test Connection
    $('#hcr-test-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#hcr-connection-result');

        $btn.prop('disabled', true).text('Testing...');
        $result.html('');

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_test_connection',
                nonce: hcrAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Connection');
            }
        });
    });

    // Purge Receipt Photos
    $('#hcr-purge-photos').on('click', function() {
        var $btn = $(this);
        var $result = $('#hcr-purge-result');
        var purgeDate = $('#hcr-purge-date').val();

        if (!purgeDate) {
            $result.html('<div class="notice notice-error"><p>Please select a date.</p></div>');
            return;
        }

        // Confirmation dialog
        var confirmMsg = 'Are you sure you want to permanently delete all receipt photos from cash up submissions before ' +
                        new Date(purgeDate).toLocaleDateString('en-GB') + '?\n\n' +
                        'This action cannot be undone!';

        if (!confirm(confirmMsg)) {
            return;
        }

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; margin-top: 3px;"></span> Purging...');
        $result.html('');

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_purge_receipt_photos',
                nonce: hcrAdmin.nonce,
                purge_date: purgeDate
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');

                    // Update the last purge date display
                    if (response.data.purge_date) {
                        var formattedDate = new Date(response.data.purge_date).toLocaleDateString('en-GB');
                        var lastPurgeHtml = '<p class="description" style="color: #666;">Last purge: ' + formattedDate + '</p>';

                        // Remove existing last purge text if it exists
                        $('#hcr-purge-date').parent().find('.description[style*="color: #666"]').remove();

                        // Add new last purge text
                        $('#hcr-purge-date').parent().append(lastPurgeHtml);
                    }
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Purge Receipt Photos');
            }
        });
    });
});
</script>

<style>
@keyframes rotation {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(359deg);
    }
}
</style>
