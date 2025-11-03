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

</div>

<script>
jQuery(document).ready(function($) {
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
});
</script>
