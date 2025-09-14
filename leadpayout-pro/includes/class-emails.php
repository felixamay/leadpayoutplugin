<?php
/**
 * Email notifications class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Emails {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }
    
    public function set_html_content_type() {
        return 'text/html';
    }
    
    public static function send_new_submission_notification($task, $user_id) {
        $user = get_user_by('ID', $user_id);
        $admin_email = LEADPAYOUT_PRO_ADMIN_EMAIL;
        
        $subject = sprintf(__('[LeadPayout] New Task Submission - %s', 'leadpayout-pro'), $task->title);
        
        $message = self::get_email_template('new_submission', array(
            'task_title' => $task->title,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'payout_amount' => number_format($task->payout_amount, 2),
            'admin_url' => admin_url('admin.php?page=leadpayout-approve-tasks')
        ));
        
        wp_mail($admin_email, $subject, $message);
    }
    
    public static function send_task_approval_notification($submission) {
        $user = get_user_by('ID', $submission->user_id);
        
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d",
            $submission->task_id
        ));
        
        $subject = sprintf(__('[LeadPayout] Task Approved - %s', 'leadpayout-pro'), $task->title);
        
        $message = self::get_email_template('task_approved', array(
            'user_name' => $user->display_name,
            'task_title' => $task->title,
            'payout_amount' => number_format($submission->payout_amount, 2),
            'earnings_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_earnings_url() : home_url())
        ));
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    public static function send_task_rejection_notification($submission, $reason = '') {
        $user = get_user_by('ID', $submission->user_id);
        
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d",
            $submission->task_id
        ));
        
        $subject = sprintf(__('[LeadPayout] Task Rejected - %s', 'leadpayout-pro'), $task->title);
        
        $message = self::get_email_template('task_rejected', array(
            'user_name' => $user->display_name,
            'task_title' => $task->title,
            'reason' => $reason,
            'tasks_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_tasks_url() : home_url())
        ));
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    public static function send_referral_notification($referrer_id, $referred_user_id) {
        $referrer = get_user_by('ID', $referrer_id);
        $referred_user = get_user_by('ID', $referred_user_id);
        
        $subject = __('[LeadPayout] New Referral Signup!', 'leadpayout-pro');
        
        $message = self::get_email_template('new_referral', array(
            'referrer_name' => $referrer->display_name,
            'referred_name' => $referred_user->display_name,
            'commission_rate' => get_option('leadpayout_referral_rate', 10),
            'referrals_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_referrals_url() : home_url())
        ));
        
        wp_mail($referrer->user_email, $subject, $message);
    }
    
    public static function send_commission_notification($referrer_id, $commission_amount, $referred_user_id) {
        $referrer = get_user_by('ID', $referrer_id);
        $referred_user = get_user_by('ID', $referred_user_id);
        
        $subject = __('[LeadPayout] Commission Earned!', 'leadpayout-pro');
        
        $message = self::get_email_template('commission_earned', array(
            'referrer_name' => $referrer->display_name,
            'referred_name' => $referred_user->display_name,
            'commission_amount' => number_format($commission_amount, 2),
            'earnings_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_earnings_url() : home_url())
        ));
        
        wp_mail($referrer->user_email, $subject, $message);
    }
    
    public static function send_withdrawal_notification($user_id, $amount, $method) {
        $user = get_user_by('ID', $user_id);
        $admin_email = LEADPAYOUT_PRO_ADMIN_EMAIL;
        
        $subject = sprintf(__('[LeadPayout] Withdrawal Request - $%s', 'leadpayout-pro'), number_format($amount, 2));
        
        $message = self::get_email_template('withdrawal_request', array(
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'amount' => number_format($amount, 2),
            'method' => $method,
            'admin_url' => admin_url('admin.php?page=leadpayout-withdrawals')
        ));
        
        wp_mail($admin_email, $subject, $message);
        
        // Also notify the user
        $user_subject = __('[LeadPayout] Withdrawal Request Received', 'leadpayout-pro');
        $user_message = self::get_email_template('withdrawal_received', array(
            'user_name' => $user->display_name,
            'amount' => number_format($amount, 2),
            'method' => $method
        ));
        
        wp_mail($user->user_email, $user_subject, $user_message);
    }
    
    public static function send_withdrawal_confirmation($user_id, $amount) {
        $user = get_user_by('ID', $user_id);
        
        $subject = sprintf(__('[LeadPayout] Withdrawal Processed - $%s', 'leadpayout-pro'), number_format($amount, 2));
        
        $message = self::get_email_template('withdrawal_confirmed', array(
            'user_name' => $user->display_name,
            'amount' => number_format($amount, 2),
            'earnings_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_earnings_url() : home_url())
        ));
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    public static function send_welcome_email($user_id) {
        $user = get_user_by('ID', $user_id);
        $referral_code = 'N/A';
        if (class_exists('LeadPayout_Referrals')) {
            $referral_code = LeadPayout_Referrals::get_user_referral_code($user_id);
        }
        
        $subject = __('[LeadPayout] Welcome to LeadPayout Pro!', 'leadpayout-pro');
        
        $message = self::get_email_template('welcome', array(
            'user_name' => $user->display_name,
            'referral_code' => $referral_code,
            'dashboard_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_user_dashboard_url() : home_url()),
            'tasks_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_tasks_url() : home_url()),
            'referrals_url' => (class_exists('LeadPayout_Frontend') ? LeadPayout_Frontend::get_referrals_url() : home_url())
        ));
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    private static function get_email_template($template_name, $variables = array()) {
        $templates = array(
            'new_submission' => '
                <h2>New Task Submission</h2>
                <p>Hello Admin,</p>
                <p>A new task submission has been received:</p>
                <ul>
                    <li><strong>Task:</strong> {task_title}</li>
                    <li><strong>User:</strong> {user_name} ({user_email})</li>
                    <li><strong>Payout:</strong> ${payout_amount}</li>
                </ul>
                <p><a href="{admin_url}">Review Submissions</a></p>
            ',
            
            'task_approved' => '
                <h2>Task Approved!</h2>
                <p>Hello {user_name},</p>
                <p>Great news! Your task submission has been approved:</p>
                <ul>
                    <li><strong>Task:</strong> {task_title}</li>
                    <li><strong>Earnings:</strong> ${payout_amount}</li>
                </ul>
                <p>The amount has been added to your account balance.</p>
                <p><a href="{earnings_url}">View Your Earnings</a></p>
            ',
            
            'task_rejected' => '
                <h2>Task Submission Update</h2>
                <p>Hello {user_name},</p>
                <p>Your task submission for "{task_title}" has been reviewed and unfortunately was not approved.</p>
                <p><strong>Reason:</strong> {reason}</p>
                <p>Don\'t worry! There are plenty of other tasks available.</p>
                <p><a href="{tasks_url}">View Available Tasks</a></p>
            ',
            
            'new_referral' => '
                <h2>New Referral Signup!</h2>
                <p>Hello {referrer_name},</p>
                <p>Congratulations! {referred_name} has signed up using your referral link.</p>
                <p>You\'ll earn {commission_rate}% commission on all their task completions.</p>
                <p><a href="{referrals_url}">View Your Referrals</a></p>
            ',
            
            'commission_earned' => '
                <h2>Commission Earned!</h2>
                <p>Hello {referrer_name},</p>
                <p>You\'ve earned a commission of ${commission_amount} from {referred_name}\'s task completion!</p>
                <p>The commission has been added to your account balance.</p>
                <p><a href="{earnings_url}">View Your Earnings</a></p>
            ',
            
            'withdrawal_request' => '
                <h2>Withdrawal Request</h2>
                <p>Hello Admin,</p>
                <p>A withdrawal request has been submitted:</p>
                <ul>
                    <li><strong>User:</strong> {user_name} ({user_email})</li>
                    <li><strong>Amount:</strong> ${amount}</li>
                    <li><strong>Method:</strong> {method}</li>
                </ul>
                <p><a href="{admin_url}">Process Withdrawal</a></p>
            ',
            
            'withdrawal_received' => '
                <h2>Withdrawal Request Received</h2>
                <p>Hello {user_name},</p>
                <p>We\'ve received your withdrawal request for ${amount} via {method}.</p>
                <p>Your request will be processed within 1-3 business days.</p>
                <p>Thank you for using LeadPayout Pro!</p>
            ',
            
            'withdrawal_confirmed' => '
                <h2>Withdrawal Processed</h2>
                <p>Hello {user_name},</p>
                <p>Your withdrawal of ${amount} has been processed successfully!</p>
                <p>The funds should appear in your account within 1-3 business days.</p>
                <p><a href="{earnings_url}">View Your Earnings</a></p>
            ',
            
            'welcome' => '
                <h2>Welcome to LeadPayout Pro!</h2>
                <p>Hello {user_name},</p>
                <p>Welcome to LeadPayout Pro! You\'re now ready to start earning money by completing simple tasks.</p>
                <h3>Your Referral Code: {referral_code}</h3>
                <p>Share your referral code with friends and earn commissions on their task completions!</p>
                <h3>Quick Links:</h3>
                <ul>
                    <li><a href="{dashboard_url}">Your Dashboard</a></li>
                    <li><a href="{tasks_url}">Available Tasks</a></li>
                    <li><a href="{referrals_url}">Refer & Earn</a></li>
                </ul>
                <p>Happy earning!</p>
            '
        );
        
        if (!isset($templates[$template_name])) {
            return '';
        }
        
        $template = $templates[$template_name];
        
        // Replace variables
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        // Wrap in basic HTML structure
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>LeadPayout Pro</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                h2 { color: #2c3e50; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
                ul { padding-left: 20px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                ' . $template . '
                <div class="footer">
                    <p>This email was sent by LeadPayout Pro. If you no longer wish to receive these emails, please contact support.</p>
                    <p>&copy; ' . date('Y') . ' LeadPayout Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}