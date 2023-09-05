<?php
/** @var \RM_PagBank\Connect\Gateway $this */
$available_methods = ['cc', 'pix', 'boleto'];
$style = $active = [];
for ($x=0, $c=count($available_methods), $first = true; $x < $c; $x++){
    $method = $available_methods[$x];
    $style[$method] = 'display: none;';
    if ($this->get_option($method.'_enabled') === 'yes' && $first){
        $style[$method] = '';
        $first = false;
        $active[$method] = 'active';
    }
}
unset($x, $c, $first);
?>
<div class="ps-connect-buttons-container">
    <?php if ($this->get_option('cc_enabled') === 'yes'):?>
        <button type="button" class="wp-element-button button button-primary <?php echo $active['cc'] ?? ''?>" id="btn-pagseguro-cc">Cartão de Crédito</button>
    <?php endif;?>

    <?php if ($this->get_option('pix_enabled') === 'yes'):?>
        <button type="button" class="wp-element-button button button-primary <?php echo $active['pix'] ?? ''?>" id="btn-pagseguro-pix"><?php echo $this->get_option('pix_title');?></button>
    <?php endif;?>

    <?php if ($this->get_option('boleto_enabled') === 'yes'):?>
        <button type="button" class="wp-element-button button button-primary <?php echo $active['boleto'] ?? ''?>" id="btn-pagseguro-boleto"><?php echo $this->get_option('boleto_title');?></button>
    <?php endif;?>
</div>
<!--Initialize PagSeguro payment form fieldset with tabs-->
<?php if ($this->get_option('cc_enabled') === 'yes'):?>
    <fieldset id="ps-connect-payment-cc" class="ps_connect_method <?php echo !isset($active['cc']) ? 'hide' : ''?>" style="<?php echo $style['cc'];?>" <?php echo !isset($active['cc']) ? '' : 'disabled';  ?>>
        <input type="hidden" name="ps_connect_method" value="cc"/>
        <?php require 'payments/creditcard.php'; ?>
    </fieldset>
<?php endif;?>

<?php if ($this->get_option('pix_enabled') === 'yes'):?>
    <fieldset id="ps-connect-payment-pix" class="ps_connect_method <?php echo !isset($active['pix']) ? 'hide' : ''?>" style="<?php echo $style['pix'];?>" <?php echo !isset($active['pix']) ? '' : 'disabled';  ?>>
        <input type="hidden" name="ps_connect_method" value="pix"/>
        <?php require 'payments/pix.php'; ?>
    </fieldset>
<?php endif;?>

<?php if ($this->get_option('boleto_enabled') === 'yes'):?>
    <fieldset id="ps-connect-payment-boleto" class="ps_connect_method <?php echo !isset($active['boleto']) ? 'hide' : ''?>" style="<?php echo $style['boleto'];?>" <?php echo !isset($active['boleto']) ? '' : 'disabled';  ?>>
        <input type="hidden" name="ps_connect_method" value="boleto"/>
        <?php require 'payments/boleto.php'; ?>
    </fieldset>
<?php endif;?>
