<?php
/**
 * Frontend functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_footer', array($this, 'add_frontend_modals'));
        add_filter('body_class', array($this, 'add_body_classes'));
    }
    
    public function add_body_classes($classes) {
        if (is_user_logged_in()) {
            $classes[] = 'leadpayout-user-logged-in';
        } else {
            $classes[] = 'leadpayout-user-logged-out';
        }
        return $classes;
    }
    
    public function add_frontend_modals() {
        // Only add modals on LeadPayout pages
        if (!$this->is_leadpayout_page()) {
            return;
        }
        ?>
        <!-- Global Loading Overlay -->
        <div id="leadpayout-loading" class="leadpayout-loading-overlay" style="display: none;">
            <div class="leadpayout-spinner"></div>
            <p><?php _e('Processing...', 'leadpayout-pro'); ?></p>
        </div>
        
        <!-- Notification Container -->
        <div id="leadpayout-notifications" class="leadpayout-notifications-container"></div>
        
        <script>
        // Global LeadPayout JavaScript functions
        window.LeadPayout = {
            showLoading: function() {
                document.getElementById('leadpayout-loading').style.display = 'flex';
            },
            
            hideLoading: function() {
                document.getElementById('leadpayout-loading').style.display = 'none';
            },
            
            showNotification: function(message, type = 'info') {
                var container = document.getElementById('leadpayout-notifications');
                var notification = document.createElement('div');
                notification.className = 'leadpayout-notification leadpayout-notification-' + type;
                notification.innerHTML = message + '<button onclick="this.parentElement.remove()">&times;</button>';
                container.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(function() {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            },
            
            formatCurrency: function(amount) {
                return '$' + parseFloat(amount).toFixed(2);
            },
            
            validateEmail: function(email) {
                var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
        };
        
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('leadpayout-modal')) {
                event.target.style.display = 'none';
            }
        });
        
        // Handle escape key for modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                var modals = document.querySelectorAll('.leadpayout-modal');
                modals.forEach(function(modal) {
                    modal.style.display = 'none';
                });
            }
        });
        </script>
        <?php
    }
    
    private function is_leadpayout_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check if current page contains LeadPayout shortcodes
        $leadpayout_shortcodes = array(
            'leadpayout_tasks',
            'leadpayout_earnings',
            'leadpayout_referrals',
            'leadpayout_leaderboard',
            'leadpayout_dashboard'
        );
        
        foreach ($leadpayout_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function get_user_dashboard_url() {
        $dashboard_page = get_page_by_path('leadpayout-dashboard');
        if ($dashboard_page) {
            return get_permalink($dashboard_page->ID);
        }
        return home_url();
    }
    
    public static function get_tasks_url() {
        $tasks_page = get_page_by_path('my-tasks');
        if ($tasks_page) {
            return get_permalink($tasks_page->ID);
        }
        return home_url();
    }
    
    public static function get_earnings_url() {
        $earnings_page = get_page_by_path('my-earnings');
        if ($earnings_page) {
            return get_permalink($earnings_page->ID);
        }
        return home_url();
    }
    
    public static function get_referrals_url() {
        $referrals_page = get_page_by_path('refer-earn');
        if ($referrals_page) {
            return get_permalink($referrals_page->ID);
        }
        return home_url();
    }
}