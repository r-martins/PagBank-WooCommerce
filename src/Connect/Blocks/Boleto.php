<?php
namespace RM_PagBank\Connect\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use RM_PagBank\Connect\Standalone\Boleto as BoletoGateway;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;

final class Boleto extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var BoletoGateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'rm-pagbank-boleto';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : new BoletoGateway();
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        if (!$this->gateway) {
            return [];
        }

        $scriptPath = 'pagbank-connect/build/js/frontend/boleto.js';

        wp_register_script(
            'rm-pagbank-boleto-blocks-integration',
            plugins_url( $scriptPath ),
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
        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'rm-pagbank-boleto-blocks-integration');
        }

        return ['rm-pagbank-boleto-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return array(
            'title'        => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Boleto via PagBank',
            'description'  => $this->get_setting( 'description' ),
            'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'paymentUnavailable' => $this->gateway->paymentUnavailable(),
            'instructions' => $this->gateway->get_option('boleto_instructions'),
            'expirationTime' => (int)$this->gateway->get_option('boleto_expiry_days'),
            'hasDiscount' => $this->gateway->get_option('boleto_discount'),
            'discountText' => Params::getDiscountText('boleto'),
            'boletoExpiryDays' => $this->gateway->get_option('boleto_expiry_days'),
        );
    }
}