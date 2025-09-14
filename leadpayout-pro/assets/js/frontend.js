/**
 * LeadPayout Pro Frontend JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        LeadPayoutFrontend.init();
    });
    
    window.LeadPayoutFrontend = {
        
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.leadpayout-tab-button', this.handleTabSwitch);
            
            // Modal controls
            $(document).on('click', '.leadpayout-close', this.closeModal);
            $(document).on('click', '.leadpayout-modal', this.closeModalOnOverlay);
            
            // Form submissions
            $(document).on('submit', '#task-submission-form', this.handleTaskSubmission);
            $(document).on('submit', '#withdrawal-form', this.handleWithdrawalRequest);
            
            // Copy to clipboard
            $(document).on('click', '[data-copy]', this.copyToClipboard);
            
            // Task actions
            $(document).on('click', '[data-task-id]', this.handleTaskAction);
            
            // Escape key for modals
            $(document).on('keydown', this.handleEscapeKey);
        },
        
        initializeComponents: function() {
            // Initialize tooltips if available
            if ($.fn.tooltip) {
                $('[data-toggle="tooltip"]').tooltip();
            }
            
            // Initialize any existing modals
            this.setupModals();
            
            // Auto-refresh data periodically
            this.setupAutoRefresh();
        },
        
        handleTabSwitch: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var targetTab = $button.data('tab');
            
            // Remove active class from all tabs and buttons
            $('.leadpayout-tab-content').removeClass('active');
            $('.leadpayout-tab-button').removeClass('active');
            
            // Add active class to current button and target tab
            $button.addClass('active');
            $('#' + targetTab + '-tab, #' + targetTab + '-tasks').addClass('active');
        },
        
        closeModal: function(e) {
            e.preventDefault();
            $(this).closest('.leadpayout-modal').hide();
        },
        
        closeModalOnOverlay: function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        },
        
        handleEscapeKey: function(e) {
            if (e.keyCode === 27) { // Escape key
                $('.leadpayout-modal').hide();
            }
        },
        
        handleTaskSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            formData.append('action', 'leadpayout_submit_task');
            formData.append('nonce', leadpayout_ajax.nonce);
            
            LeadPayoutFrontend.showLoading();
            
            $.ajax({
                url: leadpayout_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    LeadPayoutFrontend.hideLoading();
                    
                    if (response.success) {
                        LeadPayoutFrontend.showNotification(response.data, 'success');
                        $form.closest('.leadpayout-modal').hide();
                        $form[0].reset();
                        
                        // Refresh the page to show updated data
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        LeadPayoutFrontend.showNotification(response.data || 'Error submitting task.', 'error');
                    }
                },
                error: function() {
                    LeadPayoutFrontend.hideLoading();
                    LeadPayoutFrontend.showNotification('Network error. Please try again.', 'error');
                }
            });
        },
        
        handleWithdrawalRequest: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            formData.append('action', 'leadpayout_request_withdrawal');
            formData.append('nonce', leadpayout_ajax.nonce);
            
            LeadPayoutFrontend.showLoading();
            
            $.ajax({
                url: leadpayout_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    LeadPayoutFrontend.hideLoading();
                    
                    if (response.success) {
                        LeadPayoutFrontend.showNotification('Withdrawal request submitted successfully!', 'success');
                        $form.closest('.leadpayout-modal').hide();
                        $form[0].reset();
                        
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        LeadPayoutFrontend.showNotification(response.data || 'Error processing withdrawal.', 'error');
                    }
                },
                error: function() {
                    LeadPayoutFrontend.hideLoading();
                    LeadPayoutFrontend.showNotification('Network error. Please try again.', 'error');
                }
            });
        },
        
        copyToClipboard: function(e) {
            e.preventDefault();
            
            var textToCopy = $(this).data('copy') || $(this).prev('input').val();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    LeadPayoutFrontend.showNotification('Copied to clipboard!', 'success');
                }, function() {
                    LeadPayoutFrontend.fallbackCopy(textToCopy);
                });
            } else {
                LeadPayoutFrontend.fallbackCopy(textToCopy);
            }
        },
        
        fallbackCopy: function(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                LeadPayoutFrontend.showNotification('Copied to clipboard!', 'success');
            } catch (err) {
                LeadPayoutFrontend.showNotification('Copy failed. Please copy manually.', 'error');
            }
            
            document.body.removeChild(textArea);
        },
        
        handleTaskAction: function(e) {
            var $button = $(this);
            var taskId = $button.data('task-id');
            var action = $button.data('action');
            
            if (action === 'start') {
                LeadPayoutFrontend.showTaskModal(taskId, $button);
            }
        },
        
        showTaskModal: function(taskId, $button) {
            var $taskCard = $button.closest('.leadpayout-task-card');
            var title = $taskCard.find('h4').text();
            var description = $taskCard.find('.task-description').text();
            var requirements = $taskCard.find('.task-requirements span').text();
            
            var modalContent = `
                <h3>${title}</h3>
                <div class="task-modal-content">
                    <p><strong>Description:</strong> ${description}</p>
                    <p><strong>Requirements:</strong> ${requirements}</p>
                </div>
            `;
            
            $('#modal-task-title').text(title);
            $('#modal-task-content').html(modalContent);
            $('#task-id').val(taskId);
            $('#task-modal').show();
        },
        
        setupModals: function() {
            // Ensure modals are properly initialized
            $('.leadpayout-modal').each(function() {
                var $modal = $(this);
                if (!$modal.data('initialized')) {
                    $modal.data('initialized', true);
                }
            });
        },
        
        setupAutoRefresh: function() {
            // Auto-refresh certain data every 5 minutes
            if ($('.leadpayout-balance-summary, .leadpayout-dashboard-stats').length > 0) {
                setInterval(function() {
                    LeadPayoutFrontend.refreshUserData();
                }, 300000); // 5 minutes
            }
        },
        
        refreshUserData: function() {
            $.ajax({
                url: leadpayout_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'leadpayout_get_user_data',
                    nonce: leadpayout_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        LeadPayoutFrontend.updateBalanceDisplay(response.data.balance);
                        LeadPayoutFrontend.updateStatsDisplay(response.data.stats);
                    }
                }
            });
        },
        
        updateBalanceDisplay: function(balance) {
            $('.balance-amount').each(function() {
                var $element = $(this);
                var balanceType = $element.data('balance-type');
                if (balanceType && balance[balanceType] !== undefined) {
                    $element.text('$' + parseFloat(balance[balanceType]).toFixed(2));
                }
            });
        },
        
        updateStatsDisplay: function(stats) {
            $('.stat-card h3, .leadpayout-stat-box h3').each(function() {
                var $element = $(this);
                var statType = $element.data('stat-type');
                if (statType && stats[statType] !== undefined) {
                    if (statType.includes('amount') || statType.includes('earned')) {
                        $element.text('$' + parseFloat(stats[statType]).toFixed(2));
                    } else {
                        $element.text(stats[statType]);
                    }
                }
            });
        },
        
        showLoading: function() {
            if ($('#leadpayout-loading').length === 0) {
                $('body').append(`
                    <div id="leadpayout-loading" class="leadpayout-loading-overlay">
                        <div class="leadpayout-spinner"></div>
                        <p>Processing...</p>
                    </div>
                `);
            }
            $('#leadpayout-loading').show();
        },
        
        hideLoading: function() {
            $('#leadpayout-loading').hide();
        },
        
        showNotification: function(message, type) {
            type = type || 'info';
            
            if ($('#leadpayout-notifications').length === 0) {
                $('body').append('<div id="leadpayout-notifications" class="leadpayout-notifications-container"></div>');
            }
            
            var $notification = $(`
                <div class="leadpayout-notification leadpayout-notification-${type}">
                    ${message}
                    <button type="button">&times;</button>
                </div>
            `);
            
            $('#leadpayout-notifications').append($notification);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $notification.remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('button').on('click', function() {
                $notification.fadeOut(function() {
                    $notification.remove();
                });
            });
        },
        
        formatCurrency: function(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        },
        
        validateEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };
    
    // Global functions for inline usage
    window.showTab = function(tabName) {
        $('.leadpayout-tab-content').removeClass('active');
        $('.leadpayout-tab-button').removeClass('active');
        
        $('#' + tabName + '-tasks, #' + tabName + '-tab').addClass('active');
        $('[data-tab="' + tabName + '"]').addClass('active');
    };
    
    window.startTask = function(taskId) {
        var $button = $('[data-task-id="' + taskId + '"]');
        LeadPayoutFrontend.showTaskModal(taskId, $button);
    };
    
    window.closeTaskModal = function() {
        $('#task-modal').hide();
        $('#task-submission-form')[0].reset();
    };
    
    window.requestWithdrawal = function() {
        $('#withdrawal-modal').show();
    };
    
    window.closeWithdrawalModal = function() {
        $('#withdrawal-modal').hide();
        $('#withdrawal-form')[0].reset();
    };
    
    window.copyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                LeadPayoutFrontend.showNotification('Copied to clipboard!', 'success');
            });
        } else {
            LeadPayoutFrontend.fallbackCopy(text);
        }
    };
    
    // Expose LeadPayoutFrontend to global scope
    window.LeadPayout = window.LeadPayout || {};
    window.LeadPayout.Frontend = LeadPayoutFrontend;
    
})(jQuery);