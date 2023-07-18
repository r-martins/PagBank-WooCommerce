<?php
return array(
    'boleto_enabled'            => array(
        'title'       => __( 'Habilitar', \RM_PagSeguro\Connect::DOMAIN),
        'label'       => __( 'Habilitar', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ),
    'boleto_title'              => array(
        'title'       => __( 'Title', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'safe_text',
        'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'Boleto', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
    )/*,
    'boleto_description'        => array(
        'title'       => __( 'Description', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Payment method description that the customer will see on your website.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'Pay with PIX via PagSeguro.', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
    )*/,
    'boleto_instructions'       => array(
        'title'       => __( 'Instructions', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à sua página de sucesso.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'Imprima ou copie o código de barras de seu boleto para pagar no banco ou casa lotérica antes do vencimento.', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
    ),
    'boleto_expiry_days'       => array(
        'title'       => __( 'Validade do boleto', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'number',
        'description' => __( 'dias', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => 7,
        'desc_tip'    => false,
    ),
    'boleto_line_1'       => array(
        'title'       => __( 'Instruções (Linha 1)', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'text',
        'default'     => 'Sr. Caixa, favor não aceitar após vencimento.',
    ),
    'boleto_line_2'       => array(
        'title'       => __( 'Instruções (Linha 2)', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'text',
        'default'     => 'Obrigado por comprar em nossa loja!',
    ),
);