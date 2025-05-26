<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


return array(
	'enabled'      => [
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
		'default'     => __('Boleto via PagBank', 'pagbank-connect'),
		'desc_tip'    => true,
	],
	'boleto_instructions' => [
		'title'       => __('Instruções', 'pagbank-connect'),
		'type'        => 'textarea',
		'description' => __('Instruções que serão adicionadas à sua página de sucesso.', 'pagbank-connect'),
		'default'     => __(
			'Imprima ou copie o código de barras de seu boleto para pagar no banco ou casa lotérica antes do vencimento.',
			'pagbank-connect'
		),
		'desc_tip'    => true,
	],
	'boleto_expiry_days'  => [
		'title'       => __('Validade do boleto', 'pagbank-connect'),
		'type'        => 'number',
		'description' => __('dias', 'pagbank-connect'),
		'default'     => 7,
		'desc_tip'    => false,
	],
	'boleto_line_1'       => [
		'title'   => __('Instruções (Linha 1)', 'pagbank-connect'),
		'type'    => 'text',
		'default' => 'Sr. Caixa, favor não aceitar após vencimento.',
		'description'=> __('<a target="_blank" href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/36743551280013-Placeholders-dispon%C3%ADveis-para-Boleto">Placeholders disponíveis.</a> Max. 75 caracteres incluindo valores dos placeholders.', 'pagbank-connect'),
        'custom_attributes' => [
            'maxlength' => 75,
        ],
	],
	'boleto_line_2'       => [
		'title'   => __('Instruções (Linha 2)', 'pagbank-connect'),
		'type'    => 'text',
		'default' => 'Obrigado por comprar em nossa loja!',
		'description'=> __('<a target="_blank" href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/36743551280013-Placeholders-dispon%C3%ADveis-para-Boleto">Placeholders disponíveis.</a> Max. 75 caracteres incluindo valores dos placeholders.', 'pagbank-connect'),
        'custom_attributes' => [
            'maxlength' => 75,
        ],
	],
	'boleto_discount'     => [
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
    'boleto_discount_excludes_shipping' => [
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
    'boleto_send_new_order_email' => [
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
