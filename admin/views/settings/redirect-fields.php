<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


return array(
    'enabled'            => [
        'title'       => __( 'Habilitar', 'pagbank-connect'),
        'label'       => __( 'Habilitar', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ],
	'redirect_payment_methods'      => [
		'title'       => __('Habilitar', 'pagbank-connect'),
		'label'       => __('Habilitar', 'pagbank-connect'),
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'no',
	],
	'title'        => [
		'title'       => __('Title', 'pagbank-connect'),
		'type'        => 'safe_text',
		'description' => __('Nome do meio de pagamento que seu cliente irá ver no checkout.', 'pagbank-connect'),
		'default'     => __('Pagar no PagBank', 'pagbank-connect'),
		'desc_tip'    => true,
	],
	'redirect_expiry_minutes'  => [
		'title'       => __('Validade do checkout', 'pagbank-connect'),
		'type'        => 'number',
		'description' => __('minutos', 'pagbank-connect'),
		'default'     => 120,
		'desc_tip'    => false,
	],
	'redirect_discount'     => [
		'title'       => __('Oferecer Desconto de', 'pagbank-connect'),
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
    'redirect_discount_excludes_shipping' => [
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
    'redirect_payment_methods' => [
        'title'      => __('Métodos de pagamento', 'pagbank-connect'), 
        'type'       => 'multiselect',
        'class'      => 'wc-enhanced-select',
        'options'    => [
            'CREDIT_CARD' => __('Cartão de Crédito', 'pagbank-connect'),
            'PIX'         => __('PIX', 'pagbank-connect'),
            'BOLETO'      => __('Boleto', 'pagbank-connect'),
        ],
        'default'    => ['CREDIT_CARD', 'PIX'],
        'desc_tip'   => false,
        'description' => __('Selecione os métodos de pagamento que deseja habilitar.<br/>Lembre-se de configurar as opções de parcelamento na aba Cartão de Crédito.', 'pagbank-connect'),
    ],
    'redirect_send_new_order_email' => [
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
