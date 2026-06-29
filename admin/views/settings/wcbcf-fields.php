<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'wcbcf_section' => [
        'title' => esc_html(__('Brazilian Market on WooCommerce', 'pagbank-connect')),
        'type'  => 'title',
        'desc'  => esc_html(__(
            'Compatibilidade com o plugin de campos brasileiros no checkout legado (CPF/CNPJ, endereço etc.).',
            'pagbank-connect'
        )),
        'id'          => 'wc_pagseguro_connect_wcbcf_options',
        'integration' => 'wcbcf',
    ],
    'wcbcf_alnum_cnpj_compat' => [
        'title'       => esc_html(__('CNPJ alfanumérico', 'pagbank-connect')),
        'label'       => esc_html(__('Habilitar compatibilidade com CNPJ alfanumérico no checkout legado', 'pagbank-connect')),
        'type'        => 'checkbox',
        'desc_tip'    => true,
        'description' => esc_html(__(
            'Ativa máscara e validação de CNPJ alfanumérico quando o checkout em blocos não estiver em uso. Recurso paliativo até o Brazilian Market publicar suporte nativo.',
            'pagbank-connect'
        )),
        'default'     => 'no',
        'id'          => 'woocommerce_rm-pagbank-integrations_wcbcf_alnum_cnpj_compat',
        'integration' => 'wcbcf',
    ],
];
