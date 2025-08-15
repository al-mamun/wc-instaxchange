<?php
/**
 * InstaxChange Payment Gateway Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Gateway extends WC_Payment_Gateway
{

    /**
     * Test mode flag
     * @var bool
     */
    public $testmode;

    /**
     * InstaxChange account reference ID
     * @var string
     */
    public $account_ref_id;

    /**
     * Webhook secret key
     * @var string
     */
    public $webhook_secret;

    /**
     * Receiving wallet address
     * @var string
     */
    public $wallet_address;

    /**
     * Default cryptocurrency
     * @var string
     */
    public $default_crypto;

    public function __construct()
    {
        $this->id = 'instaxchange';
        $this->icon = WC_INSTAXCHANGE_PLUGIN_URL . 'assets/icon.png';
        $this->has_fields = false;
        $this->method_title = __('InstaxChange', 'wc-instaxchange');
        $this->method_description = __('Accept cryptocurrency payments via InstaxChange - ALL payment methods automatically enabled', 'wc-instaxchange');

        // Critical: Add proper supports
        $this->supports = array(
            'products',
            'refunds'
        );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->account_ref_id = $this->get_option('account_ref_id');
        $this->webhook_secret = $this->get_option('webhook_secret');
        $this->wallet_address = $this->get_option('wallet_address');
        $this->default_crypto = $this->get_option('default_crypto');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Handle order processing
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_order_processed'), 10, 3);
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-instaxchange'),
                'type' => 'checkbox',
                'label' => __('Enable InstaxChange Gateway', 'wc-instaxchange'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'wc-instaxchange'),
                'type' => 'text',
                'description' => __('Payment method title displayed to customers', 'wc-instaxchange'),
                'default' => __('Cryptocurrency Payment', 'wc-instaxchange'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'wc-instaxchange'),
                'type' => 'textarea',
                'description' => __('Payment method description displayed to customers', 'wc-instaxchange'),
                'default' => __('Pay with cryptocurrency using your preferred payment method. InstaxChange automatically shows all available options including cards, Apple Pay, Google Pay, bank transfers, and regional payment methods.', 'wc-instaxchange'),
            ),
            'account_ref_id' => array(
                'title' => __('Account Reference ID', 'wc-instaxchange'),
                'type' => 'text',
                'description' => __('Your InstaxChange Account Reference ID from the dashboard', 'wc-instaxchange'),
                'default' => '',
                'desc_tip' => true,
            ),
            'webhook_secret' => array(
                'title' => __('Webhook Secret', 'wc-instaxchange'),
                'type' => 'password',
                'description' => __('Webhook secret key for verification (set in InstaxChange dashboard)', 'wc-instaxchange'),
                'default' => '',
                'desc_tip' => true,
            ),
            'wallet_address' => array(
                'title' => __('Receiving Wallet Address', 'wc-instaxchange'),
                'type' => 'text',
                'description' => __('Your blockchain wallet address to receive payments', 'wc-instaxchange'),
                'default' => '',
                'desc_tip' => true,
            ),
            'default_crypto' => array(
                'title' => __('Default Cryptocurrency', 'wc-instaxchange'),
                'type' => 'select',
                'description' => __('Default cryptocurrency for payments', 'wc-instaxchange'),
                'default' => 'USDC',
                'options' => array(
                    'USDC' => 'USDC (Ethereum)',
                    'USDC-POLYGON' => 'USDC (Polygon)',
                    'USDT' => 'USDT (Tether)',
                    'BTC' => 'Bitcoin (BTC)',
                    'ETH' => 'Ethereum (ETH)',
                    'LTC' => 'Litecoin (LTC)'
                ),
                'desc_tip' => true,
            ),
            'testmode' => array(
                'title' => __('Test Mode', 'wc-instaxchange'),
                'label' => __('Enable Test Mode', 'wc-instaxchange'),
                'type' => 'checkbox',
                'description' => __('Enable test mode for development and testing', 'wc-instaxchange'),
                'default' => 'yes',
                'desc_tip' => true,
            ),

            'webhook_info' => array(
                'title' => __('Webhook URL', 'wc-instaxchange'),
                'type' => 'title',
                'description' => sprintf(
                    __('Set this URL in your InstaxChange dashboard: %s', 'wc-instaxchange'),
                    '<br><code style="background: #f1f3f4; padding: 8px 12px; border-radius: 4px; font-family: monospace;">' . home_url('/wc-api/wc_instaxchange_gateway') . '</code>'
                ),
            ),
            'debug_force_enable' => array(
                'title' => __('Debug: Force Enable', 'wc-instaxchange'),
                'label' => __('Force show payment method (for debugging only)', 'wc-instaxchange'),
                'type' => 'checkbox',
                'description' => __('Temporarily force the payment method to show even if settings are incomplete', 'wc-instaxchange'),
                'default' => 'no',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Check if gateway is available
     */
    public function is_available()
    {
        // Check if force enable is on (for debugging)
        $force_enable = 'yes' === $this->get_option('debug_force_enable');

        if ($force_enable) {
            error_log('InstaxChange: Force enable is ON - showing payment method');
            return true;
        }

        // Normal availability logic
        $is_enabled = ('yes' === $this->enabled);
        $has_account_id = !empty($this->account_ref_id);
        $has_wallet = !empty($this->wallet_address);
        $parent_available = parent::is_available();

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG && is_checkout()) {
            error_log('=== InstaxChange is_available() ===');
            error_log('Enabled: ' . ($is_enabled ? 'YES' : 'NO'));
            error_log('Has Account ID: ' . ($has_account_id ? 'YES' : 'NO'));
            error_log('Has Wallet: ' . ($has_wallet ? 'YES' : 'NO'));
            error_log('Parent Available: ' . ($parent_available ? 'YES' : 'NO'));
        }

        return $parent_available && $is_enabled && $has_account_id && $has_wallet;
    }

    /**
     * Handle order processing
     */
    public function handle_order_processed($order_id, $posted_data, $order)
    {
        if ($order->get_payment_method() === 'instaxchange') {
            error_log('InstaxChange: Order processed, confirming payment method');

            // Ensure proper meta data
            $order->update_meta_data('_payment_method', 'instaxchange');
            $order->update_meta_data('_payment_method_title', $this->title);
            $order->save();
        }
    }

    /**
     * Process payment
     */
    public function process_payment($order_id)
    {
        error_log('=== InstaxChange Process Payment START ===');
        error_log('Order ID: ' . $order_id);

        $order = wc_get_order($order_id);

        if (!$order) {
            error_log('InstaxChange: Order not found');
            wc_add_notice(__('Order not found.', 'wc-instaxchange'), 'error');
            return array('result' => 'fail');
        }

        // Explicitly set payment method
        $order->set_payment_method($this->id);
        $order->set_payment_method_title($this->title);

        error_log('Payment method set to: ' . $order->get_payment_method());
        error_log('Order total: ' . $order->get_total());

        try {
            // Create payment session
            $session_data = $this->create_payment_session($order);

            if ($session_data && isset($session_data['sessionId'])) {
                error_log('InstaxChange: Session created: ' . $session_data['sessionId']);

                // Store session data
                $order->update_meta_data('_instaxchange_session_id', $session_data['sessionId']);
                $order->update_meta_data('_instaxchange_payment_data', $session_data);

                // Set status to pending payment
                $order->update_status('pending', __('Awaiting InstaxChange payment', 'wc-instaxchange'));
                $order->save();

                // Get payment URL
                $payment_url = $order->get_checkout_payment_url(true);
                error_log('InstaxChange: Redirecting to: ' . $payment_url);

                // Empty cart after successful session creation
                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $payment_url
                );

            } else {
                throw new Exception(__('Failed to create payment session', 'wc-instaxchange'));
            }

        } catch (Exception $e) {
            error_log('InstaxChange: Error in process_payment: ' . $e->getMessage());

            $order->update_status('failed', 'InstaxChange error: ' . $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');

            return array('result' => 'fail');
        }
    }

    /**
     * Create payment session with InstaxChange API
     */
    private function create_payment_session($order)
    {
        // Check if session already exists
        $existing_session_id = $order->get_meta('_instaxchange_session_id');
        if ($existing_session_id) {
            error_log('InstaxChange: Reusing existing session: ' . $existing_session_id);
            return array('sessionId' => $existing_session_id);
        }

        // Convert order currency to USD for InstaxChange
        $store_currency = $order->get_currency();
        $from_currency = 'USD'; // Force USD as InstaxChange expects fiat
        $order_total = floatval($order->get_total());

        // Handle crypto store currencies
        if (in_array($store_currency, ['BTC', 'ETH', 'USDC', 'USDT'])) {
            $from_currency = 'USD';
            // In production, implement proper currency conversion
        } else {
            $from_currency = $store_currency;
        }

        // Create unique webhook reference
        $unique_ref = 'order_' . $order->get_id() . '_' . time() . '_' . wp_rand(1000, 9999);

        // ✅ CRITICAL: NO 'method' parameter = InstaxChange shows ALL available payment methods
        $payload = array(
            'accountRefId' => $this->account_ref_id,
            'fromAmount' => $order_total,
            'fromCurrency' => $from_currency,
            'toCurrency' => $this->default_crypto,
            'address' => $this->wallet_address,
            'amountDirection' => 'sending',
            'webhookRef' => $unique_ref,
            'returnUrl' => $this->get_return_url($order)
            // ✅ NO 'method' parameter = Shows ALL payment methods:
            // Cards, Apple Pay, Google Pay, iDEAL, SEPA, Interac, PIX, POLi, PayPal, etc.
        );

        // Enhanced logging for debugging
        error_log('=== InstaxChange API Request (ALL METHODS ENABLED) ===');
        error_log('Store Currency: ' . $store_currency);
        error_log('From Currency: ' . $from_currency);
        error_log('🚀 NO METHOD PARAMETER = ALL PAYMENT METHODS AVAILABLE');
        error_log('Expected methods: Cards, Apple Pay, Google Pay, iDEAL, SEPA, Interac, PIX, POLi, PayPal');
        error_log('Full Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));

        $response = wp_remote_post('https://instaxchange.com/api/session', array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WooCommerce-InstaxChange/1.0'
            ),
            'body' => json_encode($payload),
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            $error_message = 'Connection error: ' . $response->get_error_message();
            error_log('InstaxChange Connection Error: ' . $error_message);
            throw new Exception($error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('=== InstaxChange API Response ===');
        error_log('Response Code: ' . $response_code);
        error_log('Response Body: ' . $body);

        $data = json_decode($body, true);

        if ($response_code !== 200) {
            $error_details = "API Error (Code: $response_code)";

            if ($data) {
                if (isset($data['message'])) {
                    $error_details .= ": " . $data['message'];
                } elseif (isset($data['error'])) {
                    $error_details .= ": " . $data['error'];
                } else {
                    $error_details .= ": " . json_encode($data);
                }
            } else {
                $error_details .= ": " . substr($body, 0, 200);
            }

            error_log('InstaxChange API Error: ' . $error_details);
            throw new Exception($error_details);
        }

        // Check for session ID (API returns 'id' field)
        if (!$data || !isset($data['id'])) {
            error_log('InstaxChange Invalid Response: Missing id field');
            error_log('Available fields: ' . implode(', ', array_keys($data ?: [])));
            throw new Exception('Invalid response from InstaxChange - missing session ID');
        }

        // Use 'id' as sessionId
        $data['sessionId'] = $data['id'];

        // Store webhook reference
        $order->update_meta_data('_instaxchange_webhook_ref', $unique_ref);
        $order->save();

        error_log('InstaxChange SUCCESS: Session created with ID: ' . $data['id']);
        return $data;
    }

    /**
     * Receipt page - displays payment interface
     */
    public function receipt_page($order_id)
    {
        error_log('=== InstaxChange RECEIPT PAGE CALLED ===');
        error_log('Order ID: ' . $order_id);

        $order = wc_get_order($order_id);

        if (!$order) {
            echo '<div class="woocommerce-error">Order not found.</div>';
            return;
        }

        error_log('Payment method: ' . $order->get_payment_method());
        error_log('Order status: ' . $order->get_status());

        // Get or create session
        $session_id = $order->get_meta('_instaxchange_session_id');

        if (!$session_id) {
            error_log('InstaxChange: No session ID, attempting to create...');
            try {
                $session_data = $this->create_payment_session($order);
                if ($session_data && isset($session_data['sessionId'])) {
                    $session_id = $session_data['sessionId'];
                    $order->update_meta_data('_instaxchange_session_id', $session_id);
                    $order->save();
                    error_log('InstaxChange: Session created in receipt_page: ' . $session_id);
                }
            } catch (Exception $e) {
                error_log('InstaxChange: Failed to create session: ' . $e->getMessage());
            }
        }

        if (!$session_id) {
            echo '<div class="woocommerce-error">';
            echo '<h3>Payment Session Error</h3>';
            echo '<p>Unable to create payment session. Please contact support.</p>';
            echo '<a href="' . wc_get_checkout_url() . '" class="button">Return to Checkout</a>';
            echo '</div>';
            return;
        }

        // Display payment interface
        ?>
        <style>
            .instaxchange-payment-wrapper {
                max-width: 900px;
                margin: 20px auto;
                padding: 30px;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }

            .instaxchange-iframe-container {
                border: 1px solid #e1e5e9;
                border-radius: 12px;
                overflow: hidden;
                background: #f8f9fa;
                min-height: 650px;
                position: relative;
            }

            .loading-message {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
                color: #6c757d;
            }

            .order-summary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 25px;
            }

            .payment-methods-info {
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                color: white;
                border-radius: 12px;
                padding: 20px;
                margin: 20px 0;
            }

            .security-notice {
                background: #e8f4fd;
                border: 1px solid #bee5eb;
                color: #0c5460;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
        </style>

        <div class="instaxchange-payment-wrapper">
            <h2> Complete Your Cryptocurrency Payment</h2>

            <div class="security-notice">
                <strong>🛡️ Secure Payment:</strong> Your payment is processed securely through InstaxChange's encrypted
                platform.
            </div>

            <div class="order-summary">
                <h3 style="margin-top: 0; color: white;">📋 Order Summary</h3>
                <p><strong>Order #:</strong> <?php echo $order->get_order_number(); ?></p>
                <p><strong>Total:</strong> <?php echo $order->get_formatted_order_total(); ?></p>
                <p><strong>Payment Method:</strong> <?php echo $this->title; ?></p>
            </div>


            <div class="instaxchange-iframe-container" id="instaxchange-container">
                <div class="loading-message">
                    <p>🔄 Loading all payment methods...</p>
                    <p><small>Session: <?php echo esc_html($session_id); ?></small></p>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: center; color: #6c757d; font-size: 14px;">
                <p>💡 <strong>Having trouble?</strong> Refresh this page or contact our support team.</p>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                console.log('🚀 InstaxChange: ALL PAYMENT METHODS ENABLED');
                console.log('Session ID: <?php echo esc_js($session_id); ?>');
                console.log('✅ NO METHOD PARAMETER SENT = ALL METHODS AVAILABLE');
                console.log('Expected methods: Cards, Apple Pay, Google Pay, iDEAL, SEPA, Interac, PIX, POLi, PayPal');

                var iframeUrl = 'https://instaxchange.com/embed/<?php echo esc_js($session_id); ?>';
                console.log('Iframe URL: ' + iframeUrl);
                console.log('🔍 If limited methods show:');
                console.log('1. Check InstaxChange account permissions');
                console.log('2. Verify geographic location');
                console.log('3. Check device compatibility (Apple Pay = iOS, Google Pay = Chrome)');

                var $iframe = $('<iframe>')
                    .attr('src', iframeUrl)
                    .attr('width', '100%')
                    .attr('height', '650')
                    .attr('frameborder', '0')
                    .css({
                        'border': 'none',
                        'background': '#fff'
                    });

                $iframe.on('load', function () {
                    console.log('✅ InstaxChange iframe loaded successfully');
                    console.log('💡 All available payment methods should now be visible above');
                    $('#instaxchange-container .loading-message').fadeOut(500);
                });

                $iframe.on('error', function () {
                    console.error('❌ InstaxChange iframe failed to load');
                    $('#instaxchange-container').html(
                        '<div style="text-align: center; padding: 60px; color: #dc3545;">' +
                        '<h4>❌ Payment Interface Error</h4>' +
                        '<p>Unable to load payment interface. Please refresh the page.</p>' +
                        '<button onclick="location.reload()" class="button">🔄 Refresh Page</button>' +
                        '</div>'
                    );
                });

                // Add iframe to container
                $('#instaxchange-container').append($iframe);

                // Listen for payment completion messages
                window.addEventListener('message', function (event) {
                    console.log('📨 Received message:', event.origin, event.data);

                    if (event.origin === 'https://instaxchange.com') {
                        if (event.data.status === 'completed' || event.data.type === 'payment_complete') {
                            console.log('✅ Payment completed! Redirecting...');

                            $('#instaxchange-container').html(
                                '<div style="text-align: center; padding: 60px; color: #28a745;">' +
                                '<h3>✅ Payment Completed Successfully!</h3>' +
                                '<p>Redirecting to confirmation page...</p>' +
                                '<div style="margin: 20px 0;">🎉</div>' +
                                '</div>'
                            );

                            setTimeout(function () {
                                window.location.href = '<?php echo esc_url($order->get_checkout_order_received_url()); ?>';
                            }, 3000);
                        }
                    }
                });

                // Backup: Check payment status every 30 seconds
                var checkCount = 0;
                var maxChecks = 40; // 20 minutes maximum

                var statusChecker = setInterval(function () {
                    checkCount++;

                    if (checkCount > maxChecks) {
                        clearInterval(statusChecker);
                        return;
                    }

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'check_instaxchange_payment_status',
                            order_id: <?php echo $order_id; ?>,
                            nonce: '<?php echo wp_create_nonce('check_payment_' . $order_id); ?>'
                        },
                        success: function (response) {
                            if (response.success && response.data.paid) {
                                console.log('✅ Payment confirmed via status check');
                                clearInterval(statusChecker);
                                window.location.href = '<?php echo esc_url($order->get_checkout_order_received_url()); ?>';
                            }
                        },
                        error: function () {
                            console.log('Status check failed');
                        }
                    });
                }, 30000); // Check every 30 seconds
            });
        </script>
        <?php
    }

    /**
     * Enqueue payment scripts and styles
     */
    public function payment_scripts()
    {
        if (!is_admin() && (is_checkout() || is_wc_endpoint_url('order-pay'))) {
            // Enqueue jQuery
            wp_enqueue_script('jquery');

            // Enqueue frontend CSS
            wp_enqueue_style(
                'wc-instaxchange-frontend',
                WC_INSTAXCHANGE_PLUGIN_URL . 'assets/style.css',
                array(),
                WC_INSTAXCHANGE_VERSION
            );

            // Add critical inline styles for immediate hiding of default payment forms
            wp_add_inline_style('wc-instaxchange-frontend', '
                /* Critical styles to hide default payment elements immediately */
                .woocommerce-pay .woocommerce-checkout-payment,
                .woocommerce-pay .payment_methods,
                .woocommerce-pay .wc-proceed-to-checkout,
                .woocommerce-pay .place-order,
                .woocommerce-pay #payment .form-row,
                .woocommerce-pay .woocommerce-form-coupon-toggle,
                .woocommerce-pay .checkout-button {
                    display: none !important;
                }
                
                /* Ensure InstaxChange interface is visible */
                .woocommerce .instaxchange-payment-wrapper {
                    display: block !important;
                    visibility: visible !important;
                }
            ');
        }
    }
}

?>