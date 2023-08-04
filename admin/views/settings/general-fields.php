<?php
return array(
    'connect_key' => array(
        'title'       => __( 'Connect Key', \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __( 'Please enter your Connect Key; this is needed in order to take payment.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => '',
        'placeholder' => 'CON...',
        'desc_tip'    => true,
        'required'    => true,
        'validate' => 'validate-connectkey',
        'custom_attributes' => array(
            'maxlength' => 40,
            'minlength' => 40,
        )
    ),
    'general' => array(
        'title' => __( 'Configurações Visuais', \RM_PagSeguro\Connect::DOMAIN ),
        'type'  => 'title',
        'desc'  => '',
        'id'    => 'wc_pagseguro_connect_general_options',
    ),
    'title' => array(
        'title'       => __( 'Main Title' , \RM_PagSeguro\Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __( 'Main method name, displayed near the radio button.', \RM_PagSeguro\Connect::DOMAIN ),
        'default'     => __( 'PagBank UOL', \RM_PagSeguro\Connect::DOMAIN ),
        'desc_tip'    => true,
        'required'    => true,
        'validate' => 'validate-connectkey',
        'custom_attributes' => array(
            'maxlength' => 40,
        )
    )
);