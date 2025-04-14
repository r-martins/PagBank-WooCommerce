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
//    'standalone' => [
//        'title' => esc_html(__( 'Separar meios de pagamento', 'pagbank-connect')),
//        'label' => esc_html(__('Mostrar meios de pagamento de forma individual', 'pagbank-connect')),
//        'type'  => 'checkbox',
//        'desc_tip' => true,
//        'description' => esc_html(
//            __(
//                'Recomendável se você aceita outros gateways de pagamento. É apenas uma configuração visual.',
//                'pagbank-connect'
//            )),
//        'default' => 'yes',
//        'id'    => 'wc_pagseguro_connect_together_options',
//    ],
    'hide_if_unavailable' => [
        'title' => esc_html(__( 'Ocultar meios de pagamento', 'pagbank-connect')),
        'label' => esc_html(__('Ocultar meios de pagamento para pedidos com total menor que R$ 1,00', 'pagbank-connect')),
        'type'  => 'checkbox',
        'desc_tip' => true,
        'description' => esc_html(
            __(
                'PagBank não aceita pedidos inferiores a R$1,00',
                'pagbank-connect'
            )),
        'default' => 'no',
        'id'    => 'wc_pagseguro_connect_together_options',
    ],
    'skip_processing_virtual' => [
        'title' => esc_html(__( 'Produtos Virtuais', 'pagbank-connect')),
        'label' => esc_html(__('Marcar como Completo após confirmação de pagamento', 'pagbank-connect')),
        'type'  => 'checkbox',
        'desc_tip' => true,
        'description' => esc_html(
            __(
                'Por padrão, pedidos só com produtos virtuais tem o status Processando após a confirmação do pagamento.',
                'pagbank-connect'
            )),
        'default' => 'no',
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
//	'title_display' => [
//		'title'		=> __('Exibir Título', 'pagbank-connect'),
//		'type'		=> 'select',
//		'description' => __('Exibir ou não o título do meio de pagamento no checkout.', 'pagbank-connect'),
//		'default'	=> 'logo_only',
//		'options'	=> [
//			'logo_only'		=> __('Somente o Logo', 'pagbank-connect'),
//			'text_only'	=> __('Somente o Texto', 'pagbank-connect'),
//			'both'			=> __('Ambos', 'pagbank-connect'),
//		],
//	],
    'shipping_param' => [
        'title'		=> __('Endereço de Entrega', 'pagbank-connect'),
        'type'		=> 'select',
        'description' => '<a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/20835022998029" '
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
            .'Para mais customizações visuais, veja este <a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles'
            .'/18278019489677">artigo</a>.', 'pagbank-connect'),
        'default'	=> 'gray',
        'class' => 'icon-color-picker'
    ],
    'success_behavior' => [
        'title'		=> __('Comportamento ao confirmar pagamento', 'pagbank-connect'),
        'type'		=> 'select',
        'description' => 'O que deve acontecer na tela de sucesso quando um pagamento for confirmado.<br/><a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/34191612071437" '
            .'target="_blank">' . __('Saiba mais', 'pagbank-connect') . '</a>',
        'default'	=> '',
        'options'	=> [
            ''		    => __('Mostar que pedido foi pago (padrão)', 'pagbank-connect'),
            'redirect'		=> __('Redirecionar para outra URL', 'pagbank-connect'),
            'js'		=> __('Executar código JavaScript', 'pagbank-connect'),
        ],
    ],
    'success_behavior_url' => [
        'title'       => esc_html( __( 'URL' , 'pagbank-connect' ) ),
        'type'        => 'text',
        'description' => __( 'URL para onde o cliente será redirecionado quando o pagamento for confirmado. <a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/34191612071437#placeholders">Placeholders</a> disponíveis.', 'pagbank-connect' ),
        'default'     => wc_get_page_permalink('myaccount'),
        'desc_tip'    => false,
        'required'    => true
    ],
    'success_behavior_js' => [
        'title'       => esc_html(__('JavaScript Personalizado', 'pagbank-connect')),
        'type'        => 'textarea',
        'description' => __( 'Não é necessário adicionar tags javascript. <a href="https://ajuda.pbintegracoes.com/hc/pt-br/articles/34191612071437#placeholders">Placeholders</a> disponíveis.', 'pagbank-connect' ),
        'default'     => '',
        'desc_tip'    => false,
        'class'       => 'pagbank-success-js',
        'custom_attributes' => [
            'spellcheck' => 'false',
        ],
    ],
    'force_order_update' => [
        'title'       => esc_html(__('Forçar atualização de pedidos automaticamente', 'pagbank-connect')),
        'label'       => esc_html(__('Habilitar', 'pagbank-connect')),
        'type'        => 'checkbox',
        'desc_tip'    => false,
        'description' => __(
            'Habilite somente se tiver problemas com atualizações de pedidos por conta de bloqueios ou '
            .'indisponibilidade de seu site. <br/>Veja <a href="https://ajuda.pbintegracoes.com/hc/pt-br/'
            .'articles/115002699823-Usu%C3%A1rios-Cloudflare-e-CDN-s">como evitar</a> e <a href="https://'
            .'ajuda.pbintegracoes.com/hc/pt-br/">como funciona</a>.',
            'pagbank-connect'
        ),
        'default'     => 'no',
    ],
    'hide_items' => [
        'title' => esc_html(__( 'Ocultar itens do pedido', 'pagbank-connect')),
        'label' => esc_html(__('Ocultar itens do pedido na requisição de pagamento enviada ao PagBank.', 'pagbank-connect')),
        'type'  => 'checkbox',
        'desc_tip' => true,
        'description' => esc_html(
            __(
                'O uso deste recurso pode impactar negativamente a análise de risco do PagBank, especialmente em casos de chargeback, e reduzir a taxa de aprovação.',
                'pagbank-connect'
            )
        ),
        'default' => 'no',
    ],

    'hash_email_active' => [
        'title' => esc_html(__( 'Ocultar e-mail do comprador', 'pagbank-connect')),
        'label' => esc_html(__('Converte o e-mail do comprador para hash@pagbankconnect.pag.', 'pagbank-connect')),
        'type'  => 'checkbox',
        'desc_tip' => true,
        'description' => esc_html(
            __(
                'O uso deste recurso pode impactar negativamente a análise de risco do PagBank, especialmente em casos de chargeback, e reduzir a taxa de aprovação.',
                'pagbank-connect'
            )
        ),
        'default' => 'no',
    ],
);
