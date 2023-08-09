<?php
return array(
    'pix_enabled'            => array(
        'title'       => __( 'Habilitar', \RM_PagBank\Connect::DOMAIN),
        'label'       => __( 'Habilitar', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ),
    'pix_title'              => array(
        'title'       => __( 'Título Principal', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'safe_text',
        'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'PIX', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
        'class' => 'pix_attr'
    ),
    /*'pix_description'        => array(
        'title'       => __( 'Description', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Payment method description that the customer will see on your website.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'Pay with PIX via PagSeguro.', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
        'class'       => 'pix_attr'
    ),*/
    'pix_instructions'       => array(
        'title'       => __( 'Instruções', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à sua página de sucesso.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'O QrCode será exibido na finalização do pedido.', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
        'class'       => 'pix_attr'
    ),
    'pix_expiry_minutes'       => array(
        'title'       => __( 'Validade do PIX', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'number',
        'description' => __( 'minutos', \RM_PagBank\Connect::DOMAIN ),
        'default'     => 1440,
        'desc_tip'    => false,
        'custom_attr' => array(
            'min' => 1,
            'step' => 1,
        ),
    ),
);