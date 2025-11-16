<?php
/**
 * InstaxChange Blocks Payment Integration
 *
 * @package InstaxChange
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_InstaxChange_Blocks extends AbstractPaymentMethodType
{
    protected $name = 'instaxchange';
    
    private $gateway;

    public function initialize()
    {
        $this->settings = get_option('woocommerce_instaxchange_settings', []);
        
        // Get gateway instance
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->gateway = isset($gateways['instaxchange']) ? $gateways['instaxchange'] : null;
        
        wc_instaxchange_debug_log('InstaxChange Blocks initialized', [
            'gateway_found' => $this->gateway ? 'YES' : 'NO',
            'gateway_enabled' => $this->gateway ? ($this->gateway->enabled === 'yes' ? 'YES' : 'NO') : 'N/A'
        ]);
        
    }

    public function is_active()
    {
        $is_active = $this->gateway && $this->gateway->enabled === 'yes';
        wc_instaxchange_debug_log('InstaxChange Blocks is_active check', $is_active ? 'YES' : 'NO');
        return $is_active;
    }

    public function get_payment_method_script_handles()
    {
        $script_handle = 'wc-instaxchange-blocks';
        $script_url = WC_INSTAXCHANGE_PLUGIN_URL . 'assets/instaxchange-blocks.js';
        
        wc_instaxchange_debug_log('Registering blocks script', [
            'handle' => $script_handle,
            'url' => $script_url,
            'version' => WC_INSTAXCHANGE_VERSION
        ]);

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

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                $script_handle,
                'wc-instaxchange',
                WC_INSTAXCHANGE_PLUGIN_DIR . 'languages'
            );
        }

        wc_instaxchange_debug_log('Blocks script registered successfully');
        return [$script_handle];
    }

    public function get_payment_method_data()
    {
        if (!$this->gateway) {
            return [];
        }

        return [
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'supports' => [
                'products',
                'refunds',
            ],
            'icon' => $this->gateway->icon,
            'enabled' => $this->gateway->enabled === 'yes',
            'testMode' => $this->gateway->testmode === 'yes',
            'methodTitle' => $this->gateway->get_method_title(),
            'methodDescription' => $this->gateway->get_method_description(),
        ];
    }
}

// Backward compatibility alias
if (!class_exists('InstaxChange_Blocks')) {
    class_alias('WC_InstaxChange_Blocks', 'InstaxChange_Blocks');
}