/**
 * LeadPayout Pro Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        LeadPayoutAdmin.init();
    });
    
    window.LeadPayoutAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        bindEvents: function() {
            // Task approval/rejection
            $(document).on('click', '.approve-task-btn', this.handleTaskApproval);
            $(document).on('click', '.reject-task-btn', this.handleTaskRejection);
            
            // Withdrawal processing
            $(document).on('click', '.process-withdrawal-btn', this.handleWithdrawalProcessing);
            
            // Bulk actions
            $(document).on('change', '#bulk-action-selector-top', this.handleBulkActionChange);
            $(document).on('click', '#doaction', this.handleBulkAction);
            
            // Settings form
            $(document).on('submit', '#leadpayout-settings-form', this.handleSettingsSave);
            
            // Data refresh
            $(document).on('click', '.refresh-data-btn', this.refreshDashboardData);
            
            // Task filtering
            $(document).on('change', '.task-filter', this.handleTaskFiltering);
            
            // Export functionality
            $(document).on('click', '.export-data-btn', this.handleDataExport);
        },
        
        initializeComponents: function() {
            // Initialize date pickers if available
            if ($.fn.datepicker) {
                $('.datepicker').datepicker({
                    dateFormat: 'yy-mm-dd'
                });
            }
            
            // Initialize charts if data is available
            this.initializeCharts();
            
            // Setup real-time updates
            this.setupRealTimeUpdates();
            
            // Initialize tooltips
            this.initializeTooltips();
        },
        
        handleTaskApproval: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var submissionId = $button.data('submission-id');
            
            if (!confirm('Are you sure you want to approve this task submission?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_approve_task',
                    submission_id: submissionId,
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LeadPayoutAdmin.showNotice('Task approved successfully!', 'success');
                        $button.closest('tr, .approval-item').fadeOut();
                    } else {
                        LeadPayoutAdmin.showNotice(response.data || 'Error approving task.', 'error');
                        $button.prop('disabled', false).text('Approve');
                    }
                },
                error: function() {
                    LeadPayoutAdmin.showNotice('Network error. Please try again.', 'error');
                    $button.prop('disabled', false).text('Approve');
                }
            });
        },
        
        handleTaskRejection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var submissionId = $button.data('submission-id');
            var reason = prompt('Please provide a reason for rejection (optional):');
            
            if (reason === null) {
                return; // User cancelled
            }
            
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_reject_task',
                    submission_id: submissionId,
                    reason: reason,
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LeadPayoutAdmin.showNotice('Task rejected.', 'success');
                        $button.closest('tr, .approval-item').fadeOut();
                    } else {
                        LeadPayoutAdmin.showNotice(response.data || 'Error rejecting task.', 'error');
                        $button.prop('disabled', false).text('Reject');
                    }
                },
                error: function() {
                    LeadPayoutAdmin.showNotice('Network error. Please try again.', 'error');
                    $button.prop('disabled', false).text('Reject');
                }
            });
        },
        
        handleWithdrawalProcessing: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var withdrawalId = $button.data('withdrawal-id');
            
            if (!confirm('Are you sure you want to process this withdrawal?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_process_withdrawal',
                    withdrawal_id: withdrawalId,
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LeadPayoutAdmin.showNotice('Withdrawal processed successfully!', 'success');
                        $button.closest('tr').fadeOut();
                    } else {
                        LeadPayoutAdmin.showNotice(response.data || 'Error processing withdrawal.', 'error');
                        $button.prop('disabled', false).text('Process');
                    }
                },
                error: function() {
                    LeadPayoutAdmin.showNotice('Network error. Please try again.', 'error');
                    $button.prop('disabled', false).text('Process');
                }
            });
        },
        
        handleBulkActionChange: function() {
            var action = $(this).val();
            var $applyButton = $('#doaction');
            
            if (action === '-1') {
                $applyButton.prop('disabled', true);
            } else {
                $applyButton.prop('disabled', false);
            }
        },
        
        handleBulkAction: function(e) {
            var action = $('#bulk-action-selector-top').val();
            var selectedItems = [];
            
            $('input[name="bulk-select[]"]:checked').each(function() {
                selectedItems.push($(this).val());
            });
            
            if (selectedItems.length === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }
            
            if (!confirm('Are you sure you want to perform this action on ' + selectedItems.length + ' items?')) {
                e.preventDefault();
                return;
            }
            
            // Process bulk action via AJAX
            e.preventDefault();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_bulk_action',
                    bulk_action: action,
                    items: selectedItems,
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LeadPayoutAdmin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        LeadPayoutAdmin.showNotice(response.data || 'Error performing bulk action.', 'error');
                    }
                },
                error: function() {
                    LeadPayoutAdmin.showNotice('Network error. Please try again.', 'error');
                }
            });
        },
        
        handleSettingsSave: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            $submitButton.prop('disabled', true).val('Saving...');
            
            // Form will submit normally, but we can add validation here
            var stripeMode = $form.find('input[name="stripe_mode"]:checked').val();
            var stripeSecretKey = $form.find('input[name="stripe_secret_key"]').val();
            var stripePublicKey = $form.find('input[name="stripe_public_key"]').val();
            
            if (stripeSecretKey && !LeadPayoutAdmin.validateStripeKey(stripeSecretKey, stripeMode, 'secret')) {
                e.preventDefault();
                LeadPayoutAdmin.showNotice('Invalid Stripe Secret Key format for ' + stripeMode + ' mode.', 'error');
                $submitButton.prop('disabled', false).val('Save Settings');
                return;
            }
            
            if (stripePublicKey && !LeadPayoutAdmin.validateStripeKey(stripePublicKey, stripeMode, 'public')) {
                e.preventDefault();
                LeadPayoutAdmin.showNotice('Invalid Stripe Public Key format for ' + stripeMode + ' mode.', 'error');
                $submitButton.prop('disabled', false).val('Save Settings');
                return;
            }
        },
        
        validateStripeKey: function(key, mode, type) {
            var prefix = (type === 'secret' ? 'sk_' : 'pk_') + mode + '_';
            return key.startsWith(prefix);
        },
        
        refreshDashboardData: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).text('Refreshing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_refresh_dashboard',
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LeadPayoutAdmin.updateDashboardStats(response.data);
                        LeadPayoutAdmin.showNotice('Dashboard data refreshed!', 'success');
                    } else {
                        LeadPayoutAdmin.showNotice('Error refreshing data.', 'error');
                    }
                    $button.prop('disabled', false).text('Refresh');
                },
                error: function() {
                    LeadPayoutAdmin.showNotice('Network error.', 'error');
                    $button.prop('disabled', false).text('Refresh');
                }
            });
        },
        
        updateDashboardStats: function(data) {
            if (data.stats) {
                $('.leadpayout-stat-box').each(function() {
                    var $box = $(this);
                    var statType = $box.data('stat-type');
                    if (statType && data.stats[statType] !== undefined) {
                        $box.find('h3').text(data.stats[statType]);
                    }
                });
            }
        },
        
        handleTaskFiltering: function() {
            var filters = {};
            
            $('.task-filter').each(function() {
                var $filter = $(this);
                var filterName = $filter.attr('name');
                var filterValue = $filter.val();
                
                if (filterValue && filterValue !== '') {
                    filters[filterName] = filterValue;
                }
            });
            
            // Apply filters via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_filter_tasks',
                    filters: filters,
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#tasks-table-body').html(response.data.html);
                    }
                }
            });
        },
        
        handleDataExport: function(e) {
            e.preventDefault();
            
            var exportType = $(this).data('export-type');
            var dateRange = $('#export-date-range').val();
            
            var params = new URLSearchParams({
                action: 'leadpayout_export_data',
                type: exportType,
                date_range: dateRange,
                nonce: leadpayout_admin_ajax.nonce
            });
            
            window.open(ajaxurl + '?' + params.toString());
        },
        
        initializeCharts: function() {
            if (typeof Chart !== 'undefined' && $('#earnings-chart').length) {
                var ctx = document.getElementById('earnings-chart').getContext('2d');
                
                // Sample chart - replace with actual data
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Earnings',
                            data: [12, 19, 3, 5, 2, 3],
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        },
        
        setupRealTimeUpdates: function() {
            // Check for new submissions every 30 seconds
            setInterval(function() {
                LeadPayoutAdmin.checkForUpdates();
            }, 30000);
        },
        
        checkForUpdates: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'leadpayout_check_updates',
                    nonce: leadpayout_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_updates) {
                        LeadPayoutAdmin.showUpdateNotification(response.data);
                    }
                }
            });
        },
        
        showUpdateNotification: function(data) {
            var message = '';
            if (data.new_submissions > 0) {
                message += data.new_submissions + ' new task submissions. ';
            }
            if (data.new_withdrawals > 0) {
                message += data.new_withdrawals + ' new withdrawal requests. ';
            }
            
            if (message) {
                LeadPayoutAdmin.showNotice(message + '<a href="#" onclick="location.reload()">Refresh page</a>', 'info');
            }
        },
        
        initializeTooltips: function() {
            // Initialize WordPress-style tooltips
            $('.help-tip').each(function() {
                var $tip = $(this);
                var content = $tip.attr('title') || $tip.data('tip');
                
                if (content) {
                    $tip.attr('title', '').tooltip({
                        content: content,
                        position: { my: 'left top+15', at: 'left bottom' }
                    });
                }
            });
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var noticeClass = 'notice notice-' + type;
            if (type === 'error') {
                noticeClass = 'notice notice-error';
            }
            
            var $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.leadpayout-admin-notice').remove();
            
            // Add new notice
            $notice.addClass('leadpayout-admin-notice');
            $('.wrap h1').after($notice);
            
            // Make it dismissible
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
        },
        
        formatCurrency: function(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        },
        
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    };
    
    // Global functions for inline usage
    window.approveSubmission = function(submissionId) {
        var $button = $('<button>').data('submission-id', submissionId);
        LeadPayoutAdmin.handleTaskApproval.call($button[0], { preventDefault: function() {} });
    };
    
    window.rejectSubmission = function(submissionId) {
        var $button = $('<button>').data('submission-id', submissionId);
        LeadPayoutAdmin.handleTaskRejection.call($button[0], { preventDefault: function() {} });
    };
    
    window.processWithdrawal = function(withdrawalId) {
        var $button = $('<button>').data('withdrawal-id', withdrawalId);
        LeadPayoutAdmin.handleWithdrawalProcessing.call($button[0], { preventDefault: function() {} });
    };
    
})(jQuery);