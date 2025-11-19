<?php
/**
 * InstaxChange Theme Compatibility Class
 *
 * Handles theme compatibility issues and ensures gateway visibility
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_InstaxChange_Theme_Compatibility
{

    /**
     * Initialize theme compatibility
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'check_theme_compatibility'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_theme_fixes'), 9999);
        add_action('wp_footer', array(__CLASS__, 'inject_gateway_dom'), 9999);
    }

    /**
     * Check theme compatibility and show warnings if needed
     */
    public static function check_theme_compatibility()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_theme = wp_get_theme();
        $theme_name = $current_theme->get('Name');
        $theme_version = $current_theme->get('Version');
        $is_child_theme = is_child_theme();

        // Check for known compatibility issues
        $compatibility_warnings = array();

        // Check if theme supports WooCommerce
        if (!current_theme_supports('woocommerce')) {
            $compatibility_warnings[] = 'Theme does not declare WooCommerce support';
        }

        // Check for known problematic themes
        $problematic_themes = array(
            'avada' => 'ThemeFusion Avada',
            'divi' => 'Elegant Themes Divi',
            'enfold' => 'Enfold',
            'x' => 'Theme.co X',
            'pro' => 'Theme.co Pro',
            'flatsome' => 'UX Themes Flatsome',
            'woodmart' => 'XTemos WoodMart',
            'astra' => 'Brainstorm Force Astra',
            'generatepress' => 'Tom Usborne GeneratePress',
            'oceanwp' => 'OceanWP'
        );

        $theme_slug = strtolower($current_theme->get('Template'));
        if (isset($problematic_themes[$theme_slug])) {
            $compatibility_warnings[] = "Detected {$problematic_themes[$theme_slug]} theme - may require additional configuration";
        }

        // Show compatibility notice if there are warnings
        if (!empty($compatibility_warnings)) {
            add_action('admin_notices', function () use ($theme_name, $theme_version, $compatibility_warnings) {
                echo '<div class="notice notice-warning is-dismissible"><p>';
                echo '<strong>InstaxChange Gateway - Theme Compatibility:</strong><br>';
                echo "Current theme: <strong>{$theme_name} v{$theme_version}</strong><br>";
                echo '<ul style="margin: 5px 0 0 20px;">';
                foreach ($compatibility_warnings as $warning) {
                    echo "<li>{$warning}</li>";
                }
                echo '</ul>';
                echo '<br><em>If you experience issues with payment method display, try switching to a default WooCommerce-compatible theme temporarily to test.</em>';
                echo '</p></div>';
            });
        }

        // Log theme information for debugging
        wc_instaxchange_debug_log('Theme compatibility check', array(
            'theme_name' => $theme_name,
            'theme_version' => $theme_version,
            'is_child_theme' => $is_child_theme,
            'parent_theme' => $is_child_theme ? wp_get_theme()->get('Template') : 'N/A',
            'warnings' => $compatibility_warnings
        ));
    }

    /**
     * Enqueue theme compatibility fixes
     */
    public static function enqueue_theme_fixes()
    {
        if (!is_checkout()) {
            return;
        }

        // Enqueue theme compatibility CSS file
        wp_enqueue_style(
            'instaxchange-theme-compatibility',
            WC_INSTAXCHANGE_PLUGIN_URL . 'assets/css/theme-compatibility.css',
            array('instaxchange-receipt-page'),
            WC_INSTAXCHANGE_VERSION
        );
    }


    /**
     * Inject gateway DOM elements for theme compatibility
     */
    public static function inject_gateway_dom()
    {
        if (!is_checkout()) {
            return;
        }

        // Debug: Log available gateways
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $registered_gateways = WC()->payment_gateways()->payment_gateways();
        $debug_info = array(
            'available_gateways' => array_keys($available_gateways),
            'registered_gateways' => array_keys($registered_gateways),
            'instaxchange_registered' => isset($registered_gateways['instaxchange']),
            'instaxchange_available' => isset($available_gateways['instaxchange'])
        );
        wc_instaxchange_debug_log('Checkout page debug', $debug_info);

        // Inject gateway visibility JavaScript
        echo self::get_gateway_injection_script($debug_info);
    }

    /**
     * Get gateway injection JavaScript
     */
    private static function get_gateway_injection_script($debug_info)
    {
        ob_start();
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                console.log("InstaxChange: Starting gateway injection...");

                function injectInstaxChangeGateway() {
                    console.log("InstaxChange: Attempting gateway injection...");

                    // Check if already exists
                    if ($("input[name=\"payment_method\"][value=\"instaxchange\"]").length > 0) {
                        console.log("InstaxChange: Gateway already exists in DOM");
                        return;
                    }

                    // Find payment methods container
                    var containers = [
                        ".woocommerce-checkout-payment ul",
                        ".wc_payment_methods",
                        ".payment_methods",
                        "#payment ul",
                        "ul.payment_methods",
                        ".checkout_payment ul",
                        "[class*=\"payment\"][class*=\"method\"] ul"
                    ];

                    var container = null;
                    for (var i = 0; i < containers.length; i++) {
                        container = $(containers[i]);
                        if (container.length > 0) {
                            console.log("InstaxChange: Found container with selector:", containers[i]);
                            break;
                        }
                    }

                    if (!container || container.length === 0) {
                        console.log("InstaxChange: No payment container found, trying fallback injection");
                        var lastPaymentMethod = $(".woocommerce-checkout-payment li, .wc_payment_method, .payment_method").last();
                        if (lastPaymentMethod.length > 0) {
                            container = lastPaymentMethod.parent();
                            console.log("InstaxChange: Using fallback container");
                        }
                    }

                    if (container && container.length > 0) {
                        var gatewayHtml = "<li class=\"wc_payment_method payment_method_instaxchange\" style=\"display: block !important; visibility: visible !important; opacity: 1 !important; margin: 10px 0 !important;\">" +
                            "<input id=\"payment_method_instaxchange\" type=\"radio\" class=\"input-radio\" name=\"payment_method\" value=\"instaxchange\" checked=\"checked\" style=\"display: inline-block !important; margin-right: 10px !important;\">" +
                            "<label for=\"payment_method_instaxchange\" style=\"display: inline-block !important; cursor: pointer !important;\">" +
                            "<strong>🔒 Pay with InstaxChange - All Methods Available</strong><br>" +
                            "<small>Secure payments with credit/debit cards, digital wallets, bank transfers, and cryptocurrency.</small>" +
                            "</label>" +
                            "<div class=\"payment_box payment_method_instaxchange\" style=\"display: block !important; padding: 10px !important; background: #f8f9fa !important; border-radius: 4px !important; margin-top: 10px !important;\">" +
                            "<p style=\"margin: 0 !important;\">Experience seamless payments with InstaxChange - supporting all major payment methods worldwide.</p>" +
                            "<div class=\"instaxchange-checkout-info\" style=\"margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #667eea;\">" +
                            "<p style=\"margin: 0; font-size: 14px;\"><strong>🔒 Secure Payment Gateway</strong></p>" +
                            "<p style=\"margin: 5px 0 0 0; font-size: 12px; color: #666;\">You will be redirected to complete your payment securely.</p>" +
                            "</div>" +
                            "</div>" +
                            "</li>";

                        container.append(gatewayHtml);
                        console.log("InstaxChange: Gateway injected successfully");

                        // Force visibility
                        setTimeout(function () {
                            $("#payment_method_instaxchange").closest("li").css({
                                "display": "block !important",
                                "visibility": "visible !important",
                                "opacity": "1 !important",
                                "height": "auto !important",
                                "overflow": "visible !important",
                                "position": "relative !important",
                                "z-index": "1 !important"
                            });

                            $("#payment_method_instaxchange").css({
                                "display": "inline-block !important",
                                "visibility": "visible !important",
                                "opacity": "1 !important",
                                "pointer-events": "auto !important",
                                "cursor": "pointer !important"
                            });

                            console.log("InstaxChange: Gateway visibility forced");
                        }, 100);

                    } else {
                        console.log("InstaxChange: Could not find container to inject into");
                    }
                }

                // Run injection
                injectInstaxChangeGateway();
                setTimeout(injectInstaxChangeGateway, 500);
                setTimeout(injectInstaxChangeGateway, 1000);
                setTimeout(injectInstaxChangeGateway, 2000);

                // Run on WooCommerce events
                $(document.body).on("updated_checkout", function () {
                    console.log("InstaxChange: Checkout updated, reinjecting");
                    setTimeout(injectInstaxChangeGateway, 500);
                });

                $(window).on("load", function () {
                    console.log("InstaxChange: Page loaded, final injection");
                    setTimeout(injectInstaxChangeGateway, 1000);
                });

                // Debug function for checking gateway visibility
                window.checkGatewayVisibility = function () {
                    console.log("=== InstaxChange Gateway Visibility Check ===");

                    const gateway = $('input[name="payment_method"][value="instaxchange"]');
                    const gatewayLi = gateway.closest('li');
                    const paymentMethods = $('.wc_payment_methods');

                    console.log("Gateway input found:", gateway.length > 0);
                    console.log("Gateway LI found:", gatewayLi.length > 0);
                    console.log("Payment methods container found:", paymentMethods.length > 0);

                    if (gateway.length > 0) {
                        console.log("Gateway input display:", gateway.css('display'));
                        console.log("Gateway input visibility:", gateway.css('visibility'));
                        console.log("Gateway input opacity:", gateway.css('opacity'));
                    }

                    if (gatewayLi.length > 0) {
                        console.log("Gateway LI display:", gatewayLi.css('display'));
                        console.log("Gateway LI visibility:", gatewayLi.css('visibility'));
                        console.log("Gateway LI opacity:", gatewayLi.css('opacity'));
                    }

                    // Check for any WooCommerce payment gateways
                    const allGateways = $('input[name="payment_method"]');
                    console.log("Total payment method inputs found:", allGateways.length);
                    allGateways.each(function (index) {
                        console.log(`Gateway ${index + 1}:`, $(this).val(), "- Visible:", $(this).is(':visible'));
                    });

                    // Check if gateway is in available gateways list
                    if (typeof wc !== 'undefined' && wc.wcSettings && wc.wcSettings.availablePaymentGateways) {
                        console.log("Available gateways from wcSettings:", Object.keys(wc.wcSettings.availablePaymentGateways));
                    }

                    alert("Gateway visibility check completed. Check browser console for details.");
                };
            });
        </script>
        <?php


        return ob_get_clean();
    }
}