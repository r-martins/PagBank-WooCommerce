<?php
/** @var string $boleto_barcode */
/** @var string $boleto_barcode_formatted */
/** @var string $boleto_due_date */
/** @var string $boleto_pdf */
/** @var string $boleto_png */

use RM_PagBank\Connect;

?>
<div class="boleto-payment">
    <h2><?php echo __('Pague seu Boleto', Connect::DOMAIN);?></h2>
    <p><?php echo __('Copie o código de barras abaixo e pague direto em seu banco.', Connect::DOMAIN);?></p>
    <div class="code-container">
        <label>
            <?php echo __('Código de barras:', Connect::DOMAIN);?>
            <input type="text" class="pix-code" value="<?php echo esc_attr($boleto_barcode_formatted);?>" readonly="readonly"/>
        </label>
        <img src="<?php echo esc_url(plugins_url('public/images/copy-icon.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE))?>" alt="Copiar" title="Copiar" class="copy-btn"/>
        <p class="copied">Copiado ✔</p>
    </div>
    <div class="boleto-actions">
        <a href="<?php echo esc_url($boleto_pdf);?>" target="_blank" class="button button-primary"><?php echo __('Baixar Boleto', Connect::DOMAIN)?></a>
        <a href="<?php echo esc_url($boleto_png);?>" target="_blank" class="button button-primary"><?php echo __('Imprimir Boleto', Connect::DOMAIN)?></a>
    </div>
    <div class="boleto-exiration-container">
        <p><strong>Seu boleto vence em <?php echo date('d/m/Y', strtotime($boleto_due_date));?>.</strong></p>
    </div>
</div>
