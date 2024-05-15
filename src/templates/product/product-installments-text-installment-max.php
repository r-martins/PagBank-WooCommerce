<?php
/**
 * DO NOT modify this file. If you want to make changes, copy it to wp-content/YOUR_THEME/pagbank-connect/
 * and edit it there.
 * NÃO MODIFIQUE este arquivo. Se você deseja fazer alterações, copie-o para wp-content/SEU_TEMA/pagbank-connect/
 * e edite-o lá.
 */
if (!defined('ABSPATH')) {
    exit;
}
/** @var stdClass $args */

if (count($args) <= 1) {
    return;
}
$installments = $args[count($args)-1];
?>
<div class="woocommerce pagbank-connect-installments">
    <p><?php echo sprintf(
        __(
            'Em até <strong class="installment-x">%sx</strong> de <strong class="installment-amount">'
            .'R$ %s</strong> no Cartão de Crédito com PagBank',
            'pagbank-connect'
        ),
            $installments->installments,
        wc_format_localized_price($installments->amount)
    ); ?>.</p>
</div>
