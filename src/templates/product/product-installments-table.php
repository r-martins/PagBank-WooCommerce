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

$installments = $args;

$installmentInfo = '';
$iteration = 0;

foreach ($installments as $installment) {
    if ($iteration % 2 == 0) {
        $installmentInfo .= '<tr>';
    }

    $amount = number_format((float) str_replace(',', '.', str_replace('.', '', $installment->amount)), 2, ',', '.');
    $total_amount = number_format(
        (float)str_replace(',', '.', str_replace('.', '', $installment->total_amount)),
        2,
        ',',
        '.'
    );

    $installmentInfo .= '<td>'.$installment->installments.esc_html(__('x de R$ ', 'pagbank-connect')).$amount
        .($installment->interest_free ? '<br/><small> '.esc_html(__('Sem juros', 'pagbank-connect')).'</small>'
            : '<br/><small> '.esc_html(__('Total: R$ ', 'pagbank-connect')).$total_amount.'</small>').'</td>';

    if ($iteration % 2 != 0 || $iteration == count($installments) - 1) {
        $installmentInfo .= '</tr>';
    }

    $iteration++;
}

?>

<div class="woocommerce pagbank-connect-installments">
    <h2><?php echo esc_html(__('Parcelamento PagBank', 'pagbank-connect'));?></h2>
    <table class="shop_table shop_table_responsive">
        <tbody>
            <?php if ($installmentInfo) {
                echo wp_kses_post($installmentInfo);
            } ?>
        </tbody>
    </table>
</div>