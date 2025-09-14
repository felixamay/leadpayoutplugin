<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tasks table
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        $tasks_sql = "CREATE TABLE $tasks_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            task_type varchar(50) NOT NULL,
            payout_amount decimal(10,2) NOT NULL,
            total_budget decimal(10,2) NOT NULL,
            remaining_budget decimal(10,2) NOT NULL,
            proof_requirements text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Task submissions table
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $submissions_sql = "CREATE TABLE $submissions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            task_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            proof_data text,
            proof_files text,
            status varchar(20) DEFAULT 'pending',
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime NULL,
            reviewed_by bigint(20) NULL,
            review_notes text,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status),
            UNIQUE KEY unique_user_task (user_id, task_id)
        ) $charset_collate;";
        
        // User earnings table
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        $earnings_sql = "CREATE TABLE $earnings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            type varchar(20) NOT NULL,
            source_id bigint(20) NULL,
            source_type varchar(50) NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Referrals table
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        $referrals_sql = "CREATE TABLE $referrals_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            referrer_id bigint(20) NOT NULL,
            referred_id bigint(20) NOT NULL,
            referral_code varchar(50) NOT NULL,
            commission_rate decimal(5,2) DEFAULT 10.00,
            total_earned decimal(10,2) DEFAULT 0.00,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY referrer_id (referrer_id),
            KEY referred_id (referred_id),
            KEY referral_code (referral_code),
            UNIQUE KEY unique_referral (referrer_id, referred_id)
        ) $charset_collate;";
        
        // Transactions table
        $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
        $transactions_sql = "CREATE TABLE $transactions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            type varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            stripe_payment_id varchar(255) NULL,
            stripe_transfer_id varchar(255) NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // User balances table
        $balances_table = $wpdb->prefix . 'leadpayout_balances';
        $balances_sql = "CREATE TABLE $balances_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            available_balance decimal(10,2) DEFAULT 0.00,
            pending_balance decimal(10,2) DEFAULT 0.00,
            total_earned decimal(10,2) DEFAULT 0.00,
            total_withdrawn decimal(10,2) DEFAULT 0.00,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($tasks_sql);
        dbDelta($submissions_sql);
        dbDelta($earnings_sql);
        dbDelta($referrals_sql);
        dbDelta($transactions_sql);
        dbDelta($balances_sql);
        
        // Create user meta for referral codes
        self::ensure_user_referral_codes();
    }
    
    private static function ensure_user_referral_codes() {
        $users = get_users(array('fields' => 'ID'));
        foreach ($users as $user_id) {
            $referral_code = get_user_meta($user_id, 'leadpayout_referral_code', true);
            if (empty($referral_code)) {
                $referral_code = self::generate_referral_code($user_id);
                update_user_meta($user_id, 'leadpayout_referral_code', $referral_code);
            }
        }
    }
    
    public static function generate_referral_code($user_id) {
        $user = get_user_by('ID', $user_id);
        $base = strtoupper(substr($user->user_login, 0, 3));
        $random = strtoupper(wp_generate_password(5, false));
        return $base . $random;
    }
    
    public static function get_user_balance($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'leadpayout_balances';
        
        $balance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$balance) {
            // Create initial balance record
            $wpdb->insert($table, array(
                'user_id' => $user_id,
                'available_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00
            ));
            
            return (object) array(
                'available_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00
            );
        }
        
        return $balance;
    }
    
    public static function update_user_balance($user_id, $amount, $type = 'add') {
        global $wpdb;
        $table = $wpdb->prefix . 'leadpayout_balances';
        
        $balance = self::get_user_balance($user_id);
        
        if ($type === 'add') {
            $new_available = $balance->available_balance + $amount;
            $new_total_earned = $balance->total_earned + $amount;
            
            $wpdb->update($table, array(
                'available_balance' => $new_available,
                'total_earned' => $new_total_earned
            ), array('user_id' => $user_id));
        } elseif ($type === 'subtract') {
            $new_available = max(0, $balance->available_balance - $amount);
            $new_withdrawn = $balance->total_withdrawn + $amount;
            
            $wpdb->update($table, array(
                'available_balance' => $new_available,
                'total_withdrawn' => $new_withdrawn
            ), array('user_id' => $user_id));
        }
    }
    
    public static function get_leaderboard($limit = 10, $period = 'week') {
        global $wpdb;
        
        $date_condition = '';
        if ($period === 'week') {
            $date_condition = "AND e.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        } elseif ($period === 'month') {
            $date_condition = "AND e.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
        
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_login,
                SUM(e.amount) as total_earned,
                COUNT(e.id) as tasks_completed
            FROM {$wpdb->users} u
            INNER JOIN $earnings_table e ON u.ID = e.user_id
            WHERE e.status = 'approved' $date_condition
            GROUP BY u.ID
            ORDER BY total_earned DESC
            LIMIT %d
        ", $limit));
        
        return $results;
    }
}