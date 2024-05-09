<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use RM_PagBank\Connect;

return array(
	'pix_enabled'            => [
        'title'       => __( 'Habilitar', 'pagbank-connect'),
        'label'       => __( 'Habilitar', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
	],
	'pix_title'              => [
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
    'pix_discount_excludes_shipping' => [
        'title'       => __('Excluir Frete', 'pagbank-connect'),
        'label'       => __('Não aplicar ao Frete', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => __(
            'Se marcado, o desconto não será aplicado sobre o valor do frete.',
            'pagbank-connect'
        ),
        'default'     => 'no',
        'desc_tip'    => true,
    ],
);
