<?php
/**
 * InstaxChange AJAX Handlers Class
 *
 * Handles all AJAX requests for payment processing and status checking
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Ajax_Handlers
{

    /**
     * Initialize AJAX handlers
     */
    public static function init()
    {
        // Public AJAX handlers (for logged-in users)
        add_action('wp_ajax_check_instaxchange_status', array(__CLASS__, 'check_payment_status'));
        add_action('wp_ajax_create_instaxchange_session', array(__CLASS__, 'create_payment_session'));

        // Public AJAX handlers (for non-logged-in users)
        add_action('wp_ajax_nopriv_check_instaxchange_status', array(__CLASS__, 'check_payment_status_nopriv'));
    }

    /**
     * Check rate limit for AJAX requests
     *
     * @param string $action The action being rate limited
     * @param int $limit Maximum number of requests allowed
     * @param int $window Time window in seconds
     * @return bool True if rate limit exceeded, false otherwise
     */
    private static function check_rate_limit($action, $limit = 10, $window = 60)
    {
        // Get user identifier (IP + user ID for better tracking)
        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = $user_id ? 'user_' . $user_id : 'ip_' . md5($ip_address);

        $transient_key = "instaxchange_rl_{$action}_{$identifier}";
        $attempts = get_transient($transient_key);

        if ($attempts === false) {
            // First request in this window
            set_transient($transient_key, 1, $window);
            return false;
        }

        if ($attempts >= $limit) {
            // Rate limit exceeded
            wc_instaxchange_log("Rate limit exceeded for {$action}", [
                'identifier' => $identifier,
                'attempts' => $attempts,
                'limit' => $limit
            ], 'warning');
            return true;
        }

        // Increment counter
        set_transient($transient_key, $attempts + 1, $window);
        return false;
    }

    /**
     * AJAX handler for creating payment sessions
     */
    public static function create_payment_session()
    {
        try {
            wc_instaxchange_debug_log('AJAX create session request received');

            // Check rate limit: 5 session creation attempts per minute
            if (self::check_rate_limit('create_session', 5, 60)) {
                wp_send_json_error('Too many requests. Please wait a moment and try again.');
                return;
            }

            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'instaxchange_create_session')) {
                wc_instaxchange_debug_log('Nonce verification failed');
                wp_send_json_error('Security check failed');
                return;
            }

            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'card';
            $cryptocurrency = isset($_POST['cryptocurrency']) ? sanitize_text_field($_POST['cryptocurrency']) : '';

            wc_instaxchange_debug_log('AJAX request', ['order_id' => $order_id, 'method' => $payment_method, 'crypto' => $cryptocurrency]);

            if (!$order_id) {
                wc_instaxchange_debug_log('Invalid order ID provided');
                wp_send_json_error('Invalid order ID');
                return;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                wc_instaxchange_debug_log('Order not found for ID', $order_id);
                wp_send_json_error('Order not found');
                return;
            }

            // Get gateway instance
            $gateway = WC()->payment_gateways()->payment_gateways()['instaxchange'];

            // Update default crypto if specified
            if (!empty($cryptocurrency)) {
                $gateway->default_crypto = $cryptocurrency;
            }

            // Create session
            wc_instaxchange_debug_log('Creating payment session for method', $payment_method);
            try {
                $payment_session = $gateway->create_payment_session($order, $payment_method);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Payment method not supported') !== false) {
                    wc_instaxchange_debug_log('Payment method not supported, falling back to card', $payment_method);
                    // Fallback to card payment
                    $payment_session = $gateway->create_payment_session($order, 'card');
                    $payment_session['fallback_method'] = 'card';
                    $payment_session['original_method'] = $payment_method;
                    $cryptocurrency = ''; // Clear crypto for fallback
                } else {
                    throw $e; // Re-throw other exceptions
                }
            }

            if ($payment_session && isset($payment_session['session_id'])) {
                wc_instaxchange_debug_log('Session created successfully', $payment_session['session_id']);

                // Update order meta
                $order->update_meta_data('_instaxchange_session_id_' . $payment_method, $payment_session['session_id']);
                $order->update_meta_data('_instaxchange_last_method', $payment_method);
                if (!empty($cryptocurrency)) {
                    $order->update_meta_data('_instaxchange_cryptocurrency', $cryptocurrency);
                }
                $order->save();

                wp_send_json_success(array(
                    'session_id' => $payment_session['session_id'],
                    'iframe_url' => $payment_session['iframe_url'],
                    'payment_method' => isset($payment_session['fallback_method']) ? $payment_session['fallback_method'] : $payment_method,
                    'original_method' => $payment_method,
                    'cryptocurrency' => $cryptocurrency,
                    'demo_mode' => isset($payment_session['demo_mode']) ? $payment_session['demo_mode'] : false,
                    'fallback_used' => isset($payment_session['fallback_method']) ? true : false
                ));
            } else {
                wc_instaxchange_debug_log('Failed to create session - no session data returned');
                wp_send_json_error('Failed to create session - no session data returned');
            }

        } catch (Exception $e) {
            wc_instaxchange_debug_log('AJAX create session error', $e->getMessage());
            wp_send_json_error('Session creation failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for checking payment status
     */
    public static function check_payment_status()
    {
        try {
            // Check rate limit: 20 status checks per minute (one every 3 seconds)
            if (self::check_rate_limit('check_status', 20, 60)) {
                wp_send_json_error('Too many status checks. Please wait a moment.');
                return;
            }

            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'instaxchange_check_status')) {
                wp_send_json_error('Security check failed');
                return;
            }

            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

            if (!$order_id) {
                wp_send_json_error('Invalid order ID');
                return;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error('Order not found');
                return;
            }

            self::process_payment_status_check($order);

        } catch (Exception $e) {
            wc_instaxchange_debug_log('AJAX status check error', $e->getMessage());
            wp_send_json_error('Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for non-logged-in users to check payment status
     */
    public static function check_payment_status_nopriv()
    {
        try {
            // Check rate limit for non-logged users (stricter): 15 status checks per minute
            if (self::check_rate_limit('check_status_nopriv', 15, 60)) {
                wp_send_json_error('Too many status checks. Please wait a moment.');
                return;
            }

            // For non-logged in users, we still need to verify the request
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'instaxchange_check_status')) {
                wp_send_json_error('Security check failed');
                return;
            }

            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            $order_key = isset($_POST['order_key']) ? sanitize_text_field($_POST['order_key']) : '';

            if (!$order_id || !$order_key) {
                wp_send_json_error('Invalid order information');
                return;
            }

            $order = wc_get_order($order_id);
            if (!$order || $order->get_order_key() !== $order_key) {
                wp_send_json_error('Order not found or invalid key');
                return;
            }

            self::process_payment_status_check($order, true);

        } catch (Exception $e) {
            wc_instaxchange_debug_log('AJAX status check error (non-logged-in)', $e->getMessage());
            wp_send_json_error('Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Process payment status check logic
     */
    private static function process_payment_status_check($order, $is_guest = false)
    {
        // Enhanced payment status checking
        $payment_status = $order->get_meta('_instaxchange_payment_status');
        $transaction_id = $order->get_transaction_id();
        $order_status = $order->get_status();

        wc_instaxchange_debug_log('AJAX status check', [
            'order' => $order->get_id(),
            'payment_status' => $payment_status,
            'order_status' => $order_status,
            'transaction_id' => $transaction_id
        ]);

        // Check if payment is completed
        if ($order->is_paid() || $payment_status === 'completed') {
            self::handle_completed_payment($order, $transaction_id, $order_status, $is_guest);
        } else {
            self::handle_pending_payment($payment_status, $transaction_id, $order_status, $is_guest);
        }
    }

    /**
     * Handle completed payment status
     */
    private static function handle_completed_payment($order, $transaction_id, $order_status, $is_guest)
    {
        // Ensure order status is properly set
        if ($order_status === 'cancelled') {
            $order->update_status('processing', 'Payment completed, updating order status from cancelled');
            $order_status = 'processing';
        }

        // Check if order contains only virtual/digital products for status update
        if ($order_status === 'pending') {
            $has_physical_products = false;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && !$product->is_virtual() && !$product->is_downloadable()) {
                    $has_physical_products = true;
                    break;
                }
            }

            // Update order status if it's stuck in pending
            if ($has_physical_products) {
                $order->update_status('processing', 'Payment confirmed - updating from pending status');
            } else {
                $order->update_status('completed', 'Payment confirmed - digital order completed automatically');
            }
            $order->save();
            $order_status = $order->get_status();

            wc_instaxchange_debug_log('Updated stuck pending order', [
                'order_id' => $order->get_id(),
                'new_status' => $order_status
            ]);
        }

        wp_send_json_success(array(
            'status' => 'completed',
            'status_text' => 'Payment Completed Successfully!',
            'transaction_id' => $transaction_id ?: 'Completed',
            'order_status' => $order_status
        ));
    }

    /**
     * Handle pending payment status
     */
    private static function handle_pending_payment($payment_status, $transaction_id, $order_status, $is_guest)
    {
        // Check for specific payment statuses
        if ($payment_status === 'failed') {
            wp_send_json_success(array(
                'status' => 'failed',
                'status_text' => 'Payment Failed',
                'transaction_id' => 'Failed',
                'order_status' => $order_status
            ));
        } elseif ($payment_status === 'pending') {
            wp_send_json_success(array(
                'status' => 'pending',
                'status_text' => 'Payment Pending',
                'transaction_id' => 'Pending',
                'order_status' => $order_status
            ));
        } else {
            // Return limited information for non-logged in users
            $status_text = $payment_status ? ucfirst($payment_status) : wc_get_order_status_name($order_status);

            wp_send_json_success(array(
                'status' => $payment_status ?: $order_status,
                'status_text' => $status_text,
                'transaction_id' => $transaction_id ?: 'Pending',
                'order_status' => $order_status
            ));
        }
    }
}