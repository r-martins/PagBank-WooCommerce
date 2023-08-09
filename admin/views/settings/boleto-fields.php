<?php
return array(
    'boleto_enabled'            => array(
        'title'       => __( 'Habilitar', \RM_PagBank\Connect::DOMAIN),
        'label'       => __( 'Habilitar', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ),
    'boleto_title'              => array(
        'title'       => __( 'Title', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'safe_text',
        'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'Boleto', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
    )/*,
    'boleto_description'        => array(
        'title'       => __( 'Description', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Payment method description that the customer will see on your website.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'Pay with PIX via PagSeguro.', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
    )*/,
    'boleto_instructions'       => array(
        'title'       => __( 'Instruções', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à sua página de sucesso.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'Imprima ou copie o código de barras de seu boleto para pagar no banco ou casa lotérica antes do vencimento.', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
    ),
    'boleto_expiry_days'       => array(
        'title'       => __( 'Validade do boleto', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'number',
        'description' => __( 'dias', \RM_PagBank\Connect::DOMAIN ),
        'default'     => 7,
        'desc_tip'    => false,
    ),
    'boleto_line_1'       => array(
        'title'       => __( 'Instruções (Linha 1)', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'text',
        'default'     => 'Sr. Caixa, favor não aceitar após vencimento.',
    ),
    'boleto_line_2'       => array(
        'title'       => __( 'Instruções (Linha 2)', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'text',
        'default'     => 'Obrigado por comprar em nossa loja!',
    ),
);