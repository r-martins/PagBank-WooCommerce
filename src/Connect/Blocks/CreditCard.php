<?php
namespace RM_PagBank\Connect\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use RM_PagBank\Connect\Standalone\CreditCard as CreditCardGateway;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;

final class CreditCard extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var CreditCardGateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'rm-pagbank-cc';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : new CreditCardGateway();
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

        $scriptPath = 'pagbank-connect/build/js/frontend/cc.js';

        wp_register_script(
            'rm-pagbank-cc-blocks-integration',
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
            wp_set_script_translations( 'rm-pagbank-cc-blocks-integration');

        }

        return ['rm-pagbank-cc-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $recHelper = new Recurring();
        $api = new Api();

        return array(
            'title'        => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Cartão de Crédito via PagBank',
            'description'  => $this->get_setting( 'description' ),
            'icon'  => $this->gateway->get_icon(),
            'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'publicKey'  => Params::getConfig('public_key'),
            'ccThreeDEnabled'  => wc_string_to_bool(Params::getCcConfig('cc_3ds', 'no')),
            'ccThreeDCanRetry'  => wc_string_to_bool(Params::getCcConfig('cc_3ds_retry', 'yes')),
            'ccThreeDSession'  => $api->get3DSession(),
            'ccThreeDAllowContinue'  => Params::getCcConfig('cc_3ds_allow_continue', 'no'),
            'pagbankConnectEnvironment'  => $api->getIsSandbox() ? 'SANDBOX' : 'PROD',
            'isCartRecurring' => $recHelper->isCartRecurring(),
            'recurringTerms' => wp_kses($recHelper->getRecurringTermsFromCart('creditcard'), 'strong'),
            'paymentUnavailable' => $this->gateway->paymentUnavailable(),
            'defaultInstallments' => is_checkout() && !is_order_received_page() && !$recHelper->isCartRecurring() ? $this->gateway->getDefaultInstallments() : null,
            'ajax_url' => admin_url('admin-ajax.php'),
            'rm_pagbank_nonce' => wp_create_nonce('rm_pagbank_nonce'),
            'savedTokens' => $this->getSavedTokensWithBin()
        );
    }

    /**
     * Get saved tokens with their CC BIN for the current user
     * 
     * @return array
     */
    private function getSavedTokensWithBin() {
        $tokens = [];
        
        if (!is_user_logged_in()) {
            return $tokens;
        }

        $customer_tokens = \WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->name);
        
        foreach ($customer_tokens as $token) {
            if ($token->get_gateway_id() === $this->name) {
                $cc_bin = $token->get_meta('cc_bin') ?: '555566';
                $customer_document = $token->get_meta('customer_document') ?: '';
                $tokens[] = [
                    'id' => $token->get_id(),
                    'cc_bin' => $cc_bin,
                    'customer_document' => $customer_document,
                    'display_name' => $token->get_display_name()
                ];
            }
        }
        
        return $tokens;
    }
}