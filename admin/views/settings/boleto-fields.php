<?php

use RM_PagBank\Connect;

return array(
	'boleto_enabled'      => [
		'title'       => __('Habilitar', Connect::DOMAIN),
		'label'       => __('Habilitar', Connect::DOMAIN),
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'yes',
	],
	'boleto_title'        => [
		'title'       => __('Title', Connect::DOMAIN),
		'type'        => 'safe_text',
		'description' => __('Nome do meio de pagamento que seu cliente irá ver no checkout.', Connect::DOMAIN),
		'default'     => __('Boleto', Connect::DOMAIN),
		'desc_tip'    => true,
	],
	'boleto_instructions' => [
		'title'       => __('Instruções', Connect::DOMAIN),
		'type'        => 'textarea',
		'description' => __('Instruções que serão adicionadas à sua página de sucesso.', Connect::DOMAIN),
		'default'     => __(
			'Imprima ou copie o código de barras de seu boleto para pagar no banco ou casa lotérica antes do vencimento.',
			Connect::DOMAIN
		),
		'desc_tip'    => true,
	],
	'boleto_expiry_days'  => [
		'title'       => __('Validade do boleto', Connect::DOMAIN),
		'type'        => 'number',
		'description' => __('dias', Connect::DOMAIN),
		'default'     => 7,
		'desc_tip'    => false,
	],
	'boleto_line_1'       => [
		'title'   => __('Instruções (Linha 1)', Connect::DOMAIN),
		'type'    => 'text',
		'default' => 'Sr. Caixa, favor não aceitar após vencimento.',
	],
	'boleto_line_2'       => [
		'title'   => __('Instruções (Linha 2)', Connect::DOMAIN),
		'type'    => 'text',
		'default' => 'Obrigado por comprar em nossa loja!',
	],
	'boleto_discount'     => [
		'title'       => __('Oferecer Desconto de', Connect::DOMAIN),
		'type'        => 'text',
		'description' => __(
            'Ex: 5% para valor percentual ou 5.00 para um valor fixo. <br/>Deixe em branco para não oferecer '
            .'nenhum desconto.<br/><a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/199454'
            .'30928909-Oferecer-Desconto-Pix-e-Boleto-" target="_blank">Saiba mais.</a>',
			Connect::DOMAIN
		),
        'placeholder'  => __('% ou fixo', Connect::DOMAIN),
		'default'     => 0,
		'desc_tip'    => false,
	],
);
