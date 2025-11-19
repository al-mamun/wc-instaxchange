<?php
/**
 * InstaxChange Webhook Handler Class
 *
 * Handles webhook processing and validation for payment notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Webhook_Handler
{

    /**
     * Initialize webhook functionality
     */
    public static function init()
    {
        add_action('woocommerce_api_instaxchange', array(__CLASS__, 'handle_webhook'));
    }

    /**
     * WC API handler for InstaxChange webhook
     */
    public static function handle_webhook()
    {
        // Get the gateway instance
        $gateway = WC()->payment_gateways()->payment_gateways()['instaxchange'];
        $webhook_secret = $gateway->webhook_secret;

        // Get the request body
        $request_body = file_get_contents('php://input');
        $headers = getallheaders();

        // Log webhook request for debugging
        wc_instaxchange_debug_log('Webhook received', [
            'has_signature' => isset($headers['X-Instaxchange-Signature']),
            'secret_configured' => !empty($webhook_secret)
        ]);

        // In production mode, webhook secret is REQUIRED
        if (defined('WC_INSTAXCHANGE_PRODUCTION') && WC_INSTAXCHANGE_PRODUCTION) {
            if (empty($webhook_secret)) {
                wc_instaxchange_log('CRITICAL: Webhook secret not configured in production mode', null, 'error');
                status_header(503);
                echo json_encode(['error' => 'Webhook not configured']);
                exit;
            }

            if (!isset($headers['X-Instaxchange-Signature'])) {
                wc_instaxchange_log('Webhook rejected: Missing signature in production mode', null, 'warning');
                status_header(401);
                echo json_encode(['error' => 'Signature required']);
                exit;
            }
        }

        // Verify webhook signature if secret is configured
        if (!empty($webhook_secret)) {
            if (!isset($headers['X-Instaxchange-Signature'])) {
                wc_instaxchange_log('Webhook rejected: Missing signature header', null, 'warning');
                status_header(401);
                echo json_encode(['error' => 'Signature required']);
                exit;
            }

            $signature = $headers['X-Instaxchange-Signature'];
            $expected_signature = hash_hmac('sha256', $request_body, $webhook_secret);

            if (!hash_equals($expected_signature, $signature)) {
                wc_instaxchange_log('Webhook signature verification failed', [
                    'received' => substr($signature, 0, 10) . '...',
                    'expected' => substr($expected_signature, 0, 10) . '...'
                ], 'error');
                status_header(401);
                echo json_encode(['error' => 'Invalid signature']);
                exit;
            }

            wc_instaxchange_debug_log('Webhook signature verified successfully');
        } else {
            // Webhook secret not configured - only allow in development
            wc_instaxchange_log('WARNING: Webhook received without signature verification (development mode only)', null, 'warning');
        }

        // Parse the webhook payload
        $payload = json_decode($request_body, true);

        if (empty($payload) || !isset($payload['order_id'])) {
            wc_instaxchange_debug_log('Invalid webhook payload');
            status_header(400);
            echo json_encode(['error' => 'Invalid payload']);
            exit;
        }

        // Process the webhook
        $order_id = sanitize_text_field($payload['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_instaxchange_debug_log('Order not found', $order_id);
            status_header(404);
            echo json_encode(['error' => 'Order not found']);
            exit;
        }

        // Update order status based on payment status
        $payment_status = isset($payload['status']) ? sanitize_text_field($payload['status']) : '';
        $transaction_id = isset($payload['transaction_id']) ? sanitize_text_field($payload['transaction_id']) : '';

        if ($payment_status === 'completed' || $payment_status === 'success') {
            self::handle_payment_completed($order, $transaction_id);
        } elseif ($payment_status === 'failed') {
            self::handle_payment_failed($order);
        } else {
            self::handle_payment_unknown($order, $payment_status);
        }

        exit;
    }

    /**
     * Handle completed payment
     */
    private static function handle_payment_completed($order, $transaction_id)
    {
        // Save transaction ID if provided
        if (!empty($transaction_id)) {
            $order->set_transaction_id($transaction_id);
        }

        // Update order status - use 'completed' for digital/virtual products, 'processing' for physical
        $order->update_meta_data('_instaxchange_payment_status', 'completed');

        // Check if order contains only virtual/digital products
        $has_physical_products = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && !$product->is_virtual() && !$product->is_downloadable()) {
                $has_physical_products = true;
                break;
            }
        }

        // Set appropriate order status based on product type
        if ($has_physical_products) {
            // Physical products need processing/shipping
            $order->update_status('processing', 'Payment completed via InstaxChange webhook - awaiting fulfillment');
        } else {
            // Digital/virtual products can be completed immediately
            $order->update_status('completed', 'Payment completed via InstaxChange webhook - order fulfilled automatically');
        }

        $order->save();

        wc_instaxchange_debug_log('Order marked as paid', [
            'order_id' => $order->get_id(),
            'has_physical_products' => $has_physical_products,
            'final_status' => $order->get_status()
        ]);

        echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
    }

    /**
     * Handle failed payment
     */
    private static function handle_payment_failed($order)
    {
        $order->update_meta_data('_instaxchange_payment_status', 'failed');
        $order->update_status('failed', 'Payment failed via InstaxChange webhook');
        $order->save();

        wc_instaxchange_debug_log('Order payment failed', $order->get_id());
        echo json_encode(['success' => true, 'message' => 'Payment failure recorded']);
    }

    /**
     * Handle unknown payment status
     */
    private static function handle_payment_unknown($order, $payment_status)
    {
        // Unknown status
        $order->update_meta_data('_instaxchange_payment_status', $payment_status);
        $order->add_order_note('InstaxChange webhook received with status: ' . $payment_status);
        $order->save();

        wc_instaxchange_debug_log('Order updated with status', ['order_id' => $order->get_id(), 'status' => $payment_status]);
        echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    }
}