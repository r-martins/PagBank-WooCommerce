<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'regurring_general' => [
        'title' => esc_html( __( 'Configurações de Recorrência', 'pagbank-connect' ) ),
        'type'  => 'title',
        'desc'  => '',
        'id'    => 'wc_pagseguro_connect_regurring_general_options',
    ],
	'recurring_enabled'            => [
        'title'       => __( 'Habilitar Recorrência', 'pagbank-connect'),
        'label'       => __( 'Habilitar Recorrência', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
	],
	'recurring_payments'  => [
        'title'       => __( 'Meios de Pagamento Aceitos', 'pagbank-connect' ),
        'type'        => 'multiselect',
        'class' => 'recurring_attr',
		'default' => 'cc',
		'options'	=> [
			'cc' => __('Cartão de Crédito', 'pagbank-connect'),
//          Coming soon
//			'pix' => __('PIX', 'pagbank-connect'),
//			'boleto' => __('Boleto', 'pagbank-connect'),
		]
	],
//  Coming soon
//	'recurring_notice_days' => [
//		'title'       => __( 'Notificar X dias antes', 'pagbank-connect' ),
//		'type'        => 'number',
//        'visible'     => false,
//		'description' => __( 'Quantos dias antes do vencimento da assinatura o cliente deve ser notificado por e-mail? (Válido para Boleto e Pix)', 'pagbank-connect' ),
//		'default'     => 3,
//		'custom_attributes' => [
//			'min' => 1,
//			'max' => 30,
//		],
//	],
    'recurring_process_frequency' => [
        'title'       => __( 'Frequência de Processamento', 'pagbank-connect' ),
        'type'        => 'select',
        'description' => __( 'Com que frequência o plugin deve verificar se há pagamentos recorrentes a serem processados?', 'pagbank-connect' ),
        'default'     => 'hourly',
        'options'     => [
            'hourly' => __( 'A cada hora', 'pagbank-connect' ),
            'twicedaily' => __( 'Duas vezes ao dia', 'pagbank-connect' ),
            'daily' => __( 'Diariamente', 'pagbank-connect' ),
        ],
    ],
    'recurring_customer_can_cancel'   => [
        'title'       => __( 'Permitir que o cliente cancele a assinatura?', 'pagbank-connect'),
        'label'       => __( 'Permitir', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ],
    'recurring_customer_can_pause'    => [
        'title'       => __( 'Permitir que o cliente pause a assinatura?', 'pagbank-connect'),
        'label'       => __( 'Permitir', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ]
);
