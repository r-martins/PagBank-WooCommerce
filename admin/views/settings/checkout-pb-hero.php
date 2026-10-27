<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checkout_site_url = 'https://checkout.pbintegracoes.com/?utm_source=wordpressadmin&utm_medium=plugin&utm_content=checkout-pb-settings';
$benefits         = [
	__( 'Pagamento hospedado — cartão fora da loja', 'pagbank-connect' ),
	__( 'Sua marca: tema, cores e logo', 'pagbank-connect' ),
	__( 'PIX, cartão e boleto numa só página', 'pagbank-connect' ),
	__( 'Frete com Envio Fácil e compra com um clique', 'pagbank-connect' ),
];
?>
<div class="rm-checkout-pb-hero" role="region" aria-label="<?php esc_attr_e( 'Sobre o Checkout PB', 'pagbank-connect' ); ?>">
	<div class="rm-checkout-pb-hero__body">
		<p class="rm-checkout-pb-hero__eyebrow"><?php esc_html_e( 'Checkout PB', 'pagbank-connect' ); ?></p>
		<h3 class="rm-checkout-pb-hero__title">
			<?php esc_html_e( 'Checkout hospedado com a cara da sua loja', 'pagbank-connect' ); ?>
		</h3>
		<p class="rm-checkout-pb-hero__lead">
			<?php
			esc_html_e(
				'O comprador finaliza em checkout.pbintegracoes.com — você mantém a conversão e a identidade visual, sem montar o formulário de pagamento no Woo.',
				'pagbank-connect'
			);
			?>
		</p>
		<ul class="rm-checkout-pb-hero__benefits">
			<?php foreach ( $benefits as $benefit ) : ?>
				<li><?php echo esc_html( $benefit ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="rm-checkout-pb-hero__actions">
			<a
				class="button button-primary rm-checkout-pb-hero__cta"
				href="<?php echo esc_url( $checkout_site_url ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<?php esc_html_e( 'Conhecer o Checkout PB', 'pagbank-connect' ); ?>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>
		</p>
	</div>
</div>
