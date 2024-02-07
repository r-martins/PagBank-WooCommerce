<?php
/**
 * Subscription Not Found (or not Allowed)
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/subscription-not-found.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */
defined( 'ABSPATH' ) || exit;
/** @var stdClass $subscription */
?>
<?php _e('Assinatura não encontrada', 'pagbank-connect') ?>
<br/>
<!--wordpress button to go back-->
<a href="<?php echo wc_get_endpoint_url( 'rm-pagbank-subscriptions' ); ?>" class="woocommerce-button button wc-backward"><?php _e( 'Voltar', 'woocommerce' ); ?></a>