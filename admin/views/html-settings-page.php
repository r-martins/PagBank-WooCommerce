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
<!--    navigation tabs-->
    <nav class="nav-tab-wrapper ">
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank' ) ?>#tab-general" class="nav-tab <?php echo $this->id === 'rm-pagbank' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Geral', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-cc' ) ?>#tab-credit-card" class="nav-tab <?php echo $this->id === 'rm-pagbank-cc' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Cartão de Crédito', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-pix' ) ?>#tab-pix" class="nav-tab <?php echo $this->id === 'rm-pagbank-pix' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('PIX', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-boleto' ) ?>#tab-boleto" class="nav-tab <?php echo $this->id === 'rm-pagbank-boleto' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Boleto', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-redirect' ) ?>#tab-redirect" class="nav-tab <?php echo $this->id === 'rm-pagbank-redirect' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Checkout PagBank', 'pagbank-connect') ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rm-pagbank-recurring-settings' ) ?>#tab-recurring" class="nav-tab <?php echo $this->id === 'rm-pagbank-recurring' ? 'nav-tab-active' : '' ?>"><?php esc_html_e('Recorrência', 'pagbank-connect') ?></a>
    </nav>
    <?php if ($this->id === 'rm-pagbank'): ?>
        <h3><?php esc_html_e('Credenciais', 'pagbank-connect') ?></h3>
        <p><?php esc_html_e('Para utilizar o PagBank Connect, você precisa autorizar nossa aplicação e obter suas credenciais connect.', 'pagbank-connect') ?></p>
        <a href="https://pbintegracoes.com/connect/autorizar/?utm_source=wordpressadmin" onclick="window.open(this.href, '_blank'); return false;" class="button button-secondary"><?php esc_html_e('Obter Connect Key', 'pagbank-connect') ?></a>
        <a href="https://pbintegracoes.com/connect/sandbox/?utm_source=wordpressadmin" onclick="window.open(this.href, '_blank'); return false;" class="button button-secondary"><?php esc_html_e('Obter Connect Key para Testes', 'pagbank-connect') ?></a>
        <a href="https://ajuda.pbintegracoes.com/hc/pt-br/?utm_source=wordpressadmin" target="_blank" class="button button-secondary" title="<?php esc_html_e('Ir para central de ajuda. Lá você pode encontrar resposta para a maioria dos problemas e perguntas, ou entrar em contato conosco.', 'pagbank-connect');?>"><?php esc_html_e('Obter ajuda', 'pagbank-connect') ?></a>
    <?php endif; ?>
    <?php echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok. ?>
</fieldset>

<?php
//current user first name and last name
try{
    $adminName = wp_get_current_user()->first_name . ' ' . wp_get_current_user()->last_name;
    $adminEmail = wp_get_current_user()->user_email;
    $siteUrl = get_site_url();
    ?>
    <script type="text/javascript">
        // Listen for messages from the new window so we may help you to pre-fill the form
        window.addEventListener('message', (event) => {
            // Returns admin data to the new window
            if (event.data === 'requestAdminData' && event.origin.indexOf('pbintegracoes.com') !== -1) {
                event.source.postMessage(
                    {
                        adminName: '<?php echo esc_js($adminName); ?>',
                        adminEmail: '<?php echo esc_js($adminEmail); ?>',
                        siteUrl: '<?php echo esc_js($siteUrl); ?>'
                    },
                    event.origin // Use the origin of the request
                );
            }
            
            // Fill connect key
            if (event.data && event.data.connectKey && event.origin.indexOf('pbintegracoes.com') !== -1) {
                const connectKeyField = document.querySelector('input[name="woocommerce_rm-pagbank_connect_key"]');
                if (connectKeyField) {
                    // connectKeyField.value = event.data.connectKey;
                    connectKeyField.setAttribute('value', event.data.connectKey);
                    jQuery(connectKeyField).trigger("change");
                    connectKeyField.focus();
                    window.focus();
                    if(confirm('Já preenchemos a Connect Key pra você. Deseja salvar agora?')){
                        document.querySelector('button[name="save"]').click();
                    }
                }
            }
        });
    </script>
<?php
} catch (Exception $e) {
    // nothing to do here
}