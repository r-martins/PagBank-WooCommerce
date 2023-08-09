<?php
return array(
    'connect_key' => array(
        'title'       => __( 'Connect Key', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __( 'Informe sua Connect Key, obtida após Obter as Credenciais. Este NÃO é o token PagBank.', \RM_PagBank\Connect::DOMAIN ),
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
        'title' => __( 'Configurações Visuais', \RM_PagBank\Connect::DOMAIN ),
        'type'  => 'title',
        'desc'  => '',
        'id'    => 'wc_pagseguro_connect_general_options',
    ),
    'title' => array(
        'title'       => __( 'Título Principal' , \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'text',
        'description' => __( 'Nome do meio de pagamento a ser exibido no radio button do checkout.', \RM_PagBank\Connect::DOMAIN ),
        'default'     => __( 'PagBank UOL', \RM_PagBank\Connect::DOMAIN ),
        'desc_tip'    => true,
        'required'    => true,
        'custom_attributes' => array(
            'maxlength' => 40,
        )
    ),
    'enabled'              => array(
        'title'   => __( 'Habilitar/Desabilitar', \RM_PagBank\Connect::DOMAIN ),
        'type'    => 'checkbox',
        'label'   => __( 'Habilitar PagBank', \RM_PagBank\Connect::DOMAIN ),
        'default' => 'yes',
    ),
);