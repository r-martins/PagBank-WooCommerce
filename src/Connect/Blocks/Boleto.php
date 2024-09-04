<?php
namespace RM_PagBank\Connect\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Boleto extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var \RM_PagBank\Connect\Standalone\Boleto
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'rm-pagbank-boleto'; // payment gateway id

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        // get payment gateway settings
        $this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];

        // you can also initialize your payment gateway here
        // $gateways = WC()->payment_gateways->payment_gateways();
        // $this->gateway  = $gateways[ $this->name ];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
//        return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
        //return $this->gateway->is_available();
        return true;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_path = 'pagbank-connect/build/js/frontend/blocks.js';
        $script_asset_path = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'build/js/frontend/blocks.asset.php';
        $script_asset = file_exists( $script_asset_path )
            ? require( $script_asset_path )
            : [
                'dependencies' => array(),
                'version'      => '1.2.0'
            ];
        $script_url = plugins_url( 'pagbank-connect/build/js/frontend/blocks.js' );

        wp_register_script(
            'rm-pagbank-blocks-integration',
            $script_url,
            $script_asset[ 'dependencies' ],
            $script_asset[ 'version' ], // or time() or filemtime( ... ) to skip caching
            true
        );

        return ['rm-pagbank-blocks-integration'];

    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return array(
            'title'        => 'Boletinho',
//            'title'        => $this->get_setting( 'title' ),
            // almost the same way:
            // 'title'     => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Default value';
            'description'  => 'Pague com boleto bancário',
//            'description'  => $this->get_setting( 'description' ),
            // if $this->gateway was initialized on line 15
            // 'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
             'supports'  => [
                 'products'
             ],

            // example of getting a public key
            // 'publicKey' => $this->get_publishable_key(),
        );
    }

    //private function get_publishable_key() {
    //	$test_mode   = ( ! empty( $this->settings[ 'testmode' ] ) && 'yes' === $this->settings[ 'testmode' ] );
    //	$setting_key = $test_mode ? 'test_publishable_key' : 'publishable_key';
    //	return ! empty( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : '';
    //}
}