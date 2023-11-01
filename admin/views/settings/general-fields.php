<?php

use RM_PagBank\Connect;

return array(
	'connect_key' => [
        'title'       => __( 'Connect Key', Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __(
			'Informe sua Connect Key, obtida após Obter as Credenciais. Este NÃO é o token PagBank.',
			Connect::DOMAIN
		),
        'default'     => '',
        'placeholder' => 'CON...',
        'desc_tip'    => true,
        'required'    => true,
        'validate' => 'validate-connectkey',
        'custom_attributes' => [
            'maxlength' => 40,
            'minlength' => 40,
		]
	],
	'general' => [
        'title' => __( 'Configurações Gerais', Connect::DOMAIN ),
        'type'  => 'title',
        'desc'  => '',
        'id'    => 'wc_pagseguro_connect_general_options',
	],
	'title' => [
        'title'       => __( 'Título Principal' , Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __( 'Nome do meio de pagamento a ser exibido no radio button do checkout.', Connect::DOMAIN ),
        'default'     => __( 'PagBank UOL', Connect::DOMAIN ),
        'desc_tip'    => true,
        'required'    => true,
        'custom_attributes' => [
            'maxlength' => 40,
		]
	],
	'title_display' => [
		'title'		=> __('Exibir Título', Connect::DOMAIN),
		'type'		=> 'select',
		'description' => __('Exibir ou não o título do meio de pagamento no checkout.', Connect::DOMAIN),
		'default'	=> 'logo_only',
		'options'	=> [
			'logo_only'		=> __('Somente o Logo', Connect::DOMAIN),
			'text_only'	=> __('Somente o Texto', Connect::DOMAIN),
			'both'			=> __('Ambos', Connect::DOMAIN),
		],
	],
    'shipping_param' => [
        'title'		=> __('Endereço de Entrega', Connect::DOMAIN),
        'type'		=> 'select',
        'description' => '<a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/20835022998029" target="_blank">' . __('Saiba mais', Connect::DOMAIN) . '</a>',
        'default'	=> '',
        'options'	=> [
            ''		    => __('Fornecer ao PagBank sempre que aplicável', Connect::DOMAIN),
            'validate'	=> __('Não fornecer se estiver incompleto', Connect::DOMAIN),
            'never'		=> __('Nunca fornecer ao PagBank', Connect::DOMAIN),
        ],
    ],
	'enabled'              => [
        'title'   => __( 'Habilitar/Desabilitar', Connect::DOMAIN ),
        'type'    => 'checkbox',
        'label'   => __( 'Habilitar PagBank', Connect::DOMAIN ),
        'default' => 'yes',
	],
);
