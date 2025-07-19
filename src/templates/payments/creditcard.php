<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var Gateway $this */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Recurring;

wp_enqueue_script( 'wc-credit-card-form' );
$recHelper = new Recurring();
$isCartRecurring = $recHelper->isCartRecurring();
$fields = array();

$cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr( Connect::DOMAIN ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( Connect::DOMAIN ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
		</p>';

$default_fields = [
    'card-holer-name' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( Connect::DOMAIN ) . '-card-holder">' . esc_html__( 'Titular do Cartão', 'pagbank-connect' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( Connect::DOMAIN ) . '-card-holder-name" class="input-text wc-credit-card-form-holder-name" autocomplete="cc-name" autocapitalize="characters" spellcheck="false" type="text" placeholder="' . esc_html__( 'como gravado no cartão', 'pagbank-connect' ) . '" ' . $this->field_name( 'card-holder-name' ) . ' />
			</p>',
    'card-number-field' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( Connect::DOMAIN ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( Connect::DOMAIN ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocapitalize="off" spellcheck="false" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
                </p>',
    'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( Connect::DOMAIN ) . '-card-expiry">' . esc_html__( 'Validade (MM/AA)', 'pagbank-connect') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( Connect::DOMAIN ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocapitalize="off" spellcheck="false" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' maxlength="7" />
			</p>',
    'card-cvc-field' => $cvc_field,
];
if (is_wc_endpoint_url('order-pay')) {
    $order_id = absint(get_query_var('order-pay'));
    $order = wc_get_order($order_id);

    if ($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($recHelper->isProductRecurring($product)) {
                $default_fields['card-set-default'] = '<p class="form-row form-row-wide">
                    <label for="' . esc_attr(Connect::DOMAIN) . '-card-set-default">' . esc_html__('Usar este cartão em futuras cobranças', 'pagbank-connect') . '</label>
                    <input type="checkbox" id="' . esc_attr(Connect::DOMAIN) . '-card-set-default" class="input-checkbox" ' . $this->field_name('card-set-default') . ' value="1"/>
                </p>';
                break;
            }
        }
    }
}



//if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
//    $default_fields['card-cvc-field'] = $cvc_field;
//}

$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, Connect::DOMAIN ) );
?>

    <fieldset id="wc-<?php echo esc_attr( Connect::DOMAIN ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
        <?php do_action( 'woocommerce_credit_card_form_start', Connect::DOMAIN ); ?>
        <?php
        foreach ( $fields as $field ) {
            echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
        }
        ?>
        <input type="hidden" <?php echo $this->field_name('card-encrypted');?>" id="<?php echo esc_attr( Connect::DOMAIN )?>-card-encrypted" />
        <input type="hidden" <?php echo $this->field_name('card-3d');?>" id="<?php echo esc_attr( Connect::DOMAIN )?>-card-3d" />
        <?php do_action( 'woocommerce_credit_card_form_end', Connect::DOMAIN ); ?>
        <?php if ($isCartRecurring) :?>
            <p class="form-row form-row-wide">
                <?php echo wp_kses($recHelper->getRecurringTermsFromCart('creditcard'), 'strong');?>
            </p>
        <?php endif;?>
        <div class="clear"></div>
    </fieldset>
    <input type="hidden" name="ps_connect_method" value="cc"/>
<?php

//if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
//    echo '<fieldset>' . $cvc_field . '</fieldset>'; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
//}
