<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
$inviteUrl = $isVindi ? \RM_PagBank\Helpers\SplitIdentity::selfServiceRegisterUrl() : '';
$lookupUrl = $isVindi ? \RM_PagBank\Helpers\SplitIdentity::selfServiceLookupUrl() : '';

$fields = [
    'dokan_split_section' => [
        'title' => esc_html( __( 'Integração Dokan - Split de Pagamentos', 'pagbank-connect' ) ),
        'type'  => 'title',
        'desc'  => $isVindi
            ? esc_html( __(
                'Split Dokan na Vindi: vendedores informam o Affiliate ID (self-service no pbintegracoes). A loja (Connect Key) fica com o restante automaticamente — sem custódia PagBank.',
                'pagbank-connect'
            ))
            : esc_html( __(
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
        'description' => $isVindi
            ? esc_html( __(
                'Quando ativado, vendedores com Affiliate ID Vindi ativo receberão a comissão via bill_affiliates.',
                'pagbank-connect'
            ))
            : esc_html( __(
                'Quando ativado, vendedores com Account ID PagBank configurado receberão pagamentos diretos via split.',
                'pagbank-connect'
            )),
        'default' => 'no',
        'id'    => 'woocommerce_rm-pagbank-integrations_dokan_split_enabled',
    ],
];

if ($isVindi) {
    $fields['vindi_affiliate_invite_link'] = [
        'title'       => esc_html( __( 'Link self-service de afiliados', 'pagbank-connect' ) ),
        'type'        => 'text',
        'description' => sprintf(
            '%s<br><strong>%s</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a><br><strong>%s</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a><br>%s',
            esc_html__(
                'Compartilhe com os vendedores. A Connect Key nunca é exposta — o token identifica a loja no servidor.',
                'pagbank-connect'
            ),
            esc_html__('Buscar ID (e-mail/CPF/CNPJ):', 'pagbank-connect'),
            esc_url($lookupUrl),
            esc_html($lookupUrl !== '' ? $lookupUrl : __('Gerar link (salve as configurações / recarregue)', 'pagbank-connect')),
            esc_html__('Cadastrar novo afiliado:', 'pagbank-connect'),
            esc_url($inviteUrl),
            esc_html($inviteUrl !== '' ? $inviteUrl : __('Gerar link (salve as configurações / recarregue)', 'pagbank-connect')),
            esc_html__('Após o cadastro, o afiliado deve validar a conta em portal.vindi.com.br antes de colar o Affiliate ID no Dokan.', 'pagbank-connect')
        ),
        'default'     => $lookupUrl !== '' ? $lookupUrl : $inviteUrl,
        'custom_attributes' => [
            'readonly' => 'readonly',
        ],
        'desc_tip'    => false,
        'id'          => 'woocommerce_rm-pagbank-integrations_vindi_affiliate_invite_link',
    ];
} else {
    $fields['marketplace_account_id'] = [
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
    ];
    $fields['split_custody_days'] = [
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
    ];
    $fields['split_chargeback_liability'] = [
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
    ];
}

$fields['split_marketplace_reason'] = [
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
];

$fields['split_notifications'] = [
    'title' => esc_html( __( 'Notificações', 'pagbank-connect' ) ),
    'type'  => 'checkbox',
    'label' => $isVindi
        ? esc_html( __( 'Notificar admin quando Affiliate ID for configurado', 'pagbank-connect' ) )
        : esc_html( __( 'Notificar vendedores sobre configuração de Account ID', 'pagbank-connect' ) ),
    'description' => esc_html( __(
        'Enviar e-mail quando o ID de split do vendedor for configurado ou alterado.',
        'pagbank-connect'
    )),
    'default' => 'yes',
    'desc_tip'    => true,
    'id'          => 'woocommerce_rm-pagbank-integrations_split_notifications',
];

return $fields;
