<?php
if (! defined('ABSPATH')) exit;
/** @var Gateway $this */

use RM_PagBank\Connect;

$default_installments = $this->getDefaultInstallments();
$installment_options = '<option value="">' . esc_html__('Informe um número de cartão', 'pagbank-connect') . '</option>';
$html = '<fieldset id="rm-pagbank-installments-token">
<p class="form-row form-row-full">
        <label for="' . esc_attr(Connect::DOMAIN) . '-card-installments-token">' . esc_html__('Parcelas', 'pagbank-connect') . '&nbsp;<span class="required">*</span></label>
        <select id="' . esc_attr(Connect::DOMAIN) . '-card-installments-token" class="input-text wc-credit-card-form-card-installments-token"  ' . $this->field_name('card-installments-token') . ' >
            {{installment_options}}
        </select>
    </p>
</fieldset>';
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
$html = str_replace('{{installment_options}}', $installment_options, $html);
echo $html; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped