<div class="ps-connect-buttons-container">
    <button type="button" class="button button-primary" id="btn-pagseguro-cc">Cartão de Crédito</button>
    <button type="button" class="button button-primary" id="btn-pagseguro-pix">PIX</button>
    <button type="button" class="button button-primary" id="btn-pagseguro-boleto">Boleto</button>
</div>
<!--Initialize PagSeguro payment form fieldset with tabs-->
<fieldset id="ps-connect-payment-cc" class="ps_connect_method" style="display: none">
    Cartão de Crédito
    <input type="hidden" name="ps_connect_method" value="cc"/>
</fieldset>

<fieldset id="ps-connect-payment-pix" class="ps_connect_method hide" style="display: none">
    PIX
    <input type="hidden" name="ps_connect_method" value="pix"/>
</fieldset>

<fieldset id="ps-connect-payment-boleto" class="ps_connect_method hide" style="display: none">
    BOLETO
    <input type="hidden" name="ps_connect_method" value="boleto"/>
</fieldset>