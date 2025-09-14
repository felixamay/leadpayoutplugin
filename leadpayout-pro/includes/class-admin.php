<?php
/**
 * Admin functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_leadpayout_approve_task', array($this, 'approve_task'));
        add_action('wp_ajax_leadpayout_reject_task', array($this, 'reject_task'));
        add_action('wp_ajax_leadpayout_process_withdrawal', array($this, 'process_withdrawal'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('LeadPayout', 'leadpayout-pro'),
            __('LeadPayout', 'leadpayout-pro'),
            'manage_options',
            'leadpayout',
            array($this, 'dashboard_page'),
            'dashicons-money-alt',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'leadpayout',
            __('Dashboard', 'leadpayout-pro'),
            __('Dashboard', 'leadpayout-pro'),
            'manage_options',
            'leadpayout',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Create Task', 'leadpayout-pro'),
            __('Create Task', 'leadpayout-pro'),
            'edit_posts',
            'leadpayout-create-task',
            array($this, 'create_task_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Manage Tasks', 'leadpayout-pro'),
            __('Manage Tasks', 'leadpayout-pro'),
            'manage_options',
            'leadpayout-manage-tasks',
            array($this, 'manage_tasks_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Approve Tasks', 'leadpayout-pro'),
            __('Approve Tasks', 'leadpayout-pro'),
            'manage_options',
            'leadpayout-approve-tasks',
            array($this, 'approve_tasks_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Referrals', 'leadpayout-pro'),
            __('Referrals', 'leadpayout-pro'),
            'manage_options',
            'leadpayout-referrals',
            array($this, 'referrals_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Withdrawals', 'leadpayout-pro'),
            __('Withdrawals', 'leadpayout-pro'),
            'manage_options',
            'leadpayout-withdrawals',
            array($this, 'withdrawals_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Settings', 'leadpayout-pro'),
            __('Settings', 'leadpayout-pro'),
            'manage_options',
            'leadpayout-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'leadpayout',
            __('Leaderboard', 'leadpayout-pro'),
            __('Leaderboard', 'leadpayout-pro'),
            'manage_options',
            'leadpayout-leaderboard',
            array($this, 'leaderboard_page')
        );
    }
    
    public function dashboard_page() {
        global $wpdb;
        
        // Get statistics
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
        
        $total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $tasks_table");
        $pending_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table WHERE status = 'pending'");
        $total_earnings = $wpdb->get_var("SELECT SUM(amount) FROM $earnings_table WHERE status = 'approved'");
        $pending_withdrawals = $wpdb->get_var("SELECT COUNT(*) FROM $transactions_table WHERE type = 'withdrawal' AND status = 'pending'");
        
        ?>
        <div class="wrap">
            <h1><?php _e('LeadPayout Dashboard', 'leadpayout-pro'); ?></h1>
            
            <div class="leadpayout-dashboard-stats">
                <div class="leadpayout-stat-box">
                    <h3><?php echo number_format($total_tasks); ?></h3>
                    <p><?php _e('Total Tasks', 'leadpayout-pro'); ?></p>
                </div>
                <div class="leadpayout-stat-box">
                    <h3><?php echo number_format($pending_submissions); ?></h3>
                    <p><?php _e('Pending Submissions', 'leadpayout-pro'); ?></p>
                </div>
                <div class="leadpayout-stat-box">
                    <h3>$<?php echo number_format($total_earnings, 2); ?></h3>
                    <p><?php _e('Total Earnings Paid', 'leadpayout-pro'); ?></p>
                </div>
                <div class="leadpayout-stat-box">
                    <h3><?php echo number_format($pending_withdrawals); ?></h3>
                    <p><?php _e('Pending Withdrawals', 'leadpayout-pro'); ?></p>
                </div>
            </div>
            
            <div class="leadpayout-recent-activity">
                <h2><?php _e('Recent Activity', 'leadpayout-pro'); ?></h2>
                <?php $this->display_recent_submissions(); ?>
            </div>
        </div>
        <?php
    }
    
    public function create_task_page() {
        if (isset($_POST['submit_task']) && wp_verify_nonce($_POST['leadpayout_nonce'], 'create_task')) {
            $this->handle_task_creation();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Create New Task', 'leadpayout-pro'); ?></h1>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('create_task', 'leadpayout_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Task Title', 'leadpayout-pro'); ?></th>
                        <td><input type="text" name="task_title" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Description', 'leadpayout-pro'); ?></th>
                        <td><textarea name="task_description" rows="5" cols="50"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Task Type', 'leadpayout-pro'); ?></th>
                        <td>
                            <select name="task_type" required>
                                <option value=""><?php _e('Select Type', 'leadpayout-pro'); ?></option>
                                <option value="share_link"><?php _e('Share Link', 'leadpayout-pro'); ?></option>
                                <option value="watch_video"><?php _e('Watch Video', 'leadpayout-pro'); ?></option>
                                <option value="install_app"><?php _e('Install App', 'leadpayout-pro'); ?></option>
                                <option value="visit_website"><?php _e('Visit Website', 'leadpayout-pro'); ?></option>
                                <option value="social_follow"><?php _e('Social Media Follow', 'leadpayout-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Payout per Completion', 'leadpayout-pro'); ?></th>
                        <td>
                            $<input type="number" name="payout_amount" min="0.10" max="2.00" step="0.01" required />
                            <p class="description"><?php _e('Between $0.10 and $2.00', 'leadpayout-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Total Budget', 'leadpayout-pro'); ?></th>
                        <td>
                            $<input type="number" name="total_budget" min="1.00" step="0.01" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Proof Requirements', 'leadpayout-pro'); ?></th>
                        <td>
                            <label><input type="checkbox" name="proof_requirements[]" value="screenshot" /> <?php _e('Screenshot', 'leadpayout-pro'); ?></label><br>
                            <label><input type="checkbox" name="proof_requirements[]" value="url" /> <?php _e('URL/Link', 'leadpayout-pro'); ?></label><br>
                            <label><input type="checkbox" name="proof_requirements[]" value="text" /> <?php _e('Text Description', 'leadpayout-pro'); ?></label><br>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Create Task', 'leadpayout-pro'), 'primary', 'submit_task'); ?>
            </form>
        </div>
        <?php
    }
    
    public function manage_tasks_page() {
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        
        $tasks = $wpdb->get_results("SELECT * FROM $tasks_table ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Manage Tasks', 'leadpayout-pro'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Title', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Type', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Payout', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Budget', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Status', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Created', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Actions', 'leadpayout-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?php echo esc_html($task->title); ?></td>
                        <td><?php echo esc_html($task->task_type); ?></td>
                        <td>$<?php echo number_format($task->payout_amount, 2); ?></td>
                        <td>$<?php echo number_format($task->remaining_budget, 2); ?> / $<?php echo number_format($task->total_budget, 2); ?></td>
                        <td><?php echo esc_html($task->status); ?></td>
                        <td><?php echo date('M j, Y', strtotime($task->created_at)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=leadpayout-edit-task&id=' . $task->id); ?>" class="button"><?php _e('Edit', 'leadpayout-pro'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function approve_tasks_page() {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        
        $submissions = $wpdb->get_results("
            SELECT s.*, t.title as task_title, t.payout_amount, u.display_name
            FROM $submissions_table s
            JOIN $tasks_table t ON s.task_id = t.id
            JOIN {$wpdb->users} u ON s.user_id = u.ID
            WHERE s.status = 'pending'
            ORDER BY s.submitted_at DESC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Approve Task Submissions', 'leadpayout-pro'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Task', 'leadpayout-pro'); ?></th>
                        <th><?php _e('User', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Payout', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Proof', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Submitted', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Actions', 'leadpayout-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                    <tr>
                        <td><?php echo esc_html($submission->task_title); ?></td>
                        <td><?php echo esc_html($submission->display_name); ?></td>
                        <td>$<?php echo number_format($submission->payout_amount, 2); ?></td>
                        <td>
                            <?php if ($submission->proof_data): ?>
                                <div><?php echo esc_html(substr($submission->proof_data, 0, 100)) . '...'; ?></div>
                            <?php endif; ?>
                            <?php if ($submission->proof_files): ?>
                                <div><strong><?php _e('Files attached', 'leadpayout-pro'); ?></strong></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y H:i', strtotime($submission->submitted_at)); ?></td>
                        <td>
                            <button class="button button-primary" onclick="approveSubmission(<?php echo $submission->id; ?>)"><?php _e('Approve', 'leadpayout-pro'); ?></button>
                            <button class="button" onclick="rejectSubmission(<?php echo $submission->id; ?>)"><?php _e('Reject', 'leadpayout-pro'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        function approveSubmission(id) {
            if (confirm('<?php _e('Approve this submission?', 'leadpayout-pro'); ?>')) {
                jQuery.post(ajaxurl, {
                    action: 'leadpayout_approve_task',
                    submission_id: id,
                    nonce: '<?php echo wp_create_nonce('leadpayout_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            }
        }
        
        function rejectSubmission(id) {
            var reason = prompt('<?php _e('Reason for rejection (optional):', 'leadpayout-pro'); ?>');
            if (reason !== null) {
                jQuery.post(ajaxurl, {
                    action: 'leadpayout_reject_task',
                    submission_id: id,
                    reason: reason,
                    nonce: '<?php echo wp_create_nonce('leadpayout_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    public function referrals_page() {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'leadpayout_referrals';
        
        $referrals = $wpdb->get_results("
            SELECT r.*, 
                   u1.display_name as referrer_name,
                   u2.display_name as referred_name
            FROM $referrals_table r
            JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
            JOIN {$wpdb->users} u2 ON r.referred_id = u2.ID
            ORDER BY r.created_at DESC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Referrals Overview', 'leadpayout-pro'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Referrer', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Referred User', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Code', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Commission Rate', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Total Earned', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Date', 'leadpayout-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $referral): ?>
                    <tr>
                        <td><?php echo esc_html($referral->referrer_name); ?></td>
                        <td><?php echo esc_html($referral->referred_name); ?></td>
                        <td><?php echo esc_html($referral->referral_code); ?></td>
                        <td><?php echo number_format($referral->commission_rate, 2); ?>%</td>
                        <td>$<?php echo number_format($referral->total_earned, 2); ?></td>
                        <td><?php echo date('M j, Y', strtotime($referral->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function withdrawals_page() {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
        
        $withdrawals = $wpdb->get_results("
            SELECT t.*, u.display_name
            FROM $transactions_table t
            JOIN {$wpdb->users} u ON t.user_id = u.ID
            WHERE t.type = 'withdrawal'
            ORDER BY t.created_at DESC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Withdrawal Requests', 'leadpayout-pro'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Amount', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Status', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Requested', 'leadpayout-pro'); ?></th>
                        <th><?php _e('Actions', 'leadpayout-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                    <tr>
                        <td><?php echo esc_html($withdrawal->display_name); ?></td>
                        <td>$<?php echo number_format($withdrawal->amount, 2); ?></td>
                        <td><?php echo esc_html($withdrawal->status); ?></td>
                        <td><?php echo date('M j, Y H:i', strtotime($withdrawal->created_at)); ?></td>
                        <td>
                            <?php if ($withdrawal->status === 'pending'): ?>
                                <button class="button button-primary" onclick="processWithdrawal(<?php echo $withdrawal->id; ?>)"><?php _e('Process', 'leadpayout-pro'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        function processWithdrawal(id) {
            if (confirm('<?php _e('Process this withdrawal?', 'leadpayout-pro'); ?>')) {
                jQuery.post(ajaxurl, {
                    action: 'leadpayout_process_withdrawal',
                    withdrawal_id: id,
                    nonce: '<?php echo wp_create_nonce('leadpayout_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit_settings']) && wp_verify_nonce($_POST['leadpayout_nonce'], 'save_settings')) {
            $this->save_settings();
        }
        
        $min_payout = get_option('leadpayout_min_payout', '0.10');
        $max_payout = get_option('leadpayout_max_payout', '2.00');
        $referral_rate = get_option('leadpayout_referral_rate', '10');
        $auto_approve = get_option('leadpayout_auto_approve', 'no');
        $stripe_mode = get_option('leadpayout_stripe_mode', 'test');
        $stripe_public_key = get_option('leadpayout_stripe_public_key', '');
        $stripe_secret_key = get_option('leadpayout_stripe_secret_key', '');
        
        ?>
        <div class="wrap">
            <h1><?php _e('LeadPayout Settings', 'leadpayout-pro'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_settings', 'leadpayout_nonce'); ?>
                
                <h2><?php _e('General Settings', 'leadpayout-pro'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Minimum Payout', 'leadpayout-pro'); ?></th>
                        <td>$<input type="number" name="min_payout" value="<?php echo esc_attr($min_payout); ?>" min="0.01" step="0.01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Maximum Payout', 'leadpayout-pro'); ?></th>
                        <td>$<input type="number" name="max_payout" value="<?php echo esc_attr($max_payout); ?>" min="0.01" step="0.01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Referral Commission Rate', 'leadpayout-pro'); ?></th>
                        <td><input type="number" name="referral_rate" value="<?php echo esc_attr($referral_rate); ?>" min="0" max="100" />%</td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Auto Approve Tasks', 'leadpayout-pro'); ?></th>
                        <td>
                            <label><input type="radio" name="auto_approve" value="yes" <?php checked($auto_approve, 'yes'); ?> /> <?php _e('Yes', 'leadpayout-pro'); ?></label><br>
                            <label><input type="radio" name="auto_approve" value="no" <?php checked($auto_approve, 'no'); ?> /> <?php _e('No', 'leadpayout-pro'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Stripe Settings', 'leadpayout-pro'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Stripe Mode', 'leadpayout-pro'); ?></th>
                        <td>
                            <label><input type="radio" name="stripe_mode" value="test" <?php checked($stripe_mode, 'test'); ?> /> <?php _e('Test Mode', 'leadpayout-pro'); ?></label><br>
                            <label><input type="radio" name="stripe_mode" value="live" <?php checked($stripe_mode, 'live'); ?> /> <?php _e('Live Mode', 'leadpayout-pro'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Stripe Public Key', 'leadpayout-pro'); ?></th>
                        <td><input type="text" name="stripe_public_key" value="<?php echo esc_attr($stripe_public_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Stripe Secret Key', 'leadpayout-pro'); ?></th>
                        <td><input type="password" name="stripe_secret_key" value="<?php echo esc_attr($stripe_secret_key); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'leadpayout-pro'), 'primary', 'submit_settings'); ?>
            </form>
        </div>
        <?php
    }
    
    public function leaderboard_page() {
        $weekly_leaders = array();
        $monthly_leaders = array();
        
        if (class_exists('LeadPayout_Database')) {
            $weekly_leaders = LeadPayout_Database::get_leaderboard(10, 'week');
            $monthly_leaders = LeadPayout_Database::get_leaderboard(10, 'month');
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Leaderboard', 'leadpayout-pro'); ?></h1>
            
            <div class="leadpayout-leaderboard-tabs">
                <h2><?php _e('Weekly Leaders', 'leadpayout-pro'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Rank', 'leadpayout-pro'); ?></th>
                            <th><?php _e('User', 'leadpayout-pro'); ?></th>
                            <th><?php _e('Tasks Completed', 'leadpayout-pro'); ?></th>
                            <th><?php _e('Total Earned', 'leadpayout-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_leaders as $index => $leader): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo esc_html($leader->display_name); ?></td>
                            <td><?php echo number_format($leader->tasks_completed); ?></td>
                            <td>$<?php echo number_format($leader->total_earned, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h2><?php _e('Monthly Leaders', 'leadpayout-pro'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Rank', 'leadpayout-pro'); ?></th>
                            <th><?php _e('User', 'leadpayout-pro'); ?></th>
                            <th><?php _e('Tasks Completed', 'leadpayout-pro'); ?></th>
                            <th><?php _e('Total Earned', 'leadpayout-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_leaders as $index => $leader): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo esc_html($leader->display_name); ?></td>
                            <td><?php echo number_format($leader->tasks_completed); ?></td>
                            <td>$<?php echo number_format($leader->total_earned, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    private function handle_task_creation() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to create tasks.', 'leadpayout-pro'));
        }
        
        global $wpdb;
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        
        $title = sanitize_text_field($_POST['task_title']);
        $description = sanitize_textarea_field($_POST['task_description']);
        $task_type = sanitize_text_field($_POST['task_type']);
        $payout_amount = floatval($_POST['payout_amount']);
        $total_budget = floatval($_POST['total_budget']);
        $proof_requirements = isset($_POST['proof_requirements']) ? implode(',', array_map('sanitize_text_field', $_POST['proof_requirements'])) : '';
        
        $result = $wpdb->insert($tasks_table, array(
            'user_id' => get_current_user_id(),
            'title' => $title,
            'description' => $description,
            'task_type' => $task_type,
            'payout_amount' => $payout_amount,
            'total_budget' => $total_budget,
            'remaining_budget' => $total_budget,
            'proof_requirements' => $proof_requirements,
            'status' => 'active'
        ));
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . __('Task created successfully!', 'leadpayout-pro') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error creating task. Please try again.', 'leadpayout-pro') . '</p></div>';
        }
    }
    
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to manage settings.', 'leadpayout-pro'));
        }
        
        update_option('leadpayout_min_payout', sanitize_text_field($_POST['min_payout']));
        update_option('leadpayout_max_payout', sanitize_text_field($_POST['max_payout']));
        update_option('leadpayout_referral_rate', sanitize_text_field($_POST['referral_rate']));
        update_option('leadpayout_auto_approve', sanitize_text_field($_POST['auto_approve']));
        update_option('leadpayout_stripe_mode', sanitize_text_field($_POST['stripe_mode']));
        update_option('leadpayout_stripe_public_key', sanitize_text_field($_POST['stripe_public_key']));
        update_option('leadpayout_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'leadpayout-pro') . '</p></div>';
    }
    
    private function display_recent_submissions() {
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        $tasks_table = $wpdb->prefix . 'leadpayout_tasks';
        
        $recent_submissions = $wpdb->get_results("
            SELECT s.*, t.title as task_title, u.display_name
            FROM $submissions_table s
            JOIN $tasks_table t ON s.task_id = t.id
            JOIN {$wpdb->users} u ON s.user_id = u.ID
            ORDER BY s.submitted_at DESC
            LIMIT 10
        ");
        
        if ($recent_submissions) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Task</th><th>User</th><th>Status</th><th>Date</th></tr></thead>';
            echo '<tbody>';
            foreach ($recent_submissions as $submission) {
                echo '<tr>';
                echo '<td>' . esc_html($submission->task_title) . '</td>';
                echo '<td>' . esc_html($submission->display_name) . '</td>';
                echo '<td>' . esc_html($submission->status) . '</td>';
                echo '<td>' . date('M j, Y H:i', strtotime($submission->submitted_at)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No recent submissions.', 'leadpayout-pro') . '</p>';
        }
    }
    
    public function approve_task() {
        check_ajax_referer('leadpayout_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'leadpayout-pro'));
        }
        
        $submission_id = intval($_POST['submission_id']);
        
        if (class_exists('LeadPayout_Tasks')) {
            LeadPayout_Tasks::approve_submission($submission_id);
        }
        
        wp_send_json_success(__('Task approved successfully.', 'leadpayout-pro'));
    }
    
    public function reject_task() {
        check_ajax_referer('leadpayout_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'leadpayout-pro'));
        }
        
        $submission_id = intval($_POST['submission_id']);
        $reason = sanitize_text_field($_POST['reason']);
        
        if (class_exists('LeadPayout_Tasks')) {
            LeadPayout_Tasks::reject_submission($submission_id, $reason);
        }
        
        wp_send_json_success(__('Task rejected.', 'leadpayout-pro'));
    }
    
    public function process_withdrawal() {
        check_ajax_referer('leadpayout_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'leadpayout-pro'));
        }
        
        $withdrawal_id = intval($_POST['withdrawal_id']);
        
        if (class_exists('LeadPayout_Stripe')) {
            LeadPayout_Stripe::process_withdrawal($withdrawal_id);
        }
        
        wp_send_json_success(__('Withdrawal processed.', 'leadpayout-pro'));
    }
}