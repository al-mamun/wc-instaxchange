<?php
/**
 * Plugin Name: WooCommerce InstaxChange Gateway
 * Plugin URI: https://imamundevstudios.com/plugins/wc-instaxchange
 * Description: Accept cryptocurrency payments via InstaxChange payment gateway
 * Version: 1.0.4
 * Author: Md. Abdullah Al Mamun
 * Author URI: https://imamundevstudios.com/
 * Contributors: al-mamun
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.2
 * Text Domain: wc-instaxchange
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_INSTAXCHANGE_VERSION', '1.0.4');
define('WC_INSTAXCHANGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_INSTAXCHANGE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_INSTAXCHANGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Debug class for troubleshooting
 */
class WC_InstaxChange_Debug
{

    public function __construct()
    {
        // Only enable debug in WP_DEBUG mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array($this, 'debug_payment_page'));
            add_action('woocommerce_checkout_order_processed', array($this, 'debug_order_processed'), 10, 1);
        }
    }

    public function debug_payment_page()
    {
        global $wp;

        if (is_wc_endpoint_url('order-pay')) {
            $order_id = isset($wp->query_vars['order-pay']) ? $wp->query_vars['order-pay'] : 0;
            $order = wc_get_order($order_id);

            echo '<script>';
            echo 'console.log("=== INSTAXCHANGE PAYMENT PAGE DEBUG ===");';
            echo 'console.log("Order ID:", ' . $order_id . ');';

            if ($order) {
                echo 'console.log("Order exists: YES");';
                echo 'console.log("Payment method:", "' . esc_js($order->get_payment_method()) . '");';
                echo 'console.log("Order status:", "' . esc_js($order->get_status()) . '");';
                echo 'console.log("Order total:", "' . esc_js($order->get_total()) . '");';

                $gateways = WC()->payment_gateways->payment_gateways();
                if (isset($gateways['instaxchange'])) {
                    echo 'console.log("InstaxChange gateway loaded: YES");';
                } else {
                    echo 'console.log("InstaxChange gateway loaded: NO");';
                }
            } else {
                echo 'console.log("Order exists: NO");';
            }

            echo '</script>';
        }
    }

    public function debug_order_processed($order_id)
    {
        error_log('=== INSTAXCHANGE ORDER PROCESSED ===');
        error_log('Order ID: ' . $order_id);

        $order = wc_get_order($order_id);
        if ($order) {
            error_log('Payment method: ' . $order->get_payment_method());
            error_log('Order status: ' . $order->get_status());
        }
    }
}

/**
 * Main plugin class
 */
class WC_InstaxChange_Plugin
{

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'), 11);
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize debug
        new WC_InstaxChange_Debug();
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    public function init()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Include required files
        $this->includes();

        // Initialize the gateway
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));

        // Add plugin action links
        add_filter('plugin_action_links_' . WC_INSTAXCHANGE_PLUGIN_BASENAME, array($this, 'plugin_action_links'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Force payment page display for InstaxChange orders
        add_action('wp', array($this, 'force_payment_display'), 1);

        // AJAX handler for payment status
        add_action('wp_ajax_check_instaxchange_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_check_instaxchange_payment_status', array($this, 'ajax_check_payment_status'));

        // Force clear cached gateway settings
        add_action('admin_init', array($this, 'clear_gateway_cache'));
    }

    /**
     * Clear gateway cache to ensure settings update
     */
    public function clear_gateway_cache()
    {
        if (
            isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
            isset($_GET['section']) && $_GET['section'] === 'instaxchange'
        ) {

            // Force clear any cached gateway data
            delete_transient('woocommerce_payment_gateways');
            delete_option('woocommerce_instaxchange_settings_cached');
        }
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('wc-instaxchange', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function includes()
    {
        if (class_exists('WC_Payment_Gateway')) {
            include_once WC_INSTAXCHANGE_PLUGIN_PATH . 'includes/class-gateway.php';
            include_once WC_INSTAXCHANGE_PLUGIN_PATH . 'includes/class-admin.php';
            include_once WC_INSTAXCHANGE_PLUGIN_PATH . 'includes/class-webhook.php';
        }
    }

    public function add_gateway($gateways)
    {
        if (class_exists('WC_InstaxChange_Gateway')) {
            $gateways[] = 'WC_InstaxChange_Gateway';
        }
        return $gateways;
    }

    public function plugin_action_links($links)
    {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=instaxchange') . '">' . __('Settings', 'wc-instaxchange') . '</a>',
            '<a href="https://instaxchange.com/docs" target="_blank">' . __('Documentation', 'wc-instaxchange') . '</a>',
        );
        return array_merge($plugin_links, $links);
    }

    public function admin_scripts($hook)
    {
        if ($hook === 'woocommerce_page_wc-settings') {
            wp_enqueue_style(
                'wc-instaxchange-admin',
                WC_INSTAXCHANGE_PLUGIN_URL . 'assets/admin-style.css',
                array(),
                WC_INSTAXCHANGE_VERSION
            );
        }
    }

    /**
     * Force payment display for InstaxChange orders
     */
    public function force_payment_display()
    {
        global $wp;

        if (is_wc_endpoint_url('order-pay')) {
            $order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
            $order = wc_get_order($order_id);

            if ($order && $order->get_payment_method() === 'instaxchange') {
                error_log('InstaxChange: Force displaying payment page for order ' . $order_id);

                // Enqueue frontend styles immediately
                add_action('wp_enqueue_scripts', function () {
                    wp_enqueue_style(
                        'wc-instaxchange-frontend',
                        WC_INSTAXCHANGE_PLUGIN_URL . 'assets/style.css',
                        array(),
                        WC_INSTAXCHANGE_VERSION
                    );
                });

                // Add critical styles to hide default payment form
                add_action('wp_head', function () {
                    echo '<style>
                        /* Critical styles to hide default WooCommerce payment elements */
                        .woocommerce-checkout-payment,
                        .payment_methods,
                        .wc-proceed-to-checkout,
                        .place-order,
                        #payment .form-row,
                        .woocommerce-form-coupon-toggle,
                        .checkout-button {
                            display: none !important;
                        }
                        
                        /* Ensure InstaxChange interface is visible and styled */
                        .woocommerce .instaxchange-payment-wrapper {
                            display: block !important;
                            visibility: visible !important;
                        }
                        
                        /* Fallback loading indicator */
                        .loading-message {
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            text-align: center;
                            color: #6c757d;
                            z-index: 10;
                        }
                    </style>';
                });

                // Override content to show our payment interface
                add_filter('the_content', array($this, 'replace_with_payment_interface'), 999);
            }
        }
    }

    /**
     * Replace page content with payment interface
     */
    public function replace_with_payment_interface($content)
    {
        global $wp;

        if (is_wc_endpoint_url('order-pay')) {
            $order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
            $order = wc_get_order($order_id);

            if ($order && $order->get_payment_method() === 'instaxchange') {
                $gateways = WC()->payment_gateways->payment_gateways();
                $gateway = isset($gateways['instaxchange']) ? $gateways['instaxchange'] : null;

                if ($gateway) {
                    ob_start();
                    echo '<div class="woocommerce">';
                    $gateway->receipt_page($order_id);
                    echo '</div>';
                    return ob_get_clean();
                }
            }
        }

        return $content;
    }

    /**
     * AJAX handler for checking payment status
     */
    public function ajax_check_payment_status()
    {
        if (!isset($_POST['order_id']) || !isset($_POST['nonce'])) {
            wp_send_json_error('Missing parameters');
        }

        $order_id = intval($_POST['order_id']);
        $nonce = $_POST['nonce'];

        if (!wp_verify_nonce($nonce, 'check_payment_' . $order_id)) {
            wp_send_json_error('Security check failed');
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Order not found');
        }

        error_log('AJAX: Checking payment status for order ' . $order_id . ' - Status: ' . $order->get_status());

        wp_send_json_success(array(
            'paid' => $order->is_paid(),
            'status' => $order->get_status(),
            'order_id' => $order_id
        ));
    }

    public function activate()
    {
        // Create database tables
        $this->create_tables();
        update_option('wc_instaxchange_version', WC_INSTAXCHANGE_VERSION);

        // Clear any cached gateway settings on activation
        delete_transient('woocommerce_payment_gateways');
    }

    public function deactivate()
    {
        // Cleanup if needed
        delete_transient('woocommerce_payment_gateways');
    }

    private function create_tables()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'instaxchange_transactions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            session_id varchar(255) NOT NULL,
            transaction_id varchar(255),
            status varchar(50),
            webhook_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY session_id (session_id),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p><strong>' . sprintf(
            esc_html__('InstaxChange Gateway requires WooCommerce to be installed and active. You can download %s here.', 'wc-instaxchange'),
            '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
        ) . '</strong></p></div>';
    }
}

// Initialize the plugin
new WC_InstaxChange_Plugin();