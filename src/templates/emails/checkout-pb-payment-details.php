<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $redirectLink
 * @var string $checkoutExpires
 */
?>

<div class="order-payment-details method-checkout-pb">
	<h2><?php esc_html_e( 'Detalhes do pagamento com Checkout PB', 'pagbank-connect' ); ?></h2>

	<p><?php esc_html_e( 'Para pagar, clique no link abaixo. Se já realizou o pagamento, ignore este e-mail.', 'pagbank-connect' ); ?></p>

	<a href="<?php echo esc_url( $redirectLink ); ?>" class="button button-primary" target="_blank">
		<?php esc_html_e( 'Pagar no Checkout PB', 'pagbank-connect' ); ?>
	</a>
	<br>

	<p class="redirect-expiration">
		<b>
			<?php
			if ( $checkoutExpires ) {
				printf(
					/* translators: %s: payment link expiration date */
					esc_html__( 'Este Link de Pagamento expira em: %s.', 'pagbank-connect' ),
					esc_html( $checkoutExpires )
				);
			}
			?>
		</b>
	</p>
</div>
