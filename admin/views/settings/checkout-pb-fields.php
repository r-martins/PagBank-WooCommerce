<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'enabled' => [
		'title'       => __( 'Habilitar', 'pagbank-connect' ),
		'label'       => __( 'Habilitar Checkout PB', 'pagbank-connect' ),
		'type'        => 'checkbox',
		'description' => __(
			'Redireciona o comprador para o checkout hospedado da PB Integrações (checkout.pbintegracoes.com).',
			'pagbank-connect'
		),
		'default'     => 'no',
	],
	'title' => [
		'title'       => __( 'Title', 'pagbank-connect' ),
		'type'        => 'safe_text',
		'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', 'pagbank-connect' ),
		'default'     => __( 'Checkout PB', 'pagbank-connect' ),
		'desc_tip'    => true,
	],
	'checkout_pb_expiry_minutes' => [
		'title'       => __( 'Validade do checkout', 'pagbank-connect' ),
		'type'        => 'number',
		'description' => __( 'minutos', 'pagbank-connect' ),
		'default'     => 120,
		'desc_tip'    => false,
	],
	'checkout_pb_discount' => [
		'title'       => __( 'Oferecer Desconto de', 'pagbank-connect' ),
		'type'        => 'text',
		'description' => __(
			'Ex: 5% para valor percentual ou 5.00 para um valor fixo. <br/>Deixe em branco para não oferecer nenhum desconto.',
			'pagbank-connect'
		),
		'placeholder' => __( '% ou fixo', 'pagbank-connect' ),
		'default'     => 0,
		'desc_tip'    => false,
	],
	'checkout_pb_discount_excludes_shipping' => [
		'title'       => __( 'Excluir Frete', 'pagbank-connect' ),
		'label'       => __( 'Não aplicar desconto ao Frete', 'pagbank-connect' ),
		'type'        => 'checkbox',
		'description' => __(
			'Se marcado, o desconto não será aplicado sobre o valor do frete.',
			'pagbank-connect'
		),
		'default'     => 'no',
		'desc_tip'    => true,
	],
	'checkout_pb_payment_methods' => [
		'title'       => __( 'Métodos de pagamento', 'pagbank-connect' ),
		'type'        => 'multiselect',
		'class'       => 'wc-enhanced-select',
		'options'     => [
			'CREDIT_CARD' => __( 'Cartão de Crédito', 'pagbank-connect' ),
			'PIX'         => __( 'PIX', 'pagbank-connect' ),
			'BOLETO'      => __( 'Boleto', 'pagbank-connect' ),
		],
		'default'     => [ 'CREDIT_CARD', 'PIX' ],
		'desc_tip'    => false,
		'description' => __(
			'Selecione os métodos de pagamento que deseja habilitar.<br/>Lembre-se de configurar as opções de parcelamento na aba Cartão de Crédito.',
			'pagbank-connect'
		),
	],
	'checkout_pb_send_new_order_email' => [
		'title'       => __( 'Enviar e-mail de novo pedido', 'pagbank-connect' ),
		'label'       => __( 'Enviar e-mail de novo pedido', 'pagbank-connect' ),
		'type'        => 'checkbox',
		'description' => __(
			'Se marcado, um e-mail será enviado ao cliente logo após a criação do pedido com os detalhes de pagamento.',
			'pagbank-connect'
		),
		'default'     => 'yes',
		'desc_tip'    => true,
	],
	'checkout_pb_branding_title' => [
		'title'       => __( 'Aparência do Checkout PB', 'pagbank-connect' ),
		'type'        => 'title',
		'description' => __(
			'Personalize a página hospedada (tema, cores, logo). Deixe em branco para usar padrões da loja.',
			'pagbank-connect'
		),
	],
	'checkout_pb_brand_name' => [
		'title'       => __( 'Nome da loja no checkout', 'pagbank-connect' ),
		'type'        => 'text',
		'default'     => '',
		'placeholder' => get_bloginfo( 'name' ),
		'desc_tip'    => true,
		'description' => __( 'Exibido no resumo do checkout hospedado.', 'pagbank-connect' ),
	],
	'checkout_pb_theme' => [
		'title'   => __( 'Tema', 'pagbank-connect' ),
		'type'    => 'select',
		'options' => [
			'painel'  => __( 'Painel (resumo à esquerda)', 'pagbank-connect' ),
			'coluna'  => __( 'Coluna (empilhado)', 'pagbank-connect' ),
			'central' => __( 'Central (cartão compacto)', 'pagbank-connect' ),
		],
		'default' => 'painel',
	],
	'checkout_pb_logo_url' => [
		'title'       => __( 'URL do logo', 'pagbank-connect' ),
		'type'        => 'text',
		'default'     => '',
		'description' => __( 'https com .png, .jpg ou .webp (sem SVG).', 'pagbank-connect' ),
		'desc_tip'    => true,
	],
	'checkout_pb_color_button' => [
		'title'   => __( 'Cor do botão', 'pagbank-connect' ),
		'type'    => 'color',
		'default' => '#171717',
	],
	'checkout_pb_color_summary' => [
		'title'   => __( 'Cor do resumo', 'pagbank-connect' ),
		'type'    => 'color',
		'default' => '#171717',
	],
	'checkout_pb_color_background' => [
		'title'   => __( 'Cor de fundo', 'pagbank-connect' ),
		'type'    => 'color',
		'default' => '#f5f5f4',
	],
	'checkout_pb_color_text' => [
		'title'   => __( 'Cor do texto', 'pagbank-connect' ),
		'type'    => 'color',
		'default' => '#171717',
	],
	'checkout_pb_summary_bg_image' => [
		'title'       => __( 'Imagem de fundo do resumo', 'pagbank-connect' ),
		'type'        => 'text',
		'default'     => '',
		'description' => __( 'URL https png/jpg/webp opcional.', 'pagbank-connect' ),
		'desc_tip'    => true,
	],
	'checkout_pb_ga4' => [
		'title'       => __( 'GA4 Measurement ID', 'pagbank-connect' ),
		'type'        => 'text',
		'default'     => '',
		'placeholder' => 'G-XXXXXXXX',
		'description' => __(
			'Opcional. Se vazio, tenta usar o Measurement ID do Google for WooCommerce / Site Kit quando disponível.',
			'pagbank-connect'
		),
		'desc_tip'    => true,
	],
	'checkout_pb_one_click_title' => [
		'title'       => __( 'Compra com um clique', 'pagbank-connect' ),
		'type'        => 'title',
		'description' => __(
			'Botão na página do produto que cria o pedido e envia o comprador ao Checkout PB com frete a calcular (Envio Fácil). Desabilitado por padrão.',
			'pagbank-connect'
		),
	],
	'checkout_pb_one_click_enabled' => [
		'title'   => __( 'Habilitar', 'pagbank-connect' ),
		'label'   => __( 'Exibir “Compra com um clique” na página do produto', 'pagbank-connect' ),
		'type'    => 'checkbox',
		'default' => 'no',
	],
	'checkout_pb_one_click_label' => [
		'title'   => __( 'Texto do botão', 'pagbank-connect' ),
		'type'    => 'safe_text',
		'default' => __( 'Compra com um clique', 'pagbank-connect' ),
	],
];
