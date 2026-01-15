<?php
/**
 * Template Name: Boleto Instructions
 * Template Version: 1.0.0
 * DO NOT modify this file directly. If you want to make changes, copy it to wp-content/themes/YOUR_THEME/pagbank-connect/boleto-instructions.php and edit it there.
 * DO NOT remove the "Template Name" and "Template Version" lines, as they are required for proper template identification and updates.
 *
 * NÃO MODIFIQUE este arquivo diretamente. Se você deseja fazer alterações, copie-o para wp-content/themes/SEU_TEMA/pagbank-connect/boleto-instructions.php e edite-o lá.
 * NÃO remova as linhas "Template Name" e "Template Version", pois elas são necessárias para a identificação e atualização correta do template.
 */

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
        <?php
        // Format due_date (already in Brazil timezone, format: Y-m-d)
        $formatted_due_date = $boleto_due_date ? date('d/m/Y', strtotime($boleto_due_date)) : '';
        ?>
        <p><strong>Seu boleto vence em <?php echo esc_html($formatted_due_date);?>.</strong></p>
    </div>
</div>
