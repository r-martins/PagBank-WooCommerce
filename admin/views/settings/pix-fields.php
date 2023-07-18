<?php
return array(
    'pix_enabled'            => array(
        'title'       => __( 'Habilitar', \RM_PagSeguro\Connect::DOMAIN),
        'label'       => __( 'Habilitar', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ),
    'pix_title'              => array(
        'title'       => __( 'Title', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'safe_text',
        'description' => __( 'Nome do meio de pagamento que seu cliente irá ver no checkout.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'PIX', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
        'class' => 'pix_attr'
    ),
    /*'pix_description'        => array(
        'title'       => __( 'Description', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Payment method description that the customer will see on your website.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'Pay with PIX via PagSeguro.', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
        'class'       => 'pix_attr'
    ),*/
    'pix_instructions'       => array(
        'title'       => __( 'Instructions', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'textarea',
        'description' => __( 'Instruções que serão adicionadas à sua página de sucesso.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'O QrCode será exibido na finalização do pedido.', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
        'class'       => 'pix_attr'
    ),
    'pix_expiry_minutes'       => array(
        'title'       => __( 'Validade do PIX', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'number',
        'description' => __( 'minutos', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => 1440,
        'desc_tip'    => false,
    ),
);