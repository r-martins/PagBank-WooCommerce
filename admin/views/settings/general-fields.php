<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RM_PagBank\Connect;

return array(
	'connect_key' => [
        'title'       => esc_html( __( 'Connect Key', 'pagbank-connect' ) ),
        'type'        => 'text',
        'description' => esc_html(  __(
			'Informe sua Connect Key, obtida após Obter as Credenciais. Este NÃO é o token PagBank.',
			'pagbank-connect'
		) ),
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
        'title' => esc_html( __( 'Configurações Gerais', 'pagbank-connect' ) ),
        'type'  => 'title',
        'desc'  => '',
        'id'    => 'wc_pagseguro_connect_general_options',
	],
    'standalone' => [
        'title' => esc_html(__( 'Separar meios de pagamento', 'pagbank-connect')),
        'label' => esc_html(__('Mostrar meios de pagamento de forma individual', 'pagbank-connect')),
        'type'  => 'checkbox',
        'desc_tip' => true,
        'description' => esc_html(
            __(
                'Recomendável se você aceita outros gateways de pagamento. É apenas uma configuração visual.',
                'pagbank-connect'
            )), 
        'default' => 'yes',
        'id'    => 'wc_pagseguro_connect_together_options',
    ],
	'title' => [
        'title'       => esc_html( __( 'Título Principal' , 'pagbank-connect' ) ),
        'type'        => 'text',
        'description' => esc_html( __( 'Nome do meio de pagamento a ser exibido no radio button do checkout.', 'pagbank-connect' ) ),
        'default'     => esc_html( __( 'PagBank UOL', 'pagbank-connect' ) ),
        'desc_tip'    => true,
        'required'    => true,
        'custom_attributes' => [
            'maxlength' => 40,
		]
	],
	'title_display' => [
		'title'		=> __('Exibir Título', 'pagbank-connect'),
		'type'		=> 'select',
		'description' => __('Exibir ou não o título do meio de pagamento no checkout.', 'pagbank-connect'),
		'default'	=> 'logo_only',
		'options'	=> [
			'logo_only'		=> __('Somente o Logo', 'pagbank-connect'),
			'text_only'	=> __('Somente o Texto', 'pagbank-connect'),
			'both'			=> __('Ambos', 'pagbank-connect'),
		],
	],
    'shipping_param' => [
        'title'		=> __('Endereço de Entrega', 'pagbank-connect'),
        'type'		=> 'select',
        'description' => '<a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/20835022998029" '
            .'target="_blank">' . __('Saiba mais', 'pagbank-connect') . '</a>',
        'default'	=> '',
        'options'	=> [
            ''		    => __('Fornecer ao PagBank sempre que aplicável', 'pagbank-connect'),
            'validate'	=> __('Não fornecer se estiver incompleto', 'pagbank-connect'),
            'never'		=> __('Nunca fornecer ao PagBank', 'pagbank-connect'),
        ],
    ],
    'icons_color' => [
        'title'		=> __('Cor dos Ícones', 'pagbank-connect'),
        'type'		=> 'text',
        'description' => __('Escolha as cores do ícone dos meios de pagamento no checkout. <br/>'
            .'Para mais customizações visuais, veja este <a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles'
            .'/18278019489677">artigo</a>.', 'pagbank-connect'),
        'default'	=> 'gray',
        'class' => 'icon-color-picker'
    ],
	'enabled'              => [
        'title'   => __( 'Habilitar/Desabilitar', 'pagbank-connect' ),
        'type'    => 'checkbox',
        'label'   => __( 'Habilitar PagBank', 'pagbank-connect' ),
        'default' => 'yes',
	],
);
