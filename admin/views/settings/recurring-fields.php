<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    [
        'title' => esc_html( __( 'Configurações de Recorrência', 'pagbank-connect' ) ),
        'type'  => 'title',
        'desc'  => '<h4>Aceite pagamentos recorrentes e crie um clube de assinaturas</h4>
        <p>Ao ativar a recorrência, você poderá definir as configurações da assinatura em cada produto.</p>
        <p>Nosso plugin não depende do uso do WooCommerce Subscriptions ou nenhum outro. Consulte a <a href="https://ajuda.pbintegracoes.com/hc/pt-br/sections/20410120690829-Recorr%C3%AAncia-e-Clube-de-Assinatura">documentação</a> para mais detalhes.</p>',
        'id'    => 'wc_pagseguro_connect_regurring_general_options',
    ],
    [
        'type'  => RM_PagBank\Connect\Recurring::removeSandboxSubscriptions() ? 'title' : 'hidden',
        'desc'  => sprintf(
            '<div class="rm-sandbox-warning-box">
                <p><strong>%s</strong> %s</p>
                <p>%s</p>
                <a href="%s" class="rm-sandbox-clear-btn">
                    <span class="dashicons dashicons-trash"></span> %s
                </a>
            </div>',
            esc_html__('Atenção:', 'pagbank-connect'),
            esc_html__('Esta ação irá apagar todas as assinaturas de teste (sandbox).', 'pagbank-connect'),
            esc_html__('Use este recurso apenas se tiver certeza de que não precisa mais das assinaturas de teste.', 'pagbank-connect'),
            esc_url(add_query_arg('remove_sandbox_recurring', 1)),
            esc_html__('Excluir Assinaturas Sandbox', 'pagbank-connect')
        ),
        'id'    => 'woocommerce_rm-pagbank-recurring_sandbox_section',
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
        'class' => 'recurring_attr wc-enhanced-select',
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
        'id'          => 'woocommerce_rm-pagbank-recurring_clear_cart',
        'title'       => __( 'Ao adicionar um produto recorrente', 'pagbank-connect'),
        'label'       => __( 'Remover', 'pagbank-connect' ),
        'desc'        => __( 'Remover automaticamente outros produtos do carrinho', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'description' => '',
        'desc_tip'    => esc_html(__('Isso evitará que o plugin exiba a mensagem de que produtos recorrentes sejam comprados sozinhos', 'pagbank-connect')),
        'default'     => 'no'
    ],
    [
        'id'          => 'woocommerce_rm-pagbank-recurring_retry_charge',
        'title'       => __( 'Tentar novamente?', 'pagbank-connect'),
        'label'       => __( 'Habilitar', 'pagbank-connect' ),
        'type'        => 'checkbox',
        'default'     => 'yes',
        'desc_tip'    => esc_html(
            __(
                'Se o pagamento recorrente falhar, o plugin tentará cobrar novamente.',
                'pagbank-connect'
            )),
    ],
    [
		'id'          => 'woocommerce_rm-pagbank-recurring_retry_attempts',
		'title'       => __( 'Número de tentativas', 'pagbank-connect' ),
		'type'        => 'number',
		'default'     => 3,
		'custom_attributes' => [
			'min' => 1,
			'max' => 4,
		],
        'desc_tip'    => esc_html(
            __(
                'Ocorrem com intervalos de 24 horas, exceto a última que ocorre após 3 dias da última tentativa.',
                'pagbank-connect'
            )),
	],
    [
        'type' => 'sectionend',
        'id' => 'rm-pagbank-recurring-settings'
    ]
);
