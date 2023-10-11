<?php

use RM_PagBank\Connect;

return array(
	'recurring_enabled'            => [
        'title'       => __( 'Habilitar', Connect::DOMAIN),
        'label'       => __( 'Habilitar', Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
	],
	'recurring_payments'  => [
        'title'       => __( 'Meios de Pagamento Aceitos', Connect::DOMAIN ),
        'type'        => 'multiselect',
        'class' => 'recurring_attr',
		'default' => 'cc',
		'options'	=> [
			'cc' => __('Cartão de Crédito', Connect::DOMAIN),
			'pix' => __('PIX', Connect::DOMAIN),
			'boleto' => __('Boleto', Connect::DOMAIN),
		]
	],
	'recurring_notice_days' => [
		'title'       => __( 'Notificar X dias antes', Connect::DOMAIN ),
		'type'        => 'number',
		'description' => __( 'Quantos dias antes do vencimento da assinatura o cliente deve ser notificado por e-mail? (Válido para Boleto e Pix)', Connect::DOMAIN ),
		'default'     => 3,
		'custom_attributes' => [
			'min' => 1,
			'max' => 30,
		],
	],
    'recurring_process_frequency' => [
        'title'       => __( 'Frequência de Processamento', Connect::DOMAIN ),
        'type'        => 'select',
        'description' => __( 'Com que frequência o plugin deve verificar se há pagamentos recorrentes a serem processados?', Connect::DOMAIN ),
        'default'     => 'hourly',
        'options'     => [
            'hourly' => __( 'A cada hora', Connect::DOMAIN ),
            'twicedaily' => __( 'Duas vezes ao dia', Connect::DOMAIN ),
            'daily' => __( 'Diariamente', Connect::DOMAIN ),
        ],
    ],
);
