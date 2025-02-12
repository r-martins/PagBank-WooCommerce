<?php
if ( ! defined( 'ABSPATH' ) ) exit;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;

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
            <span class="pix-code-label"><?php _e('Código PIX', 'pagbank-connect');?></span>
            <input type="text" class="pix-code" value="<?php echo esc_attr($qr_code_text);?>" readonly="readonly"/>
        </label>
        <a href="javascript:void(0)" class="button copy-btn"><?php esc_html_e('Copiar', 'pagbank-connect'); ?></a>
    </div>
    <?php if($qr_code_exp):?>
    <div class="pix-exiration-container">
        <p><strong>Este código PIX expira em <?php echo Functions::formatDate($qr_code_exp);?>.</strong></p>
    </div>
    <?php endif;?>
</div>

<div class="pix-payment-confirmed" style="display: none;">
    <h2><?php _e('Pagamento Confirmado', 'pagbank-connect');?></h2>
    <p><?php _e('Seu pagamento foi confirmado com sucesso.', 'pagbank-connect');?></p>
</div>

<script type="text/javascript">
    // get order status in ?wc-api=wc_order_status&order_id=123 every 10 seconds for up to 10 minutes
    <?php /** @var int $order_id */?>
    const order_id = '<?php echo $order_id;?>';
    const url = '<?php echo add_query_arg(array('wc-api' => 'wc_order_status', 'order_id' => $order_id),
        home_url('/'));?>';
    jQuery(document).ready(function($){
        const interval = setInterval(function () {
            $.get(url, function (response) {
                if (response.data === 'processing' || response.data === 'completed') {
                    clearInterval(interval);
                    $('.pix-payment').hide();
                    $('.pix-payment-confirmed').show();
                    if (typeof handleSuccessBehaviorPagbank === 'function' && typeof pagbankVars !== 'undefined') {
                        pagbankVars.orderStatus = response.data;
                        handleSuccessBehaviorPagbank();
                    }
                }
            });
        }, 10000);
        setTimeout(function(){
            clearInterval(interval);
        }, 60*10*1000);
    });
</script>
