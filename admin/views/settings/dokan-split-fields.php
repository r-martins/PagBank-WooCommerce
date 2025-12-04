<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

return array(
    'dokan_split_section' => [
        'title' => esc_html( __( 'Integração Dokan - Split de Pagamentos', 'pagbank-connect' ) ),
        'type'  => 'title',
        'desc'  => esc_html( __(
            'Configure a integração com o Dokan para permitir split de pagamentos direto para vendedores. Esta funcionalidade permite que vendedores recebam pagamentos diretamente em suas contas PagBank, com proteção via custódia e controle de responsabilidade por chargebacks.',
            'pagbank-connect'
        )),
        'id'    => 'wc_pagseguro_connect_dokan_split_options',
    ],
    'dokan_split_enabled' => [
        'title' => esc_html( __( 'Habilitar Split Dokan', 'pagbank-connect' ) ),
        'label' => esc_html( __( 'Ativar split de pagamentos para vendedores Dokan', 'pagbank-connect' ) ),
        'type'  => 'checkbox',
        'desc_tip' => true,
        'description' => esc_html( __(
            'Quando ativado, vendedores com Account ID PagBank configurado receberão pagamentos diretos via split.',
            'pagbank-connect'
        )),
        'default' => 'no',
        'id'    => 'woocommerce_rm-pagbank-integrations_dokan_split_enabled',
    ],
    'marketplace_account_id' => [
        'title'       => esc_html( __( 'Account ID do Marketplace', 'pagbank-connect' ) ),
        'type'        => 'text',
        'description' => sprintf(
            '%s<br><a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_html( __(
                'ID da conta PagBank do marketplace (recebedor primário). Formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'pagbank-connect'
            )),
            esc_url( 'https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize' ),
            esc_html( __( 'Clique aqui para descobrir qual é o seu Account ID', 'pagbank-connect' ) )
        ),
        'default'     => '',
        'placeholder' => 'ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'desc_tip'    => true,
        'required'    => false,
        'id'          => 'woocommerce_rm-pagbank-integrations_marketplace_account_id',
        'custom_attributes' => [
            'pattern' => 'ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}',
            'title' => 'Formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
        ],
        'validate' => 'validate_pagbank_account_id'
    ],
    'split_marketplace_reason' => [
        'title'       => esc_html( __( 'Descrição do Marketplace', 'pagbank-connect' ) ),
        'type'        => 'text',
        'description' => esc_html( __(
            'Descrição que aparecerá na fatura do marketplace para transações com split.',
            'pagbank-connect'
        )),
        'default'     => 'Comissão do Marketplace',
        'placeholder' => 'Comissão do Marketplace',
        'desc_tip'    => true,
        'required'    => false,
        'id'          => 'woocommerce_rm-pagbank-integrations_split_marketplace_reason',
        'custom_attributes' => [
            'maxlength' => 64
        ]
    ],
    'split_custody_days' => [
        'title'       => esc_html( __( 'Prazo de Custódia (dias)', 'pagbank-connect' ) ),
        'type'        => 'number',
        'description' => esc_html( __(
            'Número de dias para liberação automática da custódia dos vendedores.',
            'pagbank-connect'
        )),
        'default'     => 7,
        'placeholder' => '7',
        'desc_tip'    => true,
        'required'    => false,
        'id'          => 'woocommerce_rm-pagbank-integrations_split_custody_days',
        'custom_attributes' => [
            'min' => 1,
            'max' => 30
        ]
    ],
    'split_chargeback_liability' => [
        'title' => esc_html( __( 'Responsabilidade por Chargebacks (Liable)', 'pagbank-connect' ) ),
        'type'  => 'select',
        'description' => esc_html( __(
            'Define quem será marcado como "liable" (responsável principal) nas transações com split.',
            'pagbank-connect'
        )),
        'default' => 'auto',
        'options' => [
            'auto' => esc_html( __( 'Automático (1 vendedor = vendedor liable, 2+ vendedores = marketplace liable)', 'pagbank-connect' ) ),
            'marketplace' => esc_html( __( 'Sempre marketplace liable', 'pagbank-connect' ) )
        ],
        'desc_tip'    => true,
        'id'          => 'woocommerce_rm-pagbank-integrations_split_chargeback_liability',
    ],
    'split_notifications' => [
        'title' => esc_html( __( 'Notificações', 'pagbank-connect' ) ),
        'type'  => 'checkbox',
        'label' => esc_html( __( 'Notificar vendedores sobre configuração de Account ID', 'pagbank-connect' ) ),
        'description' => esc_html( __(
            'Enviar e-mail para vendedores quando Account ID for configurado ou alterado.',
            'pagbank-connect'
        )),
        'default' => 'yes',
        'desc_tip'    => true,
        'id'          => 'woocommerce_rm-pagbank-integrations_split_notifications',
    ],
);
