<?php
if ( ! defined( 'ABSPATH' ) ) exit;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;

/** @var string $qr_code */
/** @var string $qr_code_text */
/** @var string $qr_code_exp */
?>
<div class="pix-payment">
    <h2>Pague seu PIX</h2>
    <p><?php _e('Escaneie o código abaixo com o aplicativo de seu banco.', 'pagbank-connect');?></p>
    <div class="pix-qr-container">
        <img src="<?php echo esc_url($qr_code);?>" class="pix-qr" alt="PIX QrCode" title="Escaneie o código com o aplicativo de seu banco."/>
    </div>
    <p><?php _e('Ou se preferir, copie e cole o código abaixo no aplicativo de seu banco usando o PIX com o modo Copie e Cola.', 'pagbank-connect');?></p>
    <div class="code-container">
        <label>
            <?php _e('Código PIX', 'pagbank-connect');?>
            <input type="text" class="pix-code" value="<?php echo esc_attr($qr_code_text);?>" readonly="readonly"/>
        </label>
        <img src="<?php echo esc_url(plugins_url('public/images/copy-icon.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE))?>" alt="Copiar" title="Copiar" class="copy-btn"/>
        <p class="copied">Copiado ✔</p>
    </div>
    <?php if($qr_code_exp):?>
    <div class="pix-exiration-container">
        <p><strong>Este código PIX expira em <?php echo Functions::formatDate($qr_code_exp);?>.</strong></p>
    </div>
    <?php endif;?>
</div>
