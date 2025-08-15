<?php
/**
 * InstaxChange Admin Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Admin
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        add_action('woocommerce_admin_order_data_after_payment_info', array($this, 'display_order_payment_info'));

        // Add admin notices for configuration
        add_action('admin_notices', array($this, 'admin_configuration_notices'));

        // Add order list column
        add_filter('manage_shop_order_posts_columns', array($this, 'add_order_payment_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'populate_order_payment_column'), 20, 2);

        // HPOS compatibility for order list
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_order_payment_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'populate_hpos_order_payment_column'), 20, 2);
    }

    /**
     * Add meta boxes for order details
     */
    public function add_order_meta_boxes()
    {
        // Check if HPOS is enabled
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $screen = wc_get_page_screen_id('shop-order');
        } else {
            $screen = 'shop_order';
        }

        add_meta_box(
            'wc-instaxchange-payment-details',
            __('InstaxChange Payment Details', 'wc-instaxchange'),
            array($this, 'order_meta_box_content'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Meta box content for order details
     */
    public function order_meta_box_content($post_or_order_object)
    {
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

        if (!$order || $order->get_payment_method() !== 'instaxchange') {
            echo '<p>' . __('This order does not use InstaxChange payment.', 'wc-instaxchange') . '</p>';
            return;
        }

        $session_id = $order->get_meta('_instaxchange_session_id');
        $transaction_id = $order->get_meta('_instaxchange_transaction_id');
        $deposit_tx_id = $order->get_meta('_instaxchange_deposit_tx_id');
        $withdraw_tx_id = $order->get_meta('_instaxchange_withdraw_tx_id');
        $crypto_amount = $order->get_meta('_instaxchange_crypto_amount');
        $crypto_currency = $order->get_meta('_instaxchange_crypto_currency');
        $webhook_ref = $order->get_meta('_instaxchange_webhook_ref');

        ?>
        <div class="instaxchange-payment-details">
            <?php if ($session_id): ?>
                <p><strong><?php _e('Session ID:', 'wc-instaxchange'); ?></strong><br>
                    <code class="instax-code"><?php echo esc_html($session_id); ?></code>
                    <a href="https://instaxchange.com/embed/<?php echo esc_attr($session_id); ?>" target="_blank"
                        class="button button-small" style="margin-left: 5px;">
                        <?php _e('View Payment', 'wc-instaxchange'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($transaction_id): ?>
                <p><strong><?php _e('Transaction ID:', 'wc-instaxchange'); ?></strong><br>
                    <code class="instax-code"><?php echo esc_html($transaction_id); ?></code>
                </p>
            <?php endif; ?>

            <?php if ($webhook_ref): ?>
                <p><strong><?php _e('Webhook Reference:', 'wc-instaxchange'); ?></strong><br>
                    <code class="instax-code"><?php echo esc_html($webhook_ref); ?></code>
                </p>
            <?php endif; ?>

            <?php if ($crypto_amount && $crypto_currency): ?>
                <p><strong><?php _e('Crypto Amount:', 'wc-instaxchange'); ?></strong><br>
                    <span class="instax-crypto-amount"><?php echo esc_html($crypto_amount . ' ' . $crypto_currency); ?></span>
                </p>
            <?php endif; ?>

            <?php if ($deposit_tx_id): ?>
                <p><strong><?php _e('Deposit TX:', 'wc-instaxchange'); ?></strong><br>
                    <code class="instax-code"><?php echo esc_html($deposit_tx_id); ?></code>
                    <?php if ($crypto_currency === 'BTC'): ?>
                        <a href="https://blockstream.info/tx/<?php echo esc_attr($deposit_tx_id); ?>" target="_blank"
                            class="button button-small">
                            <?php _e('View on Blockchain', 'wc-instaxchange'); ?>
                        </a>
                    <?php elseif (in_array($crypto_currency, ['ETH', 'USDC', 'USDT'])): ?>
                        <a href="https://etherscan.io/tx/<?php echo esc_attr($deposit_tx_id); ?>" target="_blank"
                            class="button button-small">
                            <?php _e('View on Etherscan', 'wc-instaxchange'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($withdraw_tx_id): ?>
                <p><strong><?php _e('Withdraw TX:', 'wc-instaxchange'); ?></strong><br>
                    <code class="instax-code"><?php echo esc_html($withdraw_tx_id); ?></code>
                    <?php if ($crypto_currency === 'BTC'): ?>
                        <a href="https://blockstream.info/tx/<?php echo esc_attr($withdraw_tx_id); ?>" target="_blank"
                            class="button button-small">
                            <?php _e('View on Blockchain', 'wc-instaxchange'); ?>
                        </a>
                    <?php elseif (in_array($crypto_currency, ['ETH', 'USDC', 'USDT'])): ?>
                        <a href="https://etherscan.io/tx/<?php echo esc_attr($withdraw_tx_id); ?>" target="_blank"
                            class="button button-small">
                            <?php _e('View on Etherscan', 'wc-instaxchange'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php
            // Show payment status
            $status_class = 'instax-status-';
            $status_text = '';

            if ($order->is_paid()) {
                $status_class .= 'completed';
                $status_text = __('Payment Completed', 'wc-instaxchange');
                $status_icon = '✅';
            } elseif ($session_id && !$transaction_id) {
                $status_class .= 'pending';
                $status_text = __('Payment session created, awaiting completion', 'wc-instaxchange');
                $status_icon = '⏳';
            } elseif (!$session_id) {
                $status_class .= 'error';
                $status_text = __('Payment session not created', 'wc-instaxchange');
                $status_icon = '❌';
            } else {
                $status_class .= 'processing';
                $status_text = __('Payment processing', 'wc-instaxchange');
                $status_icon = '🔄';
            }
            ?>

            <div class="instax-status-box <?php echo $status_class; ?>">
                <p><strong><?php _e('Payment Status:', 'wc-instaxchange'); ?></strong></p>
                <p class="instax-status-text"><?php echo $status_icon . ' ' . $status_text; ?></p>

                <?php if ($session_id && !$order->is_paid()): ?>
                    <p class="instax-status-help">
                        <small><?php _e('Customer should complete payment in InstaxChange interface', 'wc-instaxchange'); ?></small>
                    </p>
                <?php endif; ?>

                <?php if (!$session_id): ?>
                    <p class="instax-status-help">
                        <small><?php _e('Check error logs for API connection issues', 'wc-instaxchange'); ?></small>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($session_id && !$order->is_paid()): ?>
                <div style="margin-top: 15px;">
                    <button type="button" class="button button-secondary"
                        onclick="checkInstaxChangePayment(<?php echo $order->get_id(); ?>)">
                        <?php _e('Check Payment Status', 'wc-instaxchange'); ?>
                    </button>
                    <span id="instax-check-result" style="margin-left: 10px;"></span>
                </div>
            <?php endif; ?>

            <?php if ($session_id): ?>
                <div
                    style="margin-top: 15px; padding: 12px; background: #e8f4fd; border-radius: 6px; border-left: 4px solid #0073aa;">
                    <p style="margin: 0; color: #0c5460; font-size: 13px;">
                        <strong>✅ Payment Methods Available:</strong><br>
                        All payment methods automatically enabled for this order including cards, Apple Pay, Google Pay, bank
                        transfers, and regional methods based on customer location.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .instaxchange-payment-details .instax-code {
                background: #f1f3f4;
                padding: 6px 10px;
                border-radius: 4px;
                font-size: 11px;
                word-break: break-all;
                display: inline-block;
                max-width: 100%;
                font-family: monospace;
            }

            .instaxchange-payment-details p {
                margin: 12px 0;
            }

            .instax-crypto-amount {
                background: #e7f3ff;
                padding: 4px 8px;
                border-radius: 3px;
                font-weight: bold;
                color: #0073aa;
            }

            .instax-status-box {
                margin-top: 15px;
                padding: 12px;
                border-radius: 6px;
                border-left: 4px solid;
            }

            .instax-status-completed {
                background: #d4edda;
                border-left-color: #28a745;
                color: #155724;
            }

            .instax-status-pending {
                background: #fff3cd;
                border-left-color: #ffc107;
                color: #856404;
            }

            .instax-status-error {
                background: #f8d7da;
                border-left-color: #dc3545;
                color: #721c24;
            }

            .instax-status-processing {
                background: #d1ecf1;
                border-left-color: #17a2b8;
                color: #0c5460;
            }

            .instax-status-text {
                font-weight: bold;
                margin: 5px 0 !important;
            }

            .instax-status-help {
                font-style: italic;
                margin: 5px 0 !important;
            }
        </style>

        <script>
            function checkInstaxChangePayment(orderId) {
                var resultSpan = document.getElementById('instax-check-result');
                resultSpan.innerHTML = '🔄 Checking...';

                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'check_instaxchange_payment_status',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('check_payment_' . $order->get_id()); ?>'
                    },
                    success: function (response) {
                        if (response.success) {
                            if (response.data.paid) {
                                resultSpan.innerHTML = '✅ Payment confirmed! Refresh page.';
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            } else {
                                resultSpan.innerHTML = '⏳ Still pending...';
                            }
                        } else {
                            resultSpan.innerHTML = '❌ Check failed';
                        }
                    },
                    error: function () {
                        resultSpan.innerHTML = '❌ Connection error';
                    }
                });
            }
        </script>
        <?php
    }

    /**
     * Display payment info after order payment details
     */
    public function display_order_payment_info($order)
    {
        if ($order->get_payment_method() === 'instaxchange') {
            $transaction_id = $order->get_meta('_instaxchange_transaction_id');
            $session_id = $order->get_meta('_instaxchange_session_id');

            if ($transaction_id) {
                echo '<p class="form-field form-field-wide">';
                echo '<strong>' . __('InstaxChange Transaction ID:', 'wc-instaxchange') . '</strong><br>';
                echo '<code style="background: #f1f3f4; padding: 4px 8px; border-radius: 3px;">' . esc_html($transaction_id) . '</code>';
                echo '</p>';
            }

            if ($session_id && !$transaction_id) {
                echo '<p class="form-field form-field-wide">';
                echo '<strong>' . __('InstaxChange Session ID:', 'wc-instaxchange') . '</strong><br>';
                echo '<code style="background: #f1f3f4; padding: 4px 8px; border-radius: 3px;">' . esc_html($session_id) . '</code><br>';
                echo '<small style="color: #856404;">' . __('⏳ Payment session created, awaiting completion', 'wc-instaxchange') . '</small>';
                echo '</p>';
            }
        }
    }

    /**
     * Add payment method column to order list
     */
    public function add_order_payment_column($columns)
    {
        // Add column after order status
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_status') {
                $new_columns['instaxchange_payment'] = __('InstaxChange', 'wc-instaxchange');
            }
        }
        return $new_columns;
    }

    /**
     * Populate payment column for regular posts
     */
    public function populate_order_payment_column($column, $post_id)
    {
        if ($column === 'instaxchange_payment') {
            $order = wc_get_order($post_id);
            $this->render_payment_column_content($order);
        }
    }

    /**
     * Populate payment column for HPOS
     */
    public function populate_hpos_order_payment_column($column, $order)
    {
        if ($column === 'instaxchange_payment') {
            $this->render_payment_column_content($order);
        }
    }

    /**
     * Render payment column content
     */
    private function render_payment_column_content($order)
    {
        if (!$order || $order->get_payment_method() !== 'instaxchange') {
            echo '-';
            return;
        }

        $transaction_id = $order->get_meta('_instaxchange_transaction_id');
        $session_id = $order->get_meta('_instaxchange_session_id');

        if ($order->is_paid() && $transaction_id) {
            echo '<span style="color: #28a745;">✅ Paid</span><br>';
            echo '<small>TX: ' . substr($transaction_id, 0, 8) . '...</small>';
        } elseif ($session_id) {
            echo '<span style="color: #ffc107;">⏳ Pending</span><br>';
            echo '<small>All methods available</small>';
        } else {
            echo '<span style="color: #dc3545;">❌ No session</span>';
        }
    }

    /**
     * Admin configuration notices
     */
    public function admin_configuration_notices()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_wc-settings') {
            return;
        }

        // Only show on InstaxChange settings page
        if (!isset($_GET['section']) || $_GET['section'] !== 'instaxchange') {
            return;
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        $gateway = isset($gateways['instaxchange']) ? $gateways['instaxchange'] : null;

        if (!$gateway) {
            return;
        }

        $errors = array();
        $warnings = array();

        // Check required settings
        if (empty($gateway->account_ref_id)) {
            $errors[] = __('Account Reference ID is required', 'wc-instaxchange');
        }

        if (empty($gateway->wallet_address)) {
            $errors[] = __('Receiving Wallet Address is required', 'wc-instaxchange');
        }

        // Check webhook configuration
        if (empty($gateway->webhook_secret)) {
            $warnings[] = __('Webhook secret is not set - payment verification may be less secure', 'wc-instaxchange');
        }

        // Show test mode warning
        if ($gateway->testmode) {
            $warnings[] = __('InstaxChange is in test mode - remember to disable for production', 'wc-instaxchange');
        }

        // Display errors
        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p><strong>InstaxChange Configuration Errors:</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }

        // Display warnings
        if (!empty($warnings)) {
            echo '<div class="notice notice-warning"><p><strong>InstaxChange Warnings:</strong></p><ul>';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul></div>';
        }

        // Show success if properly configured
        if (empty($errors) && $gateway->enabled === 'yes') {
            echo '<div class="notice notice-success">
                <p><strong>✅ InstaxChange is properly configured and enabled!</strong></p>
                <p>✅ <strong>All payment methods are automatically available:</strong> Cards, Apple Pay, Google Pay, bank transfers, iDEAL, SEPA, Interac, PIX, POLi, and regional methods based on customer location.</p>
            </div>';
        }
    }
}

new WC_InstaxChange_Admin();

?>