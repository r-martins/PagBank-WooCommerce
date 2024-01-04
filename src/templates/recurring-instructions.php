<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var WC_Order $order */

$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
if ( $myaccount_page_id ) {
    $myaccount_page_url = get_permalink( $myaccount_page_id );
}

?>
<div class="recurring-payment">
<!--    Botão gerenciar assinaturas-->
    <div class="recurring-payment__manage">
        <a href="<?php echo esc_url( $myaccount_page_url . 'rm-pagbank-subscriptions' ); ?>" class="button button-primary">
            <?php esc_html_e( 'Ir para Minhas Assinaturas', 'pagbank-connect' ); ?>
        </a>
    </div>
</div>
