/**
 * Public JavaScript for Hotel Cash Up & Reconciliation
 *
 * This file handles the front-end shortcode functionality.
 * Most of the functionality is shared with hcr-admin.js through
 * common selectors and event handlers.
 */

jQuery(document).ready(function($) {
    'use strict';

    console.log('HCR: hcr-public.js loaded');
    console.log('HCR Public: hcrAdmin defined?', typeof hcrAdmin !== 'undefined');
    console.log('HCR Public: hcrPublic defined?', typeof hcrPublic !== 'undefined');

    // Public-specific variables
    var publicCurrentCashUpId = null;
    var publicCurrentStatus = 'draft';
    var publicNewbookPaymentTotals = null;
    var publicPendingCashUpData = null;
    var publicFormIsDirty = false;

    // =======================
    // Auto-select input field values on focus
    // =======================

    $(document).on('focus', 'input[type="number"], input[type="text"]', function() {
        // Select all text when focusing on input fields with values
        if ($(this).val()) {
            $(this).select();
        }
    });

    // =======================
    // Public Dirty Form Tracking
    // =======================

    function markPublicFormDirty() {
        if (!publicFormIsDirty) {
            publicFormIsDirty = true;
            console.log('HCR Public: Form marked as dirty');
        }
        // Also call the global markFormDirty to trigger beforeunload and show indicator
        if (typeof window.hcrMarkFormDirty === 'function') {
            window.hcrMarkFormDirty();
        }
    }

    function clearPublicFormDirty() {
        publicFormIsDirty = false;
        console.log('HCR Public: Form marked as clean');
        // Also clear the global dirty flag and show saved indicator
        if (typeof window.hcrClearFormDirty === 'function') {
            window.hcrClearFormDirty();
        }
    }

    // =======================
    // Public Date Selection Workflow
    // =======================

    // Check Date button handler
    $('#hcr-public-check-date').on('click', function(e) {
        // Warn if there are unsaved changes
        if (publicFormIsDirty) {
            if (!confirm('You have unsaved changes. Are you sure you want to check a different date?')) {
                e.stopImmediatePropagation();
                return false;
            }
            // User confirmed, clear dirty flag
            clearPublicFormDirty();
        }
        var selectedDate = $('#public_session_date').val();
        if (!selectedDate) {
            alert('Please select a date first.');
            return;
        }

        // Hide form, session header, and clear messages when checking a new date
        $('#hcr-public-cash-up-form').hide();
        $('#hcr-public-session-header').hide();
        $('#hcr-public-message').hide();

        checkPublicDateStatus(selectedDate);
    });

    // Check date status and show appropriate action buttons
    function checkPublicDateStatus(date) {
        // Hide all action buttons
        $('#hcr-public-load-draft, #hcr-public-view-final, #hcr-public-create-new').hide();
        $('#hcr-public-date-status-message').text('Checking...');
        $('#hcr-public-date-actions').show();

        var ajaxUrl, nonce;
        if (typeof hcrAdmin !== 'undefined' && hcrAdmin.ajaxUrl) {
            ajaxUrl = hcrAdmin.ajaxUrl;
            nonce = hcrAdmin.nonce;
        } else if (typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl) {
            ajaxUrl = hcrPublic.ajaxUrl;
            nonce = hcrPublic.nonce;
        } else {
            console.error('HCR: Neither hcrAdmin nor hcrPublic is defined');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_load_cash_up',
                nonce: nonce,
                session_date: date
            },
            success: function(response) {
                if (response.success) {
                    // Cash up exists
                    publicPendingCashUpData = response.data;
                    var status = response.data.cash_up.status;
                    var dateObj = new Date(date + 'T00:00:00');
                    var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

                    if (status === 'draft') {
                        $('#hcr-public-date-status-message').html('<strong>Draft found for ' + dateStr + '</strong>');
                        $('#hcr-public-load-draft').show();
                    } else if (status === 'final') {
                        $('#hcr-public-date-status-message').html('<strong>Final submission found for ' + dateStr + '</strong>');
                        $('#hcr-public-view-final').show();
                    }
                } else {
                    // No cash up exists
                    publicPendingCashUpData = null;
                    var dateObj = new Date(date + 'T00:00:00');
                    var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                    $('#hcr-public-date-status-message').html('<strong>No cash up found for ' + dateStr + '</strong>');
                    $('#hcr-public-create-new').show();
                }
            },
            error: function() {
                $('#hcr-public-date-status-message').html('<span style="color: red;">Error checking date</span>');
            }
        });
    }

    // Load Draft button handler
    $('#hcr-public-load-draft').on('click', function() {
        if (publicPendingCashUpData) {
            clearPublicForm();
            // Use the globally exposed loadCashUpData function from hcr-admin.js
            if (typeof window.hcrLoadCashUpData === 'function') {
                window.hcrLoadCashUpData(publicPendingCashUpData);
            }
            showPublicForm();
        }
    });

    // View Final button handler
    $('#hcr-public-view-final').on('click', function() {
        if (publicPendingCashUpData) {
            clearPublicForm();
            if (typeof window.hcrLoadCashUpData === 'function') {
                window.hcrLoadCashUpData(publicPendingCashUpData);
            }
            showPublicForm();
        }
    });

    // Create New button handler
    $('#hcr-public-create-new').on('click', function() {
        var selectedDate = $('#public_session_date').val();
        clearPublicForm();
        createPublicBlankDraft(selectedDate);
    });

    // Clear all public form data
    function clearPublicForm() {
        console.log('HCR Public: Clearing form');

        // Clear denomination inputs
        $('.float-denom-quantity, .float-denom-value').val('');
        $('.takings-denom-quantity, .takings-denom-value').val('');
        $('.float-denom-total, .takings-denom-total').text('£0.00');

        // Clear validation styling from denomination inputs
        $('.float-denom-quantity, .float-denom-value, .takings-denom-quantity, .takings-denom-value').css({
            'border-color': '',
            'background-color': ''
        });
        $('.float-denom-total, .takings-denom-total').css('color', '');

        // Clear totals
        $('#public-total-float-counted').text('£0.00');
        $('#public-total-cash-counted').text('£0.00');
        $('#public-float-variance').text('£0.00');

        // Clear card machines
        $('.machine-total, .machine-amex').val('');
        $('#public_front_desk_visa_mc').text('0.00');
        $('#public_restaurant_visa_mc').text('0.00');
        $('#public_combined_total').text('0.00');
        $('#public_combined_amex').text('0.00');
        $('#public_combined_visa_mc').text('0.00');

        // Clear notes
        $('#public_cash_up_notes').val('');

        // Clear Newbook data
        publicNewbookPaymentTotals = null;
        $('#hcr-reconciliation-section').hide();
        $('#hcr-fetch-status').html('');
        $('#public-cash-takings-newbook-row').hide();
        $('#public-cash-takings-variance-row').hide();

        // Clear till payments
        $('#hcr-show-till-payments').hide();
        $('#hcr-till-payments-tooltip').hide();

        // Reset state
        publicCurrentCashUpId = null;
        publicCurrentStatus = 'draft';
        $('#hcr-public-session-status').html('');

        // Clear dirty flag and hide save status
        publicFormIsDirty = false;
        $('#hcr-public-save-status').hide();
        // Also clear the global dirty flag
        if (typeof window.hcrClearFormDirty === 'function') {
            window.hcrClearFormDirty();
        }
    }

    // Show the public form
    function showPublicForm() {
        var selectedDate = $('#public_session_date').val();
        var dateObj = new Date(selectedDate + 'T00:00:00');
        var dateStr = dateObj.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

        $('#hcr-public-current-date-display').text(dateStr);
        $('#hcr-public-session-header').show();
        $('#hcr-public-cash-up-form').show();
        // Keep date selector visible so user can change dates without refreshing
        // $('#hcr-public-date-selector').hide();

        // Hide the date check info section after loading/creating
        $('#hcr-public-date-actions').hide();

        // Clear any previous messages
        $('#hcr-public-message').hide();
    }

    // Create blank draft entry
    function createPublicBlankDraft(date) {
        console.log('HCR Public: Creating blank draft for:', date);
        showPublicForm();

        // Set session status
        var dateObj = new Date(date + 'T00:00:00');
        var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        $('#hcr-public-session-status').html('<span class="hcr-status-badge hcr-status-draft">NEW ENTRY</span>');

        showPublicMessage('Creating new cash up for ' + dateStr + '. Enter counts and save as draft.', 'success');

        // Auto-fetch Newbook data for the new entry (use globally exposed function from hcr-admin.js)
        if (typeof window.hcrAutoFetchNewbookData === 'function') {
            window.hcrAutoFetchNewbookData(date);
        }

        // Calculate initial variance (will show expected float as negative variance)
        if (typeof window.hcrUpdateTotalFloat === 'function') {
            window.hcrUpdateTotalFloat();
        }
    }

    // Show message
    function showPublicMessage(message, type) {
        var $message = $('#hcr-public-message');
        $message.removeClass('error success').addClass(type)
            .html('<p>' + message + '</p>')
            .show();
    }
});
