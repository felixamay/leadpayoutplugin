<?php
/**
 * Tasks management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Tasks {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_leadpayout_submit_task', array($this, 'submit_task'));
        add_action('wp_ajax_leadpayout_get_tasks', array($this, 'get_available_tasks'));
        add_action('wp_ajax_leadpayout_get_user_tasks', array($this, 'get_user_tasks'));
        add_action('init', array($this, 'handle_file_uploads'));
    }
    
    public function handle_file_uploads() {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
    }
    
    public static function get_available_tasks($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        
        // Get tasks that user hasn't completed yet and have remaining budget
        $tasks = $wpdb->get_results($wpdb->prepare("
            SELECT t.* 
            FROM $tasks_table t
            LEFT JOIN $submissions_table s ON t.id = s.task_id AND s.user_id = %d
            WHERE t.status = 'active' 
            AND t.remaining_budget >= t.payout_amount
            AND s.id IS NULL
            ORDER BY t.created_at DESC
        ", $user_id));
        
        return $tasks;
    }
    
    public static function get_user_submissions($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        
        $submissions = $wpdb->get_results($wpdb->prepare("
            SELECT s.*, t.title as task_title, t.payout_amount
            FROM $submissions_table s
            JOIN $tasks_table t ON s.task_id = t.id
            WHERE s.user_id = %d
            ORDER BY s.submitted_at DESC
        ", $user_id));
        
        return $submissions;
    }
    
    public function submit_task() {
        check_ajax_referer('leadpayout_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to submit tasks.', 'leadpayout-pro'));
        }
        
        $task_id = intval($_POST['task_id']);
        $proof_data = sanitize_textarea_field($_POST['proof_data']);
        $user_id = get_current_user_id();
        
        // Check if user already submitted this task
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $submissions_table WHERE task_id = %d AND user_id = %d",
            $task_id, $user_id
        ));
        
        if ($existing) {
            wp_send_json_error(__('You have already submitted this task.', 'leadpayout-pro'));
        }
        
        // Get task details
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d AND status = 'active'",
            $task_id
        ));
        
        if (!$task) {
            wp_send_json_error(__('Task not found or inactive.', 'leadpayout-pro'));
        }
        
        if ($task->remaining_budget < $task->payout_amount) {
            wp_send_json_error(__('Task budget exhausted.', 'leadpayout-pro'));
        }
        
        // Handle file uploads
        $proof_files = array();
        if (!empty($_FILES['proof_files'])) {
            $proof_files = $this->handle_proof_files($_FILES['proof_files']);
        }
        
        // Insert submission
        $result = $wpdb->insert($submissions_table, array(
            'task_id' => $task_id,
            'user_id' => $user_id,
            'proof_data' => $proof_data,
            'proof_files' => json_encode($proof_files),
            'status' => 'pending',
            'ip_address' => $this->get_client_ip()
        ));
        
        if ($result) {
            // Check for auto-approval
            if (get_option('leadpayout_auto_approve') === 'yes') {
                $this->auto_approve_submission($wpdb->insert_id);
            }
            
            // Send notification email to admin
            if (class_exists('LeadPayout_Emails')) {
                LeadPayout_Emails::send_new_submission_notification($task, $user_id);
            }
            
            wp_send_json_success(__('Task submitted successfully!', 'leadpayout-pro'));
        } else {
            wp_send_json_error(__('Error submitting task. Please try again.', 'leadpayout-pro'));
        }
    }
    
    private function handle_proof_files($files) {
        $uploaded_files = array();
        $upload_dir = wp_upload_dir();
        $leadpayout_dir = $upload_dir['basedir'] . '/leadpayout-proofs/';
        
        if (!file_exists($leadpayout_dir)) {
            wp_mkdir_p($leadpayout_dir);
        }
        
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    );
                    
                    $uploaded_file = wp_handle_upload($file, array('test_form' => false));
                    if (isset($uploaded_file['file'])) {
                        $uploaded_files[] = $uploaded_file['url'];
                    }
                }
            }
        } else {
            if ($files['error'] === UPLOAD_ERR_OK) {
                $uploaded_file = wp_handle_upload($files, array('test_form' => false));
                if (isset($uploaded_file['file'])) {
                    $uploaded_files[] = $uploaded_file['url'];
                }
            }
        }
        
        return $uploaded_files;
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    private function auto_approve_submission($submission_id) {
        // Simple auto-approval logic - can be enhanced
        $this->approve_submission($submission_id);
    }
    
    public static function approve_submission($submission_id) {
        global $wpdb;
        
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        
        // Get submission details
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, t.payout_amount, t.remaining_budget 
             FROM $submissions_table s 
             JOIN $tasks_table t ON s.task_id = t.id 
             WHERE s.id = %d",
            $submission_id
        ));
        
        if (!$submission || $submission->status !== 'pending') {
            return false;
        }
        
        // Check if task still has budget
        if ($submission->remaining_budget < $submission->payout_amount) {
            return false;
        }
        
        // Update submission status
        $wpdb->update($submissions_table, array(
            'status' => 'approved',
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => get_current_user_id()
        ), array('id' => $submission_id));
        
        // Add earnings record
        $wpdb->insert($earnings_table, array(
            'user_id' => $submission->user_id,
            'amount' => $submission->payout_amount,
            'type' => 'task_completion',
            'source_id' => $submission->task_id,
            'source_type' => 'task',
            'status' => 'approved'
        ));
        
        // Update user balance
        if (class_exists('LeadPayout_Database')) {
            LeadPayout_Database::update_user_balance($submission->user_id, $submission->payout_amount, 'add');
        }
        
        // Update task remaining budget
        $new_budget = $submission->remaining_budget - $submission->payout_amount;
        $wpdb->update($tasks_table, array(
            'remaining_budget' => $new_budget,
            'status' => $new_budget <= 0 ? 'completed' : 'active'
        ), array('id' => $submission->task_id));
        
        // Process referral commission if applicable
        if (class_exists('LeadPayout_Referrals')) {
            LeadPayout_Referrals::process_referral_commission($submission->user_id, $submission->payout_amount);
        }
        
        // Send approval email
        if (class_exists('LeadPayout_Emails')) {
            LeadPayout_Emails::send_task_approval_notification($submission);
        }
        
        return true;
    }
    
    public static function reject_submission($submission_id, $reason = '') {
        global $wpdb;
        
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        
        $result = $wpdb->update($submissions_table, array(
            'status' => 'rejected',
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => get_current_user_id(),
            'review_notes' => $reason
        ), array('id' => $submission_id));
        
        if ($result) {
            // Get submission details for email
            $submission = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $submissions_table WHERE id = %d",
                $submission_id
            ));
            
            // Send rejection email
            if (class_exists('LeadPayout_Emails')) {
                LeadPayout_Emails::send_task_rejection_notification($submission, $reason);
            }
        }
        
        return $result;
    }
    
    public function get_available_tasks() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'leadpayout-pro'));
        }
        
        $tasks = self::get_available_tasks();
        wp_send_json_success($tasks);
    }
    
    public function get_user_tasks() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'leadpayout-pro'));
        }
        
        $submissions = self::get_user_submissions();
        wp_send_json_success($submissions);
    }
    
    public static function get_task_types() {
        return array(
            'share_link' => __('Share Link', 'leadpayout-pro'),
            'watch_video' => __('Watch Video', 'leadpayout-pro'),
            'install_app' => __('Install App', 'leadpayout-pro'),
            'visit_website' => __('Visit Website', 'leadpayout-pro'),
            'social_follow' => __('Social Media Follow', 'leadpayout-pro'),
            'review_product' => __('Review Product', 'leadpayout-pro'),
            'survey' => __('Complete Survey', 'leadpayout-pro'),
            'signup' => __('Sign Up', 'leadpayout-pro')
        );
    }
    
    public static function get_proof_requirements() {
        return array(
            'screenshot' => __('Screenshot', 'leadpayout-pro'),
            'url' => __('URL/Link', 'leadpayout-pro'),
            'text' => __('Text Description', 'leadpayout-pro'),
            'file' => __('File Upload', 'leadpayout-pro'),
            'social_proof' => __('Social Media Proof', 'leadpayout-pro')
        );
    }
    
    public static function get_task_stats($task_id) {
        global $wpdb;
        
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_submissions,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_submissions,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_submissions
            FROM $submissions_table 
            WHERE task_id = %d
        ", $task_id));
        
        return $stats;
    }
}