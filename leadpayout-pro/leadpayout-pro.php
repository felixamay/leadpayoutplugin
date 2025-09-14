<?php
/**
 * Plugin Name: LeadPayout Pro
 * Plugin URI: https://example.com/leadpayout-pro
 * Description: A comprehensive microtask and referral system with Stripe integration for WordPress
 * Version: 1.0.0
 * Author: LeadPayout Team
 * Author URI: https://example.com
 * Text Domain: leadpayout-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LEADPAYOUT_PRO_VERSION', '1.0.0');
define('LEADPAYOUT_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LEADPAYOUT_PRO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LEADPAYOUT_PRO_PLUGIN_FILE', __FILE__);
define('LEADPAYOUT_PRO_ADMIN_EMAIL', 'felixames0808@gmail.com');

/**
 * Main plugin class
 */
class LeadPayoutPro {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    private function load_dependencies() {
        $includes_path = LEADPAYOUT_PRO_PLUGIN_PATH . 'includes/';
        
        $files = array(
            'class-database.php',
            'class-admin.php',
            'class-frontend.php',
            'class-tasks.php',
            'class-referrals.php',
            'class-stripe.php',
            'class-shortcodes.php',
            'class-emails.php'
        );
        
        foreach ($files as $file) {
            $file_path = $includes_path . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    public function init() {
        // Load dependencies first
        $this->load_dependencies();
        
        // Load text domain
        load_plugin_textdomain('leadpayout-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components only if classes exist
        if (class_exists('LeadPayout_Database')) {
            LeadPayout_Database::get_instance();
        }
        if (class_exists('LeadPayout_Admin')) {
            LeadPayout_Admin::get_instance();
        }
        if (class_exists('LeadPayout_Frontend')) {
            LeadPayout_Frontend::get_instance();
        }
        if (class_exists('LeadPayout_Tasks')) {
            LeadPayout_Tasks::get_instance();
        }
        if (class_exists('LeadPayout_Referrals')) {
            LeadPayout_Referrals::get_instance();
        }
        if (class_exists('LeadPayout_Stripe')) {
            LeadPayout_Stripe::get_instance();
        }
        if (class_exists('LeadPayout_Shortcodes')) {
            LeadPayout_Shortcodes::get_instance();
        }
        if (class_exists('LeadPayout_Emails')) {
            LeadPayout_Emails::get_instance();
        }
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function activate() {
        // Load dependencies first
        $this->load_dependencies();
        
        // Create database tables
        if (class_exists('LeadPayout_Database')) {
            LeadPayout_Database::create_tables();
        }
        
        // Create frontend pages
        $this->create_frontend_pages();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_frontend_pages() {
        $pages = array(
            'my-tasks' => array(
                'title' => __('My Tasks', 'leadpayout-pro'),
                'content' => '[leadpayout_tasks]'
            ),
            'my-earnings' => array(
                'title' => __('My Earnings', 'leadpayout-pro'),
                'content' => '[leadpayout_earnings]'
            ),
            'refer-earn' => array(
                'title' => __('Refer & Earn', 'leadpayout-pro'),
                'content' => '[leadpayout_referrals]'
            )
        );
        
        foreach ($pages as $slug => $page_data) {
            $existing_page = get_page_by_path($slug);
            if (!$existing_page) {
                wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ));
            }
        }
    }
    
    private function set_default_options() {
        $defaults = array(
            'leadpayout_min_payout' => '0.10',
            'leadpayout_max_payout' => '2.00',
            'leadpayout_referral_rate' => '10',
            'leadpayout_auto_approve' => 'no',
            'leadpayout_stripe_mode' => 'test'
        );
        
        foreach ($defaults as $option => $value) {
            if (!get_option($option)) {
                update_option($option, $value);
            }
        }
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'leadpayout-frontend',
            LEADPAYOUT_PRO_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            LEADPAYOUT_PRO_VERSION
        );
        
        wp_enqueue_script(
            'leadpayout-frontend',
            LEADPAYOUT_PRO_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            LEADPAYOUT_PRO_VERSION,
            true
        );
        
        wp_localize_script('leadpayout-frontend', 'leadpayout_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leadpayout_nonce')
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'leadpayout') === false) {
            return;
        }
        
        wp_enqueue_style(
            'leadpayout-admin',
            LEADPAYOUT_PRO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LEADPAYOUT_PRO_VERSION
        );
        
        wp_enqueue_script(
            'leadpayout-admin',
            LEADPAYOUT_PRO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            LEADPAYOUT_PRO_VERSION,
            true
        );
        
        wp_localize_script('leadpayout-admin', 'leadpayout_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leadpayout_admin_nonce')
        ));
    }
}

// Initialize the plugin
LeadPayoutPro::get_instance();