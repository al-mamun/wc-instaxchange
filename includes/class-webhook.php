<?php
/**
 * InstaxChange Webhook Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Webhook
{

    private $gateway;

    public function __construct()
    {
        add_action('woocommerce_api_wc_instaxchange_gateway', array($this, 'handle_webhook'));
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook()
    {
        $raw_body = file_get_contents('php://input');
        $headers = getallheaders();

        // Log webhook reception
        error_log('=== InstaxChange Webhook Received ===');
        error_log('Raw Body: ' . $raw_body);
        error_log('Headers: ' . json_encode($headers));
        error_log('HTTP Method: ' . $_SERVER['REQUEST_METHOD']);

        // Validate HTTP method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log('InstaxChange Webhook: Invalid HTTP method - ' . $_SERVER['REQUEST_METHOD']);
            wp_die('Method not allowed', 'Webhook Error', array('response' => 405));
        }

        // Parse JSON data
        $data = json_decode($raw_body, true);

        if (!$data) {
            error_log('InstaxChange Webhook: Invalid JSON data');
            error_log('JSON Error: ' . json_last_error_msg());
            wp_die('Invalid webhook data', 'Webhook Error', array('response' => 400));
        }

        // Get gateway instance
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways['instaxchange']) ? $gateways['instaxchange'] : null;

        if (!$this->gateway) {
            error_log('InstaxChange Webhook: Gateway not found');
            wp_die('Gateway not found', 'Webhook Error', array('response' => 500));
        }

        // Verify webhook signature
        $signature = $headers['X-Instaxwh-Key'] ?? $_SERVER['HTTP_X_INSTAXWH_KEY'] ?? '';

        if (!$this->verify_signature($data, $signature)) {
            error_log('InstaxChange Webhook: Signature verification failed');
            wp_die('Invalid signature', 'Webhook Error', array('response' => 401));
        }

        // Process webhook
        try {
            $this->process_webhook($data);

            // Log successful processing
            error_log('InstaxChange Webhook: Successfully processed');

            // Return success response
            wp_die('OK', 'Webhook Processed', array('response' => 200));

        } catch (Exception $e) {
            error_log('InstaxChange Webhook: Processing error - ' . $e->getMessage());
            wp_die('Processing error', 'Webhook Error', array('response' => 500));
        }
    }

    /**
     * Verify webhook signature
     */
    private function verify_signature($payload, $signature)
    {
        $webhook_secret = $this->gateway->get_option('webhook_secret');

        if (empty($webhook_secret)) {
            error_log('InstaxChange Webhook: No webhook secret configured - skipping verification');
            return true; // Allow webhook if no secret is set (for testing)
        }

        if (empty($signature)) {
            error_log('InstaxChange Webhook: No signature provided');
            return false;
        }

        // Create expected signature
        ksort($payload);
        $string_to_hash = json_encode($payload, JSON_UNESCAPED_SLASHES) . ':' . $webhook_secret;
        $expected_signature = md5($string_to_hash);

        $is_valid = hash_equals($expected_signature, $signature);

        if (!$is_valid) {
            error_log('InstaxChange Webhook: Signature mismatch');
            error_log('Expected: ' . $expected_signature);
            error_log('Received: ' . $signature);
            error_log('String to hash: ' . $string_to_hash);
        } else {
            error_log('InstaxChange Webhook: Signature verified successfully');
        }

        return $is_valid;
    }

    /**
     * Process webhook data
     */
    private function process_webhook($data)
    {
        error_log('InstaxChange Webhook: Processing webhook data');
        error_log('Webhook Data: ' . json_encode($data, JSON_PRETTY_PRINT));

        // Extract webhook reference
        $webhook_ref = $data['reference'] ?? '';

        if (empty($webhook_ref)) {
            error_log('InstaxChange Webhook: No reference provided in webhook');
            throw new Exception('Missing webhook reference');
        }

        error_log('InstaxChange Webhook: Processing reference: ' . $webhook_ref);

        // Parse the webhook reference to extract order ID
        // Expected format: order_{ORDER_ID}_{TIMESTAMP}_{RANDOM}
        if (strpos($webhook_ref, 'order_') !== 0) {
            error_log('InstaxChange Webhook: Invalid reference format: ' . $webhook_ref);
            throw new Exception('Invalid webhook reference format');
        }

        // Extract order ID from reference
        $parts = explode('_', $webhook_ref);
        if (count($parts) < 2) {
            error_log('InstaxChange Webhook: Could not parse order ID from reference: ' . $webhook_ref);
            throw new Exception('Cannot parse order ID from webhook reference');
        }

        $order_id = intval($parts[1]);

        if ($order_id <= 0) {
            error_log('InstaxChange Webhook: Invalid order ID extracted: ' . $order_id);
            throw new Exception('Invalid order ID');
        }

        error_log('InstaxChange Webhook: Extracted Order ID: ' . $order_id);

        // Get the order
        $order = wc_get_order($order_id);

        if (!$order) {
            error_log('InstaxChange Webhook: Order not found - ' . $order_id);
            throw new Exception('Order not found: ' . $order_id);
        }

        // Verify order payment method
        if ($order->get_payment_method() !== 'instaxchange') {
            error_log('InstaxChange Webhook: Order payment method mismatch. Expected: instaxchange, Got: ' . $order->get_payment_method());
            throw new Exception('Order payment method mismatch');
        }

        // Verify webhook reference belongs to this order (if stored)
        $stored_webhook_ref = $order->get_meta('_instaxchange_webhook_ref');
        if ($stored_webhook_ref && $stored_webhook_ref !== $webhook_ref) {
            error_log('InstaxChange Webhook: Reference mismatch. Expected: ' . $stored_webhook_ref . ', Got: ' . $webhook_ref);
            // Continue processing for backward compatibility, but log the mismatch
        }

        // Extract payment status information
        $status = $data['data']['status'] ?? '';
        $deposit_status = $data['invoiceData']['Deposit_tx_status'] ?? '';
        $transaction_id = $data['transactionId'] ?? '';

        error_log('InstaxChange Webhook: Status=' . $status . ', Deposit_status=' . $deposit_status . ', TransactionID=' . $transaction_id);

        // Log webhook data to database
        $this->log_transaction($order_id, $data);

        // Process based on status
        if ($status === 'completed' && $deposit_status === 'completed') {
            $this->process_successful_payment($order, $data);
        } elseif ($status === 'failed') {
            $this->process_failed_payment($order, $data);
        } elseif ($status === 'cancelled') {
            $this->process_cancelled_payment($order, $data);
        } else {
            error_log('InstaxChange Webhook: Unhandled status - ' . $status);
            // Still log the transaction for debugging
        }
    }

    /**
     * Process successful payment
     */
    private function process_successful_payment($order, $data)
    {
        error_log('InstaxChange Webhook: Processing successful payment for order ' . $order->get_id());

        // Check if order is already paid
        if ($order->is_paid()) {
            error_log('InstaxChange Webhook: Order already paid - ' . $order->get_id());
            return; // Already processed
        }

        $transaction_id = $data['transactionId'] ?? '';

        if (empty($transaction_id)) {
            error_log('InstaxChange Webhook: No transaction ID provided for successful payment');
            $transaction_id = 'instax_' . time();
        }

        // Complete payment
        $order->payment_complete($transaction_id);

        // Add order note with payment method info
        $payment_method_used = $data['data']['paymentMethod'] ?? 'Unknown';
        $order->add_order_note(sprintf(
            __('✅ InstaxChange payment completed successfully via %s. Transaction ID: %s', 'wc-instaxchange'),
            $payment_method_used,
            $transaction_id
        ));

        // Store transaction details
        $order->update_meta_data('_instaxchange_transaction_id', $transaction_id);

        // Store payment method used
        if (isset($data['data']['paymentMethod'])) {
            $order->update_meta_data('_instaxchange_payment_method_used', $data['data']['paymentMethod']);
        }

        // Store blockchain transaction IDs if available
        if (isset($data['invoiceData']['Deposit_tx_ID'])) {
            $order->update_meta_data('_instaxchange_deposit_tx_id', $data['invoiceData']['Deposit_tx_ID']);
        }

        if (isset($data['invoiceData']['Withdraw_tx_ID'])) {
            $order->update_meta_data('_instaxchange_withdraw_tx_id', $data['invoiceData']['Withdraw_tx_ID']);
        }

        // Store crypto amount and currency
        if (isset($data['data']['amountInCrypto'])) {
            $order->update_meta_data('_instaxchange_crypto_amount', $data['data']['amountInCrypto']);
        }

        if (isset($data['data']['cryptoCurrency'])) {
            $order->update_meta_data('_instaxchange_crypto_currency', $data['data']['cryptoCurrency']);
        }

        // Store payment completion timestamp
        $order->update_meta_data('_instaxchange_payment_completed_at', current_time('mysql'));

        $order->save();

        error_log('InstaxChange Webhook: Payment completed successfully for order ' . $order->get_id() . ' via ' . $payment_method_used);

        // Trigger action for other plugins to hook into
        do_action('woocommerce_instaxchange_payment_completed', $order, $data);
    }

    /**
     * Process failed payment
     */
    private function process_failed_payment($order, $data)
    {
        $reason = $data['data']['statusReason'] ?? 'Unknown reason';
        $payment_method_attempted = $data['data']['paymentMethod'] ?? 'Unknown';

        error_log('InstaxChange Webhook: Processing failed payment for order ' . $order->get_id() . ' - Method: ' . $payment_method_attempted . ', Reason: ' . $reason);

        // Update order status
        $order->update_status('failed', sprintf(
            __('❌ InstaxChange payment failed via %s: %s', 'wc-instaxchange'),
            $payment_method_attempted,
            $reason
        ));

        // Store failure details
        $order->update_meta_data('_instaxchange_failure_reason', $reason);
        $order->update_meta_data('_instaxchange_payment_method_attempted', $payment_method_attempted);
        $order->update_meta_data('_instaxchange_payment_failed_at', current_time('mysql'));
        $order->save();

        error_log('InstaxChange Webhook: Payment failed for order ' . $order->get_id() . ' - ' . $reason);

        // Trigger action for other plugins to hook into
        do_action('woocommerce_instaxchange_payment_failed', $order, $data);
    }

    /**
     * Process cancelled payment
     */
    private function process_cancelled_payment($order, $data)
    {
        $reason = $data['data']['statusReason'] ?? 'Payment cancelled by user';
        $payment_method_attempted = $data['data']['paymentMethod'] ?? 'Unknown';

        error_log('InstaxChange Webhook: Processing cancelled payment for order ' . $order->get_id() . ' - Method: ' . $payment_method_attempted . ', Reason: ' . $reason);

        // Update order status
        $order->update_status('cancelled', sprintf(
            __('⚠️ InstaxChange payment cancelled via %s: %s', 'wc-instaxchange'),
            $payment_method_attempted,
            $reason
        ));

        // Store cancellation details
        $order->update_meta_data('_instaxchange_cancellation_reason', $reason);
        $order->update_meta_data('_instaxchange_payment_method_attempted', $payment_method_attempted);
        $order->update_meta_data('_instaxchange_payment_cancelled_at', current_time('mysql'));
        $order->save();

        error_log('InstaxChange Webhook: Payment cancelled for order ' . $order->get_id() . ' - ' . $reason);

        // Trigger action for other plugins to hook into
        do_action('woocommerce_instaxchange_payment_cancelled', $order, $data);
    }

    /**
     * Log transaction to database
     */
    private function log_transaction($order_id, $webhook_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'instaxchange_transactions';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            error_log('InstaxChange Webhook: Transaction table does not exist');
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'session_id' => $webhook_data['data']['sessionId'] ?? '',
                'transaction_id' => $webhook_data['transactionId'] ?? '',
                'status' => $webhook_data['data']['status'] ?? '',
                'webhook_data' => json_encode($webhook_data)
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log('InstaxChange Webhook: Failed to log transaction to database');
            error_log('Database error: ' . $wpdb->last_error);
            return false;
        } else {
            error_log('InstaxChange Webhook: Transaction logged to database with ID ' . $wpdb->insert_id);
            return true;
        }
    }

    /**
     * Get transaction history for an order
     */
    public static function get_order_transactions($order_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'instaxchange_transactions';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE order_id = %d ORDER BY created_at DESC",
                $order_id
            )
        );

        return $results;
    }

    /**
     * Clean up old transaction logs (older than 90 days)
     */
    public static function cleanup_old_transactions()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'instaxchange_transactions';

        $result = $wpdb->query(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        if ($result !== false) {
            error_log('InstaxChange: Cleaned up ' . $result . ' old transaction records');
        }

        return $result;
    }
}

new WC_InstaxChange_Webhook();

// Schedule cleanup of old transactions
if (!wp_next_scheduled('instaxchange_cleanup_transactions')) {
    wp_schedule_event(time(), 'weekly', 'instaxchange_cleanup_transactions');
}

add_action('instaxchange_cleanup_transactions', array('WC_InstaxChange_Webhook', 'cleanup_old_transactions'));

?>