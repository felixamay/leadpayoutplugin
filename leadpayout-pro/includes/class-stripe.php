<?php
/**
 * Stripe integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadPayout_Stripe {
    
    private static $instance = null;
    private $stripe_secret_key;
    private $stripe_public_key;
    private $is_test_mode;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->is_test_mode = get_option('leadpayout_stripe_mode', 'test') === 'test';
        $this->stripe_secret_key = get_option('leadpayout_stripe_secret_key', '');
        $this->stripe_public_key = get_option('leadpayout_stripe_public_key', '');
        
        add_action('wp_ajax_leadpayout_request_withdrawal', array($this, 'request_withdrawal'));
        add_action('wp_ajax_leadpayout_fund_account', array($this, 'fund_account'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_stripe_scripts'));
    }
    
    public function enqueue_stripe_scripts() {
        if (!empty($this->stripe_public_key)) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
            
            wp_localize_script('leadpayout-frontend', 'leadpayout_stripe', array(
                'public_key' => $this->stripe_public_key,
                'test_mode' => $this->is_test_mode
            ));
        }
    }
    
    public function request_withdrawal() {
        check_ajax_referer('leadpayout_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'leadpayout-pro'));
        }
        
        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        
        // Validate amount
        if ($amount < 5.00) {
            wp_send_json_error(__('Minimum withdrawal amount is $5.00.', 'leadpayout-pro'));
        }
        
        // Check user balance
        $balance = (object) array('available_balance' => 0);
        if (class_exists('LeadPayout_Database')) {
            $balance = LeadPayout_Database::get_user_balance($user_id);
        }
        if ($amount > $balance->available_balance) {
            wp_send_json_error(__('Insufficient balance.', 'leadpayout-pro'));
        }
        
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
        
        // Create withdrawal request
        $result = $wpdb->insert($transactions_table, array(
            'user_id' => $user_id,
            'amount' => $amount,
            'type' => 'withdrawal',
            'status' => 'pending',
            'description' => 'Withdrawal request via ' . $method
        ));
        
        if ($result) {
            // Update user balance (subtract from available, add to pending withdrawal)
            if (class_exists('LeadPayout_Database')) {
                LeadPayout_Database::update_user_balance($user_id, $amount, 'subtract');
            }
            
            // Send notification to admin
            if (class_exists('LeadPayout_Emails')) {
                LeadPayout_Emails::send_withdrawal_notification($user_id, $amount, $method);
            }
            
            wp_send_json_success(__('Withdrawal request submitted successfully.', 'leadpayout-pro'));
        } else {
            wp_send_json_error(__('Error processing withdrawal request.', 'leadpayout-pro'));
        }
    }
    
    public function fund_account() {
        check_ajax_referer('leadpayout_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'leadpayout-pro'));
        }
        
        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount']);
        $payment_method_id = sanitize_text_field($_POST['payment_method_id']);
        
        if (empty($this->stripe_secret_key)) {
            wp_send_json_error(__('Stripe is not configured.', 'leadpayout-pro'));
        }
        
        try {
            // Initialize Stripe (in a real implementation, you'd use the Stripe PHP library)
            $stripe_data = array(
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'payment_method' => $payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => array(
                    'user_id' => $user_id,
                    'type' => 'account_funding'
                )
            );
            
            // This is a simplified version - in production, use official Stripe PHP SDK
            $payment_intent = $this->create_stripe_payment_intent($stripe_data);
            
            if ($payment_intent && $payment_intent['status'] === 'succeeded') {
                global $wpdb;
                $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
                
                // Record successful payment
                $wpdb->insert($transactions_table, array(
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'type' => 'deposit',
                    'status' => 'completed',
                    'stripe_payment_id' => $payment_intent['id'],
                    'description' => 'Account funding via Stripe'
                ));
                
                // Update user balance
                if (class_exists('LeadPayout_Database')) {
                    LeadPayout_Database::update_user_balance($user_id, $amount, 'add');
                }
                
                wp_send_json_success(array(
                    'message' => __('Payment successful!', 'leadpayout-pro'),
                    'payment_intent' => $payment_intent
                ));
            } else {
                wp_send_json_error(__('Payment failed.', 'leadpayout-pro'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public static function process_withdrawal($withdrawal_id) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'leadpayout_transactions';
        
        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $transactions_table WHERE id = %d AND type = 'withdrawal' AND status = 'pending'",
            $withdrawal_id
        ));
        
        if (!$withdrawal) {
            return false;
        }
        
        $instance = self::get_instance();
        
        try {
            // In a real implementation, process via Stripe Connect or bank transfer
            $transfer_result = $instance->process_stripe_transfer($withdrawal);
            
            if ($transfer_result) {
                // Update withdrawal status
                $wpdb->update($transactions_table, array(
                    'status' => 'completed',
                    'processed_at' => current_time('mysql'),
                    'stripe_transfer_id' => $transfer_result['id']
                ), array('id' => $withdrawal_id));
                
                // Send confirmation email
                if (class_exists('LeadPayout_Emails')) {
                    LeadPayout_Emails::send_withdrawal_confirmation($withdrawal->user_id, $withdrawal->amount);
                }
                
                return true;
            } else {
                // Mark as failed
                $wpdb->update($transactions_table, array(
                    'status' => 'failed',
                    'processed_at' => current_time('mysql')
                ), array('id' => $withdrawal_id));
                
                // Refund balance
                if (class_exists('LeadPayout_Database')) {
                    LeadPayout_Database::update_user_balance($withdrawal->user_id, $withdrawal->amount, 'add');
                }
                
                return false;
            }
            
        } catch (Exception $e) {
            error_log('LeadPayout Stripe Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function create_stripe_payment_intent($data) {
        // Simplified Stripe API call - in production use official Stripe PHP SDK
        $url = 'https://api.stripe.com/v1/payment_intents';
        
        $headers = array(
            'Authorization: Bearer ' . $this->stripe_secret_key,
            'Content-Type: application/x-www-form-urlencoded'
        );
        
        $post_data = http_build_query($data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    private function process_stripe_transfer($withdrawal) {
        // Simplified Stripe transfer - in production implement Stripe Connect
        // This would typically involve:
        // 1. Get user's connected Stripe account
        // 2. Create transfer to their account
        // 3. Handle any errors or requirements
        
        $url = 'https://api.stripe.com/v1/transfers';
        
        $data = array(
            'amount' => $withdrawal->amount * 100, // Convert to cents
            'currency' => 'usd',
            'destination' => $this->get_user_stripe_account($withdrawal->user_id),
            'metadata' => array(
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id
            )
        );
        
        $headers = array(
            'Authorization: Bearer ' . $this->stripe_secret_key,
            'Content-Type: application/x-www-form-urlencoded'
        );
        
        $post_data = http_build_query($data);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    private function get_user_stripe_account($user_id) {
        // Get user's connected Stripe account ID
        // In production, this would be stored when user connects their Stripe account
        return get_user_meta($user_id, 'stripe_account_id', true);
    }
    
    public function get_payment_form_html() {
        if (empty($this->stripe_public_key)) {
            return '<p>' . __('Payment processing is not available at this time.', 'leadpayout-pro') . '</p>';
        }
        
        ob_start();
        ?>
        <div id="stripe-payment-form" style="display: none;">
            <h3><?php _e('Fund Your Account', 'leadpayout-pro'); ?></h3>
            
            <form id="payment-form">
                <div class="form-group">
                    <label for="amount"><?php _e('Amount to Add:', 'leadpayout-pro'); ?></label>
                    <input type="number" id="amount" name="amount" min="5.00" step="0.01" required />
                </div>
                
                <div class="form-group">
                    <label for="card-element"><?php _e('Credit or Debit Card:', 'leadpayout-pro'); ?></label>
                    <div id="card-element">
                        <!-- Stripe Elements will create form elements here -->
                    </div>
                    <div id="card-errors" role="alert"></div>
                </div>
                
                <button type="submit" id="submit-payment" class="leadpayout-btn leadpayout-btn-primary">
                    <?php _e('Add Funds', 'leadpayout-pro'); ?>
                </button>
            </form>
        </div>
        
        <script>
        if (typeof Stripe !== 'undefined' && leadpayout_stripe.public_key) {
            var stripe = Stripe(leadpayout_stripe.public_key);
            var elements = stripe.elements();
            
            var cardElement = elements.create('card');
            cardElement.mount('#card-element');
            
            cardElement.on('change', function(event) {
                var displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
            
            var form = document.getElementById('payment-form');
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                
                stripe.createPaymentMethod({
                    type: 'card',
                    card: cardElement,
                }).then(function(result) {
                    if (result.error) {
                        document.getElementById('card-errors').textContent = result.error.message;
                    } else {
                        var formData = new FormData();
                        formData.append('action', 'leadpayout_fund_account');
                        formData.append('nonce', leadpayout_ajax.nonce);
                        formData.append('amount', document.getElementById('amount').value);
                        formData.append('payment_method_id', result.paymentMethod.id);
                        
                        fetch(leadpayout_ajax.ajax_url, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('<?php _e('Payment successful!', 'leadpayout-pro'); ?>');
                                location.reload();
                            } else {
                                alert(data.data || '<?php _e('Payment failed.', 'leadpayout-pro'); ?>');
                            }
                        });
                    }
                });
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    public static function get_stripe_connect_url($user_id) {
        // Generate Stripe Connect onboarding URL
        // This would be used to connect user's bank account for withdrawals
        $return_url = home_url('/my-earnings/?stripe_connect=success');
        $refresh_url = home_url('/my-earnings/?stripe_connect=refresh');
        
        // In production, create actual Stripe Connect account link
        return "https://connect.stripe.com/oauth/authorize?response_type=code&client_id=YOUR_CLIENT_ID&scope=read_write&redirect_uri=" . urlencode($return_url);
    }
    
    public function validate_stripe_settings() {
        $errors = array();
        
        if (empty($this->stripe_secret_key)) {
            $errors[] = __('Stripe Secret Key is required.', 'leadpayout-pro');
        }
        
        if (empty($this->stripe_public_key)) {
            $errors[] = __('Stripe Public Key is required.', 'leadpayout-pro');
        }
        
        // Validate key format
        if (!empty($this->stripe_secret_key) && !preg_match('/^sk_' . ($this->is_test_mode ? 'test' : 'live') . '_/', $this->stripe_secret_key)) {
            $errors[] = __('Invalid Stripe Secret Key format.', 'leadpayout-pro');
        }
        
        if (!empty($this->stripe_public_key) && !preg_match('/^pk_' . ($this->is_test_mode ? 'test' : 'live') . '_/', $this->stripe_public_key)) {
            $errors[] = __('Invalid Stripe Public Key format.', 'leadpayout-pro');
        }
        
        return $errors;
    }
}