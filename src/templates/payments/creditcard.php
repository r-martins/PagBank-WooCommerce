<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var Gateway $this */

use RM_PagBank\Connect\Gateway;

wp_enqueue_script( 'wc-credit-card-form' );
$default_installments = $this->getDefaultInstallments();
$installment_options = '<option value="">' . __( 'Informe um número de cartão', 'pagbank-connect' ) . '</option>';
$fields = array();

$cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr( $this->id ) . '-card-cvc">' . __( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
		</p>';

$default_fields = [
    'card-holer-name' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-card-holder">' . __( 'Titular do Cartão', 'pagbank-connect' ) . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-holder-name" class="input-text wc-credit-card-form-holder-name" autocomplete="cc-name" autocapitalize="characters" spellcheck="false" type="text" placeholder="' . __( 'JOSÉ DA SILVA', 'pagbank-connect' ) . '" ' . $this->field_name( 'card-holder-name' ) . ' />
			</p>',
    'card-number-field' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( $this->id ) . '-card-number">' . __( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocapitalize="off" spellcheck="false" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
                </p>',
    'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-card-expiry">' . __( 'Validade (MM/AA)', 'pagbank-connect') . '&nbsp;<span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocapitalize="off" spellcheck="false" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' maxlength="7" />
			</p>',
    'card-cvc-field' => $cvc_field,
    'card-installments' => '<p class="form-row form-row-full">
                    <label for="' . esc_attr( $this->id ) . '-card-installments">' . __( 'Parcelas', 'pagbank-connect' ) . '&nbsp;<span class="required">*</span></label>
                    <select id="' . esc_attr( $this->id ) . '-card-installments" class="input-text wc-credit-card-form-card-installments"  ' . $this->field_name( 'card-installments' ) . ' >
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


//if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
//    $default_fields['card-cvc-field'] = $cvc_field;
//}

$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
?>

    <fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
        <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
        <?php
        foreach ( $fields as $field ) {
            echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
        }
        ?>
        <input type="hidden" <?php echo $this->field_name('card-encrypted');?>" id="<?php echo esc_attr( $this->id )?>-card-encrypted" />
        <input type="hidden" <?php echo $this->field_name('card-3d');?>" id="<?php echo esc_attr( $this->id )?>-card-3d" />
        <?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
        <div class="clear"></div>
    </fieldset>
<?php

//if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
//    echo '<fieldset>' . $cvc_field . '</fieldset>'; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
//}
