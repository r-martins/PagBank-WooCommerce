<?php
if (!defined('ABSPATH')) exit;
/** @var Gateway $this */

use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Api;

//wp_enqueue_style(
//    'pagseguro-connect-checkout',
//    plugins_url('public/css/checkout.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
//    [],
//    WC_PAGSEGURO_CONNECT_VERSION
//);
wp_register_style( 'pagbank-connect-inline-css', false ); // phpcs:ignore
wp_enqueue_style( 'pagbank-connect-inline-css' ); // phpcs:ignore
wp_add_inline_style(
    'pagbank-connect-inline-css',
    apply_filters(
        'pagbank-connect-inline-css',
        '.ps-button svg, .ps-payment-icon svg{ fill: ' . Params::getConfig('icons_color', 'gray') . '};'
    )
);

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

$pixEnabled = Params::isPaymentMethodEnabled('pix');
$boletoEnabled = Params::isPaymentMethodEnabled('boleto');

$apiHelper = new Api();
$isCcEnabledAndHealthy = $apiHelper->isCcEnabledAndHealthy();
$wpKsesSvg = ['svg'  => ['xmlns'   => [], 'width'   => [], 'height'  => [], 'viewbox' => [], 'version' => [],], 'path' => ['d' => [],],];
?>
<div class="ps-connect-buttons-container">
    <?php if ($isCcEnabledAndHealthy):?>
        <button type="button" class="ps-button <?php echo isset($active['cc']) ? 'active' : ''?>" id="btn-pagseguro-cc" title="<?php esc_attr_e('Cartão de Crédito', 'pagbank-connect');?>">
			<?php echo wp_kses(file_get_contents(plugin_dir_path(WC_PAGSEGURO_CONNECT_PLUGIN_FILE) . 'public/images/cc.svg'), $wpKsesSvg)?>
		</button>
    <?php endif;?>
    <?php if ($pixEnabled):?>
        <button type="button" class="ps-button <?php echo isset($active['pix']) ? 'active' : ''?>" id="btn-pagseguro-pix" title="<?php esc_attr_e('PIX', 'pagbank-connect');?>">
            <?php echo wp_kses(file_get_contents(plugin_dir_path(WC_PAGSEGURO_CONNECT_PLUGIN_FILE) . 'public/images/pix.svg'), $wpKsesSvg)?>
		</button>
    <?php endif;?>
    <?php if ($boletoEnabled):?>
        <button type="button" class="ps-button <?php echo isset($active['boleto']) ? 'active' : ''?>" id="btn-pagseguro-boleto" title="<?php esc_attr_e('Boleto', 'pagbank-connect');?>">
            <?php echo wp_kses(file_get_contents(plugin_dir_path(WC_PAGSEGURO_CONNECT_PLUGIN_FILE) . 'public/images/boleto.svg'), $wpKsesSvg)?>
		</button>
    <?php endif;?>
</div>
<!--Initialize PagSeguro payment form fieldset with tabs-->
<?php if ($isCcEnabledAndHealthy):?>
    <fieldset id="ps-connect-payment-cc" class="ps_connect_method" style="<?php esc_attr_e($style['cc'], 'pagbank-connect');?>" <?php echo !isset($active['cc']) ? 'disabled' : '';  ?>>
        <?php require 'payments/creditcard.php'; ?>
    </fieldset>
<?php endif;?>

<?php if ($pixEnabled):?>
    <fieldset id="ps-connect-payment-pix" class="ps_connect_method" style="<?php esc_attr_e($style['pix'], 'pagbank-connect');?>" <?php echo !isset($active['pix']) ? 'disabled' : '';  ?>>
        <?php require 'payments/pix.php'; ?>
    </fieldset>
<?php endif;?>

<?php if ($boletoEnabled):?>
    <fieldset id="ps-connect-payment-boleto" class="ps_connect_method" style="<?php esc_attr_e($style['boleto'], 'pagbank-connect');?>" <?php echo !isset($active['boleto']) ? 'disabled' : '';  ?>>
        <?php require 'payments/boleto.php'; ?>
    </fieldset>
<?php endif;?>
