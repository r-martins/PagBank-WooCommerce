<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var string $boleto_barcode */
/** @var string $boleto_barcode_formatted */
/** @var string $boleto_due_date */
/** @var string $boleto_pdf */
/** @var string $boleto_png */

use RM_PagBank\Connect;

?>
<div class="boleto-payment">
    <h2><?php _e('Pague seu Boleto', 'pagbank-connect');?></h2>
    <p><?php _e('Copie o código de barras abaixo e pague direto em seu banco.', 'pagbank-connect');?></p>
    <div class="code-container">
        <label>
            <?php echo esc_html(__('Código de barras:', 'pagbank-connect'));?>
            <input type="text" class="pix-code" value="<?php echo esc_attr($boleto_barcode_formatted);?>" readonly="readonly"/>
        </label>
        <a href="javascript:void(0)" class="button copy-btn"><?php esc_html_e('Copiar', 'pagbank-connect'); ?></a>
    </div>
    <div class="boleto-actions">
        <a href="<?php echo esc_url($boleto_pdf);?>" target="_blank" class="button button-primary"><?php esc_html_e('Baixar Boleto', 'pagbank-connect')?></a>
        <a href="<?php echo esc_url($boleto_png);?>" target="_blank" class="button button-primary"><?php esc_html_e('Imprimir Boleto', 'pagbank-connect')?></a>
    </div>
    <div class="boleto-exiration-container">
        <p><strong>Seu boleto vence em <?php echo esc_html(gmdate('d/m/Y', strtotime($boleto_due_date) - 3600*3));?>.</strong></p>
    </div>
</div>
