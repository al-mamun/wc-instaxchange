<?php
/**
 * InstaxChange Payment Gateway Class
 *
 * WooCommerce payment gateway for InstaxChange cryptocurrency payments
 */

if (!defined('ABSPATH')) {
    exit;
}

#[\AllowDynamicProperties]
class WC_InstaxChange_Gateway extends WC_Payment_Gateway
{
    public $testmode;
    public $account_ref_id;
    public $webhook_secret;
    public $wallet_address;
    public $default_crypto;

    // Explicit blocks support properties
    public $supports_blocks = true;
    public $blocks_supported = true;

    public function __construct()
    {
        try {
            $this->id = 'instaxchange';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = 'InstaxChange';
            $this->method_description = 'Accept cryptocurrency payments via InstaxChange - ALL payment methods automatically enabled';

            // Initialize supports array with comprehensive blocks support
            $this->supports = array(
                'products',
                'refunds',
                'tokenization',
                'blocks',
                'block_checkout',
                'checkout_blocks'
            );

            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables with safe defaults
            $this->title = $this->get_option('title', 'Pay with InstaxChange - All Methods Available');
            $this->description = $this->get_option('description', 'Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.');
            $this->enabled = $this->get_option('enabled', 'yes');
            $this->testmode = $this->get_option('testmode', 'yes');
            $this->account_ref_id = $this->get_option('account_ref_id', '');
            $this->webhook_secret = $this->get_option('webhook_secret', '');
            $this->wallet_address = $this->get_option('wallet_address', '');
            $this->default_crypto = $this->get_option('default_crypto', 'USDC');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            wc_instaxchange_debug_log('Gateway constructed successfully');

        } catch (Exception $e) {
            wc_instaxchange_debug_log('Exception in constructor', $e->getMessage());
            $this->enabled = 'no';
        }
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable InstaxChange Gateway',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'Pay with InstaxChange - All Methods Available',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Pay securely using InstaxChange with multiple payment methods including cards, digital wallets, and regional payment options.',
                'desc_tip' => true,
            ),
            'testmode' => array(
                'title' => 'Test Mode',
                'type' => 'checkbox',
                'label' => 'Enable Test Mode',
                'default' => 'yes',
                'description' => 'Place the payment gateway in test mode.',
            ),
            'account_ref_id' => array(
                'title' => 'Account Reference ID',
                'type' => 'text',
                'description' => 'Your InstaxChange account reference ID.',
                'default' => '',
                'desc_tip' => true,
            ),
            'wallet_address' => array(
                'title' => 'Wallet Address',
                'type' => 'text',
                'description' => 'Your blockchain wallet address to receive payments.',
                'default' => '',
                'desc_tip' => true,
            ),
            'webhook_secret' => array(
                'title' => 'Webhook Secret',
                'type' => 'text',
                'description' => 'Secret key for webhook verification.',
                'default' => '',
                'desc_tip' => true,
            ),
            'default_crypto' => array(
                'title' => 'Default Cryptocurrency',
                'type' => 'select',
                'description' => 'Default cryptocurrency to receive payments in.',
                'default' => 'USDC',
                'desc_tip' => true,
                'options' => array(
                    'USDC' => 'USDC (USD Coin)',
                    'USDC_POLYGON' => 'USDC Polygon (USD Coin on Polygon)',
                    'USDT' => 'USDT (Tether)',
                    'BTC' => 'Bitcoin (BTC)',
                    'ETH' => 'Ethereum (ETH)',
                    'LTC' => 'Litecoin (LTC)',
                    'BCH' => 'Bitcoin Cash (BCH)',
                    'XRP' => 'Ripple (XRP)',
                    'ADA' => 'Cardano (ADA)',
                    'DOT' => 'Polkadot (DOT)',
                    'LINK' => 'Chainlink (LINK)',
                    'UNI' => 'Uniswap (UNI)',
                    'MATIC' => 'Polygon (MATIC)',
                    'SOL' => 'Solana (SOL)',
                    'AVAX' => 'Avalanche (AVAX)',
                    'ATOM' => 'Cosmos (ATOM)',
                ),
            ),
            'webhook_info' => array(
                'title' => 'Webhook URL',
                'type' => 'title',
                'description' => 'Set this URL in your InstaxChange dashboard: <br><code>' . home_url('/wc-api/instaxchange') . '</code><br><br><button type="button" class="button button-secondary" onclick="testWebhookEndpoint()">Test Webhook Endpoint</button><span id="webhook-test-result" style="margin-left: 10px;"></span>'
            ),
            'payment_methods' => array(
                'title' => 'Payment Methods',
                'type' => 'title',
                'description' => 'Select which payment methods to display on the payment page:'
            ),
            'enable_traditional_methods' => array(
                'title' => 'Traditional Payment Methods',
                'type' => 'checkbox',
                'label' => 'Enable Traditional Payment Methods',
                'default' => 'yes',
                'description' => 'Show credit/debit cards, Apple Pay, and Google Pay.',
            ),
            'enable_card' => array(
                'title' => 'Credit/Debit Cards',
                'type' => 'checkbox',
                'label' => 'Enable Credit/Debit Cards',
                'default' => 'yes',
                'description' => 'Show Visa, Mastercard, Amex payment option.',
            ),
            'enable_apple_pay' => array(
                'title' => 'Apple Pay',
                'type' => 'checkbox',
                'label' => 'Enable Apple Pay',
                'default' => 'yes',
                'description' => 'Show Apple Pay payment option.',
            ),
            'enable_google_pay' => array(
                'title' => 'Google Pay',
                'type' => 'checkbox',
                'label' => 'Enable Google Pay',
                'default' => 'yes',
                'description' => 'Show Google Pay payment option.',
            ),
            'enable_regional_methods' => array(
                'title' => 'Regional Payment Methods',
                'type' => 'checkbox',
                'label' => 'Enable Regional Payment Methods',
                'default' => 'yes',
                'description' => 'Show regional payment methods like iDEAL, SEPA, etc.',
            ),
            'enable_ideal' => array(
                'title' => 'iDEAL',
                'type' => 'checkbox',
                'label' => 'Enable iDEAL',
                'default' => 'yes',
                'description' => 'Show Dutch bank transfer option.',
            ),
            'enable_bancontact' => array(
                'title' => 'Bancontact',
                'type' => 'checkbox',
                'label' => 'Enable Bancontact',
                'default' => 'yes',
                'description' => 'Show Belgian payment option.',
            ),
            'enable_interac' => array(
                'title' => 'Interac',
                'type' => 'checkbox',
                'label' => 'Enable Interac',
                'default' => 'yes',
                'description' => 'Show Canadian e-Transfer option.',
            ),
            'enable_pix' => array(
                'title' => 'PIX',
                'type' => 'checkbox',
                'label' => 'Enable PIX',
                'default' => 'yes',
                'description' => 'Show Brazilian instant payment option.',
            ),
            'enable_sepa' => array(
                'title' => 'SEPA',
                'type' => 'checkbox',
                'label' => 'Enable SEPA',
                'default' => 'yes',
                'description' => 'Show European bank transfer option.',
            ),
            'enable_poli' => array(
                'title' => 'POLi',
                'type' => 'checkbox',
                'label' => 'Enable POLi',
                'default' => 'yes',
                'description' => 'Show Australian online banking option.',
            ),
            'enable_blik' => array(
                'title' => 'BLIK',
                'type' => 'checkbox',
                'label' => 'Enable BLIK',
                'default' => 'yes',
                'description' => 'Show Polish mobile payment option.',
            ),
            'order_management_section' => array(
                'title' => 'Order Status Management',
                'type' => 'title',
                'description' => 'Configure automatic order status management:'
            ),
            'enable_order_management' => array(
                'title' => 'Automatic Order Management',
                'type' => 'checkbox',
                'label' => 'Enable Automatic Order Status Management',
                'default' => 'yes',
                'description' => 'Automatically check for stuck orders and update their status hourly. Recommended for optimal payment processing.',
            ),
            'crypto_section' => array(
                'title' => 'Cryptocurrency Payments',
                'type' => 'title',
                'description' => 'Configure cryptocurrency payment options:'
            ),
            'enable_crypto' => array(
                'title' => 'Cryptocurrency Payments',
                'type' => 'checkbox',
                'label' => 'Enable Cryptocurrency Payments',
                'default' => 'yes',
                'description' => 'Show cryptocurrency payment options (Bitcoin, Ethereum, USDC, etc.).',
            ),
        );
    }

    /**
     * Validate gateway configuration
     *
     * @return array Array of validation errors, empty if valid
     */
    public function validate_configuration()
    {
        $errors = [];

        // Required fields
        if (empty($this->account_ref_id)) {
            $errors[] = 'Account Reference ID is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->account_ref_id)) {
            $errors[] = 'Account Reference ID contains invalid characters';
        }

        if (empty($this->wallet_address)) {
            $errors[] = 'Wallet Address is required';
        } elseif (strlen($this->wallet_address) < 26) {
            $errors[] = 'Wallet Address appears to be invalid (too short)';
        }

        // Webhook secret validation (required in production)
        if (defined('WC_INSTAXCHANGE_PRODUCTION') && WC_INSTAXCHANGE_PRODUCTION) {
            if (empty($this->webhook_secret)) {
                $errors[] = 'Webhook Secret is required in production mode for security';
            } elseif (strlen($this->webhook_secret) < 16) {
                $errors[] = 'Webhook Secret should be at least 16 characters for security';
            }
        }

        return $errors;
    }

    /**
     * Check if this gateway is available - Enhanced for theme compatibility
     */
    public function is_available()
    {
        wc_instaxchange_debug_log('Checking gateway availability');

        // Check basic requirements
        if ($this->enabled !== 'yes') {
            wc_instaxchange_debug_log('Gateway not enabled in settings', $this->enabled);
            return false;
        }

        wc_instaxchange_debug_log('Gateway enabled in settings');

        // Validate configuration
        $validation_errors = $this->validate_configuration();

        if (!empty($validation_errors)) {
            wc_instaxchange_debug_log('Gateway configuration invalid', $validation_errors);

            // In test mode, allow gateway to show for testing purposes (with warnings)
            if ($this->testmode === 'yes') {
                wc_instaxchange_debug_log('Test mode enabled - allowing gateway despite validation errors');
                // Show admin notice about configuration issues
                if (is_admin()) {
                    add_action('admin_notices', function() use ($validation_errors) {
                        echo '<div class="notice notice-warning"><p>';
                        echo '<strong>InstaxChange Gateway:</strong> Configuration issues detected:<br>';
                        echo implode('<br>', array_map('esc_html', $validation_errors));
                        echo '</p></div>';
                    });
                }
            } else {
                // In production mode, don't show gateway if configuration is invalid
                return false;
            }
        }

        wc_instaxchange_debug_log('Gateway properly configured');

        // Enhanced theme compatibility checks
        if (!$this->check_theme_compatibility()) {
            wc_instaxchange_debug_log('Theme compatibility check failed');
            return false;
        }

        // Check if we're on checkout page, admin, or related pages
        global $wp;
        if (
            is_checkout() || isset($wp->query_vars['wc-api']) || isset($_GET['wc-api']) ||
            is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received') ||
            is_admin() || (defined('WP_CLI') && WP_CLI) // Allow in admin and CLI for configuration
        ) {
            wc_instaxchange_debug_log('On checkout/order page, admin, or API request, gateway should be available');

            // Always return true for checkout pages and admin to ensure visibility
            // The actual payment processing will handle configuration validation
            return true;
        }

        // Check if WooCommerce is properly loaded
        if (!function_exists('WC') || !WC()->session) {
            wc_instaxchange_debug_log('WooCommerce session not available');
            return false;
        }

        // Check WooCommerce country settings with better error handling
        if (class_exists('WC_Geolocation')) {
            try {
                $customer_country = WC()->customer ? WC()->customer->get_billing_country() : '';
                if (empty($customer_country)) {
                    $location = WC_Geolocation::geolocate_ip();
                    $customer_country = $location['country'];
                }

                wc_instaxchange_debug_log('Customer country detected', $customer_country);

                // We support all countries, so this should always return true
                // But log it for debugging purposes
                $countries = WC()->countries->get_allowed_countries();
                if (!empty($customer_country) && !array_key_exists($customer_country, $countries)) {
                    wc_instaxchange_debug_log('Customer country not in allowed countries list', $customer_country);
                    // Don't return false here, as we want to support all countries
                }
            } catch (Exception $e) {
                wc_instaxchange_debug_log('Error checking customer country', $e->getMessage());
                // Continue anyway
            }
        }

        wc_instaxchange_debug_log('Gateway is available');
        return true;
    }

    /**
     * Check theme compatibility
     */
    private function check_theme_compatibility()
    {
        // Check for common theme conflicts
        $current_theme = wp_get_theme();

        // Log theme information for debugging
        wc_instaxchange_debug_log('Theme compatibility check', [
            'theme_name' => $current_theme->get('Name'),
            'theme_version' => $current_theme->get('Version'),
            'template' => $current_theme->get('Template'),
            'stylesheet' => $current_theme->get('Stylesheet')
        ]);

        // Check if theme supports WooCommerce
        if (!current_theme_supports('woocommerce')) {
            wc_instaxchange_debug_log('Theme does not declare WooCommerce support');
            // Don't return false, just log it
        }

        // Check for known problematic themes
        $problematic_themes = [
            'avada', // ThemeFusion Avada
            'divi', // Elegant Themes Divi
            'enfold', // Enfold
            'x', // Theme.co X
            'pro', // Theme.co Pro
            'flatsome', // UX Themes Flatsome
            'woodmart', // XTemos WoodMart
            'astra', // Brainstorm Force Astra
            'generatepress', // Tom Usborne GeneratePress
            'oceanwp', // OceanWP
            'storefront', // WooCommerce Storefront
        ];

        $theme_slug = strtolower($current_theme->get('Template'));
        if (in_array($theme_slug, $problematic_themes)) {
            wc_instaxchange_debug_log('Detected potentially problematic theme', $theme_slug);
            // Don't return false, themes can still work with proper configuration
        }

        return true;
    }

    /**
     * Get gateway title for blocks compatibility
     */
    public function get_title()
    {
        return $this->title;
    }

    /**
     * Get gateway description for blocks compatibility
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Explicitly declare blocks support
     */
    public function supports_blocks()
    {
        return true;
    }

    /**
     * Check if gateway supports a specific feature - Enhanced for blocks
     */
    public function supports($feature)
    {
        $supported_features = [
            'products',
            'refunds',
            'tokenization',
            'blocks',
            'block_checkout',
            'checkout_blocks'
        ];

        $result = in_array($feature, $supported_features) || parent::supports($feature);
        
        wc_instaxchange_debug_log("Gateway supports check for '{$feature}'", $result ? 'YES' : 'NO');
        
        return $result;
    }

    /**
     * Check if gateway supports blocks checkout
     */
    public function supports_blocks_checkout()
    {
        return true;
    }

    /**
     * Check if gateway supports block checkout
     */
    public function supports_block_checkout()
    {
        return true;
    }

    /**
     * Check if gateway supports WooCommerce blocks
     */
    public function supports_woocommerce_blocks()
    {
        return true;
    }

    /**
     * Check if gateway is compatible with blocks
     */
    public function is_blocks_compatible()
    {
        return true;
    }

    /**
     * Check if gateway supports WooCommerce blocks checkout
     */
    public function supports_wc_blocks_checkout()
    {
        return true;
    }

    /**
     * Check if gateway is blocks ready
     */
    public function is_blocks_ready()
    {
        return true;
    }

    /**
     * Check if gateway is compatible with WooCommerce blocks
     */
    public function is_wc_blocks_compatible()
    {
        return true;
    }

    /**
     * Check if gateway is WooCommerce blocks ready
     */
    public function is_wc_blocks_ready()
    {
        return true;
    }

    /**
     * Check if gateway supports WooCommerce blocks
     */
    public function supports_wc_blocks()
    {
        return true;
    }



    /**
     * Process admin options with security verification
     */
    public function process_admin_options()
    {
        // Verify nonce before saving options
        if (!isset($_POST['instaxchange_settings_nonce']) || !wp_verify_nonce($_POST['instaxchange_settings_nonce'], 'woocommerce_save_instaxchange_settings')) {
            wp_die('Security check failed');
        }

        // Call parent method to save options
        return parent::process_admin_options();
    }

    /**
     * Process the payment
     */
    public function process_payment($order_id)
    {
        try {
            wc_instaxchange_debug_log('Processing payment for order', $order_id);

            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Validate order
            if ($order->get_total() <= 0) {
                throw new Exception('Invalid order total');
            }

            // Set payment method
            $order->set_payment_method($this->id);
            $order->set_payment_method_title($this->title);

            // Set initial status - use 'pending' for payment processing
            $order->update_status('pending', 'Awaiting InstaxChange payment');
            $order->update_meta_data('_instaxchange_payment_initiated', current_time('mysql'));
            $order->save();

            // Create payment session
            $payment_session = $this->create_payment_session($order, 'card');

            if ($payment_session && isset($payment_session['session_id'])) {
                // Store session data
                $order->update_meta_data('_instaxchange_session_id', $payment_session['session_id']);
                $order->update_meta_data('_instaxchange_iframe_url', $payment_session['iframe_url']);
                $order->update_meta_data('_instaxchange_payment_data', $payment_session['payment_data']);
                $order->save();

                // Redirect to payment page
                $payment_url = $this->get_payment_page_url($order);

                return array(
                    'result' => 'success',
                    'redirect' => $payment_url
                );
            } else {
                throw new Exception('Failed to create payment session');
            }

        } catch (Exception $e) {
            wc_instaxchange_debug_log('Payment processing failed', $e->getMessage());
            wc_add_notice('Payment setup failed: ' . $e->getMessage(), 'error');
            return array('result' => 'fail');
        }
    }

    /**
     * Create payment session with API integration
     */
    public function create_payment_session($order, $payment_method = 'card')
    {
        try {
            wc_instaxchange_debug_log('Creating payment session for order', $order->get_id());

            // Validate required credentials before making API call
            if (empty($this->account_ref_id) || empty($this->wallet_address)) {
                $missing_fields = array();
                if (empty($this->account_ref_id))
                    $missing_fields[] = 'Account Reference ID';
                if (empty($this->wallet_address))
                    $missing_fields[] = 'Wallet Address';

                throw new Exception('InstaxChange configuration incomplete. Missing: ' . implode(', ', $missing_fields) .
                    '. Please configure these in WooCommerce > Settings > Payments > InstaxChange.');
            }

            $account_ref_id = $this->account_ref_id;
            $webhook_secret = $this->webhook_secret;
            $wallet_address = $this->wallet_address;

            // Map payment methods to InstaxChange supported methods
            $instaxchange_methods = array(
                'card' => 'card',
                'apple-pay' => 'apple_pay',
                'google-pay' => 'google_pay',
                'paypal' => 'paypal',
                // Regional methods - InstaxChange may not support all of these
                'ideal' => 'ideal',
                'bancontact' => 'bancontact',
                'interac' => 'interac',
                'pix' => 'pix',
                'sepa' => 'sepa',
                'poli' => 'poli',
                'blik' => 'blik',
            );

            // Use mapped method or default to 'card' if not supported
            $api_method = isset($instaxchange_methods[$payment_method]) ? $instaxchange_methods[$payment_method] : 'card';

            // Map cryptocurrency selection to InstaxChange API format
            $to_currency = $this->default_crypto;
            if ($this->default_crypto === 'USDC_POLYGON') {
                $to_currency = 'USDC'; // InstaxChange API uses 'USDC' for Polygon USDC
            }

            // Prepare payment data according to InstaxChange API documentation
            $payment_data = array(
                'accountRefId' => $account_ref_id, // Mandatory: unique account identifier from dashboard
                'fromAmount' => floatval($order->get_total()), // Amount being sent
                'fromCurrency' => $order->get_currency(), // Currency being sent (USD, EUR, etc.)
                'toCurrency' => $to_currency, // Currency to convert to (USDC, USDT, etc.)
                'address' => $wallet_address, // Mandatory: blockchain address to receive funds
                'amountDirection' => 'sending', // Direction of amount calculation
                'webhookRef' => 'order_' . $order->get_id() . '_' . time(), // Unique reference for webhook
                'returnUrl' => $this->get_return_url($order), // URL to redirect after payment
                'method' => $api_method, // Payment method mapped to InstaxChange format
                'network' => ($this->default_crypto === 'USDC_POLYGON') ? 'polygon' : null, // Specify network for Polygon USDC
            );

            // Remove null network value if not needed
            if ($payment_data['network'] === null) {
                unset($payment_data['network']);
            }

            wc_instaxchange_debug_log('Payment data prepared', $payment_data);

            // Try multiple API endpoints
            $api_endpoints = array(
                'https://instaxchange.com/api/session',
                'https://api.instaxchange.com/session',
            );

            $response = null;
            $last_error = '';

            foreach ($api_endpoints as $endpoint) {
                wc_instaxchange_debug_log('Trying endpoint', $endpoint);

                $response = wp_remote_post($endpoint, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'WooCommerce-InstaxChange/' . WC_INSTAXCHANGE_VERSION,
                        'Accept' => 'application/json'
                    ),
                    'body' => json_encode($payment_data),
                    'timeout' => WC_INSTAXCHANGE_API_TIMEOUT,
                    'sslverify' => true
                ));

                if (!is_wp_error($response)) {
                    $response_code = wp_remote_retrieve_response_code($response);
                    if ($response_code === 200 || $response_code === 201) {
                        break;
                    } else {
                        $last_error = 'HTTP ' . $response_code;
                    }
                } else {
                    $last_error = $response->get_error_message();
                }
            }

            // Handle API failures
            if (is_wp_error($response) || !in_array(wp_remote_retrieve_response_code($response), [200, 201])) {
                $response_code = is_wp_error($response) ? 'WP_ERROR' : wp_remote_retrieve_response_code($response);
                $response_body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);

                wc_instaxchange_log('API request failed', [
                    'response_code' => $response_code,
                    'response_body' => substr($response_body, 0, 200),
                    'order_id' => $order->get_id()
                ], 'error');

                // Check if it's a 400 Bad Request (unsupported payment method)
                if ($response_code === 400) {
                    wc_instaxchange_log('Payment method not supported by InstaxChange API', $payment_method, 'warning');
                    throw new Exception('Payment method not supported. Please use credit card payment.');
                }

                // In production, fail immediately - no demo mode
                if (defined('WC_INSTAXCHANGE_PRODUCTION') && WC_INSTAXCHANGE_PRODUCTION) {
                    // Log critical error for admin notification
                    wc_instaxchange_log('CRITICAL: Payment gateway API unavailable in production', [
                        'order_id' => $order->get_id(),
                        'error' => $response_code . ': ' . $response_body
                    ], 'error');

                    // Notify admin via email about critical failure
                    $this->notify_admin_critical_error($order, $response_code, $response_body);

                    throw new Exception('Payment gateway temporarily unavailable. Please try again later or contact support.');
                }

                // Only allow demo mode in development/test environments
                wc_instaxchange_debug_log('Creating demo session for testing (development mode only)');

                // Create demo session for testing in non-production only
                $demo_session_id = 'demo_' . $order->get_id() . '_' . time();

                return array(
                    'session_id' => $demo_session_id,
                    'iframe_url' => 'https://instaxchange.com/embed/' . $demo_session_id,
                    'payment_url' => 'https://instaxchange.com/pay/' . $demo_session_id,
                    'payment_data' => $payment_data,
                    'demo_mode' => true,
                    'api_error' => 'HTTP ' . $response_code . ': ' . $response_body
                );
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            wc_instaxchange_debug_log('API Response received', $response_body);

            // Extract session ID
            $session_id = null;
            if (isset($response_data['sessionId'])) {
                $session_id = $response_data['sessionId'];
            } elseif (isset($response_data['id'])) {
                $session_id = $response_data['id'];
            }

            if ($session_id) {
                return array(
                    'session_id' => $session_id,
                    'iframe_url' => 'https://instaxchange.com/embed/' . $session_id,
                    'payment_url' => 'https://instaxchange.com/pay/' . $session_id,
                    'payment_data' => $payment_data,
                    'api_response' => $response_data
                );
            } else {
                throw new Exception('No session ID in API response');
            }

        } catch (Exception $e) {
            wc_instaxchange_debug_log('Session creation error', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Notify admin of critical payment gateway errors
     */
    private function notify_admin_critical_error($order, $error_code, $error_message)
    {
        // Check if we've already sent a notification recently (prevent spam)
        $last_notification = get_transient('instaxchange_last_critical_error_notification');
        if ($last_notification) {
            return; // Don't send multiple notifications within 15 minutes
        }

        // Set transient to prevent notification spam
        set_transient('instaxchange_last_critical_error_notification', time(), 15 * MINUTE_IN_SECONDS);

        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');

        $subject = '[CRITICAL] InstaxChange Payment Gateway Error - ' . $site_name;

        $message = "CRITICAL ERROR: InstaxChange Payment Gateway API Failure\n\n";
        $message .= "A customer attempted to make a payment but the InstaxChange API is unavailable.\n\n";
        $message .= "Order Details:\n";
        $message .= "- Order ID: #" . $order->get_id() . "\n";
        $message .= "- Order Total: " . $order->get_formatted_order_total() . "\n";
        $message .= "- Customer Email: " . $order->get_billing_email() . "\n\n";
        $message .= "Error Details:\n";
        $message .= "- Error Code: " . $error_code . "\n";
        $message .= "- Error Message: " . substr($error_message, 0, 200) . "\n\n";
        $message .= "Action Required:\n";
        $message .= "1. Check InstaxChange API status\n";
        $message .= "2. Verify gateway credentials in WooCommerce > Settings > Payments > InstaxChange\n";
        $message .= "3. Contact InstaxChange support if issue persists\n\n";
        $message .= "Timestamp: " . current_time('Y-m-d H:i:s') . "\n";
        $message .= "---\n";
        $message .= "This is an automated notification from " . $site_name;

        // Send email notification
        wp_mail($admin_email, $subject, $message);

        // Also store in WordPress options for admin dashboard viewing
        $error_log = get_option('instaxchange_error_log', []);
        $error_log[] = [
            'timestamp' => current_time('mysql'),
            'order_id' => $order->get_id(),
            'error_code' => $error_code,
            'error_message' => substr($error_message, 0, 200)
        ];
        // Keep only last 10 errors
        $error_log = array_slice($error_log, -10);
        update_option('instaxchange_error_log', $error_log);
    }

    /**
     * Get payment page URL
     */
    private function get_payment_page_url($order)
    {
        $payment_url = add_query_arg(array(
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'instaxchange_payment' => '1'
        ), home_url('/instaxchange-payment/'));

        return $payment_url;
    }

    /**
     * AJAX handler for creating payment sessions
     */
    public function ajax_create_session()
    {
        try {
            wc_instaxchange_debug_log('AJAX create session request received');

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

            // Update default crypto if specified
            if (!empty($cryptocurrency)) {
                $this->default_crypto = $cryptocurrency;
            }

            // Create session
            wc_instaxchange_debug_log('Creating payment session for method', $payment_method);
            try {
                $payment_session = $this->create_payment_session($order, $payment_method);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Payment method not supported') !== false) {
                    wc_instaxchange_debug_log('Payment method not supported, falling back to card', $payment_method);
                    // Fallback to card payment
                    $payment_session = $this->create_payment_session($order, 'card');
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
    public function ajax_check_status()
    {
        try {
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

            // Enhanced payment status checking
            $payment_status = $order->get_meta('_instaxchange_payment_status');
            $transaction_id = $order->get_transaction_id();
            $order_status = $order->get_status();

            wc_instaxchange_debug_log('AJAX status check', [
                'order' => $order_id,
                'payment_status' => $payment_status,
                'order_status' => $order_status,
                'transaction_id' => $transaction_id
            ]);

            // Check if payment is completed
            if ($order->is_paid() || $payment_status === 'completed') {
                // Ensure order status is properly set
                if ($order_status === 'cancelled') {
                    $order->update_status('processing', 'Payment completed, updating order status from cancelled');
                    $order_status = 'processing';
                }

                wp_send_json_success(array(
                    'status' => 'completed',
                    'status_text' => 'Payment Completed',
                    'transaction_id' => $transaction_id ?: 'Completed',
                    'order_status' => $order_status
                ));
            } else {
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
                    wp_send_json_success(array(
                        'status' => $order_status,
                        'status_text' => $this->get_status_display_text($order_status),
                        'transaction_id' => $transaction_id ?: 'Pending',
                        'order_status' => $order_status
                    ));
                }
            }

        } catch (Exception $e) {
            wc_instaxchange_debug_log('AJAX status check error', $e->getMessage());
            wp_send_json_error('Status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get human-readable status text
     */
    private function get_status_display_text($status)
    {
        switch ($status) {
            case 'pending':
                return 'Awaiting Payment';
            case 'processing':
                return 'Processing Payment';
            case 'completed':
                return 'Payment Completed';
            case 'failed':
                return 'Payment Failed';
            case 'cancelled':
                return 'Payment Cancelled';
            default:
                return ucfirst(str_replace('-', ' ', $status));
        }
    }

    /**
     * Payment fields for checkout - required for gateway display
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        // Add hidden field to identify this gateway
        echo '<input type="hidden" name="instaxchange_gateway_selected" value="1" />';

        // Add some basic information about the gateway
        echo '<div class="instaxchange-checkout-info" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #667eea;">';
        echo '<p style="margin: 0; font-size: 14px;"><strong>🔒 Secure Payment Gateway</strong></p>';
        echo '<p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">You will be redirected to complete your payment securely.</p>';
        echo '</div>';
    }

    /**
     * Receipt page
     */
    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $payment_url = $this->get_payment_page_url($order);
            wp_redirect($payment_url);
            exit;
        }
    }

    /**
     * Admin options
     */
    public function admin_options()
    {
        // Enqueue admin CSS
        wp_enqueue_style(
            'instaxchange-admin-settings',
            WC_INSTAXCHANGE_PLUGIN_URL . 'assets/css/admin-settings.css',
            array(),
            WC_INSTAXCHANGE_VERSION
        );

        // Enqueue admin JavaScript for webhook testing
        wp_enqueue_script(
            'instaxchange-admin-webhook-test',
            WC_INSTAXCHANGE_PLUGIN_URL . 'assets/js/admin-webhook-test.js',
            array('jquery'),
            WC_INSTAXCHANGE_VERSION,
            true
        );

        // Localization is now handled in the main plugin file

        echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
        echo '<p>' . esc_html($this->get_method_description()) . '</p>';

        // Add nonce field for settings form
        wp_nonce_field('woocommerce_save_instaxchange_settings', 'instaxchange_settings_nonce');

        // Configuration status with validation
        echo '<div class="instaxchange-config-status">';
        echo '<h3>Configuration Status</h3>';

        // Run validation
        $validation_errors = $this->validate_configuration();
        $is_valid = empty($validation_errors);

        echo '<p><strong>Gateway Enabled:</strong> ' . ($this->enabled === 'yes' ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Test Mode:</strong> ' . ($this->testmode === 'yes' ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Environment:</strong> ' . (defined('WC_INSTAXCHANGE_PRODUCTION') && WC_INSTAXCHANGE_PRODUCTION ? '🔴 Production' : '🟡 Development') . '</p>';
        echo '<p><strong>Available:</strong> ' . ($this->is_available() ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Configuration Valid:</strong> ' . ($is_valid ? '✅ Yes' : '❌ No') . '</p>';

        // Show validation errors if any
        if (!$is_valid) {
            echo '<div style="background: #fff3cd; border: 1px solid #856404; padding: 10px; border-radius: 4px; margin: 10px 0;">';
            echo '<p style="margin: 0; color: #856404;"><strong>⚠️ Configuration Issues:</strong></p>';
            echo '<ul style="margin: 5px 0 0 20px; color: #856404;">';
            foreach ($validation_errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div style="background: #d4edda; border: 1px solid #28a745; padding: 10px; border-radius: 4px; margin: 10px 0;">';
            echo '<p style="margin: 0; color: #155724;"><strong>✅ Configuration Valid:</strong> Gateway is ready to accept payments.</p>';
            echo '</div>';
        }

        echo '<p><strong>Account ID:</strong> ' . (!empty($this->account_ref_id) ? '✅ Configured' : '❌ REQUIRED') . '</p>';
        echo '<p><strong>Wallet Address:</strong> ' . (!empty($this->wallet_address) ? '✅ Configured' : '❌ REQUIRED') . '</p>';

        // Display cryptocurrency information with network details
        $crypto_display = $this->default_crypto;
        if ($this->default_crypto === 'USDC_POLYGON') {
            $crypto_display = 'USDC (Polygon Network)';
        } elseif ($this->default_crypto === 'USDC') {
            $crypto_display = 'USDC (Ethereum Network)';
        }

        echo '<p><strong>Default Cryptocurrency:</strong> ' . esc_html($crypto_display) . '</p>';
        echo '<p><a href="' . esc_url(wc_get_checkout_url()) . '" target="_blank" class="button">Test Checkout</a></p>';

        // Order status management section
        echo '<div class="instaxchange-order-management" style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 15px; margin: 20px 0; border-radius: 4px;">';
        echo '<h3>Order Status Management</h3>';
        echo '<p><strong>Check Stuck Orders:</strong> <button type="button" class="button button-secondary" onclick="checkStuckOrders()">Check & Fix Stuck Orders</button></p>';
        echo '<p><small>This will check for orders that have completed payments but are stuck in "pending" status and update them appropriately.</small></p>';
        echo '<div id="stuck-orders-result" style="margin-top: 10px;"></div>';
        echo '<p><strong>Cron Status:</strong> ' . (wp_next_scheduled('instaxchange_check_stuck_orders') ? '✅ Scheduled (runs hourly)' : '❌ Not scheduled') . '</p>';
        echo '<p><small>The automatic check runs every hour. Use the manual check above for immediate processing.</small></p>';
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

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }
}
?>