<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Admin options screen.
 *
 * @package RM_PagBank/Admin/Settings
 */

?>
<fieldset name="PagSeguro">
    <div class="pslogo-container">
        <img src="<?php echo esc_url(plugins_url('public/images/pb-cart.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE));?>" class="pslogo" alt="PB Integrações"/>
        <?php
        echo '<h2>' . esc_html( __('PB Integrações', 'pagbank-connect') );
        wc_back_link( __( 'Voltar para Pagamentos', 'pagbank-connect' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
        ?>
    </div>
    <div class="ps-subtitle">
        <?php
        echo '<h4>' . esc_html(sprintf(
            /* translators: %s = PagBank support cutoff date (e.g. 5/nov/2026) */
            __('Aceite PIX, Cartão e Boleto de forma transparente com Vindi (ou PagBank até %s).', 'pagbank-connect'),
            \RM_PagBank\Partner\Cutoff::SHORT
        )) . '</h4>';
        ?>
    </div>
    <nav class="nav-tab-wrapper ">
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank' ) ?>#tab-general" class="nav-tab"><?php esc_html_e('Geral', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-cc' ) ?>#tab-credit-card" class="nav-tab"><?php esc_html_e('Cartão de Crédito', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-pix' ) ?>#tab-pix" class="nav-tab"><?php esc_html_e('PIX', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-boleto' ) ?>#tab-boleto" class="nav-tab"><?php esc_html_e('Boleto', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-redirect' ) ?>#tab-redirect" class="nav-tab"><?php esc_html_e('Checkout PagBank', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-recurring-settings' ) ?>#tab-recurring" class="nav-tab nav-tab-active"><?php esc_html_e('Recorrência', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-integrations' ) ?>#tab-integrations" class="nav-tab"><?php esc_html_e('Integrações', 'pagbank-connect') ?></a>
    </nav>
</fieldset>
