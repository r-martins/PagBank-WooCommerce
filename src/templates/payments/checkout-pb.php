<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RM_PagBank\Helpers\Params;

$expiry = (int) $this->get_option( 'checkout_pb_expiry_minutes', 120 );
$text   = sprintf(
	esc_html__( 'Você terá %s para finalizar sua compra no checkout.', 'pagbank-connect' ),
	esc_html( Params::convertMinutesToHumanTime( $expiry ) )
);

$has_discount  = $this->get_option( 'checkout_pb_discount' );
$discount_text = Params::getDiscountText( 'checkout_pb' );
?>
<p class="instructions">
	<?php echo wp_kses( $text, 'strong' ); ?>

	<?php if ( $has_discount ) : ?>
		<br/>
		<?php echo wp_kses( $discount_text, 'strong' ); ?>
	<?php endif; ?>
</p>
<input type="hidden" name="ps_connect_method" value="checkout_pb"/>
