<?php
/**
 * Referrals system class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Referrals {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('user_register', array($this, 'handle_user_registration'));
        add_action('init', array($this, 'handle_referral_tracking'));
        add_action('wp_ajax_leadpayout_get_referral_stats', array($this, 'get_referral_stats'));
    }
    
    public function handle_user_registration($user_id) {
        // Generate unique referral code for new user
        if (class_exists('LeadPayout_Database')) {
            $referral_code = LeadPayout_Database::generate_referral_code($user_id);
        } else {
            // Fallback method
            $user = get_user_by('ID', $user_id);
            $base = strtoupper(substr($user->user_login, 0, 3));
            $random = strtoupper(wp_generate_password(5, false));
            $referral_code = $base . $random;
        }
        update_user_meta($user_id, 'leadpayout_referral_code', $referral_code);
        
        // Check if user was referred
        $referral_code_used = get_transient('leadpayout_referral_' . session_id());
        if ($referral_code_used) {
            $this->create_referral_relationship($referral_code_used, $user_id);
            delete_transient('leadpayout_referral_' . session_id());
        }
    }
    
    public function handle_referral_tracking() {
        if (isset($_GET['ref']) && !is_user_logged_in()) {
            $referral_code = sanitize_text_field($_GET['ref']);
            
            // Find the referrer
            $referrer = $this->get_user_by_referral_code($referral_code);
            if ($referrer) {
                // Store referral code in session/transient for registration
                if (!session_id()) {
                    session_start();
                }
                set_transient('leadpayout_referral_' . session_id(), $referral_code, HOUR_IN_SECONDS);
                
                // Set cookie for longer tracking
                setcookie('leadpayout_ref', $referral_code, time() + (30 * DAY_IN_SECONDS), '/');
            }
        }
    }
    
    private function get_user_by_referral_code($referral_code) {
        $users = get_users(array(
            'meta_key' => 'leadpayout_referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ));
        
        return !empty($users) ? $users[0] : false;
    }
    
    private function create_referral_relationship($referral_code, $referred_user_id) {
        $referrer = $this->get_user_by_referral_code($referral_code);
        if (!$referrer) {
            return false;
        }
        
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        
        // Check if relationship already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $referrals_table WHERE referrer_id = %d AND referred_id = %d",
            $referrer->ID, $referred_user_id
        ));
        
        if (!$existing) {
            $commission_rate = get_option('leadpayout_referral_rate', 10);
            
            $result = $wpdb->insert($referrals_table, array(
                'referrer_id' => $referrer->ID,
                'referred_id' => $referred_user_id,
                'referral_code' => $referral_code,
                'commission_rate' => $commission_rate,
                'status' => 'active'
            ));
            
            if ($result) {
                // Send notification to referrer
                if (class_exists('LeadPayout_Emails')) {
                    LeadPayout_Emails::send_referral_notification($referrer->ID, $referred_user_id);
                }
                return true;
            }
        }
        
        return false;
    }
    
    public static function process_referral_commission($user_id, $amount) {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        
        // Find if this user was referred
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $referrals_table WHERE referred_id = %d AND status = 'active'",
            $user_id
        ));
        
        if ($referral) {
            $commission_amount = ($amount * $referral->commission_rate) / 100;
            
            // Add commission to referrer's earnings
            $wpdb->insert($earnings_table, array(
                'user_id' => $referral->referrer_id,
                'amount' => $commission_amount,
                'type' => 'referral_commission',
                'source_id' => $referral->id,
                'source_type' => 'referral',
                'status' => 'approved'
            ));
            
            // Update referrer's balance
            if (class_exists('LeadPayout_Database')) {
                LeadPayout_Database::update_user_balance($referral->referrer_id, $commission_amount, 'add');
            }
            
            // Update total earned in referrals table
            $wpdb->update($referrals_table, array(
                'total_earned' => $referral->total_earned + $commission_amount
            ), array('id' => $referral->id));
            
            // Send commission notification
            if (class_exists('LeadPayout_Emails')) {
                LeadPayout_Emails::send_commission_notification($referral->referrer_id, $commission_amount, $user_id);
            }
        }
    }
    
    public static function get_user_referrals($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        
        $referrals = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, u.display_name as referred_name, u.user_registered
            FROM $referrals_table r
            JOIN {$wpdb->users} u ON r.referred_id = u.ID
            WHERE r.referrer_id = %d
            ORDER BY r.created_at DESC
        ", $user_id));
        
        return $referrals;
    }
    
    public static function get_referral_stats($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(r.id) as total_referrals,
                COALESCE(SUM(r.total_earned), 0) as total_commission_earned,
                COUNT(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as referrals_this_month
            FROM $referrals_table r
            WHERE r.referrer_id = %d AND r.status = 'active'
        ", $user_id));
        
        // Get commission earnings this month
        $monthly_commission = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM $earnings_table
            WHERE user_id = %d 
            AND type = 'referral_commission'
            AND status = 'approved'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $user_id));
        
        $stats->commission_this_month = $monthly_commission;
        
        return $stats;
    }
    
    public static function get_user_referral_code($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $referral_code = get_user_meta($user_id, 'leadpayout_referral_code', true);
        
        if (empty($referral_code)) {
            if (class_exists('LeadPayout_Database')) {
                $referral_code = LeadPayout_Database::generate_referral_code($user_id);
            } else {
                // Fallback method
                $user = get_user_by('ID', $user_id);
                $base = strtoupper(substr($user->user_login, 0, 3));
                $random = strtoupper(wp_generate_password(5, false));
                $referral_code = $base . $random;
            }
            update_user_meta($user_id, 'leadpayout_referral_code', $referral_code);
        }
        
        return $referral_code;
    }
    
    public static function get_referral_link($user_id = null) {
        $referral_code = self::get_user_referral_code($user_id);
        return home_url('/?ref=' . $referral_code);
    }
    
    public function get_referral_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'leadpayout-pro'));
        }
        
        $stats = self::get_referral_stats();
        $referrals = self::get_user_referrals();
        $referral_code = self::get_user_referral_code();
        $referral_link = self::get_referral_link();
        
        wp_send_json_success(array(
            'stats' => $stats,
            'referrals' => $referrals,
            'referral_code' => $referral_code,
            'referral_link' => $referral_link
        ));
    }
    
    public static function get_top_referrers($limit = 10, $period = 'all') {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        
        $date_condition = '';
        if ($period === 'month') {
            $date_condition = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        } elseif ($period === 'week') {
            $date_condition = "AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        }
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID as user_id,
                u.display_name,
                COUNT(r.id) as total_referrals,
                SUM(r.total_earned) as total_commission
            FROM {$wpdb->users} u
            INNER JOIN $referrals_table r ON u.ID = r.referrer_id
            WHERE r.status = 'active' $date_condition
            GROUP BY u.ID
            ORDER BY total_referrals DESC, total_commission DESC
            LIMIT %d
        ", $limit));
        
        return $results;
    }
    
    public static function calculate_referral_tier_bonus($referrer_id, $level = 1) {
        // Multi-level referral system (optional enhancement)
        // This can be used for implementing tiered referral bonuses
        
        if ($level > 3) { // Limit to 3 levels
            return 0;
        }
        
        $tier_rates = array(
            1 => get_option('leadpayout_referral_rate', 10), // Direct referral
            2 => 5, // Second level
            3 => 2  // Third level
        );
        
        return isset($tier_rates[$level]) ? $tier_rates[$level] : 0;
    }
    
    public static function get_referral_conversion_rate($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        
        // Get total referrals
        $total_referrals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE referrer_id = %d",
            $user_id
        ));
        
        // Get active referrals (those who have earned something)
        $active_referrals = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT r.referred_id)
            FROM $referrals_table r
            JOIN $earnings_table e ON r.referred_id = e.user_id
            WHERE r.referrer_id = %d AND e.status = 'approved'
        ", $user_id));
        
        if ($total_referrals > 0) {
            return ($active_referrals / $total_referrals) * 100;
        }
        
        return 0;
    }
}