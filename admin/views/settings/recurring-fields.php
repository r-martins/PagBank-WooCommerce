<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    [
        'title' => esc_html( __( 'Configurações de Recorrência (beta)', 'pagbank-connect' ) ),
        'type'  => 'title',
        'desc'  => '<h4>Aceite pagamentos recorrentes e crie um clube de assinaturas</h4>
        <p>Ao ativar a recorrência, você poderá definir as configurações da assinatura em cada produto.</p>
        <p>Nosso plugin não depende do uso do WooCommerce Subscriptions ou nenhum outro. Consulte a <a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/sections/20410120690829-Recorr%C3%AAncia-e-Clube-de-Assinatura">documentação</a> para mais detalhes.</p>
        <p>Este é um recurso em fase de testes (beta). Erros podem acontecer, incluindo cobranças a mais ou a menos. Ajude a melhorar <a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/requests/new" target="_blank">reportando os erros</a> que encontrar.</p>',
        'id'    => 'wc_pagseguro_connect_regurring_general_options',
    ],
	[
        'id'          => 'woocommerce_rm-pagbank-recurring_enabled',
        'title'       => __( 'Habilitar Recorrência', 'pagbank-connect'),
        'desc'       => __( 'Habilitar Recorrência', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
	],
	[
        'id'          => 'woocommerce_rm-pagbank-recurring_payments',
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
//	[
//		'id'          => 'woocommerce_rm-pagbank-recurring_notice_days',
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
    [
        'id'          => 'woocommerce_rm-pagbank-recurring_process_frequency',
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
    [
        'id'          => 'woocommerce_rm-pagbank-recurring_customer_can_cancel',
        'title'       => __( 'Permitir que o cliente cancele a assinatura?', 'pagbank-connect'),
        'label'       => __( 'Permitir', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ],
    [
        'id'          => 'woocommerce_rm-pagbank-recurring_customer_can_pause',
        'title'       => __( 'Permitir que o cliente pause a assinatura?', 'pagbank-connect'),
        'label'       => __( 'Permitir', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ],
    [
        'type' => 'sectionend',
        'id' => 'rm-pagbank-recurring-settings'
    ]
);
