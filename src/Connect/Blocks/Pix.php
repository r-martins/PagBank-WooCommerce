<?php
namespace RM_PagBank\Connect\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use RM_PagBank\Connect\Standalone\Pix as PixGateway;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;

final class Pix extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var PixGateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'rm-pagbank-pix';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : new PixGateway();
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

        $assetData = [
            'dependencies' => [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            'version' => defined( 'WC_PAGSEGURO_CONNECT_VERSION' ) ? WC_PAGSEGURO_CONNECT_VERSION : null,
        ];

        $assetPath = WC_PAGSEGURO_CONNECT_BASE_DIR . '/build/js/frontend/pix.asset.php';
        if ( file_exists( $assetPath ) ) {
            $assetFile = include $assetPath;
            if ( is_array( $assetFile ) ) {
                $assetData = wp_parse_args( $assetFile, $assetData );
            }
        }

        wp_register_script(
            'rm-pagbank-pix-blocks-integration',
            plugins_url( 'build/js/frontend/pix.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE ),
            $assetData['dependencies'],
            $assetData['version'],
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                'rm-pagbank-pix-blocks-integration',
                'pagbank-connect',
                WC_PAGSEGURO_CONNECT_BASE_DIR . '/languages'
            );
        }

        return [ 'rm-pagbank-pix-blocks-integration' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return array(
            'title'        => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Pix via PagBank',
            'description'  => $this->get_setting( 'description' ),
            'icon'  => $this->gateway->get_icon(),
            'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'paymentUnavailable' => $this->gateway->paymentUnavailable(),
            'instructions' => $this->gateway->get_option('pix_instructions'),
            'expirationTime' => Params::convertMinutesToHumanTime((int)$this->gateway->get_option('pix_expiry_minutes')),
            'hasDiscount' => $this->gateway->get_option('pix_discount'),
            'discountText' => Params::getDiscountText('pix'),
        );
    }
}