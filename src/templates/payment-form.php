<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var Gateway $this */

use RM_PagBank\Connect\Gateway;

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
        <button type="button" class="ps-button <?php echo esc_attr($active['cc']) ?? ''?>" id="btn-pagseguro-cc">
			<img src="<?php echo esc_url(plugins_url('public/images/cc.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE))?>" alt="<?php echo esc_attr($this->get_option('cc_title'));?>" title="<?php echo esc_attr($this->get_option('cc_title'));?>"/>
		</button>
    <?php endif;?>
    <?php if ($this->get_option('pix_enabled') === 'yes'):?>
        <button type="button" class="ps-button <?php echo esc_attr($active['pix']) ?? ''?>" id="btn-pagseguro-pix">
			<img src="<?php echo esc_url(plugins_url('public/images/pix.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE))?>" alt="<?php echo esc_attr($this->get_option('pix_title'));?>" title="<?php echo esc_attr($this->get_option('pix_title'));?>"/>
		</button>
    <?php endif;?>
    <?php if ($this->get_option('boleto_enabled') === 'yes'):?>
        <button type="button" class="ps-button <?php echo esc_attr($active['boleto']) ?? ''?>" id="btn-pagseguro-boleto">
			<img src="<?php echo esc_url(plugins_url('public/images/boleto.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE))?>" alt="<?php echo esc_attr($this->get_option('boleto_title'));?>" title="<?php echo esc_attr($this->get_option('boleto_title'));?>"/>
		</button>
    <?php endif;?>
</div>
<!--Initialize PagSeguro payment form fieldset with tabs-->
<?php if ($this->get_option('cc_enabled') === 'yes'):?>
    <fieldset id="ps-connect-payment-cc" class="ps_connect_method" style="<?php esc_attr_e($style['cc'], 'pagbank-connect');?>" <?php echo !isset($active['cc']) ? 'disabled' : '';  ?>>
        <input type="hidden" name="ps_connect_method" value="cc"/>
        <?php require 'payments/creditcard.php'; ?>
    </fieldset>
<?php endif;?>

<?php if ($this->get_option('pix_enabled') === 'yes'):?>
    <fieldset id="ps-connect-payment-pix" class="ps_connect_method" style="<?php esc_attr_e($style['pix'], 'pagbank-connect');?>" <?php echo !isset($active['pix']) ? 'disabled' : '';  ?>>
        <input type="hidden" name="ps_connect_method" value="pix"/>
        <?php require 'payments/pix.php'; ?>
    </fieldset>
<?php endif;?>

<?php if ($this->get_option('boleto_enabled') === 'yes'):?>
    <fieldset id="ps-connect-payment-boleto" class="ps_connect_method" style="<?php esc_attr_e($style['boleto'], 'pagbank-connect');?>" <?php echo !isset($active['boleto']) ? 'disabled' : '';  ?>>
        <input type="hidden" name="ps_connect_method" value="boleto"/>
        <?php require 'payments/boleto.php'; ?>
    </fieldset>
<?php endif;?>
