<?php
/** @var \RM_PagSeguro\Connect\Gateway $this */
?>
<div class="ps-connect-buttons-container">
    <button type="button" class="button button-primary" id="btn-pagseguro-cc">Cartão de Crédito</button>
    <button type="button" class="button button-primary" id="btn-pagseguro-pix"><?php echo $this->get_option('pix_title');?></button>
    <button type="button" class="button button-primary" id="btn-pagseguro-boleto"><?php echo $this->get_option('boleto_title');?></button>
</div>
<!--Initialize PagSeguro payment form fieldset with tabs-->
<fieldset id="ps-connect-payment-cc" class="ps_connect_method" style="display: none">
    <input type="hidden" name="ps_connect_method" value="cc"/>
    <?php require 'payments/creditcard.php'; ?>
</fieldset>

<fieldset id="ps-connect-payment-pix" class="ps_connect_method hide" style="display: none">
    <input type="hidden" name="ps_connect_method" value="pix"/>
    <?php require 'payments/pix.php'; ?>
</fieldset>

<fieldset id="ps-connect-payment-boleto" class="ps_connect_method hide" style="display: none">
    <input type="hidden" name="ps_connect_method" value="boleto"/>
    <?php require 'payments/boleto.php'; ?>
</fieldset>