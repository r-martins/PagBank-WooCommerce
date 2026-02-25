<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

return array(
	'enabled'            => [
        'title'       => __( 'Habilitar', 'pagbank-connect'),
        'label'       => __( 'Habilitar', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' =>  __('ℹ️ Lembre-se de <a href="https://minhaconta.pagbank.com.br/conta-digital/pix" target="_blank">gerar uma chave aleatória PIX no painel PagBank</a>, ou os códigos PIX gerados serão inválidos.
        <a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/20449852438157-QrCode-Pix-gerado-%C3%A9-Inv%C3%A1lido" target="_blank">Saiba mais.</a>'),
        'default'     => 'yes'
	],
	'title'              => [
        'title'       => __( 'Título Principal', 'pagbank-connect' ),
        'type'        => 'safe_text',
        'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', 'pagbank-connect' ),
        'default'     => __( 'PIX via PagBank', 'pagbank-connect' ),
        'desc_tip'    => true,
        'class' => 'pix_attr'
	],
	'pix_instructions'       => [
        'title'       => __( 'Instruções', 'pagbank-connect' ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à sua página de sucesso.', 'pagbank-connect' ),
        'default'     => __( 'O QrCode será exibido na finalização do pedido.', 'pagbank-connect' ),
        'desc_tip'    => true,
        'class'       => 'pix_attr'
	],
	'pix_expiry_minutes'       => [
		'title'       => __( 'Validade do PIX', 'pagbank-connect' ),
		'type'        => 'number',
		'description' => __( 'minutos', 'pagbank-connect' ),
		'default'     => 1440,
		'desc_tip'    => false,
		'custom_attr' => [
            'min' => 1,
            'step' => 1,
		],
	],
	'pix_discount'       => [
        'title'       => __( 'Oferecer Desconto de', 'pagbank-connect' ),
        'type'        => 'text',
        'description' => __(
            'Ex: 5% para valor percentual ou 5.00 para um valor fixo. <br/>Deixe em branco para não oferecer '
            .'nenhum desconto.<br/><a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/199454'
            .'30928909-Oferecer-Desconto-Pix-e-Boleto-" target="_blank">Saiba mais.</a>',
			'pagbank-connect'
		),
        'placeholder'  => __('% ou fixo', 'pagbank-connect'),
        'default'     => 0,
        'desc_tip'    => false,
	],
    'pix_show_price_discount' => [
        'title'       => __('Exibir desconto', 'pagbank-connect'),
        'label'       => __('Exibir desconto no produto', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => __(
            'Se marcado, o desconto do Pix será exibido de acordo com a configuração abaixo.',
            'pagbank-connect'
        ),
        'default'     => 'no',
        'desc_tip'    => false,
    ],
    'pix_show_price_locations' => [
        'title'       => __('Onde exibir o preço com desconto', 'pagbank-connect'),
        'type'       => 'multiselect',
        'class'      => 'wc-enhanced-select',
        'description' => __('Escolha onde exibir o desconto do Pix.', 'pagbank-connect'),
        'default'     => ['product', 'category'],
        'options'     => [
            'product'   => __('Página do produto', 'pagbank-connect'),
            'category' => __('Página de categoria', 'pagbank-connect'),
        ],
    ],
    'pix_discount_excludes_shipping' => [
        'title'       => __('Excluir Frete', 'pagbank-connect'),
        'label'       => __('Não aplicar desconto ao Frete', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => __(
            'Se marcado, o desconto não será aplicado sobre o valor do frete.',
            'pagbank-connect'
        ),
        'default'     => 'no',
        'desc_tip'    => true,
    ],
    'pix_send_new_order_email' => [
        'title'       => __('Enviar e-mail de novo pedido', 'pagbank-connect'),
        'label'       => __('Enviar e-mail de novo pedido', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => __(
            'Se marcado, um e-mail será enviado ao cliente logo após a criação do pedido com os detalhes de pagamento.',
            'pagbank-connect'
        ),
        'default'     => 'yes',
        'desc_tip'    => true,
    ],
);
