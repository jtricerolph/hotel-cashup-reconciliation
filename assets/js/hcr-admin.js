/**
 * Admin JavaScript for Hotel Cash Up & Reconciliation
 */

jQuery(document).ready(function($) {
    'use strict';

    console.log('HCR: hcr-admin.js loaded');
    console.log('HCR: hcrAdmin defined?', typeof hcrAdmin !== 'undefined');
    console.log('HCR: hcrPublic defined?', typeof hcrPublic !== 'undefined');
    if (typeof hcrAdmin !== 'undefined') {
        console.log('HCR: hcrAdmin =', hcrAdmin);
    }
    if (typeof hcrPublic !== 'undefined') {
        console.log('HCR: hcrPublic =', hcrPublic);
    }

    var currentCashUpId = null;
    var currentStatus = 'draft';
    var newbookPaymentTotals = null;
    var formIsDirty = false;
    var isLoadingData = false; // Flag to prevent marking dirty during data load
    var cachedTillPayments = null; // Store till payments for dynamic updates

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
    // Auto-check date from history
    // =======================

    if ($('#hcr-auto-load').val() === 'true') {
        console.log('HCR: Auto-checking date for cash up:', $('#session_date').val());
        // Trigger check date button after a short delay to ensure DOM is ready
        setTimeout(function() {
            $('#hcr-check-date').trigger('click');
        }, 100);
    }

    // =======================
    // Setup Tab Order (Vertical)
    // =======================

    function setupTabOrder() {
        var tabIndex = 1;

        // Float table - Quantity column first (vertical)
        $('.float-denomination-row').each(function() {
            $(this).find('.float-denom-quantity').attr('tabindex', tabIndex++);
        });

        // Float table - Value column second (vertical)
        $('.float-denomination-row').each(function() {
            $(this).find('.float-denom-value').attr('tabindex', tabIndex++);
        });

        // Takings table - Quantity column first (vertical)
        $('.takings-denomination-row').each(function() {
            $(this).find('.takings-denom-quantity').attr('tabindex', tabIndex++);
        });

        // Takings table - Value column second (vertical)
        $('.takings-denomination-row').each(function() {
            $(this).find('.takings-denom-value').attr('tabindex', tabIndex++);
        });
    }

    // Run on page load
    setupTabOrder();

    // =======================
    // Dirty Form Tracking
    // =======================

    function markFormDirty() {
        // Don't mark as dirty if we're currently loading data
        if (isLoadingData) {
            return;
        }

        if (!formIsDirty) {
            formIsDirty = true;
            console.log('HCR: Form marked as dirty');
        }
        // Show "UNSAVED CHANGES" indicator with box styling
        $('#hcr-save-status, #hcr-public-save-status')
            .removeClass('hcr-status-saved')
            .addClass('hcr-status-unsaved')
            .text('UNSAVED CHANGES')
            .show();
    }

    function clearFormDirty() {
        formIsDirty = false;
        console.log('HCR: Form marked as clean');
        // Show "SAVED" indicator briefly with box styling
        $('#hcr-save-status, #hcr-public-save-status')
            .removeClass('hcr-status-unsaved')
            .addClass('hcr-status-saved')
            .text('SAVED')
            .show();

        // Hide the indicator after 3 seconds
        setTimeout(function() {
            $('#hcr-save-status, #hcr-public-save-status').fadeOut();
        }, 3000);
    }

    // Warn before leaving page with unsaved changes
    $(window).on('beforeunload', function(e) {
        if (formIsDirty) {
            var message = 'You have unsaved changes. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    });

    // =======================
    // Date Selection Workflow
    // =======================

    // Check Date button handler
    $('#hcr-check-date').on('click', function(e) {
        // Warn if there are unsaved changes
        if (formIsDirty) {
            if (!confirm('You have unsaved changes. Are you sure you want to check a different date?')) {
                e.stopImmediatePropagation();
                return false;
            }
            // User confirmed, clear dirty flag
            clearFormDirty();
        }
        var selectedDate = $('#session_date').val();
        if (!selectedDate) {
            alert('Please select a date first.');
            return;
        }

        // Hide form, session header, and clear messages when checking a new date
        $('#hcr-cash-up-form').hide();
        $('#hcr-session-header').hide();
        $('#hcr-message, #hcr-public-message').hide();

        checkDateStatus(selectedDate);
    });

    var pendingCashUpData = null; // Store cash up data for loading

    // Check date status and show appropriate action buttons
    function checkDateStatus(date) {
        // Hide all action buttons
        $('#hcr-load-draft, #hcr-view-final, #hcr-create-new').hide();
        $('#hcr-date-status-message').text('Checking...');
        $('#hcr-date-actions').show();

        // Check if auto-load is enabled
        var autoLoad = $('#hcr-auto-load').val() === 'true';

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_load_cash_up',
                nonce: hcrAdmin.nonce,
                session_date: date
            },
            success: function(response) {
                if (response.success) {
                    // Cash up exists
                    pendingCashUpData = response.data;
                    var status = response.data.cash_up.status;
                    var dateObj = new Date(date + 'T00:00:00');
                    var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

                    if (status === 'draft') {
                        $('#hcr-date-status-message').html('<strong>Draft found for ' + dateStr + '</strong>');
                        $('#hcr-load-draft').show();

                        // Auto-load if coming from history
                        if (autoLoad) {
                            $('#hcr-auto-load').val(''); // Clear auto-load flag
                            setTimeout(function() {
                                $('#hcr-load-draft').trigger('click');
                            }, 100);
                        }
                    } else if (status === 'final') {
                        $('#hcr-date-status-message').html('<strong>Final submission found for ' + dateStr + '</strong>');
                        $('#hcr-view-final').show();

                        // Auto-load if coming from history
                        if (autoLoad) {
                            $('#hcr-auto-load').val(''); // Clear auto-load flag
                            setTimeout(function() {
                                $('#hcr-view-final').trigger('click');
                            }, 100);
                        }
                    }
                } else {
                    // No cash up exists
                    pendingCashUpData = null;
                    var dateObj = new Date(date + 'T00:00:00');
                    var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                    $('#hcr-date-status-message').html('<strong>No cash up found for ' + dateStr + '</strong>');
                    $('#hcr-create-new').show();
                }
            },
            error: function() {
                $('#hcr-date-status-message').html('<span style="color: red;">Error checking date</span>');
            }
        });
    }

    // Load Draft button handler
    $('#hcr-load-draft').on('click', function() {
        if (pendingCashUpData) {
            clearForm();
            loadCashUpData(pendingCashUpData);
            showForm();
        }
    });

    // View Final button handler
    $('#hcr-view-final').on('click', function() {
        if (pendingCashUpData) {
            clearForm();
            loadCashUpData(pendingCashUpData);
            showForm();
        }
    });

    // Create New button handler
    $('#hcr-create-new').on('click', function() {
        var selectedDate = $('#session_date').val();
        clearForm();
        createBlankDraft(selectedDate);
    });

    // Clear all form data
    function clearForm() {
        console.log('HCR: Clearing form');

        // Clear denomination inputs and re-enable all fields
        $('.float-denom-quantity, .float-denom-value').val('').prop('disabled', false);
        $('.takings-denom-quantity, .takings-denom-value').val('').prop('disabled', false);
        $('.float-denom-total, .takings-denom-total').text('£0.00');

        // Clear validation styling from denomination inputs
        $('.float-denom-quantity, .float-denom-value, .takings-denom-quantity, .takings-denom-value').css({
            'border-color': '',
            'background-color': ''
        });
        $('.float-denom-total, .takings-denom-total').css('color', '');

        // Clear totals
        $('#total-float-counted, #public-total-float-counted').text('£0.00');
        $('#total-cash-counted, #public-total-cash-counted').text('£0.00');
        $('#float-variance, #public-float-variance').text('£0.00');

        // Clear card machines
        $('.machine-total, .machine-amex').val('');
        $('#front_desk_visa_mc, #public_front_desk_visa_mc').text('0.00');
        $('#restaurant_visa_mc, #public_restaurant_visa_mc').text('0.00');
        $('#combined_total, #public_combined_total').text('0.00');
        $('#combined_amex, #public_combined_amex').text('0.00');
        $('#combined_visa_mc, #public_combined_visa_mc').text('0.00');

        // Clear notes
        $('#cash_up_notes, #public_cash_up_notes').val('');

        // Clear photo
        $('#hcr-machine-photo-id, #hcr-public-machine-photo-id').val('');
        $('#hcr-photo-display-area, #hcr-public-photo-display-area').hide();
        $('#hcr-photo-upload-area, #hcr-public-photo-upload-area').show();
        $('#hcr-machine-photo-preview, #hcr-public-machine-photo-preview').attr('src', '');

        // Clear Newbook data
        newbookPaymentTotals = null;
        $('#hcr-reconciliation-section').hide();
        $('#hcr-fetch-status').html('');
        $('#cash-takings-newbook-row, #public-cash-takings-newbook-row').hide();
        $('#cash-takings-variance-row, #public-cash-takings-variance-row').hide();

        // Clear till payments
        $('#hcr-show-till-payments').hide();
        $('#hcr-till-payments-tooltip').hide();

        // Clear transaction breakdown
        $('#hcr-transaction-breakdown-section').hide();
        $('#hcr-breakdown-content').hide();
        $('#hcr-breakdown-arrow').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
        $('#hcr-reception-breakdown').empty();
        $('#hcr-restaurant-breakdown').empty();

        // Reset state
        currentCashUpId = null;
        currentStatus = 'draft';
        $('#hcr-status-indicator, #hcr-session-status').html('');

        // Clear dirty flag and hide save status
        formIsDirty = false;
        $('#hcr-save-status, #hcr-public-save-status').hide();
    }

    // Show the form
    function showForm() {
        var selectedDate = $('#session_date').val();
        var dateObj = new Date(selectedDate + 'T00:00:00');
        var dateStr = dateObj.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

        $('#hcr-current-date-display').text(dateStr);
        $('#hcr-session-header').show();
        $('#hcr-cash-up-form').show();
        // Keep date selector visible so user can change dates without refreshing
        // $('#hcr-date-selector').hide();

        // Hide the date check info section after loading/creating
        $('#hcr-date-actions').hide();

        // Clear any previous messages
        $('#hcr-message, #hcr-public-message').hide();
    }

    // Create blank draft entry
    function createBlankDraft(date) {
        console.log('HCR: Creating blank draft for:', date);
        showForm();

        // Set session status
        var dateObj = new Date(date + 'T00:00:00');
        var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        $('#hcr-session-status').html('<span class="hcr-status-badge hcr-status-draft">NEW ENTRY</span>');

        showMessage('Creating new cash up for ' + dateStr + '. Enter counts and save as draft.', 'success');

        // Auto-fetch Newbook data for the new entry
        autoFetchNewbookData(date);

        // Calculate initial variance (will show expected float as negative variance)
        updateTotalFloat();
    }

    // =======================
    // Float Denomination Calculations
    // =======================

    // Handle float quantity input
    $(document).on('input', '.float-denom-quantity', function() {
        var $row = $(this).closest('.float-denomination-row');
        var $valueInput = $row.find('.float-denom-value');
        var $total = $row.find('.float-denom-total');
        var denomination = parseFloat($row.data('value'));
        var quantity = parseInt($(this).val()) || 0;

        if (quantity > 0) {
            $valueInput.val('').prop('disabled', true);
            var total = denomination * quantity;
            $total.text('£' + total.toFixed(2));
        } else {
            $valueInput.prop('disabled', false);
            $total.text('£0.00');
        }

        updateTotalFloat();
    });

    // Handle float value input
    $(document).on('input', '.float-denom-value', function() {
        var $row = $(this).closest('.float-denomination-row');
        var $quantityInput = $row.find('.float-denom-quantity');
        var $total = $row.find('.float-denom-total');
        var denomination = parseFloat($row.data('value'));
        var value = parseFloat($(this).val()) || 0;

        // Validate that value is a multiple of denomination
        if (value > 0) {
            // Check if value is a multiple of denomination (with small tolerance for floating point errors)
            var remainder = value % denomination;
            if (remainder > 0.001 && (denomination - remainder) > 0.001) {
                $(this).css('border-color', '#dc3232');
                $(this).css('background-color', '#f8d7da');
                $total.text('£0.00').css('color', '#dc3232');
                // Clear any previous timeout
                if ($(this).data('validationTimeout')) {
                    clearTimeout($(this).data('validationTimeout'));
                }
                // Show error message briefly
                var $input = $(this);
                var timeout = setTimeout(function() {
                    showMessage('Value must be a multiple of £' + denomination.toFixed(2), 'error');
                }, 500);
                $(this).data('validationTimeout', timeout);
                updateTotalFloat();
                return;
            }

            // Reset validation styling
            $(this).css('border-color', '');
            $(this).css('background-color', '');
            $total.css('color', '');

            $quantityInput.val('').prop('disabled', true);
            $total.text('£' + value.toFixed(2));
        } else {
            $(this).css('border-color', '');
            $(this).css('background-color', '');
            $total.css('color', '');
            $quantityInput.prop('disabled', false);
            $total.text('£0.00');
        }

        updateTotalFloat();
    });

    // Update total float
    function updateTotalFloat() {
        var total = 0;

        $('.float-denomination-row').each(function() {
            var rowTotal = parseFloat($(this).find('.float-denom-total').text().replace('£', '')) || 0;
            total += rowTotal;
        });

        $('#total-float-counted, #public-total-float-counted').text('£' + total.toFixed(2));

        // Calculate and display variance
        var expectedFloat = parseFloat(hcrAdmin.expectedTillFloat) || 0;
        var variance = total - expectedFloat;
        var $varianceElement = $('#float-variance, #public-float-variance');

        $varianceElement.text('£' + variance.toFixed(2));

        // Apply styling based on variance
        applyVarianceStyle($varianceElement, variance);

        // Mark form as dirty
        markFormDirty();
    }

    // Apply variance styling: green (balanced), amber (over), red (short)
    function applyVarianceStyle($element, variance) {
        $element.removeClass('hcr-variance-balanced hcr-variance-over hcr-variance-short');

        if (Math.abs(variance) < 0.005) {
            // Balanced (less than half a penny - effectively zero)
            $element.addClass('hcr-variance-balanced');
        } else if (variance > 0) {
            // Over
            $element.addClass('hcr-variance-over');
        } else {
            // Short
            $element.addClass('hcr-variance-short');
        }
    }

    // =======================
    // Takings Denomination Calculations
    // =======================

    // Handle takings quantity input
    $(document).on('input', '.takings-denom-quantity', function() {
        var $row = $(this).closest('.takings-denomination-row');
        var $valueInput = $row.find('.takings-denom-value');
        var $total = $row.find('.takings-denom-total');
        var denomination = parseFloat($row.data('value'));
        var quantity = parseInt($(this).val()) || 0;

        if (quantity > 0) {
            $valueInput.val('').prop('disabled', true);
            var total = denomination * quantity;
            $total.text('£' + total.toFixed(2));
        } else {
            $valueInput.prop('disabled', false);
            $total.text('£0.00');
        }

        updateTotalCash();
    });

    // Handle takings value input
    $(document).on('input', '.takings-denom-value', function() {
        var $row = $(this).closest('.takings-denomination-row');
        var $quantityInput = $row.find('.takings-denom-quantity');
        var $total = $row.find('.takings-denom-total');
        var denomination = parseFloat($row.data('value'));
        var value = parseFloat($(this).val()) || 0;

        // Validate that value is a multiple of denomination
        if (value > 0) {
            // Check if value is a multiple of denomination (with small tolerance for floating point errors)
            var remainder = value % denomination;
            if (remainder > 0.001 && (denomination - remainder) > 0.001) {
                $(this).css('border-color', '#dc3232');
                $(this).css('background-color', '#f8d7da');
                $total.text('£0.00').css('color', '#dc3232');
                // Clear any previous timeout
                if ($(this).data('validationTimeout')) {
                    clearTimeout($(this).data('validationTimeout'));
                }
                // Show error message briefly
                var $input = $(this);
                var timeout = setTimeout(function() {
                    showMessage('Value must be a multiple of £' + denomination.toFixed(2), 'error');
                }, 500);
                $(this).data('validationTimeout', timeout);
                updateTotalCash();
                return;
            }

            // Reset validation styling
            $(this).css('border-color', '');
            $(this).css('background-color', '');
            $total.css('color', '');

            $quantityInput.val('').prop('disabled', true);
            $total.text('£' + value.toFixed(2));
        } else {
            $(this).css('border-color', '');
            $(this).css('background-color', '');
            $total.css('color', '');
            $quantityInput.prop('disabled', false);
            $total.text('£0.00');
        }

        updateTotalCash();
    });

    // Update total cash takings
    function updateTotalCash() {
        var total = 0;

        $('.takings-denomination-row').each(function() {
            var rowTotal = parseFloat($(this).find('.takings-denom-total').text().replace('£', '')) || 0;
            total += rowTotal;
        });

        $('#total-cash-counted, #public-total-cash-counted').text('£' + total.toFixed(2));

        // Update cash takings variance if Newbook data is available
        updateCashTakingsVariance();

        // Update reconciliation table in real-time
        calculateReconciliation();

        // Mark form as dirty
        markFormDirty();
    }

    // Update cash takings variance against Newbook
    function updateCashTakingsVariance() {
        if (!newbookPaymentTotals || !newbookPaymentTotals.cash) {
            return;
        }

        var cashCounted = parseFloat($('#total-cash-counted, #public-total-cash-counted').text().replace('£', '')) || 0;
        var newbookCash = newbookPaymentTotals.cash || 0;
        var variance = cashCounted - newbookCash;

        // Show the variance rows
        $('#cash-takings-newbook-row, #public-cash-takings-newbook-row').show();
        $('#cash-takings-variance-row, #public-cash-takings-variance-row').show();

        // Update expected amount
        $('#newbook-cash-expected, #public-newbook-cash-expected').text('£' + newbookCash.toFixed(2));

        // Update variance
        var $varianceElement = $('#cash-takings-variance, #public-cash-takings-variance');
        $varianceElement.text('£' + variance.toFixed(2));

        // Apply styling based on variance
        applyVarianceStyle($varianceElement, variance);
    }

    // =======================
    // Card Machine Calculations
    // =======================

    $(document).on('input', '.machine-total, .machine-amex', function() {
        var machine = $(this).data('machine');
        var total = parseFloat($('#' + machine + '_total, #public_' + machine + '_total').val()) || 0;
        var amex = parseFloat($('#' + machine + '_amex, #public_' + machine + '_amex').val()) || 0;
        var visaMc = total - amex;

        $('#' + machine + '_visa_mc, #public_' + machine + '_visa_mc').text(visaMc.toFixed(2));

        updateCombinedTotals();
    });

    // Update combined PDQ totals
    function updateCombinedTotals() {
        var frontDeskTotal = parseFloat($('#front_desk_total, #public_front_desk_total').val()) || 0;
        var frontDeskAmex = parseFloat($('#front_desk_amex, #public_front_desk_amex').val()) || 0;
        var restaurantTotal = parseFloat($('#restaurant_total, #public_restaurant_total').val()) || 0;
        var restaurantAmex = parseFloat($('#restaurant_amex, #public_restaurant_amex').val()) || 0;

        var combinedTotal = frontDeskTotal + restaurantTotal;
        var combinedAmex = frontDeskAmex + restaurantAmex;
        var combinedVisaMc = combinedTotal - combinedAmex;

        $('#combined_total, #public_combined_total').text(combinedTotal.toFixed(2));
        $('#combined_amex, #public_combined_amex').text(combinedAmex.toFixed(2));
        $('#combined_visa_mc, #public_combined_visa_mc').text(combinedVisaMc.toFixed(2));

        // Update reconciliation table in real-time
        calculateReconciliation();

        // Mark form as dirty
        markFormDirty();
    }

    // =======================
    // Notes Field Change Tracking
    // =======================

    $(document).on('input', '#cash_up_notes, #public_cash_up_notes', function() {
        markFormDirty();
    });

    // =======================
    // Load Existing Cash Up
    // =======================

    $('#hcr-load-existing, #hcr-public-load-existing').on('click', function() {
        console.log('HCR: Load button clicked');
        var sessionDate = $('#session_date, #public_session_date').val();
        console.log('HCR: Session date:', sessionDate);

        if (!sessionDate) {
            alert('Please select a date first.');
            return;
        }

        // Safely determine which context we're in (admin or public)
        var ajaxUrl, nonce;

        if (typeof hcrAdmin !== 'undefined' && hcrAdmin.ajaxUrl) {
            console.log('HCR: Using hcrAdmin context for load');
            ajaxUrl = hcrAdmin.ajaxUrl;
            nonce = hcrAdmin.nonce;
        } else if (typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl) {
            console.log('HCR: Using hcrPublic context for load');
            ajaxUrl = hcrPublic.ajaxUrl;
            nonce = hcrPublic.nonce;
        } else {
            console.error('HCR: No valid context found for load!');
            showMessage('Configuration error: AJAX settings not found.', 'error');
            return;
        }

        console.log('HCR: Loading with URL:', ajaxUrl, 'Nonce:', nonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_load_cash_up',
                nonce: nonce,
                session_date: sessionDate
            },
            success: function(response) {
                console.log('HCR: Load response:', response);
                if (response.success) {
                    loadCashUpData(response.data);
                    showMessage('Cash up loaded successfully.', 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('HCR: Load error:', xhr, status, error);
                showMessage('An error occurred loading the cash up.', 'error');
            }
        });
    });

    // Load cash up data into form
    function loadCashUpData(data) {
        console.log('HCR: loadCashUpData called with:', data);

        // Set loading flag to prevent marking as dirty
        isLoadingData = true;

        currentCashUpId = data.cash_up.id;
        currentStatus = data.cash_up.status;

        // Update status indicators
        var statusBadge = '<span class="hcr-status-badge hcr-status-' + currentStatus + '">' +
            currentStatus.toUpperCase() +
            '</span>';
        $('#hcr-status-indicator').html(statusBadge);
        $('#hcr-session-status').html(statusBadge);

        // Load notes
        $('#cash_up_notes, #public_cash_up_notes').val(data.cash_up.notes);
        console.log('HCR: Loaded notes:', data.cash_up.notes);

        // Load photo if exists
        if (data.cash_up.machine_photo_id && data.photo_url) {
            console.log('HCR: Loading photo with ID:', data.cash_up.machine_photo_id);
            $('#hcr-machine-photo-id, #hcr-public-machine-photo-id').val(data.cash_up.machine_photo_id);
            $('#hcr-machine-photo-preview, #hcr-public-machine-photo-preview').attr('src', data.photo_url);
            $('#hcr-photo-display-area, #hcr-public-photo-display-area').show();
            $('#hcr-photo-upload-area, #hcr-public-photo-upload-area').hide();
        }

        // Load denominations - separate float and takings
        console.log('HCR: Loading', data.denominations.length, 'denominations');
        data.denominations.forEach(function(denom) {
            var countType = denom.count_type || 'takings'; // Default to takings for backward compatibility
            var $row;

            console.log('HCR: Processing denom:', denom);
            console.log('HCR: Count type:', countType, 'Value:', denom.denomination_value);

            if (countType === 'float') {
                var selector = '.float-denomination-row[data-value="' + denom.denomination_value + '"]';
                console.log('HCR: Float selector:', selector);
                $row = $(selector);
                console.log('HCR: Found rows:', $row.length);

                if (denom.quantity) {
                    console.log('HCR: Setting float quantity to:', denom.quantity);
                    var $input = $row.find('.float-denom-quantity');
                    console.log('HCR: Found quantity input:', $input.length);
                    $input.val(denom.quantity).trigger('input');
                } else if (denom.value_entered) {
                    console.log('HCR: Setting float value to:', denom.value_entered);
                    var $input = $row.find('.float-denom-value');
                    console.log('HCR: Found value input:', $input.length);
                    $input.val(denom.value_entered).trigger('input');
                }
            } else {
                var selector = '.takings-denomination-row[data-value="' + denom.denomination_value + '"]';
                console.log('HCR: Takings selector:', selector);
                $row = $(selector);
                console.log('HCR: Found rows:', $row.length);

                if (denom.quantity) {
                    console.log('HCR: Setting takings quantity to:', denom.quantity);
                    var $input = $row.find('.takings-denom-quantity');
                    console.log('HCR: Found quantity input:', $input.length);
                    $input.val(denom.quantity).trigger('input');
                } else if (denom.value_entered) {
                    console.log('HCR: Setting takings value to:', denom.value_entered);
                    var $input = $row.find('.takings-denom-value');
                    console.log('HCR: Found value input:', $input.length);
                    $input.val(denom.value_entered).trigger('input');
                }
            }
        });

        // Load card machines
        console.log('HCR: Loading', data.card_machines.length, 'card machines');
        data.card_machines.forEach(function(machine) {
            console.log('HCR: Processing machine:', machine);

            // Map machine names to their ID prefixes
            var machineName;
            if (machine.machine_name === 'Front Desk') {
                machineName = 'front_desk';
            } else if (machine.machine_name === 'Restaurant/Bar') {
                machineName = 'restaurant';
            } else {
                // Fallback: convert to lowercase and replace non-alpha with underscore
                machineName = machine.machine_name.toLowerCase().replace(/[^a-z]/g, '_');
            }

            console.log('HCR: Machine name "' + machine.machine_name + '" converted to:', machineName);
            $('#' + machineName + '_total, #public_' + machineName + '_total').val(machine.total_amount).trigger('input');
            $('#' + machineName + '_amex, #public_' + machineName + '_amex').val(machine.amex_amount).trigger('input');
        });

        // If final, disable editing
        if (currentStatus === 'final') {
            disableForm();
        }

        // Load reconciliation if exists
        if (data.reconciliation && data.reconciliation.length > 0) {
            displayReconciliation(data.reconciliation);
        }

        // Clear accumulated receipt photos for new data load
        accumulatedReceiptPhotos = [];
        accumulatedPublicReceiptPhotos = [];

        // Load existing receipt photo attachments
        if (data.attachments && data.attachments.length > 0) {
            displayExistingAttachments(data.attachments);
        }

        // Auto-fetch Newbook data for the loaded date
        var sessionDate = data.cash_up.session_date;
        if (sessionDate) {
            console.log('HCR: Auto-fetching Newbook data for date:', sessionDate);
            autoFetchNewbookData(sessionDate);
        }

        // Clear loading flag after a short delay to allow all triggers to complete
        setTimeout(function() {
            isLoadingData = false;
            console.log('HCR: Data loading complete, dirty tracking re-enabled');
        }, 100);
    }

    // Auto-fetch Newbook data when loading a cash up
    function autoFetchNewbookData(sessionDate) {
        var $status = $('#hcr-fetch-status');
        $status.html('<span class="hcr-spinner"></span> Auto-fetching Newbook data...');

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_fetch_newbook_payments',
                nonce: hcrAdmin.nonce,
                session_date: sessionDate
            },
            success: function(response) {
                console.log('HCR: Auto-fetch response:', response);
                if (response.success) {
                    newbookPaymentTotals = response.data.totals;
                    $status.html('<span style="color: green;">✓ Auto-fetched ' + response.data.count + ' payments</span>');
                    calculateReconciliation();
                    updateCashTakingsVariance();

                    // Display till system payments if available
                    console.log('HCR: Auto-fetch checking till_payments:', response.data.till_payments);
                    if (response.data.till_payments && response.data.till_payments.length > 0) {
                        console.log('HCR: Auto-fetch found till payments, calling displayTillPayments');
                        cachedTillPayments = response.data.till_payments; // Cache for dynamic updates
                        displayTillPayments(response.data.till_payments);
                    } else {
                        console.log('HCR: Auto-fetch no till payments, hiding button');
                        cachedTillPayments = null;
                        $('#hcr-show-till-payments').hide();
                    }

                    // Display transaction breakdown if available
                    if (response.data.transaction_breakdown) {
                        displayTransactionBreakdown(response.data.transaction_breakdown);
                    }
                } else {
                    $status.html('<span style="color: orange;">⚠ Could not auto-fetch Newbook data</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: orange;">⚠ Could not auto-fetch Newbook data</span>');
            }
        });
    }

    // Disable form for final cash ups
    function disableForm() {
        $('#hcr-cash-up-form input, #hcr-cash-up-form textarea, #hcr-cash-up-form button[type="submit"]').prop('disabled', true);
        $('#hcr-fetch-payments').prop('disabled', false); // Keep fetch payments enabled
        showMessage('This cash up is final and cannot be edited.', 'error');
    }

    // =======================
    // Fetch Newbook Payments
    // =======================

    $('#hcr-fetch-payments').on('click', function() {
        var $btn = $(this);
        var $status = $('#hcr-fetch-status');
        var sessionDate = $('#session_date, #public_session_date').val();

        if (!sessionDate) {
            alert('Please select a date first.');
            return;
        }

        $btn.prop('disabled', true).text('Fetching...');
        $status.html('<span class="hcr-spinner"></span> Fetching payments from Newbook...');

        $.ajax({
            url: hcrAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_fetch_newbook_payments',
                nonce: hcrAdmin.nonce,
                session_date: sessionDate
            },
            success: function(response) {
                console.log('HCR: Fetch payments response:', response);
                if (response.success) {
                    newbookPaymentTotals = response.data.totals;
                    $status.html('<span style="color: green;">✓ Fetched ' + response.data.count + ' payments</span>');
                    calculateReconciliation();
                    updateCashTakingsVariance();

                    // Display till system payments if available
                    console.log('HCR: Checking till_payments:', response.data.till_payments);
                    if (response.data.till_payments && response.data.till_payments.length > 0) {
                        console.log('HCR: Till payments found, calling displayTillPayments');
                        cachedTillPayments = response.data.till_payments; // Cache for dynamic updates
                        displayTillPayments(response.data.till_payments);
                    } else {
                        console.log('HCR: No till payments, hiding button');
                        cachedTillPayments = null;
                        // Hide till payments button if no data
                        $('#hcr-show-till-payments').hide();
                    }

                    // Display transaction breakdown if available
                    if (response.data.transaction_breakdown) {
                        displayTransactionBreakdown(response.data.transaction_breakdown);
                    }
                } else {
                    $status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">✗ An error occurred</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Fetch Newbook Payments');
            }
        });
    });

    // =======================
    // Calculate Reconciliation
    // =======================

    function calculateReconciliation() {
        if (!newbookPaymentTotals) {
            return;
        }

        // Get banked amounts
        var cashBanked = parseFloat($('#total-cash-counted, #public-total-cash-counted').text().replace('£', '')) || 0;
        var pdqAmexBanked = parseFloat($('#combined_amex, #public_combined_amex').text()) || 0;
        var pdqVisaMcBanked = parseFloat($('#combined_visa_mc, #public_combined_visa_mc').text()) || 0;

        // Get reported amounts from Newbook
        var cashReported = newbookPaymentTotals.cash || 0;
        var manualAmex = newbookPaymentTotals.manual_amex || 0;
        var manualVisaMc = newbookPaymentTotals.manual_visa_mc || 0;
        var gatewayAmex = newbookPaymentTotals.gateway_amex || 0;
        var gatewayVisaMc = newbookPaymentTotals.gateway_visa_mc || 0;
        var bacsReported = newbookPaymentTotals.bacs || 0;

        // Calculate variances
        var reconciliation = [
            {
                category: 'Cash',
                banked: cashBanked,
                reported: cashReported,
                variance: cashBanked - cashReported
            },
            {
                category: 'PDQ Visa/Mastercard',
                banked: pdqVisaMcBanked,
                reported: manualVisaMc,
                variance: pdqVisaMcBanked - manualVisaMc
            },
            {
                category: 'PDQ Amex',
                banked: pdqAmexBanked,
                reported: manualAmex,
                variance: pdqAmexBanked - manualAmex
            },
            {
                category: 'Gateway Visa/Mastercard',
                banked: gatewayVisaMc,
                reported: gatewayVisaMc,
                variance: 0
            },
            {
                category: 'Gateway Amex',
                banked: gatewayAmex,
                reported: gatewayAmex,
                variance: 0
            },
            {
                category: 'BACS/Bank Transfer',
                banked: bacsReported,
                reported: bacsReported,
                variance: 0
            }
        ];

        displayReconciliation(reconciliation);
    }

    // Display reconciliation table
    function displayReconciliation(reconciliationData) {
        var $tbody = $('#hcr-reconciliation-body');
        $tbody.empty();

        var dataArray = Array.isArray(reconciliationData) ? reconciliationData : [];

        // If data is from database, transform it
        if (dataArray.length > 0 && dataArray[0].hasOwnProperty('id')) {
            dataArray = dataArray.map(function(item) {
                return {
                    category: formatCategory(item.category),
                    banked: parseFloat(item.banked_amount),
                    reported: parseFloat(item.reported_amount),
                    variance: parseFloat(item.variance)
                };
            });
        }

        // Group data by type for total variance calculation
        var cashRows = [];
        var cardRows = [];
        var bacsRows = [];

        dataArray.forEach(function(item) {
            if (item.category === 'Cash') {
                cashRows.push(item);
            } else if (item.category === 'BACS/Bank Transfer') {
                bacsRows.push(item);
            } else {
                // All card types (PDQ and Gateway, Visa/MC and Amex)
                cardRows.push(item);
            }
        });

        // Calculate total variances
        var cardTotalVariance = cardRows.reduce(function(sum, item) { return sum + item.variance; }, 0);

        // Render rows with total variance column
        var cardRowsRendered = 0;

        dataArray.forEach(function(item) {
            var varianceClass = getVarianceClass(item.variance);
            var varianceSign = item.variance >= 0 ? '+' : '';
            var varianceBgStyle = getVarianceBgStyle(item.variance);

            var row = '<tr>' +
                '<td>' + item.category + '</td>' +
                '<td>£' + item.banked.toFixed(2) + '</td>' +
                '<td>£' + item.reported.toFixed(2) + '</td>' +
                '<td class="' + varianceClass + '" style="' + varianceBgStyle + '">' + varianceSign + '£' + item.variance.toFixed(2) + '</td>';

            // Total Variance column
            if (item.category === 'Cash' || item.category === 'BACS/Bank Transfer') {
                // For cash and BACS, total variance is the same as individual variance
                row += '<td class="' + varianceClass + '" style="' + varianceBgStyle + '">' + varianceSign + '£' + item.variance.toFixed(2) + '</td>';
            } else {
                // For card rows, show total variance only on the first card row with rowspan
                if (cardRowsRendered === 0) {
                    var totalVarianceClass = getVarianceClass(cardTotalVariance);
                    var totalVarianceSign = cardTotalVariance >= 0 ? '+' : '';
                    var totalVarianceBgStyle = getVarianceBgStyle(cardTotalVariance);
                    row += '<td rowspan="' + cardRows.length + '" class="' + totalVarianceClass + '" style="' + totalVarianceBgStyle + ' vertical-align: middle; text-align: center; font-size: 1.1em;">' +
                           totalVarianceSign + '£' + cardTotalVariance.toFixed(2) + '</td>';
                }
                cardRowsRendered++;
            }

            row += '</tr>';
            $tbody.append(row);
        });

        // Update title with date
        var selectedDate = $('#session_date, #public_session_date').val();
        if (selectedDate) {
            var dateObj = new Date(selectedDate + 'T00:00:00');
            var dateStr = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            $('#hcr-reconciliation-title').text('Reconciliation Summary for ' + dateStr);
        }

        $('#hcr-reconciliation-section').show();
    }

    // Format category name
    function formatCategory(category) {
        var map = {
            'cash': 'Cash',
            'pdq_visa_mc': 'PDQ Visa/Mastercard',
            'pdq_amex': 'PDQ Amex',
            'gateway_visa_mc': 'Gateway Visa/Mastercard',
            'gateway_amex': 'Gateway Amex',
            'bacs': 'BACS/Bank Transfer'
        };
        return map[category] || category;
    }

    // Get variance CSS class
    function getVarianceClass(variance) {
        if (Math.abs(variance) < 0.005) return 'hcr-variance-balanced';
        if (variance > 0) return 'hcr-variance-over';
        if (variance < 0) return 'hcr-variance-short';
        return 'hcr-variance-balanced';
    }

    // Get variance background style
    function getVarianceBgStyle(variance) {
        if (Math.abs(variance) < 0.005) {
            // Balanced - green background (less than half a penny)
            return 'background-color: #d4edda; color: #155724; font-weight: bold;';
        } else if (variance > 0) {
            // Over - amber background
            return 'background-color: #fff3cd; color: #856404; font-weight: bold;';
        } else {
            // Short - red background
            return 'background-color: #f8d7da; color: #721c24; font-weight: bold;';
        }
    }

    // =======================
    // Display Till System Payments
    // =======================

    function displayTillPayments(tillPayments) {
        console.log('HCR: Displaying till payments:', tillPayments);

        if (!tillPayments || tillPayments.length === 0) {
            $('#hcr-show-till-payments').hide();
            return;
        }

        // Calculate till card total (excluding cash)
        var tillCardTotal = 0;
        tillPayments.forEach(function(item) {
            // Exclude cash from card total calculation
            if (item.payment_type.toLowerCase() !== 'cash') {
                tillCardTotal += item.total_value;
            }
        });

        // Get restaurant/bar machine total
        var restaurantTotal = parseFloat($('#restaurant_total, #public_restaurant_total').val()) || 0;
        var restaurantAmex = parseFloat($('#restaurant_amex, #public_restaurant_amex').val()) || 0;
        var restaurantMachineTotal = restaurantTotal;

        // Calculate variance (card only)
        var variance = restaurantMachineTotal - tillCardTotal;

        // Build tooltip content
        var content = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
        content += '<thead><tr style="border-bottom: 1px solid #ddd;">';
        content += '<th style="text-align: left; padding: 5px 0;">Payment Type</th>';
        content += '<th style="text-align: center; padding: 5px 0;">Qty</th>';
        content += '<th style="text-align: right; padding: 5px 0;">Value</th>';
        content += '</tr></thead><tbody>';

        tillPayments.forEach(function(item) {
            var qtyDisplay = item.quantity;
            var valueFormatted = '£' + Math.abs(item.total_value).toFixed(2);
            var valueStyle = '';
            var rowStyle = '';
            var isCash = item.payment_type.toLowerCase() === 'cash';

            // If negative (refund in revenue perspective), show in red with parentheses
            if (item.total_value < 0) {
                valueFormatted = '(£' + Math.abs(item.total_value).toFixed(2) + ')';
                valueStyle = ' style="color: #721c24;"';
            }

            // Grey out cash row (excluded from variance)
            if (isCash) {
                rowStyle = ' style="color: #999; opacity: 0.6;"';
            }

            content += '<tr' + rowStyle + '>';
            content += '<td style="padding: 3px 0;">' + item.payment_type + '</td>';
            content += '<td style="text-align: center; padding: 3px 0;">' + qtyDisplay + '</td>';
            content += '<td style="text-align: right; padding: 3px 0;"' + (isCash ? rowStyle : valueStyle) + '>' + valueFormatted + '</td>';
            content += '</tr>';
        });

        // Till Card Total row (excluding cash)
        var tillCardTotalFormatted = '£' + Math.abs(tillCardTotal).toFixed(2);
        var tillCardTotalStyle = '';
        if (tillCardTotal < 0) {
            tillCardTotalFormatted = '(£' + Math.abs(tillCardTotal).toFixed(2) + ')';
            tillCardTotalStyle = ' style="color: #721c24;"';
        }

        content += '<tr style="border-top: 2px solid #666; font-weight: bold;">';
        content += '<td colspan="2" style="padding: 5px 0;">Till Card Total</td>';
        content += '<td style="text-align: right; padding: 5px 0;"' + tillCardTotalStyle + '>' + tillCardTotalFormatted + '</td>';
        content += '</tr>';

        // Restaurant/Bar PDQ row
        content += '<tr style="font-weight: bold;">';
        content += '<td colspan="2" style="padding: 5px 0;">Restaurant/Bar PDQ</td>';
        content += '<td style="text-align: right; padding: 5px 0;">£' + restaurantMachineTotal.toFixed(2) + '</td>';
        content += '</tr>';

        // Till Card Variance row with color coding
        var varianceColor = '';
        var varianceSign = '';
        if (Math.abs(variance) < 0.005) {
            varianceColor = '#155724'; // Green (less than half a penny)
            varianceSign = '';
        } else if (variance > 0) {
            varianceColor = '#856404'; // Amber
            varianceSign = '+';
        } else {
            varianceColor = '#721c24'; // Red
            varianceSign = '';
        }

        content += '<tr style="border-top: 2px solid #666; font-weight: bold; color: ' + varianceColor + ';">';
        content += '<td colspan="2" style="padding: 5px 0;">Till Card Variance</td>';
        content += '<td style="text-align: right; padding: 5px 0;">' + varianceSign + '£' + variance.toFixed(2) + '</td>';
        content += '</tr>';

        content += '</tbody></table>';

        $('#hcr-till-payments-content').html(content);

        // Show the button
        $('#hcr-show-till-payments').show();
    }

    // =======================
    // Transaction Breakdown Display
    // =======================

    function displayTransactionBreakdown(breakdown) {
        console.log('HCR: Displaying transaction breakdown:', breakdown);

        // Check if there's any data
        var hasData = false;
        if (breakdown) {
            hasData = (Object.keys(breakdown.reception_manual || {}).length > 0) ||
                     (Object.keys(breakdown.reception_gateway || {}).length > 0) ||
                     (Object.keys(breakdown.restaurant_bar || {}).length > 0);
        }

        if (!hasData) {
            $('#hcr-transaction-breakdown-section').hide();
            return;
        }

        // Clear existing content
        $('#hcr-reception-breakdown').empty();
        $('#hcr-restaurant-breakdown').empty();

        var receptionHasData = false;

        // Populate Reception table - Manual payments grouped by type
        if (breakdown.reception_manual && Object.keys(breakdown.reception_manual).length > 0) {
            receptionHasData = true;
            Object.keys(breakdown.reception_manual).forEach(function(paymentCategory) {
                var transactions = breakdown.reception_manual[paymentCategory];
                if (transactions.length > 0) {
                    // Calculate subtotal for this category
                    var subtotal = 0;
                    transactions.forEach(function(transaction) {
                        subtotal += transaction.amount;
                    });
                    var subtotalFormatted = formatTransactionAmount(subtotal);

                    $('#hcr-reception-breakdown').append('<tr class="hcr-breakdown-subheading"><td colspan="3"><strong>Manual ' + paymentCategory + '</strong></td><td style="text-align: right;"><strong>' + subtotalFormatted + '</strong></td></tr>');
                    transactions.forEach(function(transaction) {
                        var timeFormatted = formatTransactionTime(transaction.time);
                        var amountFormatted = formatTransactionAmount(transaction.amount, transaction.item_type);
                        var rowClass = transaction.is_voided ? 'hcr-selectable-row hcr-voided-transaction' : 'hcr-selectable-row';
                        $('#hcr-reception-breakdown').append(
                            '<tr class="' + rowClass + '">' +
                            '<td>' + timeFormatted + '</td>' +
                            '<td>' + (transaction.payment_type || '') + '</td>' +
                            '<td>' + transaction.details + '</td>' +
                            '<td style="text-align: right;">' + amountFormatted + '</td>' +
                            '</tr>'
                        );
                    });
                }
            });
        }

        // Populate Reception table - Gateway payments grouped by type
        if (breakdown.reception_gateway && Object.keys(breakdown.reception_gateway).length > 0) {
            receptionHasData = true;
            Object.keys(breakdown.reception_gateway).forEach(function(paymentCategory) {
                var transactions = breakdown.reception_gateway[paymentCategory];
                if (transactions.length > 0) {
                    // Calculate subtotal for this category
                    var subtotal = 0;
                    transactions.forEach(function(transaction) {
                        subtotal += transaction.amount;
                    });
                    var subtotalFormatted = formatTransactionAmount(subtotal);

                    $('#hcr-reception-breakdown').append('<tr class="hcr-breakdown-subheading"><td colspan="3"><strong>Gateway ' + paymentCategory + '</strong></td><td style="text-align: right;"><strong>' + subtotalFormatted + '</strong></td></tr>');
                    transactions.forEach(function(transaction) {
                        var timeFormatted = formatTransactionTime(transaction.time);
                        var amountFormatted = formatTransactionAmount(transaction.amount, transaction.item_type);
                        var rowClass = transaction.is_voided ? 'hcr-selectable-row hcr-voided-transaction' : 'hcr-selectable-row';
                        $('#hcr-reception-breakdown').append(
                            '<tr class="' + rowClass + '">' +
                            '<td>' + timeFormatted + '</td>' +
                            '<td>' + (transaction.payment_type || '') + '</td>' +
                            '<td>' + transaction.details + '</td>' +
                            '<td style="text-align: right;">' + amountFormatted + '</td>' +
                            '</tr>'
                        );
                    });
                }
            });
        }

        if (!receptionHasData) {
            $('#hcr-reception-breakdown').append('<tr><td colspan="4" style="text-align: center; color: #666;">No reception payments</td></tr>');
        }

        // Populate Restaurant/Bar table - grouped by payment type
        if (breakdown.restaurant_bar && Object.keys(breakdown.restaurant_bar).length > 0) {
            Object.keys(breakdown.restaurant_bar).forEach(function(paymentCategory) {
                var transactions = breakdown.restaurant_bar[paymentCategory];
                if (transactions.length > 0) {
                    // Calculate subtotal for this category
                    var subtotal = 0;
                    transactions.forEach(function(transaction) {
                        subtotal += transaction.amount;
                    });
                    var subtotalFormatted = formatTransactionAmount(subtotal);

                    $('#hcr-restaurant-breakdown').append('<tr class="hcr-breakdown-subheading"><td colspan="3"><strong>' + paymentCategory + '</strong></td><td style="text-align: right;"><strong>' + subtotalFormatted + '</strong></td></tr>');
                    transactions.forEach(function(transaction) {
                        var timeFormatted = formatTransactionTime(transaction.time);
                        var amountFormatted = formatTransactionAmount(transaction.amount, transaction.item_type);
                        var rowClass = transaction.is_voided ? 'hcr-selectable-row hcr-voided-transaction' : 'hcr-selectable-row';
                        $('#hcr-restaurant-breakdown').append(
                            '<tr class="' + rowClass + '">' +
                            '<td>' + timeFormatted + '</td>' +
                            '<td>' + (transaction.payment_type || '') + '</td>' +
                            '<td>' + transaction.details + '</td>' +
                            '<td style="text-align: right;">' + amountFormatted + '</td>' +
                            '</tr>'
                        );
                    });
                }
            });
        } else {
            $('#hcr-restaurant-breakdown').append('<tr><td colspan="4" style="text-align: center; color: #666;">No restaurant/bar payments</td></tr>');
        }

        // Show the section
        $('#hcr-transaction-breakdown-section').show();
    }

    // Format transaction time (extract time from datetime)
    function formatTransactionTime(datetime) {
        if (!datetime) return '';
        var parts = datetime.split(' ');
        return parts.length > 1 ? parts[1] : datetime;
    }

    // Format transaction amount (accounting style)
    // In Newbook: payments are negative (money in), refunds are positive (money out)
    // Display: show refunds in red with parentheses
    // itemType parameter: 'payments_voided', 'refunds_voided', 'payments_raised', 'refunds_raised'
    function formatTransactionAmount(amount, itemType) {
        var absAmount = Math.abs(amount);
        var formatted = '£' + absAmount.toFixed(2);
        var isVoided = (itemType === 'payments_voided' || itemType === 'refunds_voided');

        if (amount > 0) {
            // Refund (positive) - show in red with parentheses
            var display = '<span style="color: #721c24;">(' + formatted + ')</span>';
            if (itemType === 'refunds_voided') {
                // Voided refund - strikethrough only
                display = '<span style="text-decoration: line-through;">' + display + '</span>';
            }
            return display;
        }

        // Payment (negative) - show normally
        if (itemType === 'payments_voided') {
            // Voided payment - show in brackets AND strikethrough
            return '<span style="text-decoration: line-through; color: #721c24;">(' + formatted + ')</span>';
        }

        return formatted;
    }

    // =======================
    // Transaction Breakdown Toggle
    // =======================

    $(document).on('click', '#hcr-breakdown-toggle', function() {
        var $content = $('#hcr-breakdown-content');
        var $arrow = $('#hcr-breakdown-arrow');

        if ($content.is(':visible')) {
            $content.slideUp(200);
            $arrow.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
        } else {
            $content.slideDown(200);
            $arrow.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
        }
    });

    // =======================
    // Selectable Transaction Rows
    // =======================

    $(document).on('click', '.hcr-selectable-row', function() {
        $(this).toggleClass('hcr-row-selected');
    });

    // =======================
    // Till Payments Button Hover
    // =======================

    $(document).on('mouseenter', '#hcr-show-till-payments', function() {
        var $button = $(this);
        var $tooltip = $('#hcr-till-payments-tooltip');

        // Position tooltip below button
        var buttonOffset = $button.offset();
        var buttonHeight = $button.outerHeight();

        $tooltip.css({
            top: (buttonOffset.top + buttonHeight + 5) + 'px',
            left: (buttonOffset.left - $tooltip.outerWidth() + $button.outerWidth()) + 'px'
        }).fadeIn(200);
    });

    $(document).on('mouseleave', '#hcr-show-till-payments', function() {
        // Small delay before hiding to allow moving to tooltip
        setTimeout(function() {
            if (!$('#hcr-till-payments-tooltip:hover').length && !$('#hcr-show-till-payments:hover').length) {
                $('#hcr-till-payments-tooltip').fadeOut(200);
            }
        }, 100);
    });

    // Keep tooltip visible when hovering over it
    $(document).on('mouseenter', '#hcr-till-payments-tooltip', function() {
        $(this).stop(true, true).show();
    });

    $(document).on('mouseleave', '#hcr-till-payments-tooltip', function() {
        $(this).fadeOut(200);
    });

    // =======================
    // Dynamic Till Payments Update
    // =======================

    // Update till payments tooltip when restaurant/bar PDQ values change
    $(document).on('input change', '#restaurant_total, #public_restaurant_total, #restaurant_amex, #public_restaurant_amex', function() {
        console.log('HCR: Restaurant/Bar PDQ value changed, updating till payments display');
        if (cachedTillPayments && cachedTillPayments.length > 0) {
            displayTillPayments(cachedTillPayments);
        }
    });

    // =======================
    // Save Cash Up
    // =======================

    $('#hcr-cash-up-form, #hcr-public-cash-up-form').on('submit', function(e) {
        e.preventDefault();
        console.log('HCR: Form submit triggered');

        var $form = $(this);
        var status = 'draft';

        // Determine if this is draft or final
        var clickedButton = $(document.activeElement);
        console.log('HCR: Clicked button ID:', clickedButton.attr('id'));

        if (clickedButton.attr('id') === 'hcr-submit-final' || clickedButton.attr('id') === 'hcr-public-submit-final') {
            status = 'final';

            if (!confirm('Are you sure you want to submit this as final? It cannot be edited afterward.')) {
                return;
            }
        }

        // Gather float denomination data
        var floatDenominations = [];
        $('.float-denomination-row').each(function() {
            var $row = $(this);
            var quantity = parseInt($row.find('.float-denom-quantity').val()) || null;
            var value = parseFloat($row.find('.float-denom-value').val()) || null;
            var total = parseFloat($row.find('.float-denom-total').text().replace('£', '')) || 0;

            if (total > 0) {
                floatDenominations.push({
                    count_type: 'float',
                    type: $row.data('type'),
                    value: $row.data('value'),
                    quantity: quantity,
                    value_entered: value,
                    total_amount: total
                });
            }
        });

        // Gather takings denomination data
        var takingsDenominations = [];
        $('.takings-denomination-row').each(function() {
            var $row = $(this);
            var quantity = parseInt($row.find('.takings-denom-quantity').val()) || null;
            var value = parseFloat($row.find('.takings-denom-value').val()) || null;
            var total = parseFloat($row.find('.takings-denom-total').text().replace('£', '')) || 0;

            if (total > 0) {
                takingsDenominations.push({
                    count_type: 'takings',
                    type: $row.data('type'),
                    value: $row.data('value'),
                    quantity: quantity,
                    value_entered: value,
                    total_amount: total
                });
            }
        });

        // Combine both into one denominations array
        var denominations = floatDenominations.concat(takingsDenominations);

        // Gather card machine data
        var cardMachines = [
            {
                name: 'Front Desk',
                total: parseFloat($('#front_desk_total, #public_front_desk_total').val()) || 0,
                amex: parseFloat($('#front_desk_amex, #public_front_desk_amex').val()) || 0,
                visa_mc: parseFloat($('#front_desk_visa_mc, #public_front_desk_visa_mc').text()) || 0
            },
            {
                name: 'Restaurant/Bar',
                total: parseFloat($('#restaurant_total, #public_restaurant_total').val()) || 0,
                amex: parseFloat($('#restaurant_amex, #public_restaurant_amex').val()) || 0,
                visa_mc: parseFloat($('#restaurant_visa_mc, #public_restaurant_visa_mc').text()) || 0
            }
        ];

        // Safely determine which context we're in (admin or public)
        var ajaxUrl, nonce;

        if (typeof hcrAdmin !== 'undefined' && hcrAdmin.ajaxUrl) {
            console.log('HCR: Using hcrAdmin context');
            ajaxUrl = hcrAdmin.ajaxUrl;
            nonce = hcrAdmin.nonce;
        } else if (typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl) {
            console.log('HCR: Using hcrPublic context');
            ajaxUrl = hcrPublic.ajaxUrl;
            nonce = hcrPublic.nonce;
        } else {
            console.error('HCR: No valid context found!');
            showMessage('Configuration error: AJAX settings not found.', 'error');
            return;
        }

        // Use FormData to handle file uploads
        var formData = new FormData();
        formData.append('action', 'hcr_save_cash_up');
        formData.append('nonce', nonce);
        formData.append('session_date', $('#session_date, #public_session_date').val());
        formData.append('status', status);
        formData.append('notes', $('#cash_up_notes, #public_cash_up_notes').val());
        formData.append('machine_photo_id', $('#hcr-machine-photo-id, #hcr-public-machine-photo-id').val() || '');

        // Append arrays as JSON strings
        formData.append('denominations', JSON.stringify(denominations));
        formData.append('card_machines', JSON.stringify(cardMachines));

        // Append receipt photos from accumulated arrays
        var isPublicContext = typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl;
        var photosToUpload = isPublicContext ? accumulatedPublicReceiptPhotos : accumulatedReceiptPhotos;

        if (photosToUpload && photosToUpload.length > 0) {
            for (var i = 0; i < photosToUpload.length; i++) {
                formData.append('receipt_photos[]', photosToUpload[i]);
            }
        }

        console.log('HCR: Saving cash up with files');

        // Disable buttons
        $form.find('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('HCR: Save response:', response);
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    currentCashUpId = response.data.cash_up_id;
                    currentStatus = status;

                    // Clear dirty flag on successful save
                    clearFormDirty();

                    // Clear accumulated receipt photos arrays for new uploads
                    if (isPublicContext) {
                        accumulatedPublicReceiptPhotos = [];
                    } else {
                        accumulatedReceiptPhotos = [];
                    }

                    // Display all attachments (including newly uploaded ones)
                    if (response.data.attachments && response.data.attachments.length > 0) {
                        displayExistingAttachments(response.data.attachments);
                    } else {
                        // If no attachments, clear the preview
                        var previewContainer = isPublicContext ? $('#hcr-public-receipt-photos-preview') : $('#hcr-receipt-photos-preview');
                        previewContainer.empty();
                    }

                    if (status === 'final') {
                        disableForm();
                    }
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('HCR: Save error:', xhr, status, error);
                showMessage('An error occurred saving the cash up.', 'error');
            },
            complete: function() {
                $form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    // =======================
    // Helper Functions
    // =======================

    function showMessage(message, type) {
        // Remove any existing popup
        $('.hcr-floating-popup').remove();

        // Create floating popup
        var popupClass = type === 'success' ? 'hcr-popup-success' : 'hcr-popup-error';
        var popup = $('<div class="hcr-floating-popup ' + popupClass + '">' +
            '<div class="hcr-popup-content">' +
                '<p>' + message + '</p>' +
            '</div>' +
        '</div>');

        // Add to body and fade in
        $('body').append(popup);
        popup.fadeIn(300);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            popup.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // =======================
    // Photo Upload Handlers
    // =======================

    // Admin upload photo button
    $('#hcr-upload-photo-btn, #hcr-public-upload-photo-btn').on('click', function() {
        console.log('HCR: Photo upload button clicked');
        var isPublic = $(this).attr('id').includes('public');
        var inputId = isPublic ? '#hcr-public-machine-photo-input' : '#hcr-machine-photo-input';
        $(inputId).trigger('click');
    });

    // Handle file selection
    $('#hcr-machine-photo-input, #hcr-public-machine-photo-input').on('change', function(e) {
        var file = e.target.files[0];
        if (!file) {
            console.log('HCR: No file selected');
            return;
        }

        console.log('HCR: File selected:', file.name, file.type, file.size);

        // Validate file type
        if (!file.type.match('image.*')) {
            alert('Please select an image file (JPEG, PNG, or GIF).');
            return;
        }

        // Validate file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            alert('File is too large. Maximum size is 10MB.');
            return;
        }

        var isPublic = $(this).attr('id').includes('public');
        var statusId = isPublic ? '#hcr-public-photo-upload-status' : '#hcr-photo-upload-status';
        var displayAreaId = isPublic ? '#hcr-public-photo-display-area' : '#hcr-photo-display-area';
        var uploadAreaId = isPublic ? '#hcr-public-photo-upload-area' : '#hcr-photo-upload-area';
        var previewId = isPublic ? '#hcr-public-machine-photo-preview' : '#hcr-machine-photo-preview';
        var photoIdInput = isPublic ? '#hcr-public-machine-photo-id' : '#hcr-machine-photo-id';

        $(statusId).html('<span class="hcr-spinner"></span> Uploading...');

        // Safely determine which context we're in (admin or public)
        var ajaxUrl, nonce;
        if (typeof hcrAdmin !== 'undefined' && hcrAdmin.ajaxUrl) {
            ajaxUrl = hcrAdmin.ajaxUrl;
            nonce = hcrAdmin.nonce;
        } else if (typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl) {
            ajaxUrl = hcrPublic.ajaxUrl;
            nonce = hcrPublic.nonce;
        } else {
            console.error('HCR: No valid context found!');
            $(statusId).html('<span style="color: red;">Error: Configuration not found</span>');
            return;
        }

        // Create FormData for file upload
        var formData = new FormData();
        formData.append('action', 'hcr_upload_machine_photo');
        formData.append('nonce', nonce);
        formData.append('photo', file);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('HCR: Upload response:', response);
                if (response.success) {
                    $(statusId).html('<span style="color: green;">✓ Uploaded</span>');
                    $(photoIdInput).val(response.data.attachment_id);
                    $(previewId).attr('src', response.data.photo_url);
                    $(displayAreaId).show();
                    $(uploadAreaId).hide();
                    markFormDirty();

                    // Clear status after 3 seconds
                    setTimeout(function() {
                        $(statusId).html('');
                    }, 3000);
                } else {
                    $(statusId).html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('HCR: Upload error:', xhr, status, error);
                $(statusId).html('<span style="color: red;">✗ Upload failed</span>');
            }
        });

        // Clear the input so the same file can be selected again
        $(this).val('');
    });

    // Remove photo button
    $('#hcr-remove-photo-btn, #hcr-public-remove-photo-btn').on('click', function() {
        if (!confirm('Are you sure you want to remove this photo?')) {
            return;
        }

        var isPublic = $(this).attr('id').includes('public');
        var photoIdInput = isPublic ? '#hcr-public-machine-photo-id' : '#hcr-machine-photo-id';
        var displayAreaId = isPublic ? '#hcr-public-photo-display-area' : '#hcr-photo-display-area';
        var uploadAreaId = isPublic ? '#hcr-public-photo-upload-area' : '#hcr-photo-upload-area';
        var previewId = isPublic ? '#hcr-public-machine-photo-preview' : '#hcr-machine-photo-preview';

        var attachmentId = $(photoIdInput).val();

        if (!attachmentId) {
            // Just hide the photo if no attachment ID
            $(displayAreaId).hide();
            $(uploadAreaId).show();
            $(previewId).attr('src', '');
            return;
        }

        // Safely determine which context we're in (admin or public)
        var ajaxUrl, nonce;
        if (typeof hcrAdmin !== 'undefined' && hcrAdmin.ajaxUrl) {
            ajaxUrl = hcrAdmin.ajaxUrl;
            nonce = hcrAdmin.nonce;
        } else if (typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl) {
            ajaxUrl = hcrPublic.ajaxUrl;
            nonce = hcrPublic.nonce;
        } else {
            console.error('HCR: No valid context found!');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'hcr_delete_machine_photo',
                nonce: nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                console.log('HCR: Delete response:', response);
                if (response.success) {
                    $(photoIdInput).val('');
                    $(previewId).attr('src', '');
                    $(displayAreaId).hide();
                    $(uploadAreaId).show();
                    markFormDirty();
                } else {
                    alert('Failed to delete photo: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('HCR: Delete error:', xhr, status, error);
                alert('An error occurred deleting the photo.');
            }
        });
    });

    // Photo lightbox handlers
    $('#hcr-machine-photo-preview, #hcr-public-machine-photo-preview').on('click', function() {
        var photoSrc = $(this).attr('src');
        if (photoSrc) {
            var isPublic = $(this).attr('id').includes('public');
            var lightboxId = isPublic ? '#hcr-public-photo-lightbox' : '#hcr-photo-lightbox';
            var lightboxImageId = isPublic ? '#hcr-public-lightbox-image' : '#hcr-lightbox-image';

            $(lightboxImageId).attr('src', photoSrc);
            $(lightboxId).fadeIn(300);
        }
    });

    // Close lightbox
    $('#hcr-photo-lightbox, #hcr-lightbox-close, #hcr-public-photo-lightbox, #hcr-public-lightbox-close').on('click', function() {
        var isPublic = $(this).attr('id').includes('public');
        var lightboxId = isPublic ? '#hcr-public-photo-lightbox' : '#hcr-photo-lightbox';
        $(lightboxId).fadeOut(300);
    });

    // Prevent lightbox from closing when clicking on image
    $('#hcr-lightbox-image, #hcr-public-lightbox-image').on('click', function(e) {
        e.stopPropagation();
    });

    // =======================
    // Expose functions globally for public form access
    // =======================

    window.hcrLoadCashUpData = loadCashUpData;
    // =======================
    // Receipt Photos Preview and Popup
    // =======================

    // Arrays to accumulate files across multiple selections
    var accumulatedReceiptPhotos = [];
    var accumulatedPublicReceiptPhotos = [];

    // Button click handlers to trigger file input
    $(document).on('click', '#hcr-upload-receipt-photos-btn', function() {
        $('#hcr-receipt-photos-input').trigger('click');
    });

    $(document).on('click', '#hcr-public-upload-receipt-photos-btn', function() {
        $('#hcr-public-receipt-photos-input').trigger('click');
    });

    // Handle file input change to show previews
    $(document).on('change', '#hcr-receipt-photos-input, #hcr-public-receipt-photos-input', function() {
        var files = this.files;
        var previewContainer = $(this).siblings('.hcr-photos-grid');
        var isPublic = this.id === 'hcr-public-receipt-photos-input';
        var targetArray = isPublic ? accumulatedPublicReceiptPhotos : accumulatedReceiptPhotos;

        if (files.length === 0) {
            return;
        }

        // Add new files to accumulated array
        for (var i = 0; i < files.length; i++) {
            targetArray.push(files[i]);
        }

        // Display all accumulated files
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var reader = new FileReader();
            var fileIndex = targetArray.length - files.length + i;

            (function(f, idx) {
                reader.onload = function(e) {
                    var thumbnail = '';
                    var removeBtn = '<span class="hcr-photo-remove" data-index="' + idx + '" data-public="' + isPublic + '" title="Remove">&times;</span>';

                    if (f.type.indexOf('image/') === 0) {
                        // Image file - show thumbnail
                        thumbnail = '<div class="hcr-photo-thumbnail hcr-new-photo" data-index="' + idx + '">' +
                                   removeBtn +
                                   '<img src="' + e.target.result + '" alt="' + f.name + '">' +
                                   '<div class="hcr-photo-name">' + f.name + '</div>' +
                                   '</div>';
                    } else if (f.type === 'application/pdf') {
                        // PDF file - show PDF icon
                        thumbnail = '<div class="hcr-photo-thumbnail hcr-pdf-thumbnail hcr-new-photo" data-index="' + idx + '">' +
                                   removeBtn +
                                   '<span class="dashicons dashicons-pdf"></span>' +
                                   '<div class="hcr-photo-name">' + f.name + '</div>' +
                                   '</div>';
                    }
                    previewContainer.append(thumbnail);
                };
                reader.readAsDataURL(f);
            })(file, fileIndex);
        }

        // Clear the input so the same file can be selected again
        this.value = '';
    });

    // Handle remove photo
    $(document).on('click', '.hcr-photo-remove', function(e) {
        e.stopPropagation();

        // Check if this is an existing photo (already saved to database) or new photo
        if ($(this).hasClass('hcr-remove-existing')) {
            // Remove existing photo from database
            var attachmentId = parseInt($(this).data('attachment-id'));
            var $thumbnail = $(this).closest('.hcr-photo-thumbnail');

            if (!confirm('Are you sure you want to delete this photo? This cannot be undone.')) {
                return;
            }

            // Determine which nonce to use
            var isPublicContext = typeof hcrPublic !== 'undefined' && hcrPublic.ajaxUrl;
            var ajaxUrl = isPublicContext ? hcrPublic.ajaxUrl : hcrAdmin.ajaxUrl;
            var nonce = isPublicContext ? hcrPublic.nonce : hcrAdmin.nonce;

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hcr_delete_attachment',
                    nonce: nonce,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        $thumbnail.fadeOut(300, function() {
                            $(this).remove();
                        });
                        showMessage(response.data.message, 'success');
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage('An error occurred deleting the photo.', 'error');
                }
            });
        } else {
            // Remove new photo from accumulated array
            var index = parseInt($(this).data('index'));
            var isPublic = $(this).data('public');
            var targetArray = isPublic ? accumulatedPublicReceiptPhotos : accumulatedReceiptPhotos;

            // Remove from array
            targetArray.splice(index, 1);

            // Remove thumbnail and update remaining indices
            var $container = $(this).closest('.hcr-photos-grid');
            $container.find('.hcr-new-photo').each(function(i) {
                var oldIndex = parseInt($(this).data('index'));
                if (oldIndex === index) {
                    $(this).remove();
                } else if (oldIndex > index) {
                    // Update indices for photos after the removed one
                    $(this).data('index', oldIndex - 1);
                    $(this).attr('data-index', oldIndex - 1);
                    $(this).find('.hcr-photo-remove').data('index', oldIndex - 1);
                    $(this).find('.hcr-photo-remove').attr('data-index', oldIndex - 1);
                }
            });
        }
    });

    // Display existing attachments when loading cash up
    function displayExistingAttachments(attachments) {
        if (!attachments || attachments.length === 0) {
            return;
        }

        var previewContainer = $('#hcr-receipt-photos-preview, #hcr-public-receipt-photos-preview');
        previewContainer.empty();

        attachments.forEach(function(attachment) {
            var removeBtn = '<span class="hcr-photo-remove hcr-remove-existing" data-attachment-id="' + attachment.id + '" title="Remove">&times;</span>';
            var thumbnail = '';
            if (attachment.file_type.indexOf('image/') === 0) {
                // Image file
                thumbnail = '<div class="hcr-photo-thumbnail hcr-existing-photo" data-url="' + attachment.file_path + '" data-attachment-id="' + attachment.id + '">' +
                           removeBtn +
                           '<img src="' + attachment.file_path + '" alt="' + attachment.file_name + '">' +
                           '<div class="hcr-photo-name">' + attachment.file_name + '</div>' +
                           '</div>';
            } else if (attachment.file_type === 'application/pdf') {
                // PDF file
                thumbnail = '<div class="hcr-photo-thumbnail hcr-pdf-thumbnail hcr-existing-photo" data-url="' + attachment.file_path + '" data-attachment-id="' + attachment.id + '">' +
                           removeBtn +
                           '<span class="dashicons dashicons-pdf"></span>' +
                           '<div class="hcr-photo-name">' + attachment.file_name + '</div>' +
                           '</div>';
            }
            previewContainer.append(thumbnail);
        });
    }

    // Click thumbnail to open popup preview
    $(document).on('click', '.hcr-existing-photo', function() {
        var imageUrl = $(this).data('url');
        var fileName = $(this).find('.hcr-photo-name').text();

        // Create popup overlay
        var popup = '<div id="hcr-image-popup-overlay">' +
                   '<div id="hcr-image-popup-content">' +
                   '<span id="hcr-image-popup-close">&times;</span>' +
                   '<h3>' + fileName + '</h3>' +
                   '<img src="' + imageUrl + '" alt="' + fileName + '">' +
                   '</div>' +
                   '</div>';

        $('body').append(popup);
        $('#hcr-image-popup-overlay').fadeIn(200);
    });

    // Close popup
    $(document).on('click', '#hcr-image-popup-close, #hcr-image-popup-overlay', function(e) {
        if (e.target.id === 'hcr-image-popup-overlay' || e.target.id === 'hcr-image-popup-close') {
            $('#hcr-image-popup-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        }
    });

    // Close popup on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#hcr-image-popup-overlay').length) {
            $('#hcr-image-popup-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        }
    });

    window.hcrClearForm = clearForm;
    window.hcrShowForm = showForm;
    window.hcrAutoFetchNewbookData = autoFetchNewbookData;
    window.hcrMarkFormDirty = markFormDirty;
    window.hcrClearFormDirty = clearFormDirty;
    window.hcrDisplayTillPayments = displayTillPayments;
    window.hcrUpdateTotalFloat = updateTotalFloat;
    window.hcrDisplayExistingAttachments = displayExistingAttachments;

    // =======================
    // Section Header Click Scrolling
    // =======================

    // Make section headers clickable to scroll to top
    $(document).on('click', '.hcr-section-header', function() {
        var targetId = $(this).attr('id');
        if (targetId) {
            scrollToSection(targetId);
        }
    });

    // NEXT button click handler
    $(document).on('click', '.hcr-next-section-btn', function() {
        var targetId = $(this).data('target');
        if (targetId) {
            scrollToSection(targetId);
        }
    });

    // Smooth scroll function to align section to top
    function scrollToSection(sectionId) {
        var $target = $('#' + sectionId);
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top
            }, 400);
        }
    }
});
