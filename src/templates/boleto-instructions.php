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
<?php
// Verificar se há boleto válido antes de exibir
$has_barcode = !empty($boleto_barcode) || !empty($boleto_barcode_formatted);
$has_pdf = !empty($boleto_pdf);
$has_png = !empty($boleto_png);
$has_valid_boleto = $has_barcode || $has_pdf || $has_png;

if ($has_valid_boleto):
?>
<div class="boleto-payment">
    <h2><?php _e('Pague seu Boleto', 'pagbank-connect');?></h2>
    <p><?php _e('Copie o código de barras abaixo e pague direto em seu banco.', 'pagbank-connect');?></p>
    <?php if ($has_barcode): ?>
    <div class="code-container">
        <label>
            <?php echo esc_html(__('Código de barras:', 'pagbank-connect'));?>
            <input type="text" class="pix-code" value="<?php echo esc_attr($boleto_barcode_formatted);?>" readonly="readonly"/>
        </label>
        <a href="javascript:void(0)" class="button copy-btn"><?php esc_html_e('Copiar', 'pagbank-connect'); ?></a>
    </div>
    <?php endif; ?>
    <?php if ($has_pdf || $has_png): ?>
    <div class="boleto-actions">
        <?php if ($has_pdf): ?>
        <a href="<?php echo esc_url($boleto_pdf);?>" target="_blank" class="button button-primary"><?php esc_html_e('Baixar Boleto', 'pagbank-connect')?></a>
        <?php endif; ?>
        <?php if ($has_png): ?>
        <a href="<?php echo esc_url($boleto_png);?>" target="_blank" class="button button-primary"><?php esc_html_e('Imprimir Boleto', 'pagbank-connect')?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($boleto_due_date): ?>
    <div class="boleto-exiration-container">
        <?php
        // Format due_date (already in Brazil timezone, format: Y-m-d)
        $formatted_due_date = date('d/m/Y', strtotime($boleto_due_date));
        ?>
        <p><strong>Seu boleto vence em <?php echo esc_html($formatted_due_date);?>.</strong></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
