<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var stdClass $installments */

use RM_PagBank\Connect;

$installments = $args;

$installment_info = '';
$iteration = 0;

foreach ($installments as $installment) {
    if ($iteration % 2 == 0) {
        $installment_info .= '<tr>';
    }

    $amount = number_format((float) str_replace(',', '.', str_replace('.', '', $installment->amount)), 2, ',', '.');
    $total_amount = number_format((float) str_replace(',', '.', str_replace('.', '', $installment->total_amount)), 2, ',', '.');

    $installment_info .= '<td>' . $installment->installments . esc_html(__('x de R$ ', 'pagbank-connect') ) . $amount . ($installment->interest_free ? '<small> '. esc_html(__('Sem juros', 'pagbank-connect') ) .'</small>' : '<small> '. esc_html(__('Total: R$ ', 'pagbank-connect') ) . $total_amount . '</small>') . '</td>';

    if ($iteration % 2 != 0 || $iteration == count($installments) - 1) {
        $installment_info .= '</tr>';
    }

    $iteration++;
}

?>

<div class="rm_installment-table">
    <h3><?php echo esc_html(__('Parcelamento PagBank', 'pagbank-connect'));?></h3>
    <table>
        <?php if($installment_info) {
            echo esc_html( $installment_info );
        } ?>
    </table>
</div>