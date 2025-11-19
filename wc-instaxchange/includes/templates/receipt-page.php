<?php
/**
 * InstaxChange Receipt Page Template
 *
 * Clean version with external JavaScript and CSS
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get order details
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$order = wc_get_order($order_id);

if (!$order) {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>

    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php _e('Order Not Found', 'wc-instaxchange'); ?> - <?php bloginfo('name'); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 0;
                padding: 20px;
                background: #f5f7fa;
            }

            .error-container {
                max-width: 600px;
                margin: 50px auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                text-align: center;
            }

            .error-icon {
                font-size: 48px;
                color: #dc3545;
                margin-bottom: 20px;
            }

            h1 {
                color: #333;
                margin-bottom: 15px;
            }

            p {
                color: #666;
                margin-bottom: 20px;
            }

            .back-link {
                display: inline-block;
                padding: 10px 20px;
                background: #007cba;
                color: white;
                text-decoration: none;
                border-radius: 4px;
            }

            .back-link:hover {
                background: #005a87;
            }
        </style>
    </head>

    <body>
        <div class="error-container">
            <div class="error-icon">❌</div>
            <h1><?php _e('Order Not Found', 'wc-instaxchange'); ?></h1>
            <p><?php _e('The order you are looking for could not be found. Please check your payment link or contact support.', 'wc-instaxchange'); ?>
            </p>
            <a href="<?php echo esc_url(home_url()); ?>"
                class="back-link"><?php _e('Return to Home', 'wc-instaxchange'); ?></a>
        </div>
    </body>

    </html>
    <?php
    exit;
}

$session_id = $order->get_meta('_instaxchange_session_id');
$order_total = $order->get_total();
$order_currency = $order->get_currency();

// Get gateway instance to access settings
$gateway = new WC_InstaxChange_Gateway();
$default_crypto = $gateway->default_crypto;

// Enqueue the receipt page JavaScript
wp_enqueue_script(
    'instaxchange-receipt-page',
    WC_INSTAXCHANGE_PLUGIN_URL . 'assets/js/receipt-page.js',
    array('jquery'),
    WC_INSTAXCHANGE_VERSION,
    true
);

// Enqueue the receipt page CSS
wp_enqueue_style(
    'instaxchange-receipt-page',
    WC_INSTAXCHANGE_PLUGIN_URL . 'assets/css/receipt-page.css',
    array(),
    WC_INSTAXCHANGE_VERSION
);

// Localize data for JavaScript
wp_localize_script('instaxchange-receipt-page', 'instaxchangeData', array(
    'orderId' => $order->get_id(),
    'currentSessionId' => $session_id,
    'currentCrypto' => $default_crypto,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'orderReceivedUrl' => $order->get_checkout_order_received_url(),
    'nonces' => array(
        'createSession' => wp_create_nonce('instaxchange_create_session'),
        'checkStatus' => wp_create_nonce('instaxchange_check_status')
    )
));

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php printf(__('Complete Payment for Order #%s', 'wc-instaxchange'), $order->get_order_number()); ?> -
        <?php bloginfo('name'); ?>
    </title>

    <?php wp_head(); ?>

    <!-- Preload critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Favicon -->
    <link rel="icon" href="<?php echo esc_url(get_site_icon_url(32)); ?>" sizes="32x32">
</head>

<body <?php body_class('instaxchange-payment-page'); ?>>

    <div class="instaxchange-payment-page">
        <div class="instaxchange-container">
            <div class="woocommerce-notices-wrapper"></div>

            <div class="instaxchange-payment-wrapper">
                <!-- Header -->
                <div class="instaxchange-header">
                    <div class="instaxchange-logo">
                        <span class="payment-icon">💳</span>
                        <h1>Complete Your Payment</h1>
                    </div>
                    <div class="order-summary-badge">
                        <span class="order-number">Order #<?php echo $order->get_order_number(); ?></span>
                        <span class="order-total"><?php echo $order->get_formatted_order_total(); ?></span>
                    </div>
                </div>

                <?php if ($session_id): ?>
                    <!-- Payment Interface Section -->
                    <div class="instaxchange-payment-section">
                        <!-- Payment Status Indicator -->
                        <div class="payment-status-indicator">
                            <div class="status-icon success">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <div class="status-content">
                                <h3>Payment Session Ready</h3>
                                <p>Your secure payment interface is ready. Complete your payment using any available method.
                                </p>
                            </div>
                        </div>

                        <!-- Payment Method Selection -->
                        <div class="payment-method-selection compact">
                            <h5>Select Payment Method</h5>
                            <p>Choose your preferred payment method. InstaxChange will show the best options for your
                                region.
                            </p>

                            <div class="method-categories compact">
                                <?php if ($gateway->get_option('enable_traditional_methods') === 'yes'): ?>
                                    <!-- Traditional Payment Methods -->
                                    <div class="method-category compact">
                                        <h6>💳 Traditional Payments</h6>
                                        <div class="method-buttons traditional-methods compact">
                                            <?php if ($gateway->get_option('enable_card') === 'yes'): ?>
                                                <button type="button" class="method-btn active compact" data-method="card"
                                                    onclick="switchPaymentMethod('card')">
                                                    <span class="method-icon">💳</span>
                                                    <span class="method-name">Credit/Debit Cards</span>
                                                    <span class="method-desc">Visa, Mastercard, Amex</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_apple_pay') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="apple-pay"
                                                    onclick="switchPaymentMethod('apple-pay')">
                                                    <span class="method-icon">🍎</span>
                                                    <span class="method-name">Apple Pay</span>
                                                    <span class="method-desc">Quick & secure</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_google_pay') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="google-pay"
                                                    onclick="switchPaymentMethod('google-pay')">
                                                    <span class="method-icon">📱</span>
                                                    <span class="method-name">Google Pay</span>
                                                    <span class="method-desc">Fast mobile payment</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($gateway->get_option('enable_regional_methods') === 'yes'): ?>
                                    <!-- Regional Payment Methods -->
                                    <div class="method-category compact">
                                        <h6>🌍 Regional Methods</h6>
                                        <div class="method-buttons regional-methods compact">
                                            <?php if ($gateway->get_option('enable_ideal') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="ideal"
                                                    onclick="switchPaymentMethod('ideal')">
                                                    <span class="method-icon">🇳🇱</span>
                                                    <span class="method-name">iDEAL</span>
                                                    <span class="method-desc">Dutch bank transfer</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_bancontact') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="bancontact"
                                                    onclick="switchPaymentMethod('bancontact')">
                                                    <span class="method-icon">🇧🇪</span>
                                                    <span class="method-name">Bancontact</span>
                                                    <span class="method-desc">Belgian payment</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_interac') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="interac"
                                                    onclick="switchPaymentMethod('interac')">
                                                    <span class="method-icon">🇨🇦</span>
                                                    <span class="method-name">Interac</span>
                                                    <span class="method-desc">Canadian e-Transfer</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_pix') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="pix"
                                                    onclick="switchPaymentMethod('pix')">
                                                    <span class="method-icon">🇧🇷</span>
                                                    <span class="method-name">PIX</span>
                                                    <span class="method-desc">Brazilian instant payment</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_sepa') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="sepa"
                                                    onclick="switchPaymentMethod('sepa')">
                                                    <span class="method-icon">🇪🇺</span>
                                                    <span class="method-name">SEPA</span>
                                                    <span class="method-desc">European bank transfer</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_poli') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="poli"
                                                    onclick="switchPaymentMethod('poli')">
                                                    <span class="method-icon">🇦🇺</span>
                                                    <span class="method-name">POLi</span>
                                                    <span class="method-desc">Australian online banking</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($gateway->get_option('enable_blik') === 'yes'): ?>
                                                <button type="button" class="method-btn compact" data-method="blik"
                                                    onclick="switchPaymentMethod('blik')">
                                                    <span class="method-icon">🇵🇱</span>
                                                    <span class="method-name">BLIK</span>
                                                    <span class="method-desc">Polish mobile payment</span>
                                                </button>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($gateway->get_option('enable_crypto') === 'yes'): ?>
                                    <!-- Cryptocurrency Payment Methods -->
                                    <div class="method-category compact">
                                        <h6>₿ Cryptocurrency</h6>
                                        <div class="method-buttons crypto-methods compact">
                                            <button type="button" class="method-btn compact" data-method="bitcoin"
                                                onclick="switchPaymentMethod('bitcoin')">
                                                <span class="method-icon">₿</span>
                                                <span class="method-name">Bitcoin</span>
                                                <span class="method-desc">BTC - Digital gold</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="ethereum"
                                                onclick="switchPaymentMethod('ethereum')">
                                                <span class="method-icon">⧫</span>
                                                <span class="method-name">Ethereum</span>
                                                <span class="method-desc">ETH - Smart contracts</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="usdc"
                                                onclick="switchPaymentMethod('usdc')">
                                                <span class="method-icon">💵</span>
                                                <span class="method-name">USDC</span>
                                                <span class="method-desc">Stable digital dollar</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="usdc-polygon"
                                                onclick="switchPaymentMethod('usdc-polygon')">
                                                <span class="method-icon">🟢</span>
                                                <span class="method-name">USDC (Polygon)</span>
                                                <span class="method-desc">Lower gas fees</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="litecoin"
                                                onclick="switchPaymentMethod('litecoin')">
                                                <span class="method-icon">Ł</span>
                                                <span class="method-name">Litecoin</span>
                                                <span class="method-desc">LTC - Fast payments</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="tether"
                                                onclick="switchPaymentMethod('tether')">
                                                <span class="method-icon">₮</span>
                                                <span class="method-name">Tether</span>
                                                <span class="method-desc">USDT - Stablecoin</span>
                                            </button>

                                            <!-- More crypto options button -->
                                            <button type="button" class="method-btn compact method-btn-more" onclick="showMoreCryptoOptions()">
                                                <span class="method-icon">⚡</span>
                                                <span class="method-name">More Crypto</span>
                                                <span class="method-desc">SOL, ADA, DOT & more</span>
                                            </button>
                                        </div>
                                        
                                        <!-- Additional crypto options (initially hidden) -->
                                        <div class="method-buttons crypto-methods-extended compact" style="display: none;">
                                            <button type="button" class="method-btn compact" data-method="solana"
                                                onclick="switchPaymentMethod('solana')">
                                                <span class="method-icon">◎</span>
                                                <span class="method-name">Solana</span>
                                                <span class="method-desc">SOL - High speed</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="cardano"
                                                onclick="switchPaymentMethod('cardano')">
                                                <span class="method-icon">₳</span>
                                                <span class="method-name">Cardano</span>
                                                <span class="method-desc">ADA - Sustainable</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="polkadot"
                                                onclick="switchPaymentMethod('polkadot')">
                                                <span class="method-icon">●</span>
                                                <span class="method-name">Polkadot</span>
                                                <span class="method-desc">DOT - Interchain</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="avalanche"
                                                onclick="switchPaymentMethod('avalanche')">
                                                <span class="method-icon">🔺</span>
                                                <span class="method-name">Avalanche</span>
                                                <span class="method-desc">AVAX - Fast consensus</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="chainlink"
                                                onclick="switchPaymentMethod('chainlink')">
                                                <span class="method-icon">🔗</span>
                                                <span class="method-name">Chainlink</span>
                                                <span class="method-desc">LINK - Oracle network</span>
                                            </button>

                                            <button type="button" class="method-btn compact" data-method="ripple"
                                                onclick="switchPaymentMethod('ripple')">
                                                <span class="method-icon">◉</span>
                                                <span class="method-name">Ripple</span>
                                                <span class="method-desc">XRP - Cross border</span>
                                            </button>
                                        </div>

                                        <div class="crypto-info-note">
                                            <p><strong>💡 Crypto Benefits:</strong> Direct payments, lower fees, no chargebacks. All major cryptocurrencies supported with automatic conversion.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <div class="method-selection-note compact">
                                <p><strong>Note:</strong> Payment methods shown depend on your location and admin
                                    configuration.
                                    InstaxChange automatically optimizes available options for the best conversion rates.
                                </p>
                            </div>


                            <?php
                            // Check if any payment methods are enabled
                            $has_traditional = $gateway->get_option('enable_traditional_methods') === 'yes' &&
                                ($gateway->get_option('enable_card') === 'yes' ||
                                    $gateway->get_option('enable_apple_pay') === 'yes' ||
                                    $gateway->get_option('enable_google_pay') === 'yes');

                            $has_regional = $gateway->get_option('enable_regional_methods') === 'yes' &&
                                ($gateway->get_option('enable_ideal') === 'yes' ||
                                    $gateway->get_option('enable_bancontact') === 'yes' ||
                                    $gateway->get_option('enable_interac') === 'yes' ||
                                    $gateway->get_option('enable_pix') === 'yes' ||
                                    $gateway->get_option('enable_sepa') === 'yes' ||
                                    $gateway->get_option('enable_poli') === 'yes' ||
                                    $gateway->get_option('enable_blik') === 'yes');

                            $has_crypto = $gateway->get_option('enable_crypto') === 'yes';

                            if (!$has_traditional && !$has_regional && !$has_crypto): ?>
                                <div class="no-methods-warning">
                                    <p><strong>⚠️ No Payment Methods Available:</strong> Please enable at least one payment
                                        method
                                        in the admin settings.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Interface -->
                        <div class="payment-interface-container">

                            <div class="payment-interface-header">
                                <h4><span class="icon">🔒</span> Secure Payment Interface</h4>
                                <p>Powered by InstaxChange - Your payment is encrypted and secure</p>
                            </div>

                            <div class="iframe-wrapper">
                                <iframe id="instaxchange-payment-iframe" src="" title="InstaxChange Payment Interface"
                                    frameborder="0" allowtransparency="true" allowpaymentrequest="true" allow="payment">
                                </iframe>
                                <div class="iframe-loading-overlay">
                                    <div class="loading-spinner"></div>
                                    <p>Loading secure payment interface...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Status -->
                        <div class="payment-status-section">
                            <h4>Payment Status</h4>
                            <div class="status-check-container">
                                <button type="button" class="instax-button secondary" onclick="checkPaymentStatus()">
                                    <span class="dashicons dashicons-update"></span>
                                    Check Payment Status
                                </button>
                                <div id="status-result" class="status-result"></div>
                            </div>

                        </div>
                    </div>

                <?php else: ?>
                    <!-- Error Section -->
                    <div class="instaxchange-error-section">
                        <div class="error-icon">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <div class="error-content">
                            <h3>Payment Gateway Not Configured</h3>
                            <p>The InstaxChange payment gateway is not properly configured. Please contact the store
                                administrator to set up the following:</p>
                            <ul>
                                <li>InstaxChange Account Reference ID</li>
                                <li>Cryptocurrency Wallet Address</li>
                                <li>Webhook Configuration</li>
                            </ul>
                            <p
                                style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #007cba;">
                                <strong>Admin:</strong> Configure these settings in
                                <code>WooCommerce > Settings > Payments > InstaxChange</code>
                            </p>
                            <div class="error-actions">
                                <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="instax-button primary">
                                    Return to Checkout
                                </a>
                                <button type="button" class="instax-button secondary" onclick="location.reload()">
                                    Refresh Page
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Details -->
                <div class="order-details-section">
                    <button type="button" class="details-toggle" onclick="toggleOrderDetails()">
                        <span class="toggle-text">View Order Details</span>
                        <span class="toggle-icon">▼</span>
                    </button>

                    <div class="order-details-content" style="display: none;">
                        <div class="order-info-grid">
                            <div class="info-item">
                                <span class="label">Order Number:</span>
                                <span class="value">#<?php echo $order->get_order_number(); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Order Date:</span>
                                <span class="value"><?php echo $order->get_date_created()->format('M j, Y'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Order Status:</span>
                                <span
                                    class="value status-<?php echo $order->get_status(); ?>"><?php echo wc_get_order_status_name($order->get_status()); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Payment Method:</span>
                                <span class="value">InstaxChange</span>
                            </div>
                            <div class="info-item">
                                <span class="label">Subtotal:</span>
                                <span class="value"><?php echo $order->get_subtotal_to_display(); ?></span>
                            </div>
                            <?php if ($order->get_shipping_total() > 0): ?>
                                <div class="info-item">
                                    <span class="label">Shipping:</span>
                                    <span class="value"><?php echo $order->get_shipping_to_display(); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($order->get_total_tax() > 0): ?>
                                <div class="info-item">
                                    <span class="label">Tax:</span>
                                    <span class="value"><?php echo $order->get_total_tax(); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="info-item total-item">
                                <span class="label">Total Amount:</span>
                                <span class="value"><?php echo $order->get_formatted_order_total(); ?></span>
                            </div>
                            <?php if ($order->get_billing_email()): ?>
                                <div class="info-item">
                                    <span class="label">Billing Email:</span>
                                    <span class="value"><?php echo $order->get_billing_email(); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($order->get_billing_phone()): ?>
                                <div class="info-item">
                                    <span class="label">Billing Phone:</span>
                                    <span class="value"><?php echo $order->get_billing_phone(); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>

</html>