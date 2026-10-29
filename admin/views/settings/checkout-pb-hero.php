<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checkout_site_url = 'https://checkout.pbintegracoes.com/?utm_source=wordpressadmin&utm_medium=plugin&utm_content=checkout-pb-settings';
$benefits         = [
	__( 'Auto-completar de Endereço e/ou CEP nativo', 'pagbank-connect' ),
	__( 'Auto-completa informações de pessoas jurídicas', 'pagbank-connect' ),
	__( 'Permite salvar dados para poupar tempo', 'pagbank-connect' ),
	__( 'Cálculo de Frete e Compra com um clique (opcionais)', 'pagbank-connect' ),
];
?>
<div class="rm-checkout-pb-hero" role="region" aria-label="<?php esc_attr_e( 'Sobre o Checkout PB', 'pagbank-connect' ); ?>">
	<div class="rm-checkout-pb-hero__body">
		<p class="rm-checkout-pb-hero__eyebrow"><?php esc_html_e( 'Checkout PB', 'pagbank-connect' ); ?></p>
		<h3 class="rm-checkout-pb-hero__title">
			<?php esc_html_e( 'Um checkout preparado para converter mais', 'pagbank-connect' ); ?>
		</h3>
		<p class="rm-checkout-pb-hero__lead">
			<?php esc_html_e( 'Pensado para melhor experiência e conversão', 'pagbank-connect' ); ?>
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
