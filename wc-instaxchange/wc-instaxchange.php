<?php
/**
 * Plugin Name: WooCommerce InstaxChange Gateway
 * Plugin URI: https://mamundevstudios.com
 * Description: Accept cryptocurrency payments via InstaxChange - ALL payment methods automatically enabled including cards, Apple Pay, Google Pay, bank transfers, and regional payment methods.
 * Version: 1.0.4
 * Author: Md. Abdullah Al Mamun
 * Author URI: https://mamundevstudios.com
 * Text Domain: wc-instaxchange
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.6
 * WC requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_INSTAXCHANGE_VERSION', '1.0.4');
define('WC_INSTAXCHANGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_INSTAXCHANGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_INSTAXCHANGE_PLUGIN_FILE', __FILE__);

// Production constants
if (!defined('WC_INSTAXCHANGE_DEBUG')) {
    define('WC_INSTAXCHANGE_DEBUG', false);
}

// Production environment detection
define('WC_INSTAXCHANGE_PRODUCTION', !WC_INSTAXCHANGE_DEBUG);

// API timeout settings
define('WC_INSTAXCHANGE_API_TIMEOUT', WC_INSTAXCHANGE_PRODUCTION ? 30 : 60);

// Maximum retry attempts for API calls
define('WC_INSTAXCHANGE_MAX_API_RETRIES', WC_INSTAXCHANGE_PRODUCTION ? 2 : 5);

// Webhook verification strictness
define('WC_INSTAXCHANGE_STRICT_WEBHOOK', WC_INSTAXCHANGE_PRODUCTION);

/**
 * Enhanced logging function with different levels
 */
function wc_instaxchange_log($message, $data = null, $level = 'debug')
{
    // Only log debug messages if debug mode is enabled
    if ($level === 'debug' && !WC_INSTAXCHANGE_DEBUG) {
        return;
    }

    // Always log errors and warnings in production
    if (in_array($level, ['error', 'warning']) || WC_INSTAXCHANGE_DEBUG) {
        $prefix = 'InstaxChange [' . strtoupper($level) . ']: ';
        $log_message = $prefix . $message;

        if ($data !== null) {
            $log_message .= ' - ' . (is_string($data) ? $data : json_encode($data));
        }

        error_log($log_message);
    }
}

/**
 * Backward compatibility - alias for debug logging
 */
function wc_instaxchange_debug_log($message, $data = null)
{
    wc_instaxchange_log($message, $data, 'debug');
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Check if WooCommerce is active
 */
function wc_instaxchange_check_woocommerce()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>InstaxChange Gateway:</strong> WooCommerce is required but not active. ';
            echo 'Please install and activate WooCommerce first.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}


/**
 * Main plugin initialization - ONLY runs after WooCommerce is loaded
 */
function wc_instaxchange_init()
{
    // Double-check WooCommerce is available
    if (!wc_instaxchange_check_woocommerce()) {
        return;
    }

    // Now it's safe to include the gateway class
    if (!class_exists('WC_InstaxChange_Gateway')) {
        // Include the gateway class file
        require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-gateway-simple.php';
    }

    // Include new modular classes
    if (!class_exists('WC_InstaxChange_Ajax_Handlers')) {
        require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
    }
    if (!class_exists('WC_InstaxChange_Webhook_Handler')) {
        require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-webhook-handler.php';
    }
    if (!class_exists('WC_InstaxChange_Admin_Settings')) {
        require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-admin-settings.php';
    }
    if (!class_exists('WC_InstaxChange_Theme_Compatibility')) {
        require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-theme-compatibility.php';
    }

    // Include blocks integration if WooCommerce Blocks is available
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        if (!class_exists('WC_InstaxChange_Blocks')) {
            require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-instaxchange-blocks.php';
        }
        wc_instaxchange_debug_log('Blocks integration class loaded');
    } else {
        wc_instaxchange_debug_log('WooCommerce Blocks AbstractPaymentMethodType not available');
    }

    // Initialize new modular classes
    WC_InstaxChange_Ajax_Handlers::init();
    WC_InstaxChange_Webhook_Handler::init();
    
    // Ensure gateway settings are properly displayed
    add_action('admin_init', function() {
        if (is_admin() && isset($_GET['page'], $_GET['tab'], $_GET['section']) 
            && $_GET['page'] === 'wc-settings' 
            && $_GET['tab'] === 'checkout' 
            && $_GET['section'] === 'instaxchange') {
            
            // Force refresh gateway instance to ensure all fields are loaded
            $gateways = WC()->payment_gateways();
            if (method_exists($gateways, 'init')) {
                $gateways->init();
            }
        }
    });
    WC_InstaxChange_Admin_Settings::init();
    WC_InstaxChange_Theme_Compatibility::init();


    // Load internationalization
    load_plugin_textdomain('wc-instaxchange', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // Localize data for blocks JavaScript and checkout
    add_action('wp_enqueue_scripts', function () {
        if (function_exists('is_checkout') && is_checkout()) {
            // Get gateway instance for settings
            $gateway = null;
            if (class_exists('WC_InstaxChange_Gateway')) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $gateway = isset($gateways['instaxchange']) ? $gateways['instaxchange'] : null;
            }
            
            $blocks_data = array(
                'title' => $gateway ? $gateway->get_title() : __('Pay with InstaxChange - All Methods Available', 'wc-instaxchange'),
                'description' => $gateway ? $gateway->get_description() : __('Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.', 'wc-instaxchange'),
                'enabled' => $gateway ? ($gateway->enabled === 'yes') : false,
                'testMode' => $gateway ? ($gateway->testmode === 'yes') : false,
                'supports' => array('products', 'refunds'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonces' => array(
                    'create_session' => wp_create_nonce('instaxchange_create_session'),
                    'check_status' => wp_create_nonce('instaxchange_check_status'),
                    'test_webhook' => wp_create_nonce('instaxchange_test_webhook')
                )
            );
            
            // Localize for classic checkout
            wp_localize_script('instaxchange-blocks', 'wcInstaxChangeData', $blocks_data);
            
            // Register data for blocks checkout via wcSettings
            wp_add_inline_script(
                'wc-settings',
                "window.wc = window.wc || {}; window.wc.wcSettings = window.wc.wcSettings || {}; window.wc.wcSettings.getSetting = window.wc.wcSettings.getSetting || function(key, fallback) { return window.wc.wcSettings[key] || fallback; }; window.wc.wcSettings.instaxchange_data = " . wp_json_encode($blocks_data) . ";",
                'before'
            );
            
            // Force enqueue blocks script on checkout
            wp_enqueue_script('wc-instaxchange-blocks');
        }
    });

    // Localize data for admin JavaScript
    add_action('admin_enqueue_scripts', function () {
        wp_localize_script('instaxchange-admin-webhook-test', 'instaxchangeAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'checkWebhookStatus' => wp_create_nonce('instaxchange_check_webhook_status'),
                'checkStuckOrders' => wp_create_nonce('instaxchange_check_stuck_orders')
            )
        ));
    });

    // Enqueue JavaScript files for classic checkout
    add_action('wp_enqueue_scripts', function () {
        if (function_exists('is_checkout') && is_checkout()) {
            // Enqueue checkout integration JavaScript
            wp_enqueue_script(
                'instaxchange-checkout-integration',
                WC_INSTAXCHANGE_PLUGIN_URL . 'assets/js/checkout-integration.js',
                array('jquery'),
                WC_INSTAXCHANGE_VERSION,
                true
            );

            // Enqueue theme fixes JavaScript
            wp_enqueue_script(
                'instaxchange-theme-fixes',
                WC_INSTAXCHANGE_PLUGIN_URL . 'assets/js/theme-fixes.js',
                array('jquery'),
                WC_INSTAXCHANGE_VERSION,
                true
            );
        }
    });

    /**
     * Add the gateway to WooCommerce
     */
    function wc_instaxchange_add_gateway($methods)
    {
        $methods[] = 'WC_InstaxChange_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'wc_instaxchange_add_gateway');

    // Enhanced gateway availability with theme compatibility
    add_filter('woocommerce_available_payment_gateways', function ($gateways) {
        wc_instaxchange_debug_log('Available gateways filter called');

        // Log current gateways
        if (is_array($gateways)) {
            wc_instaxchange_debug_log('Current available gateways', array_keys($gateways));
        } else {
            wc_instaxchange_debug_log('No gateways currently available');
            // Initialize empty array if no gateways are available
            $gateways = array();
        }

        // Always check registered gateways
        $registered_gateways = WC()->payment_gateways()->payment_gateways();

        if (isset($registered_gateways['instaxchange'])) {
            wc_instaxchange_debug_log('Gateway found in registered gateways');
            // Get the gateway from registered gateways
            $gateway = $registered_gateways['instaxchange'];

            // Enhanced availability check with theme compatibility
            if ($gateway->is_available()) {
                wc_instaxchange_debug_log('Gateway is available, adding to list');

                // Force add the gateway even if theme tries to hide it
                $gateways['instaxchange'] = $gateway;

                // Log theme information for debugging
                $current_theme = wp_get_theme();
                wc_instaxchange_debug_log('Theme context', [
                    'theme' => $current_theme->get('Name'),
                    'is_child_theme' => is_child_theme(),
                    'parent_theme' => is_child_theme() ? wp_get_theme()->get('Template') : 'N/A'
                ]);
            } else {
                wc_instaxchange_debug_log('Gateway is not available, not adding to list');
            }
        } else {
            wc_instaxchange_debug_log('Gateway not found in registered gateways');
        }

        // Log final gateways
        if (is_array($gateways) && !empty($gateways)) {
            wc_instaxchange_debug_log('Final available gateways', array_keys($gateways));
        } else {
            wc_instaxchange_debug_log('No gateways in final list');
        }

        return $gateways;
    }, 9999); // Use very high priority to ensure our filter runs last



    // Force gateway registration for theme compatibility
    add_action('woocommerce_init', function () {
        if (class_exists('WC_Payment_Gateways')) {
            $gateways = WC()->payment_gateways();
            if ($gateways && !isset($gateways->payment_gateways()['instaxchange'])) {
                wc_instaxchange_debug_log('Force registering InstaxChange gateway');
                $gateways->payment_gateways()['instaxchange'] = new WC_InstaxChange_Gateway();
            }
        }
    }, 9999);

    // Aggressive gateway forcing for checkout page
    add_action('woocommerce_checkout_before_customer_details', function () {
        if (!is_checkout())
            return;

        // Force add gateway to available gateways
        add_filter('woocommerce_available_payment_gateways', function ($gateways) {
            if (!isset($gateways['instaxchange'])) {
                $registered_gateways = WC()->payment_gateways()->payment_gateways();
                if (isset($registered_gateways['instaxchange'])) {
                    $gateways['instaxchange'] = $registered_gateways['instaxchange'];
                    wc_instaxchange_debug_log('Force added InstaxChange to available gateways');
                }
            }
            return $gateways;
        }, 99999); // Ultra high priority
    });


    wc_instaxchange_debug_log('Plugin initialization complete');
}

/**
 * Debug function to log payment gateway status (only in debug mode)
 */
if (WC_INSTAXCHANGE_DEBUG) {
    function wc_instaxchange_debug_gateways()
    {
        if (!class_exists('WooCommerce')) {
            wc_instaxchange_debug_log('WooCommerce not loaded');
            return;
        }

        wc_instaxchange_debug_log('=== Checkout Payment Gateways ===');
        $gateways = WC()->payment_gateways()->payment_gateways();

        foreach ($gateways as $id => $gateway) {
            $enabled = $gateway->enabled === 'yes' ? 'yes' : 'no';
            wc_instaxchange_debug_log($id . ': ' . $gateway->get_title() . ' (enabled: ' . $enabled . ')');
        }

        // Check if our gateway is available on checkout
        $available = WC()->payment_gateways()->get_available_payment_gateways();
        $is_available = isset($available['instaxchange']) ? 'YES' : 'NO';
        wc_instaxchange_debug_log('InstaxChange available on checkout: ' . $is_available);

        // Check if our gateway's is_available method returns true
        if (isset($gateways['instaxchange'])) {
            $is_method_available = $gateways['instaxchange']->is_available() ? 'YES' : 'NO';
            wc_instaxchange_debug_log('InstaxChange is_available(): ' . $is_method_available);
        }

        // Check for theme or plugin conflicts
        wc_instaxchange_check_conflicts();
    }

    /**
     * Check for theme or plugin conflicts (only in debug mode)
     */
    function wc_instaxchange_check_conflicts()
    {
        wc_instaxchange_debug_log('=== Checking for InstaxChange Conflicts ===');

        // Check if any plugin is filtering payment gateways
        global $wp_filter;
        if (isset($wp_filter['woocommerce_available_payment_gateways'])) {
            wc_instaxchange_debug_log('Filters on woocommerce_available_payment_gateways:');
            foreach ($wp_filter['woocommerce_available_payment_gateways']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': ' . get_class($callback['function'][0]) . '->' . $callback['function'][1] . '()');
                    } elseif (is_array($callback['function']) && is_string($callback['function'][0])) {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': ' . $callback['function'][0] . '::' . $callback['function'][1] . '()');
                    } elseif (is_string($callback['function'])) {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': ' . $callback['function'] . '()');
                    } else {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': Anonymous function');
                    }
                }
            }
        } else {
            wc_instaxchange_debug_log('No filters on woocommerce_available_payment_gateways');
        }

        // Check if any plugin is filtering is_available
        if (isset($wp_filter['woocommerce_gateway_instaxchange_is_available'])) {
            wc_instaxchange_debug_log('Filters on woocommerce_gateway_instaxchange_is_available:');
            foreach ($wp_filter['woocommerce_gateway_instaxchange_is_available']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': ' . get_class($callback['function'][0]) . '->' . $callback['function'][1] . '()');
                    } elseif (is_array($callback['function']) && is_string($callback['function'][0])) {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': ' . $callback['function'][0] . '::' . $callback['function'][1] . '()');
                    } elseif (is_string($callback['function'])) {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': ' . $callback['function'] . '()');
                    } else {
                        wc_instaxchange_debug_log('Priority ' . $priority . ': Anonymous function');
                    }
                }
            }
        } else {
            wc_instaxchange_debug_log('No filters on woocommerce_gateway_instaxchange_is_available');
        }

        // Check if checkout is restricted by country
        $allowed_countries = get_option('woocommerce_allowed_countries');
        $specific_countries = get_option('woocommerce_specific_allowed_countries', array());
        wc_instaxchange_debug_log('WooCommerce allowed countries: ' . $allowed_countries);
        if ($allowed_countries === 'specific' && !empty($specific_countries)) {
            wc_instaxchange_debug_log('Specific countries: ' . implode(', ', $specific_countries));
        }
    }
}

/**
 * Register WooCommerce Blocks integration - Enhanced method
 */
add_action('woocommerce_blocks_loaded', function () {
    if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\IntegrationRegistry')) {
        // Ensure the blocks class is loaded
        if (!class_exists('WC_InstaxChange_Blocks')) {
            require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-instaxchange-blocks.php';
        }
        
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (\Automattic\WooCommerce\Blocks\Payments\Integrations\IntegrationRegistry $integration_registry) {
                $integration_registry->register(new WC_InstaxChange_Blocks());
                wc_instaxchange_debug_log('InstaxChange Blocks integration registered successfully');
            },
            5, // Higher priority to ensure early registration
            1
        );
        
        wc_instaxchange_debug_log('WooCommerce Blocks loaded, preparing InstaxChange integration');
    } else {
        wc_instaxchange_debug_log('WooCommerce Blocks IntegrationRegistry not available');
    }
});

/**
 * Alternative blocks registration method - runs earlier
 */
add_action('init', function() {
    if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\IntegrationRegistry')) {
        // Load blocks class early
        if (!class_exists('WC_InstaxChange_Blocks')) {
            require_once WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/class-instaxchange-blocks.php';
        }
        
        // Register with blocks registry if available
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function ($payment_method_registry) {
                $payment_method_registry->register(new WC_InstaxChange_Blocks());
                wc_instaxchange_debug_log('InstaxChange Blocks registered via init hook');
            }
        );
    }
}, 5); // Very early priority

/**
 * WooCommerce Blocks Support - Consolidated Implementation
 *
 * This section handles blocks compatibility for the InstaxChange gateway.
 * Multiple filters are needed because WooCommerce uses different hooks in different contexts.
 */

// Shared callback function for blocks support
function wc_instaxchange_blocks_support_callback() {
    $blocks_features = ['blocks', 'block_checkout', 'checkout_blocks', 'products'];
    $args = func_get_args();
    $filter_name = current_filter();

    // Handle different filter signatures
    if (count($args) >= 3) {
        // 3-parameter filters
        list($supports, $param1, $param2) = $args;

        // Check if this is for InstaxChange gateway
        $is_instaxchange = false;
        if (is_object($param1) && isset($param1->id) && $param1->id === 'instaxchange') {
            $is_instaxchange = true;
            $feature = $param2;
        } elseif (is_object($param2) && isset($param2->id) && $param2->id === 'instaxchange') {
            $is_instaxchange = true;
            $feature = $param1;
        } elseif ($param1 === 'instaxchange') {
            $is_instaxchange = true;
            $feature = $param2;
        } elseif ($param2 === 'instaxchange') {
            $is_instaxchange = true;
            $feature = $param1;
        }

        if ($is_instaxchange && in_array($feature, $blocks_features)) {
            wc_instaxchange_debug_log("{$filter_name}: {$feature} = TRUE");
            return true;
        }
    } elseif (count($args) >= 2) {
        // 2-parameter filters
        list($supports, $gateway) = $args;
        if (is_object($gateway) && isset($gateway->id) && $gateway->id === 'instaxchange') {
            wc_instaxchange_debug_log("{$filter_name}: RETURNING TRUE");
            return true;
        }
    }

    return $supports;
}

// Apply the callback to all relevant filters
$blocks_support_filters = [
    'woocommerce_gateway_supports',
    'woocommerce_payment_method_supports',
    'woocommerce_payment_gateway_supports',
    'woocommerce_payment_gateway_feature_supports',
    'woocommerce_blocks_payment_method_supports',
    'woocommerce_gateway_method_supports',
    'woocommerce_gateway_supports_feature',
    'woocommerce_payment_gateway_supports_blocks',
];

foreach ($blocks_support_filters as $filter) {
    add_filter($filter, 'wc_instaxchange_blocks_support_callback', 10, 3);
}

/**
 * Critical: Force blocks compatibility recognition
 */
add_filter('woocommerce_payment_gateways', function($gateways) {
    // Ensure our gateway declares blocks support
    if (isset($gateways['instaxchange'])) {
        $gateway = $gateways['instaxchange'];
        if (method_exists($gateway, 'supports')) {
            // Force add blocks support to the gateway
            $gateway->supports = array_unique(array_merge(
                $gateway->supports ?? [],
                ['blocks', 'block_checkout', 'checkout_blocks']
            ));
            wc_instaxchange_debug_log('Forced blocks support on gateway', $gateway->supports);
        }
    }
    return $gateways;
}, 999); // Very late priority

/**
 * Hook into gateway registration to force blocks support
 */
add_action('woocommerce_payment_gateways_initialized', function() {
    $gateways = WC()->payment_gateways()->payment_gateways();
    if (isset($gateways['instaxchange'])) {
        $gateway = $gateways['instaxchange'];
        $gateway->supports = array_unique(array_merge(
            $gateway->supports ?? [],
            ['blocks', 'block_checkout', 'checkout_blocks']
        ));
        wc_instaxchange_debug_log('Payment gateways initialized - forced blocks support');
    }
});


/**
 * Register blocks script
 */
add_action('init', function () {
    wp_register_script(
        'wc-instaxchange-blocks',
        WC_INSTAXCHANGE_PLUGIN_URL . 'assets/instaxchange-blocks.js',
        array(
            'wc-blocks-registry',
            'wc-settings',
            'wp-element',
            'wp-html-entities',
            'wp-i18n'
        ),
        WC_INSTAXCHANGE_VERSION,
        true
    );
    
    // Set script translations
    if (function_exists('wp_set_script_translations')) {
        wp_set_script_translations(
            'wc-instaxchange-blocks',
            'wc-instaxchange',
            WC_INSTAXCHANGE_PLUGIN_DIR . 'languages'
        );
    }
});

/**
 * Force enqueue blocks script for debugging
 */
add_action('wp_enqueue_scripts', function() {
    if (is_checkout()) {
        // Ensure blocks script is registered and enqueued
        $script_handle = 'wc-instaxchange-blocks';
        $script_url = WC_INSTAXCHANGE_PLUGIN_URL . 'assets/instaxchange-blocks.js';
        
        if (!wp_script_is($script_handle, 'registered')) {
            wp_register_script(
                $script_handle,
                $script_url,
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ],
                WC_INSTAXCHANGE_VERSION,
                true
            );
        }
        
        if (!wp_script_is($script_handle, 'enqueued')) {
            wp_enqueue_script($script_handle);
            wc_instaxchange_debug_log('Force enqueued blocks script on checkout');
        }
        
    }
}, 99);

// Note: Duplicate filter hooks removed - now handled by consolidated callback above


/**
 * Initialize the plugin AFTER plugins are loaded and WooCommerce is available
 */
add_action('plugins_loaded', function () {
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        wc_instaxchange_init();

        // Run debug after WooCommerce is fully loaded (only in debug mode)
        if (WC_INSTAXCHANGE_DEBUG) {
            add_action('woocommerce_init', 'wc_instaxchange_debug_gateways');
        }
    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>InstaxChange Gateway:</strong> WooCommerce is required but not active. ';
            echo 'Please install and activate WooCommerce first.';
            echo '</p></div>';
        });
    }
}, 20); // Priority 20 to ensure WooCommerce loads first

/**
 * Plugin activation check.
 */
register_activation_hook(__FILE__, function () {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WooCommerce must be installed and active for InstaxChange Gateway to work.', 'wc-instaxchange'), 'Plugin dependency check', ['back_link' => true]);
    }

    // Flush rewrite rules to register payment page URL
    flush_rewrite_rules();

    wc_instaxchange_debug_log('Plugin activated successfully');
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules to clean up
    flush_rewrite_rules();

    // Clear scheduled events
    wp_clear_scheduled_hook('instaxchange_check_stuck_orders');
});

/**
 * Schedule order status check on activation
 */
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('instaxchange_check_stuck_orders')) {
        wp_schedule_event(time(), 'hourly', 'instaxchange_check_stuck_orders');
    }
});

/**
 * Manage cron job scheduling based on settings
 */
add_action('init', function() {
    // Get gateway settings
    $gateway_settings = get_option('woocommerce_instaxchange_settings', array());
    $order_management_enabled = isset($gateway_settings['enable_order_management']) 
                                ? $gateway_settings['enable_order_management'] === 'yes' 
                                : true; // Default to enabled
    
    $is_scheduled = wp_next_scheduled('instaxchange_check_stuck_orders');
    
    if ($order_management_enabled && !$is_scheduled) {
        // Enable cron job
        wp_schedule_event(time(), 'hourly', 'instaxchange_check_stuck_orders');
        wc_instaxchange_debug_log('Enabled stuck orders cron job');
    } elseif (!$order_management_enabled && $is_scheduled) {
        // Disable cron job
        wp_clear_scheduled_hook('instaxchange_check_stuck_orders');
        wc_instaxchange_debug_log('Disabled stuck orders cron job');
    }
});

/**
 * Hook to update cron job when gateway settings are saved
 */
add_action('woocommerce_update_options_payment_gateways_instaxchange', function() {
    // Get updated settings
    $gateway_settings = get_option('woocommerce_instaxchange_settings', array());
    $order_management_enabled = isset($gateway_settings['enable_order_management']) 
                                ? $gateway_settings['enable_order_management'] === 'yes' 
                                : true;
    
    $is_scheduled = wp_next_scheduled('instaxchange_check_stuck_orders');
    
    if ($order_management_enabled && !$is_scheduled) {
        // Enable cron job
        wp_schedule_event(time(), 'hourly', 'instaxchange_check_stuck_orders');
        wc_instaxchange_debug_log('Order management enabled - scheduled cron job');
    } elseif (!$order_management_enabled && $is_scheduled) {
        // Disable cron job
        wp_clear_scheduled_hook('instaxchange_check_stuck_orders');
        wc_instaxchange_debug_log('Order management disabled - cleared cron job');
    }
});

/**
 * Check for stuck orders and update their status
 */
function wc_instaxchange_check_stuck_orders($manual_trigger = false)
{
    wc_instaxchange_debug_log('Running stuck orders check', $manual_trigger ? 'Manual trigger' : 'Scheduled');

    $updated_count = 0;
    $checked_count = 0;

    // Get orders that are pending but have payment completed
    $args = array(
        'status' => 'pending',
        'payment_method' => 'instaxchange', // Only check InstaxChange orders
        'meta_query' => array(
            array(
                'key' => '_instaxchange_payment_status',
                'value' => 'completed',
                'compare' => '='
            )
        ),
        'date_query' => array(
            array(
                'column' => 'post_date',
                'after' => '2 hours ago' // Extended time window
            )
        ),
        'limit' => $manual_trigger ? 100 : 50 // Process more in manual mode
    );

    $orders = wc_get_orders($args);
    $checked_count = count($orders);

    wc_instaxchange_debug_log('Found potential stuck orders', $checked_count);

    foreach ($orders as $order) {
        $payment_status = $order->get_meta('_instaxchange_payment_status');
        $payment_initiated = $order->get_meta('_instaxchange_payment_initiated');
        $transaction_id = $order->get_meta('_instaxchange_transaction_id');

        // Only update if payment was initiated more than 5 minutes ago
        if ($payment_status === 'completed') {
            $should_update = false;
            $current_time = current_time('timestamp');
            
            if ($payment_initiated) {
                $initiated_time = strtotime($payment_initiated);
                $should_update = ($current_time - $initiated_time) > 300; // 5 minutes
            } else {
                // If no payment_initiated timestamp, check order creation time
                $order_time = strtotime($order->get_date_created());
                $should_update = ($current_time - $order_time) > 600; // 10 minutes for orders without timestamp
            }

            if ($should_update) {
                // Additional check: ensure order is not already paid
                if (!$order->is_paid()) {
                    // Check if order contains only virtual/digital products
                    $has_physical_products = false;
                    foreach ($order->get_items() as $item) {
                        $product = $item->get_product();
                        if ($product && !$product->is_virtual() && !$product->is_downloadable()) {
                            $has_physical_products = true;
                            break;
                        }
                    }

                    // Set transaction ID if available
                    if (!empty($transaction_id) && !$order->get_transaction_id()) {
                        $order->set_transaction_id($transaction_id);
                    }

                    // Update order status
                    if ($has_physical_products) {
                        $order->update_status('processing', 'Payment confirmed via ' . ($manual_trigger ? 'manual' : 'scheduled') . ' check - awaiting fulfillment');
                    } else {
                        $order->update_status('completed', 'Payment confirmed via ' . ($manual_trigger ? 'manual' : 'scheduled') . ' check - digital order completed');
                    }

                    $order->save();
                    $updated_count++;

                    wc_instaxchange_debug_log('Updated stuck order', [
                        'order_id' => $order->get_id(),
                        'new_status' => $order->get_status(),
                        'has_physical_products' => $has_physical_products,
                        'trigger' => $manual_trigger ? 'manual' : 'scheduled'
                    ]);
                } else {
                    wc_instaxchange_debug_log('Order already marked as paid', $order->get_id());
                }
            } else {
                wc_instaxchange_debug_log('Order too recent to update', [
                    'order_id' => $order->get_id(),
                    'initiated' => $payment_initiated,
                    'minutes_old' => round(($current_time - $initiated_time) / 60, 1)
                ]);
            }
        }
    }

    wc_instaxchange_debug_log('Stuck orders check completed', [
        'checked' => $checked_count,
        'updated' => $updated_count,
        'trigger' => $manual_trigger ? 'manual' : 'scheduled'
    ]);

    return array(
        'checked' => $checked_count,
        'updated' => $updated_count
    );
}

add_action('instaxchange_check_stuck_orders', function () {
    wc_instaxchange_check_stuck_orders(false);
});

/**
 * Plugin update hook - flush rewrite rules on version change
 */
add_action('upgrader_process_complete', function ($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        if (isset($options['plugins']) && in_array(plugin_basename(__FILE__), $options['plugins'])) {
            flush_rewrite_rules();
        }
    }
}, 10, 2);

/**
 * Add settings link
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=instaxchange') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});



/**
 * Add rewrite rule for payment page
 */
add_action('init', function () {
    add_rewrite_rule(
        '^instaxchange-payment/?$',
        'index.php?instaxchange_payment=1',
        'top'
    );
});

/**
 * Add query var for payment page
 */
add_filter('query_vars', function ($vars) {
    $vars[] = 'instaxchange_payment';
    return $vars;
});

/**
 * Handle payment page template
 */
add_action('template_redirect', function () {
    if (get_query_var('instaxchange_payment')) {
        // Get order details from URL parameters
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $order_key = isset($_GET['order_key']) ? sanitize_text_field($_GET['order_key']) : '';

        if (!$order_id || !$order_key) {
            wp_die('Invalid payment link');
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $order_key) {
            wp_die('Order not found or invalid key');
        }

        // Load the receipt page template
        include WC_INSTAXCHANGE_PLUGIN_DIR . 'includes/templates/receipt-page.php';
        exit;
    }
});



/**
 * Debug info for troubleshooting (only in debug mode)
 */
if (WC_INSTAXCHANGE_DEBUG) {
    add_action('wp_enqueue_scripts', function () {
        if (is_checkout() && current_user_can('manage_options')) {
            wp_enqueue_style(
                'instaxchange-admin-settings',
                WC_INSTAXCHANGE_PLUGIN_URL . 'assets/css/admin-settings.css',
                array(),
                WC_INSTAXCHANGE_VERSION
            );
        }
    });

    add_action('wp_footer', function () {
        if (is_checkout() && current_user_can('manage_options')) {
            $debug_info = array();
            $debug_info[] = 'Plugin Active: YES';
            $debug_info[] = 'WooCommerce: ' . (class_exists('WooCommerce') ? 'YES' : 'NO');
            $debug_info[] = 'Gateway Class: ' . (class_exists('WC_InstaxChange_Gateway') ? 'YES' : 'NO');

            if (class_exists('WooCommerce') && WC()->payment_gateways()) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $available = WC()->payment_gateways()->get_available_payment_gateways();

                $debug_info[] = 'Registered: ' . (isset($gateways['instaxchange']) ? 'YES' : 'NO');
                $debug_info[] = 'Available: ' . (isset($available['instaxchange']) ? 'YES' : 'NO');

                if (isset($gateways['instaxchange'])) {
                    $debug_info[] = 'Enabled: ' . ($gateways['instaxchange']->enabled === 'yes' ? 'YES' : 'NO');
                    $debug_info[] = 'Is Available: ' . ($gateways['instaxchange']->is_available() ? 'YES' : 'NO');
                }
            }

            echo '<div class="instaxchange-debug-info">';
            echo '<strong>InstaxChange Debug</strong><br>';
            echo implode('<br>', $debug_info);
            echo '</div>';
        }
    });
}

?>