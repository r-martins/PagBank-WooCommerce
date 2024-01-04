<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Admin options screen.
 *
 * @package RM_PagBank/Admin/Settings
 */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;

if ( ! defined ( 'ABSPATH' ) ) {
    exit;
}

/** @var Gateway $this */
?>
<fieldset name="PagSeguro">
    <div class="pslogo-container">
        <img src="<?php echo esc_url(plugins_url('public/images/pagseguro-icon.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE));?>" class="pslogo" alt="PagBank Icon"/>
        <?php
        echo '<h2>' . esc_html( $this->get_method_title() );
        wc_back_link( __( 'Voltar para Pagamentos', 'pagbank-connect' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
        ?>
    </div>
    <div class="ps-subtitle">
        <?php echo '<h4>' . esc_html( $this->get_method_description() ) . '</h4>'; ?>
    </div>
<!--    navigation tabs-->
    <nav class="nav-tab-wrapper woo-nav-tab-wrapper" id="ps-nav">
        <a href="#tab-general" class="nav-tab nav-tab-active"><?php esc_html_e('Geral', 'pagbank-connect') ?></a>
        <a href="#tab-credit-card" class="nav-tab"><?php esc_html_e('Cartão de Crédito', 'pagbank-connect') ?></a>
        <a href="#tab-pix" class="nav-tab"><?php esc_html_e('PIX', 'pagbank-connect') ?></a>
        <a href="#tab-boleto" class="nav-tab"><?php esc_html_e('Boleto', 'pagbank-connect') ?></a>
        <a href="#tab-recurring" class="nav-tab"><?php esc_html_e('Recorrência', 'pagbank-connect') ?></a>
        <a href="#tab-recurring" class="nav-tab"><?php esc_html_e('Recorrência', Connect::DOMAIN) ?></a>
    </nav>
    <div class="tab-content active" id="tab-general">
        <h3><?php esc_html_e('Credenciais', 'pagbank-connect') ?></h3>
        <p><?php esc_html_e('Para utilizar o PagBank Connect, você precisa autorizar nossa aplicação e obter suas credenciais connect.', 'pagbank-connect') ?></p>
        <a href="https://pagseguro.ricardomartins.net.br/connect/autorizar.html?utm_source=wordpressadmin" target="_blank" class="button button-secondary"><?php esc_html_e('Obter Connect Key', 'pagbank-connect') ?></a>
		<a href="https://pagseguro.ricardomartins.net.br/connect/sandbox.html?utm_source=wordpressadmin" target="_blank" class="button button-secondary"><?php esc_html_e('Obter Connect Key para Testes', 'pagbank-connect') ?></a>
		<a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/?utm_source=wordpressadmin" target="_blank" class="button button-secondary" title="<?php esc_html_e('Ir para central de ajuda. Lá você pode encontrar resposta para a maioria dos problemas e perguntas, ou entrar em contato conosco.', 'pagbank-connect');?>"><?php esc_html_e('Obter ajuda', 'pagbank-connect') ?></a>
        <?php
        echo $this->get_admin_fields('general'); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
        ?>
    </div>
    <div class="tab-content hidden" id="tab-credit-card">
        <?php echo $this->get_admin_fields('cc'); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped?>
    </div>
    <div class="tab-content hidden" id="tab-pix">
        <?php echo $this->get_admin_fields('pix'); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped?>
    </div>
    <div class="tab-content hidden" id="tab-boleto">
        <?php echo $this->get_admin_fields('boleto'); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped?>
    </div>
	<div class="tab-content hidden" id="tab-recurring">
        <?php echo $this->get_admin_fields('recurring'); ?>
    </div>
</fieldset>
