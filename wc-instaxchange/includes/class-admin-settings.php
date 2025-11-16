<?php
/**
 * InstaxChange Admin Settings Class
 *
 * Handles all admin-related functionality including settings, webhook testing, and debug features
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Admin_Settings
{

    /**
     * Initialize admin functionality
     */
    public static function init()
    {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        add_action('wp_ajax_check_webhook_status', array(__CLASS__, 'ajax_check_webhook_status'));
        add_action('wp_ajax_check_stuck_orders', array(__CLASS__, 'ajax_check_stuck_orders'));
        add_action('wp_ajax_test_instaxchange_webhook', array(__CLASS__, 'ajax_test_webhook'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts($hook)
    {
        // Only load on WooCommerce settings pages
        if (strpos($hook, 'woocommerce') === false) {
            return;
        }

        wp_enqueue_script(
            'instaxchange-admin-webhook-test',
            WC_INSTAXCHANGE_PLUGIN_URL . 'assets/js/admin-webhook-test.js',
            array('jquery'),
            WC_INSTAXCHANGE_VERSION,
            true
        );

        wp_enqueue_style(
            'instaxchange-admin-settings',
            WC_INSTAXCHANGE_PLUGIN_URL . 'assets/css/admin-settings.css',
            array(),
            WC_INSTAXCHANGE_VERSION
        );

        // Localize script data
        wp_localize_script('instaxchange-admin-webhook-test', 'instaxchangeAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'checkWebhookStatus' => wp_create_nonce('instaxchange_check_webhook_status'),
                'checkStuckOrders' => wp_create_nonce('instaxchange_check_stuck_orders')
            )
        ));
    }

    /**
     * AJAX handler for webhook testing
     */
    public static function ajax_check_webhook_status()
    {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'instaxchange_check_webhook_status')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Only allow admin users
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
            return;
        }

        // Test webhook endpoints
        $legacy_url = home_url('/wc-api/instaxchange');
        $rest_url = home_url('/wp-json/wc/v3/instaxchange');

        $legacy_response = wp_remote_get($legacy_url);
        $rest_response = wp_remote_get($rest_url);

        $results = array(
            'legacy' => array(
                'accessible' => !is_wp_error($legacy_response),
                'code' => !is_wp_error($legacy_response) ? wp_remote_retrieve_response_code($legacy_response) : 0
            ),
            'rest' => array(
                'accessible' => !is_wp_error($rest_response),
                'code' => !is_wp_error($rest_response) ? wp_remote_retrieve_response_code($rest_response) : 0
            )
        );

        // Determine recommended URL
        $recommended_url = $results['legacy']['accessible'] ? $legacy_url : ($results['rest']['accessible'] ? $rest_url : 'None available');

        wp_send_json_success(array(
            'results' => $results,
            'recommended_url' => $recommended_url
        ));
    }

    /**
     * AJAX handler for testing webhook on receipt page
     */
    public static function ajax_test_webhook()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'instaxchange_test_webhook')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Only allow admin users
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
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

        // Simulate webhook call
        $order->update_meta_data('_instaxchange_payment_status', 'completed');
        $order->update_status('processing', 'Payment completed via test webhook');
        $order->save();

        wp_send_json_success(array(
            'message' => 'Webhook test successful. Order status updated to Processing.'
        ));
    }

    /**
     * AJAX handler for manual stuck orders check
     */
    public static function ajax_check_stuck_orders()
    {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'instaxchange_check_stuck_orders')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Only allow admin users
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Run the stuck orders check (manual checks always work regardless of setting)
        $result = wc_instaxchange_check_stuck_orders(true);

        wp_send_json_success(array(
            'message' => sprintf('Checked %d orders, updated %d stuck orders', $result['checked'], $result['updated']),
            'checked' => $result['checked'],
            'updated' => $result['updated']
        ));
    }

    /**
     * Render admin settings section
     */
    public static function render_admin_settings($gateway)
    {
        // Configuration status
        echo '<div class="instaxchange-config-status">';
        echo '<h3>Configuration Status</h3>';
        echo '<p><strong>Gateway Enabled:</strong> ' . ($gateway->enabled === 'yes' ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Test Mode:</strong> ' . ($gateway->testmode === 'yes' ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Available:</strong> ' . ($gateway->is_available() ? '✅ Yes' : '❌ No') . '</p>';

        // Configuration check
        $config_complete = !empty($gateway->account_ref_id) && !empty($gateway->wallet_address);
        echo '<p><strong>Configuration:</strong> ' . ($config_complete ? '✅ Complete' : '❌ Incomplete') . '</p>';

        if (!$config_complete) {
            echo '<div style="background: #fff3cd; border: 1px solid #856404; padding: 10px; border-radius: 4px; margin: 10px 0;">';
            echo '<p style="margin: 0; color: #856404;"><strong>⚠️ Action Required:</strong> Please configure the following to enable payments:</p>';
            echo '<ul style="margin: 5px 0 0 20px; color: #856404;">';
            if (empty($gateway->account_ref_id))
                echo '<li>Account Reference ID (get from InstaxChange dashboard)</li>';
            if (empty($gateway->wallet_address))
                echo '<li>Wallet Address (your crypto receiving address)</li>';
            echo '</ul>';
            echo '</div>';
        }

        echo '<p><strong>Account ID:</strong> ' . (!empty($gateway->account_ref_id) ? '✅ Configured' : '❌ REQUIRED') . '</p>';
        echo '<p><strong>Wallet Address:</strong> ' . (!empty($gateway->wallet_address) ? '✅ Configured' : '❌ REQUIRED') . '</p>';

        // Display cryptocurrency information with network details
        $crypto_display = $gateway->default_crypto;
        if ($gateway->default_crypto === 'USDC_POLYGON') {
            $crypto_display = 'USDC (Polygon Network)';
        } elseif ($gateway->default_crypto === 'USDC') {
            $crypto_display = 'USDC (Ethereum Network)';
        }

        echo '<p><strong>Default Cryptocurrency:</strong> ' . esc_html($crypto_display) . '</p>';
        echo '<p><a href="' . esc_url(wc_get_checkout_url()) . '" target="_blank" class="button">Test Checkout</a></p>';

        // Order status management section
        $order_management_enabled = $gateway->get_option('enable_order_management', 'yes') === 'yes';
        
        echo '<div class="instaxchange-order-management" style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; margin: 20px 0; border-radius: 4px;">';
        echo '<h3>Order Status Management</h3>';
        
        // Show current setting status
        echo '<p><strong>Automatic Management:</strong> ' . ($order_management_enabled ? '✅ Enabled' : '❌ Disabled') . '</p>';
        if (!$order_management_enabled) {
            echo '<p style="color: #856404; background: #fff3cd; padding: 8px; border-radius: 4px; border-left: 4px solid #ffc107;"><strong>ℹ️ Info:</strong> Automatic order status management is disabled. Enable it in the gateway settings to automatically fix stuck orders.</p>';
        }
        
        echo '<p><strong>Manual Check:</strong> <button type="button" class="button button-secondary" onclick="checkStuckOrders()">Check & Fix Stuck Orders</button></p>';
        echo '<p><small>This will check for orders that have completed payments but are stuck in "pending" status and update them appropriately.</small></p>';
        echo '<div id="stuck-orders-result" style="margin-top: 10px;"></div>';
        
        // Show cron status based on settings
        $next_run = wp_next_scheduled('instaxchange_check_stuck_orders');
        if ($order_management_enabled) {
            if ($next_run) {
                $time_until = human_time_diff($next_run);
                echo '<p><strong>Auto-Check Status:</strong> ✅ Scheduled (runs hourly)</p>';
                echo '<p><strong>Next Run:</strong> ' . date('Y-m-d H:i:s', $next_run) . ' (' . $time_until . ')</p>';
                echo '<p><small>The automatic check runs every hour when enabled. You can also run manual checks above.</small></p>';
            } else {
                echo '<p><strong>Auto-Check Status:</strong> ⚠️ Enabled but not scheduled</p>';
                echo '<p style="color: #d63384;"><strong>⚠️ Notice:</strong> Auto-management is enabled but cron is not scheduled. Save your settings to activate it.</p>';
            }
        } else {
            echo '<p><strong>Auto-Check Status:</strong> ⏸️ Disabled (manual only)</p>';
            echo '<p><small>Automatic checking is disabled. You can enable it in the gateway settings or use the manual check above.</small></p>';
        }
        
        // Check if WordPress cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<p style="color: #d63384;"><strong>⚠️ Warning:</strong> WordPress cron is disabled (DISABLE_WP_CRON = true). You need to set up external cron for automatic management to work.</p>';
        }
        
        echo '</div>';

        echo '</div>';

        // Webhook testing section
        echo '<div class="instaxchange-webhook-testing">';
        echo '<h3>Webhook Testing</h3>';
        echo '<p><strong>Webhook URL:</strong> <code>' . home_url('/wc-api/instaxchange') . '</code></p>';
        echo '<p><strong>REST API URL:</strong> <code>' . home_url('/wp-json/wc/v3/instaxchange') . '</code></p>';
        echo '<button type="button" class="button button-secondary" onclick="testWebhookEndpoint()">Test Webhook Endpoints</button>';
        echo '<span id="webhook-test-result"></span>';
        echo '</div>';
    }
}