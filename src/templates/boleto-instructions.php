<?php
use RM_PagBank\Helpers\Functions;

/** @var string $boleto_barcode */
/** @var string $boleto_barcode_formatted */
/** @var string $boleto_due_date */
/** @var string $boleto_pdf */
/** @var string $boleto_png */
?>
<div class="boleto-payment">
    <h2>Pague seu Boleto</h2>
    <p>Copie o código de barras abaixo e pague direto em seu banco.</p>
    <div class="code-container">
        <label>
            Código de barras: 
            <input type="text" class="pix-code" value="<?php echo esc_attr($boleto_barcode_formatted);?>" readonly="readonly"/>
        </label>
        <img src="<?php echo esc_url(plugins_url('public/images/copy-icon.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE))?>" alt="Copiar" title="Copiar" class="copy-btn"/>
        <p class="copied">Copiado ✔</p>
    </div>
    <div class="boleto-actions">
        <a href="<?php echo esc_url($boleto_pdf);?>" target="_blank" class="button button-primary">Baixar Boleto</a>
        <a href="<?php echo esc_url($boleto_png);?>" target="_blank" class="button button-primary">Imprimir Boleto</a>
    </div>
    <div class="boleto-exiration-container">
        <p><strong>Seu boleto vence em <?php echo date('d/m/Y', strtotime($boleto_due_date));?>.</strong></p>
    </div>
</div>