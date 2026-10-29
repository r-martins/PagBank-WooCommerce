<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'checkout_pb_mode' => [
		'title'       => __( 'Habilitar', 'pagbank-connect' ),
		'type'        => 'select',
		'description' => __(
			'Desabilitado: não usa o Checkout PB. Checkout padrão: ao finalizar a compra, o comprador vai ao checkout hospedado (configure o frete abaixo). Meio de pagamento: aparece no checkout do WooCommerce, que já calcula o frete.',
			'pagbank-connect'
		),
		'default'     => 'disabled',
		'desc_tip'    => true,
		'options'     => [
			'disabled'       => __( 'Desabilitado', 'pagbank-connect' ),
			'default'        => __( 'Usar como checkout padrão', 'pagbank-connect' ),
			'payment_method' => __( 'Oferecer como meio de pagamento', 'pagbank-connect' ),
		],
	],
	'title' => [
		'title'       => __( 'Título', 'pagbank-connect' ),
		'type'        => 'safe_text',
		'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', 'pagbank-connect' ),
		'default'     => __( 'Checkout externo', 'pagbank-connect' ),
		'desc_tip'    => true,
	],
	'checkout_pb_shipping_mode' => [
		'title'       => __( 'Frete', 'pagbank-connect' ),
		'type'        => 'select',
		'description' => __(
			'Usado quando o Checkout PB é o checkout padrão (o Woo não calcula frete antes do redirecionamento).',
			'pagbank-connect'
		),
		'desc_tip'    => true,
		'default'     => 'free',
		'options'     => [
			'free'      => __( 'Frete grátis', 'pagbank-connect' ),
			'fixed'     => __( 'Valor fixo', 'pagbank-connect' ),
			'calculate' => __( 'Calcular frete no checkout PB', 'pagbank-connect' ),
		],
	],
	'checkout_pb_shipping_fixed_amount' => [
		'title'       => __( 'Valor do frete', 'pagbank-connect' ),
		'type'        => 'text',
		'description' => __( 'Valor fixo em reais (ex.: 15.90).', 'pagbank-connect' ),
		'placeholder' => '0.00',
		'default'     => '',
		'desc_tip'    => true,
	],
	'checkout_pb_shipping_calculate_hint' => [
		'title'       => ' ',
		'type'        => 'title',
		'class'       => 'rm-checkout-pb-shipping-calculate-hint',
		'description' => __(
			'Lembre-se de configurar seu CEP de Origem, Dimensões dos Produtos e suas Embalagens disponíveis (opcional).',
			'pagbank-connect'
		),
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
			'Selecione os métodos de pagamento que deseja habilitar. Com cartão, use as opções de parcelamento abaixo.',
			'pagbank-connect'
		),
	],
	'checkout_pb_installment_options' => [
		'title'       => __( 'Opções de Parcelamento', 'pagbank-connect' ),
		'type'        => 'select',
		'description' => __(
			'<a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/19945359660173-'
			.'Op%C3%A7%C3%B5es-de-Parcelamento" target="_blank">Saiba mais</a>',
			'pagbank-connect'
		),
		'desc_tip'    => false,
		'default'     => 'buyer',
		'options'     => [
			'buyer'     => __( 'Juros por conta do comprador (padrão)', 'pagbank-connect' ),
			'fixed'     => __( 'Até X parcelas sem juros', 'pagbank-connect' ),
			'min_total' => __( 'Até X parcelas sem juros dependendo do valor da parcela', 'pagbank-connect' ),
		],
	],
	'checkout_pb_installment_options_fixed' => [
		'title'             => __( 'Número de Parcelas sem Juros', 'pagbank-connect' ),
		'type'              => 'number',
		'default'           => 3,
		'custom_attributes' => [
			'min' => 1,
			'max' => 12,
		],
	],
	'checkout_pb_installments_options_min_total' => [
		'title'             => __( 'Valor Mínimo da Parcela sem Juros', 'pagbank-connect' ),
		'type'              => 'number',
		'description'       => __(
			'Valor inteiro sem decimais. Exemplo: 10 para R$ 10,00 <br/><small>Neste exemplo, um pedido '
			.'de R$100 poderá ser parcelado em 10x sem juros.<br/>Taxa padrão de juros: '
			.'2,99% a.m (consulte valor atualizado).</small>',
			'pagbank-connect'
		),
		'default'           => 50,
		'custom_attributes' => [
			'min' => 5,
			'max' => 99999,
		],
	],
	'checkout_pb_installments_options_limit_installments' => [
		'title'       => __( 'Limitar parcelas?', 'pagbank-connect' ),
		'type'        => 'select',
		'description' => __(
			'<a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/19945359660173'
			.'-Op%C3%A7%C3%B5es-de-Parcelamento#limitar-parcelas" target="_blank">Saiba mais</a>',
			'pagbank-connect'
		),
		'options'     => [
			'no'  => __( 'Não (recomendável)', 'pagbank-connect' ),
			'yes' => __( 'Sim', 'pagbank-connect' ),
		],
		'default'     => 'no',
	],
	'checkout_pb_installments_options_max_installments' => [
		'title'             => __( 'Número Máximo de Parcelas', 'pagbank-connect' ),
		'type'              => 'number',
		'default'           => 12,
		'custom_attributes' => [
			'min' => 1,
			'max' => 12,
		],
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
		'title'       => __( 'Habilitar', 'pagbank-connect' ),
		'label'       => __( 'Exibir “Compra com um clique” na página do produto', 'pagbank-connect' ),
		'type'        => 'checkbox',
		'default'     => 'no',
		'description' => __( 'Desabilitado por padrão.', 'pagbank-connect' ),
		'desc_tip'    => true,
	],
	'checkout_pb_one_click_label' => [
		'title'   => __( 'Texto do botão', 'pagbank-connect' ),
		'type'    => 'safe_text',
		'default' => __( 'Compra com um clique', 'pagbank-connect' ),
	],
	'checkout_pb_preview_title' => [
		'title'       => __( 'Pré-visualização', 'pagbank-connect' ),
		'type'        => 'title',
		'description' => __(
			'Abre o Checkout PB com as configurações atuais do formulário (mesmo sem salvar) e um produto publicado da loja — ou um item de exemplo se não houver produto.',
			'pagbank-connect'
		),
	],
];
