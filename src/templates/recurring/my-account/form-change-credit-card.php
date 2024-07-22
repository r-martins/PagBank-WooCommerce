<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var stdClass $subscription */

use RM_PagBank\Connect;

defined( 'ABSPATH' ) || exit;
do_action('rm_pagbank_before_account_recurring_view_subscription_payment_rows', $subscription);

if ( ! isset($subscription->id) || ! $subscription->id ) {
    return;
}
$payment = json_decode($subscription->payment_info);
$fields = array();

wp_enqueue_script( 'wc-credit-card-form' );

$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
$gateway = array_key_exists('rm-pagbank-cc',$available_gateways) ? $available_gateways['rm-pagbank-cc'] : null;
?>
<?php if ( $payment->method == 'credit_card' && $gateway) :?>
<form id="order_update" action="<?php echo WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=changePaymentMethod&id=' . $subscription->id ?>" method="post">
    <div class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?>">
        <?php
        $default_fields = [
            'card-holer-name' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( Connect::DOMAIN ) . '-card-holder">' . esc_html__( 'Titular do Cartão', 'pagbank-connect' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( Connect::DOMAIN ) . '-card-holder-name" class="input-text wc-credit-card-form-holder-name" autocomplete="cc-name" autocapitalize="characters" spellcheck="false" type="text" placeholder="' . esc_html__( 'como gravado no cartão', 'pagbank-connect' ) . '" ' . $gateway->field_name( 'card-holder-name' ) . ' />
			</p>',
            'card-number-field' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( Connect::DOMAIN ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( Connect::DOMAIN ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocapitalize="off" spellcheck="false" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $gateway->field_name( 'card-number' ) . ' />
                </p>',
            'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( Connect::DOMAIN ) . '-card-expiry">' . esc_html__( 'Validade (MM/AA)', 'pagbank-connect') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( Connect::DOMAIN ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocapitalize="off" spellcheck="false" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $gateway->field_name( 'card-expiry' ) . ' maxlength="7" />
			</p>',
            'card-cvc-field' => '<p class="form-row form-row-last">
                <label for="' . esc_attr( Connect::DOMAIN ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
                <input id="' . esc_attr( Connect::DOMAIN ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $gateway->field_name( 'card-cvc' ) . ' style="width:100px" />
            </p>',
        ];

        $fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, Connect::DOMAIN ) );
        ?>
        <fieldset id="wc-<?php echo esc_attr( Connect::DOMAIN ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" checked="checked"/>
            <?php do_action( 'woocommerce_credit_card_form_start', Connect::DOMAIN ); ?>
            <?php
            foreach ( $fields as $field ) {
                echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
            }
            ?>
            <input type="hidden" <?php echo $gateway->field_name('card-encrypted');?> id="<?php echo esc_attr( Connect::DOMAIN )?>-card-encrypted" />
            <input type="hidden" <?php echo $gateway->field_name('card-3d');?> id="<?php echo esc_attr( Connect::DOMAIN )?>-card-3d" />
            <div class="clear"></div>
        </fieldset>
        <input type="hidden" name="ps_connect_method" value="cc"/>
    </div>
    <button type="submit" class="button alt" id="place_order">
        <?php _e('Alterar cartão', 'pagbank-connect');?>
    </button>
</form>
<?php endif;



