<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var \WC_Order $order
 * @var string|null $redirect_link
 */
?>
<section class="woocommerce-order-checkout-pb">
	<h2><?php esc_html_e( 'Checkout PB', 'pagbank-connect' ); ?></h2>
	<?php if ( ! empty( $redirect_link ) ) : ?>
		<p>
			<a class="button" href="<?php echo esc_url( $redirect_link ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Concluir pagamento no Checkout PB', 'pagbank-connect' ); ?>
			</a>
		</p>
	<?php endif; ?>
</section>
