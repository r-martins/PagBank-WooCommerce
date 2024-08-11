<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Admin options screen.
 *
 * @package RM_PagBank/Admin/Settings
 */

use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Functions;

$isCheckoutBlocksInUse = Functions::isBlockCheckoutInUse();

/** @var Gateway $this */
?>
<fieldset name="PagSeguro">
    <div class="pslogo-container">
        <img src="<?php echo esc_url(plugins_url('public/images/pagseguro-icon.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE));?>" class="pslogo" alt="PagBank Icon"/>
        <?php
        echo '<h2>' . esc_html( __('PagBank Connect') );
        wc_back_link( __( 'Voltar para Pagamentos', 'pagbank-connect' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
        ?>
    </div>
    <div class="ps-subtitle">
        <?php echo '<h4>' . esc_html( $this->get_method_description() ) . '</h4>'; ?>
    </div>
    <div class="block-checkout-not-supported" style="<?php echo (!$isCheckoutBlocksInUse) ? 'display:none' : ''?>">
        <h4>
            🚨<?php
            esc_html_e('Você parece estar usando o checkout em blocos. O PagBank não será exibido '
                .'se ele estiver ativo.', 'pagbank-connect');
            printf(' <a href="%s" target="_blank">Saiba mais</a>.', esc_url('https://pagsegurotransparente.'
                .'zendesk.com/hc/pt-br/articles/12925708634125#block-checkout'));
            ?>
        </h4>
    </div>
<!--    navigation tabs-->
    <nav class="nav-tab-wrapper ">
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank' ) ?>#tab-general" class="nav-tab <?php echo $this->id === 'rm-pagbank' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Geral', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-cc' ) ?>#tab-credit-card" class="nav-tab <?php echo $this->id === 'rm-pagbank-cc' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Cartão de Crédito', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-pix' ) ?>#tab-pix" class="nav-tab <?php echo $this->id === 'rm-pagbank-pix' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('PIX', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-boleto' ) ?>#tab-boleto" class="nav-tab <?php echo $this->id === 'rm-pagbank-boleto' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Boleto', 'pagbank-connect') ?></a>
    </nav>
    <?php echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok. ?>
</fieldset>
