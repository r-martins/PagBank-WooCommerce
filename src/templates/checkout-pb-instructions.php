<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var \WC_Order $order
 * @var string|null $redirect_link
 * @var string $checkout_title
 */
$checkout_title = isset( $checkout_title ) && $checkout_title !== ''
	? $checkout_title
	: __( 'Checkout externo', 'pagbank-connect' );
?>
<section class="woocommerce-order-checkout-pb">
	<h2><?php echo esc_html( $checkout_title ); ?></h2>
	<?php if ( ! empty( $redirect_link ) ) : ?>
		<p>
			<a class="button" href="<?php echo esc_url( $redirect_link ); ?>" target="_blank" rel="noopener noreferrer">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s = Checkout PB payment method title from settings */
						__( 'Concluir pagamento no %s', 'pagbank-connect' ),
						$checkout_title
					)
				);
				?>
			</a>
		</p>
	<?php endif; ?>
</section>
