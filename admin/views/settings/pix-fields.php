<?php

use RM_PagBank\Connect;

return array(
	'pix_enabled'            => [
        'title'       => __( 'Habilitar', Connect::DOMAIN),
        'label'       => __( 'Habilitar', Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
	],
	'pix_title'              => [
        'title'       => __( 'Título Principal', Connect::DOMAIN ),
        'type'        => 'safe_text',
        'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', Connect::DOMAIN ),
        'default'     => __( 'PIX', Connect::DOMAIN ),
        'desc_tip'    => true,
        'class' => 'pix_attr'
	],
	'pix_instructions'       => [
        'title'       => __( 'Instruções', Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à sua página de sucesso.', Connect::DOMAIN ),
        'default'     => __( 'O QrCode será exibido na finalização do pedido.', Connect::DOMAIN ),
        'desc_tip'    => true,
        'class'       => 'pix_attr'
	],
	'pix_expiry_minutes'       => [
		'title'       => __( 'Validade do PIX', Connect::DOMAIN ),
		'type'        => 'number',
		'description' => __( 'minutos', Connect::DOMAIN ),
		'default'     => 1440,
		'desc_tip'    => false,
		'custom_attr' => [
            'min' => 1,
            'step' => 1,
		],
	],
	'pix_discount'       => [
        'title'       => __( 'Oferecer Desconto de', Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __(
			'Ex: 5% para valor percentual ou 5.00 para um valor fixo. <br/>Deixe em branco '
			.'para não oferecer nenhum desconto.',
			Connect::DOMAIN
		),
        'default'     => 0,
        'desc_tip'    => false,
	],
);
