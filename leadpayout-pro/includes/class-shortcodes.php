<?php
/**
 * Shortcodes class for frontend display
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('leadpayout_tasks', array($this, 'tasks_shortcode'));
        add_shortcode('leadpayout_earnings', array($this, 'earnings_shortcode'));
        add_shortcode('leadpayout_referrals', array($this, 'referrals_shortcode'));
        add_shortcode('leadpayout_leaderboard', array($this, 'leaderboard_shortcode'));
        add_shortcode('leadpayout_dashboard', array($this, 'dashboard_shortcode'));
    }
    
    public function tasks_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="leadpayout-login-required">' . 
                   __('Please log in to view available tasks.', 'leadpayout-pro') . 
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login', 'leadpayout-pro') . '</a>' .
                   '</div>';
        }
        
        $available_tasks = array();
        $user_submissions = array();
        
        if (class_exists('LeadPayout_Tasks')) {
            $available_tasks = LeadPayout_Tasks::get_available_tasks();
            $user_submissions = LeadPayout_Tasks::get_user_submissions();
        }
        
        ob_start();
        ?>
        <div class="leadpayout-tasks-container">
            <div class="leadpayout-tabs">
                <button class="leadpayout-tab-button active" onclick="showTab('available')"><?php _e('Available Tasks', 'leadpayout-pro'); ?></button>
                <button class="leadpayout-tab-button" onclick="showTab('submitted')"><?php _e('My Submissions', 'leadpayout-pro'); ?></button>
            </div>
            
            <div id="available-tasks" class="leadpayout-tab-content active">
                <h3><?php _e('Available Tasks', 'leadpayout-pro'); ?></h3>
                
                <?php if (empty($available_tasks)): ?>
                    <p><?php _e('No tasks available at the moment. Check back later!', 'leadpayout-pro'); ?></p>
                <?php else: ?>
                    <div class="leadpayout-tasks-grid">
                        <?php foreach ($available_tasks as $task): ?>
                            <div class="leadpayout-task-card">
                                <h4><?php echo esc_html($task->title); ?></h4>
                                <p class="task-description"><?php echo esc_html($task->description); ?></p>
                                <div class="task-meta">
                                    <span class="task-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', $task->task_type))); ?></span>
                                    <span class="task-payout">$<?php echo number_format($task->payout_amount, 2); ?></span>
                                </div>
                                <div class="task-requirements">
                                    <strong><?php _e('Proof Required:', 'leadpayout-pro'); ?></strong>
                                    <span><?php echo esc_html($task->proof_requirements); ?></span>
                                </div>
                                <button class="leadpayout-btn leadpayout-btn-primary" onclick="startTask(<?php echo $task->id; ?>)">
                                    <?php _e('Start Task', 'leadpayout-pro'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div id="submitted-tasks" class="leadpayout-tab-content">
                <h3><?php _e('My Submissions', 'leadpayout-pro'); ?></h3>
                
                <?php if (empty($user_submissions)): ?>
                    <p><?php _e('You haven\'t submitted any tasks yet.', 'leadpayout-pro'); ?></p>
                <?php else: ?>
                    <div class="leadpayout-submissions-list">
                        <?php foreach ($user_submissions as $submission): ?>
                            <div class="leadpayout-submission-item">
                                <h4><?php echo esc_html($submission->task_title); ?></h4>
                                <div class="submission-meta">
                                    <span class="submission-status status-<?php echo $submission->status; ?>">
                                        <?php echo esc_html(ucfirst($submission->status)); ?>
                                    </span>
                                    <span class="submission-amount">$<?php echo number_format($submission->payout_amount, 2); ?></span>
                                    <span class="submission-date"><?php echo date('M j, Y', strtotime($submission->submitted_at)); ?></span>
                                </div>
                                <?php if ($submission->review_notes): ?>
                                    <div class="submission-notes">
                                        <strong><?php _e('Review Notes:', 'leadpayout-pro'); ?></strong>
                                        <p><?php echo esc_html($submission->review_notes); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Task Submission Modal -->
        <div id="task-modal" class="leadpayout-modal" style="display: none;">
            <div class="leadpayout-modal-content">
                <span class="leadpayout-close" onclick="closeTaskModal()">&times;</span>
                <h3 id="modal-task-title"></h3>
                <div id="modal-task-content"></div>
                
                <form id="task-submission-form" enctype="multipart/form-data">
                    <input type="hidden" id="task-id" name="task_id" />
                    
                    <div class="form-group">
                        <label for="proof-data"><?php _e('Proof Description:', 'leadpayout-pro'); ?></label>
                        <textarea id="proof-data" name="proof_data" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="proof-files"><?php _e('Upload Proof Files (optional):', 'leadpayout-pro'); ?></label>
                        <input type="file" id="proof-files" name="proof_files[]" multiple accept="image/*,.pdf,.doc,.docx" />
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="leadpayout-btn leadpayout-btn-primary"><?php _e('Submit Task', 'leadpayout-pro'); ?></button>
                        <button type="button" class="leadpayout-btn leadpayout-btn-secondary" onclick="closeTaskModal()"><?php _e('Cancel', 'leadpayout-pro'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function showTab(tabName) {
            // Hide all tab contents
            var contents = document.querySelectorAll('.leadpayout-tab-content');
            contents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // Remove active class from all buttons
            var buttons = document.querySelectorAll('.leadpayout-tab-button');
            buttons.forEach(function(button) {
                button.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tasks').classList.add('active');
            event.target.classList.add('active');
        }
        
        function startTask(taskId) {
            // Get task details and show modal
            var taskCard = event.target.closest('.leadpayout-task-card');
            var title = taskCard.querySelector('h4').textContent;
            var description = taskCard.querySelector('.task-description').textContent;
            
            document.getElementById('modal-task-title').textContent = title;
            document.getElementById('modal-task-content').innerHTML = '<p>' + description + '</p>';
            document.getElementById('task-id').value = taskId;
            document.getElementById('task-modal').style.display = 'block';
        }
        
        function closeTaskModal() {
            document.getElementById('task-modal').style.display = 'none';
            document.getElementById('task-submission-form').reset();
        }
        
        // Handle form submission
        document.getElementById('task-submission-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'leadpayout_submit_task');
            formData.append('nonce', leadpayout_ajax.nonce);
            
            fetch(leadpayout_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('<?php _e('Task submitted successfully!', 'leadpayout-pro'); ?>');
                    closeTaskModal();
                    location.reload();
                } else {
                    alert(data.data || '<?php _e('Error submitting task.', 'leadpayout-pro'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?php _e('Error submitting task.', 'leadpayout-pro'); ?>');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function earnings_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="leadpayout-login-required">' . 
                   __('Please log in to view your earnings.', 'leadpayout-pro') . 
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login', 'leadpayout-pro') . '</a>' .
                   '</div>';
        }
        
        $user_id = get_current_user_id();
        $balance = (object) array(
            'available_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0
        );
        
        if (class_exists('LeadPayout_Database')) {
            $balance = LeadPayout_Database::get_user_balance($user_id);
        }
        
        global $wpdb;
        $earnings_table = $wpdb->prefix . 'leadpayout_earnings';
        $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
        
        // Get recent earnings
        $recent_earnings = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $earnings_table 
            WHERE user_id = %d AND status = 'approved'
            ORDER BY created_at DESC 
            LIMIT 10
        ", $user_id));
        
        // Get withdrawal history
        $withdrawals = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $transactions_table 
            WHERE user_id = %d AND type = 'withdrawal'
            ORDER BY created_at DESC 
            LIMIT 10
        ", $user_id));
        
        ob_start();
        ?>
        <div class="leadpayout-earnings-container">
            <div class="leadpayout-balance-summary">
                <div class="balance-card">
                    <h3><?php _e('Available Balance', 'leadpayout-pro'); ?></h3>
                    <div class="balance-amount">$<?php echo number_format($balance->available_balance, 2); ?></div>
                    <button class="leadpayout-btn leadpayout-btn-primary" onclick="requestWithdrawal()">
                        <?php _e('Request Withdrawal', 'leadpayout-pro'); ?>
                    </button>
                </div>
                
                <div class="balance-card">
                    <h3><?php _e('Total Earned', 'leadpayout-pro'); ?></h3>
                    <div class="balance-amount">$<?php echo number_format($balance->total_earned, 2); ?></div>
                </div>
                
                <div class="balance-card">
                    <h3><?php _e('Total Withdrawn', 'leadpayout-pro'); ?></h3>
                    <div class="balance-amount">$<?php echo number_format($balance->total_withdrawn, 2); ?></div>
                </div>
            </div>
            
            <div class="leadpayout-tabs">
                <button class="leadpayout-tab-button active" onclick="showEarningsTab('earnings')"><?php _e('Recent Earnings', 'leadpayout-pro'); ?></button>
                <button class="leadpayout-tab-button" onclick="showEarningsTab('withdrawals')"><?php _e('Withdrawal History', 'leadpayout-pro'); ?></button>
            </div>
            
            <div id="earnings-tab" class="leadpayout-tab-content active">
                <h3><?php _e('Recent Earnings', 'leadpayout-pro'); ?></h3>
                <?php if (empty($recent_earnings)): ?>
                    <p><?php _e('No earnings yet. Complete some tasks to start earning!', 'leadpayout-pro'); ?></p>
                <?php else: ?>
                    <div class="leadpayout-earnings-list">
                        <?php foreach ($recent_earnings as $earning): ?>
                            <div class="earning-item">
                                <div class="earning-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', $earning->type))); ?></div>
                                <div class="earning-amount">+$<?php echo number_format($earning->amount, 2); ?></div>
                                <div class="earning-date"><?php echo date('M j, Y H:i', strtotime($earning->created_at)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div id="withdrawals-tab" class="leadpayout-tab-content">
                <h3><?php _e('Withdrawal History', 'leadpayout-pro'); ?></h3>
                <?php if (empty($withdrawals)): ?>
                    <p><?php _e('No withdrawals yet.', 'leadpayout-pro'); ?></p>
                <?php else: ?>
                    <div class="leadpayout-withdrawals-list">
                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <div class="withdrawal-item">
                                <div class="withdrawal-amount">$<?php echo number_format($withdrawal->amount, 2); ?></div>
                                <div class="withdrawal-status status-<?php echo $withdrawal->status; ?>">
                                    <?php echo esc_html(ucfirst($withdrawal->status)); ?>
                                </div>
                                <div class="withdrawal-date"><?php echo date('M j, Y H:i', strtotime($withdrawal->created_at)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Withdrawal Modal -->
        <div id="withdrawal-modal" class="leadpayout-modal" style="display: none;">
            <div class="leadpayout-modal-content">
                <span class="leadpayout-close" onclick="closeWithdrawalModal()">&times;</span>
                <h3><?php _e('Request Withdrawal', 'leadpayout-pro'); ?></h3>
                
                <form id="withdrawal-form">
                    <div class="form-group">
                        <label for="withdrawal-amount"><?php _e('Amount to Withdraw:', 'leadpayout-pro'); ?></label>
                        <input type="number" id="withdrawal-amount" name="amount" min="5.00" max="<?php echo $balance->available_balance; ?>" step="0.01" required />
                        <small><?php printf(__('Available: $%s (Minimum: $5.00)', 'leadpayout-pro'), number_format($balance->available_balance, 2)); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="withdrawal-method"><?php _e('Withdrawal Method:', 'leadpayout-pro'); ?></label>
                        <select id="withdrawal-method" name="method" required>
                            <option value=""><?php _e('Select Method', 'leadpayout-pro'); ?></option>
                            <option value="stripe"><?php _e('Bank Transfer (Stripe)', 'leadpayout-pro'); ?></option>
                            <option value="manual"><?php _e('Manual Review', 'leadpayout-pro'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="leadpayout-btn leadpayout-btn-primary"><?php _e('Request Withdrawal', 'leadpayout-pro'); ?></button>
                        <button type="button" class="leadpayout-btn leadpayout-btn-secondary" onclick="closeWithdrawalModal()"><?php _e('Cancel', 'leadpayout-pro'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function showEarningsTab(tabName) {
            document.querySelectorAll('.leadpayout-tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.querySelectorAll('.leadpayout-tab-button').forEach(function(button) {
                button.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function requestWithdrawal() {
            document.getElementById('withdrawal-modal').style.display = 'block';
        }
        
        function closeWithdrawalModal() {
            document.getElementById('withdrawal-modal').style.display = 'none';
            document.getElementById('withdrawal-form').reset();
        }
        
        document.getElementById('withdrawal-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'leadpayout_request_withdrawal');
            formData.append('nonce', leadpayout_ajax.nonce);
            
            fetch(leadpayout_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('<?php _e('Withdrawal requested successfully!', 'leadpayout-pro'); ?>');
                    closeWithdrawalModal();
                    location.reload();
                } else {
                    alert(data.data || '<?php _e('Error requesting withdrawal.', 'leadpayout-pro'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?php _e('Error requesting withdrawal.', 'leadpayout-pro'); ?>');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function referrals_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="leadpayout-login-required">' . 
                   __('Please log in to view your referrals.', 'leadpayout-pro') . 
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login', 'leadpayout-pro') . '</a>' .
                   '</div>';
        }
        
        $user_id = get_current_user_id();
        $stats = (object) array('total_referrals' => 0, 'total_commission_earned' => 0, 'commission_this_month' => 0);
        $referrals = array();
        $referral_code = 'N/A';
        $referral_link = home_url();
        
        if (class_exists('LeadPayout_Referrals')) {
            $stats = LeadPayout_Referrals::get_referral_stats($user_id);
            $referrals = LeadPayout_Referrals::get_user_referrals($user_id);
            $referral_code = LeadPayout_Referrals::get_user_referral_code($user_id);
            $referral_link = LeadPayout_Referrals::get_referral_link($user_id);
        }
        
        ob_start();
        ?>
        <div class="leadpayout-referrals-container">
            <div class="referral-stats">
                <div class="stat-card">
                    <h3><?php echo number_format($stats->total_referrals); ?></h3>
                    <p><?php _e('Total Referrals', 'leadpayout-pro'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>$<?php echo number_format($stats->total_commission_earned, 2); ?></h3>
                    <p><?php _e('Total Commission', 'leadpayout-pro'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>$<?php echo number_format($stats->commission_this_month, 2); ?></h3>
                    <p><?php _e('This Month', 'leadpayout-pro'); ?></p>
                </div>
            </div>
            
            <div class="referral-tools">
                <h3><?php _e('Your Referral Tools', 'leadpayout-pro'); ?></h3>
                
                <div class="referral-code-section">
                    <label><?php _e('Your Referral Code:', 'leadpayout-pro'); ?></label>
                    <div class="code-display">
                        <input type="text" value="<?php echo esc_attr($referral_code); ?>" readonly />
                        <button onclick="copyToClipboard('<?php echo esc_js($referral_code); ?>')"><?php _e('Copy', 'leadpayout-pro'); ?></button>
                    </div>
                </div>
                
                <div class="referral-link-section">
                    <label><?php _e('Your Referral Link:', 'leadpayout-pro'); ?></label>
                    <div class="link-display">
                        <input type="text" value="<?php echo esc_attr($referral_link); ?>" readonly />
                        <button onclick="copyToClipboard('<?php echo esc_js($referral_link); ?>')"><?php _e('Copy', 'leadpayout-pro'); ?></button>
                    </div>
                </div>
                
                <div class="social-share">
                    <h4><?php _e('Share on Social Media:', 'leadpayout-pro'); ?></h4>
                    <div class="social-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="social-btn facebook">
                            <?php _e('Facebook', 'leadpayout-pro'); ?>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_link); ?>&text=<?php echo urlencode(__('Join me and start earning money online!', 'leadpayout-pro')); ?>" target="_blank" class="social-btn twitter">
                            <?php _e('Twitter', 'leadpayout-pro'); ?>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode(__('Join me and start earning money online! ', 'leadpayout-pro') . $referral_link); ?>" target="_blank" class="social-btn whatsapp">
                            <?php _e('WhatsApp', 'leadpayout-pro'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="referral-history">
                <h3><?php _e('Your Referrals', 'leadpayout-pro'); ?></h3>
                
                <?php if (empty($referrals)): ?>
                    <p><?php _e('You haven\'t referred anyone yet. Share your referral link to start earning commissions!', 'leadpayout-pro'); ?></p>
                <?php else: ?>
                    <div class="referrals-list">
                        <?php foreach ($referrals as $referral): ?>
                            <div class="referral-item">
                                <div class="referral-info">
                                    <strong><?php echo esc_html($referral->referred_name); ?></strong>
                                    <span class="referral-date"><?php echo date('M j, Y', strtotime($referral->created_at)); ?></span>
                                </div>
                                <div class="referral-earnings">
                                    <span class="commission-earned">$<?php echo number_format($referral->total_earned, 2); ?></span>
                                    <small><?php _e('Commission Earned', 'leadpayout-pro'); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('<?php _e('Copied to clipboard!', 'leadpayout-pro'); ?>');
            }, function(err) {
                console.error('Could not copy text: ', err);
                // Fallback for older browsers
                var textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('<?php _e('Copied to clipboard!', 'leadpayout-pro'); ?>');
                } catch (err) {
                    alert('<?php _e('Copy failed. Please copy manually.', 'leadpayout-pro'); ?>');
                }
                document.body.removeChild(textArea);
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function leaderboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'period' => 'week'
        ), $atts);
        
        $leaders = array();
        if (class_exists('LeadPayout_Database')) {
            $leaders = LeadPayout_Database::get_leaderboard($atts['limit'], $atts['period']);
        }
        
        ob_start();
        ?>
        <div class="leadpayout-leaderboard-container">
            <h3><?php printf(__('Top %d Earners - %s', 'leadpayout-pro'), $atts['limit'], ucfirst($atts['period'])); ?></h3>
            
            <?php if (empty($leaders)): ?>
                <p><?php _e('No data available yet.', 'leadpayout-pro'); ?></p>
            <?php else: ?>
                <div class="leaderboard-list">
                    <?php foreach ($leaders as $index => $leader): ?>
                        <div class="leaderboard-item rank-<?php echo $index + 1; ?>">
                            <div class="rank">#<?php echo $index + 1; ?></div>
                            <div class="user-info">
                                <strong><?php echo esc_html($leader->display_name); ?></strong>
                                <small><?php printf(__('%d tasks completed', 'leadpayout-pro'), $leader->tasks_completed); ?></small>
                            </div>
                            <div class="earnings">$<?php echo number_format($leader->total_earned, 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="leadpayout-login-required">' . 
                   __('Please log in to view your dashboard.', 'leadpayout-pro') . 
                   ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Login', 'leadpayout-pro') . '</a>' .
                   '</div>';
        }
        
        $user_id = get_current_user_id();
        $balance = (object) array('available_balance' => 0, 'total_earned' => 0);
        $referral_stats = (object) array('total_referrals' => 0);
        
        if (class_exists('LeadPayout_Database')) {
            $balance = LeadPayout_Database::get_user_balance($user_id);
        }
        if (class_exists('LeadPayout_Referrals')) {
            $referral_stats = LeadPayout_Referrals::get_referral_stats($user_id);
        }
        
        global $wpdb;
        $submissions_table = $wpdb->prefix . 'leadpayout_submissions';
        
        $task_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_tasks,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks
            FROM $submissions_table 
            WHERE user_id = %d
        ", $user_id));
        
        ob_start();
        ?>
        <div class="leadpayout-dashboard-container">
            <h2><?php _e('Welcome to Your Dashboard', 'leadpayout-pro'); ?></h2>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>$<?php echo number_format($balance->available_balance, 2); ?></h3>
                    <p><?php _e('Available Balance', 'leadpayout-pro'); ?></p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($task_stats->approved_tasks); ?></h3>
                    <p><?php _e('Tasks Completed', 'leadpayout-pro'); ?></p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($referral_stats->total_referrals); ?></h3>
                    <p><?php _e('Total Referrals', 'leadpayout-pro'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>$<?php echo number_format($balance->total_earned, 2); ?></h3>
                    <p><?php _e('Total Earned', 'leadpayout-pro'); ?></p>
                </div>
            </div>
            
            <div class="dashboard-quick-actions">
                <h3><?php _e('Quick Actions', 'leadpayout-pro'); ?></h3>
                <div class="action-buttons">
                    <a href="<?php echo get_permalink(get_page_by_path('my-tasks')); ?>" class="leadpayout-btn leadpayout-btn-primary">
                        <?php _e('View Available Tasks', 'leadpayout-pro'); ?>
                    </a>
                    <a href="<?php echo get_permalink(get_page_by_path('my-earnings')); ?>" class="leadpayout-btn leadpayout-btn-secondary">
                        <?php _e('Check Earnings', 'leadpayout-pro'); ?>
                    </a>
                    <a href="<?php echo get_permalink(get_page_by_path('refer-earn')); ?>" class="leadpayout-btn leadpayout-btn-secondary">
                        <?php _e('Refer & Earn', 'leadpayout-pro'); ?>
                    </a>
                </div>
            </div>
            
            <?php if ($task_stats->pending_tasks > 0): ?>
            <div class="dashboard-alerts">
                <div class="alert alert-info">
                    <?php printf(__('You have %d pending task submissions awaiting review.', 'leadpayout-pro'), $task_stats->pending_tasks); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}