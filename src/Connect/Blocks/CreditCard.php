<?php
namespace RM_PagBank\Connect\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use RM_PagBank\Connect\Standalone\CreditCard as CreditCardGateway;
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

        return array(
            'title'        => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Cartão de Crédito via PagBank',
            'description'  => $this->get_setting( 'description' ),
            'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'isCartRecurring' => $recHelper->isCartRecurring(),
            'recurringTerms' => wp_kses($recHelper->getRecurringTermsFromCart('creditcard'), 'strong'),
            'paymentUnavailable' => $this->gateway->paymentUnavailable(),
            'defaultInstallments' => $this->gateway->getDefaultInstallments(),
            'formFields' => $this->getFormFields(),
        );
    }

    private function getFormFields() {
        $default_installments = $this->gateway->getDefaultInstallments();
        $installment_options = '<option value="">' . esc_html__( 'Informe um número de cartão', 'pagbank-connect' ) . '</option>';
        $recHelper = new Recurring();
        $isCartRecurring = $recHelper->isCartRecurring();
        $fields = array();

        $cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr( Connect::DOMAIN ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( Connect::DOMAIN ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->gateway->field_name( 'card-cvc' ) . ' style="width:100px" />
		</p>';

        $default_fields = [
            'card-holer-name' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( Connect::DOMAIN ) . '-card-holder">' . esc_html__( 'Titular do Cartão', 'pagbank-connect' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( Connect::DOMAIN ) . '-card-holder-name" class="input-text wc-credit-card-form-holder-name" autocomplete="cc-name" autocapitalize="characters" spellcheck="false" type="text" placeholder="' . esc_html__( 'como gravado no cartão', 'pagbank-connect' ) . '" ' . $this->gateway->field_name( 'card-holder-name' ) . ' />
			</p>',
            'card-number-field' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( Connect::DOMAIN ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( Connect::DOMAIN ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocapitalize="off" spellcheck="false" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->gateway->field_name( 'card-number' ) . ' />
                </p>',
            'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( Connect::DOMAIN ) . '-card-expiry">' . esc_html__( 'Validade (MM/AA)', 'pagbank-connect') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( Connect::DOMAIN ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocapitalize="off" spellcheck="false" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->gateway->field_name( 'card-expiry' ) . ' maxlength="7" />
			</p>',
            'card-cvc-field' => $cvc_field,
            'card-installments' => '<p class="form-row form-row-full">
                    <label for="' . esc_attr( Connect::DOMAIN ) . '-card-installments">' . esc_html__( 'Parcelas', 'pagbank-connect' ) . '&nbsp;<span class="required">*</span></label>
                    <select id="' . esc_attr( Connect::DOMAIN ) . '-card-installments" class="input-text wc-credit-card-form-card-installments"  ' . $this->gateway->field_name( 'card-installments' ) . ' >
                        {{installment_options}}
                    </select>
                </p>',
        ];

        if ($default_installments){
            $installment_options = '';
            foreach ($default_installments as $installment){
                if (is_string($installment)) {
                    $installment_options .= '<option value="">' . $installment . '</option>'; //error message
                    break;
                }
                $installment_options .= '<option value="'.$installment['installments'].'">'.$installment['installments'].'x de R$ '. $installment['installment_amount'] . ' (';
                $installment_options .= $installment['interest_free'] ? 'sem acréscimo)' : 'Total: R$ ' . $installment['total_amount'] . ')';
            }
        }

        $default_fields['card-installments'] = str_replace('{{installment_options}}', $installment_options, $default_fields['card-installments']);

        return wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, Connect::DOMAIN ) );
    }
}