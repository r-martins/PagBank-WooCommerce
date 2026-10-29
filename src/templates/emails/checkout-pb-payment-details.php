<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $redirectLink
 * @var string $checkoutExpires
 * @var string $checkoutTitle
 */
$checkoutTitle = isset( $checkoutTitle ) && $checkoutTitle !== ''
	? $checkoutTitle
	: __( 'Checkout externo', 'pagbank-connect' );
?>

<div class="order-payment-details method-checkout-pb">
	<h2>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s = Checkout PB payment method title from settings */
				__( 'Detalhes do pagamento com %s', 'pagbank-connect' ),
				$checkoutTitle
			)
		);
		?>
	</h2>

	<p><?php esc_html_e( 'Para pagar, clique no link abaixo. Se já realizou o pagamento, ignore este e-mail.', 'pagbank-connect' ); ?></p>

	<a href="<?php echo esc_url( $redirectLink ); ?>" class="button button-primary" target="_blank">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s = Checkout PB payment method title from settings */
				__( 'Pagar no %s', 'pagbank-connect' ),
				$checkoutTitle
			)
		);
		?>
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
