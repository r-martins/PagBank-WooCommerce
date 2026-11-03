<?php
namespace RM_PagBank\Connect\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use RM_PagBank\Connect\Standalone\CheckoutPb as CheckoutPbGateway;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Partner\Branding;
use RM_PagBank\Partner\Capabilities;

final class CheckoutPb extends AbstractPaymentMethodType
{
    /** @var CheckoutPbGateway */
    private $gateway;

    protected $name = 'rm-pagbank-checkout-pb';

    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : new CheckoutPbGateway();
    }

    public function is_active()
    {
        $caps = new Capabilities();
        if (!$caps->hasCheckoutPb()) {
            return false;
        }
        return $this->gateway && $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        if (!$this->gateway) {
            return [];
        }

        wp_register_script(
            'rm-pagbank-checkout-pb-blocks-integration',
            plugins_url('build/js/frontend/checkout-pb.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('rm-pagbank-checkout-pb-blocks-integration');
        }

        return ['rm-pagbank-checkout-pb-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        $branding = new Branding();

        return [
            'title' => isset($this->settings['title']) ? $this->settings['title'] : 'Checkout externo',
            'description' => $this->get_setting('description'),
            'icon' => '',
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'paymentUnavailable' => $this->gateway->paymentUnavailable(),
            'unavailableMessage' => $branding->paymentUnavailableBelowMinimumMessage(),
            'expirationTime' => Params::convertMinutesToHumanTime(
                (int) $this->gateway->get_option('checkout_pb_expiry_minutes', 120)
            ),
            'hasDiscount' => $this->gateway->get_option('checkout_pb_discount'),
            'discountText' => Params::getDiscountText('checkout_pb'),
            'checkoutPbExpiryMinutes' => $this->gateway->get_option('checkout_pb_expiry_minutes'),
        ];
    }
}
