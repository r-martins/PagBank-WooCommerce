<?php
if (! defined('ABSPATH')) exit;
/** @var Gateway $this */

use RM_PagBank\Connect;

$default_installments = $this->getDefaultInstallments();
$installment_options = '<option value="">' . esc_html__('Informe um número de cartão', 'pagbank-connect') . '</option>';
$fields = array();

$default_fields = [
    'card-installments' => '<p class="form-row form-row-full">
        <label for="' . esc_attr(Connect::DOMAIN) . '-card-installments">' . esc_html__('Parcelas', 'pagbank-connect') . '&nbsp;<span class="required">*</span></label>
        <select id="' . esc_attr(Connect::DOMAIN) . '-card-installments" class="input-text wc-credit-card-form-card-installments"  ' . $this->field_name('card-installments') . ' >
            {{installment_options}}
        </select>
    </p>',
];
if ($default_installments) {
    $installment_options = '';
    foreach ($default_installments as $installment) {
        if (is_string($installment)) {
            $installment_options .= '<option value="">' . $installment . '</option>'; //error message
            break;
        }
        $installment_options .= '<option value="' . $installment['installments'] . '">' . $installment['installments'] . 'x de R$ ' . $installment['installment_amount'] . ' (';
        $installment_options .= $installment['interest_free'] ? 'sem juros)' : 'Total: R$ ' . $installment['total_amount'] . ')';
    }
}
$default_fields['card-installments'] = str_replace('{{installment_options}}', $installment_options, $default_fields['card-installments']);
$fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, Connect::DOMAIN));
?>
<?php do_action('woocommerce_credit_card_form_start', Connect::DOMAIN); ?>
<?php
foreach ($fields as $field) {
    echo $field; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
}
?>
<?php
