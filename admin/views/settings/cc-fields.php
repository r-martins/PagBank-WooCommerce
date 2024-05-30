<?php
if (!defined('ABSPATH')) {
    exit;
}

use RM_PagBank\Connect;

return array(
    'cc_enabled'                                 => [
        'title'       => __('Habilitar', 'pagbank-connect'),
        'label'       => __('Habilitar', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes',
    ],
    'cc_title'                                   => [
        'title'       => __('Título Principal', 'pagbank-connect'),
        'type'        => 'safe_text',
        'description' => __('Nome do meio de pagamento que seu cliente irá ver no checkout.', 'pagbank-connect'),
        'default'     => __('Cartão de Crédito via PagBank', 'pagbank-connect'),
        'desc_tip'    => true,
    ],
    'cc_installment_options'                     => [
        'title'       => __('Opções de Parcelamento', 'pagbank-connect'),
        'type'        => 'select',
        'description' => __(
            '<a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945359660173-'
            .'Op%C3%A7%C3%B5es-de-Parcelamento" target="_blank">Saiba mais</a>',
            'pagbank-connect'
        ),
        'desc_tip'    => false,
        'options'     => [
            'external'  => __('Obedecer configurações da conta PagBank (padrão)', 'pagbank-connect'),
            'buyer'     => __('Juros por conta do comprador', 'pagbank-connect'),
            'fixed'     => __('Até X parcelas sem juros', 'pagbank-connect'),
            'min_total' => __('Até X parcelas sem juros dependendo do valor da parcela', 'pagbank-connect'),
        ],
    ],
    'cc_installment_options_fixed'               => [
        'title'             => __('Número de Parcelas sem Juros', 'pagbank-connect'),
        'type'              => 'number',
        'desc'              => '',
        'default'           => 3,
        'custom_attributes' => [
            'min' => 1,
            'max' => 18,
        ],
    ],
    'cc_installments_options_min_total'          => [
        'title'             => __('Valor Mínimo da Parcela sem Juros', 'pagbank-connect'),
        'type'              => 'number',
        'description'       => __(
            'Valor inteiro sem decimais. Exemplo: 10 para R$ 10,00 <br/><small>Neste exemplo, um pedido '
            .'de R$100 poderá ser parcelado em 10x sem juros.<br/>Taxa padrão de juros: '
            .'2,99% a.m (consulte valor atualizado).</small>',
            'pagbank-connect'
        ),
        //        'desc_tip' => true,
        'default'           => 50,
        'custom_attributes' => [
            'min' => 5,
            'max' => 99999,
        ],
    ],
    'cc_installments_options_limit_installments' => [
        'title'       => __('Limitar parcelas?', 'pagbank-connect'),
        'type'        => 'select',
        'description' => __(
            '<a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945359660173'
            .'-Op%C3%A7%C3%B5es-de-Parcelamento#limitar-parcelas" target="_blank">Saiba mais</a>',
            'pagbank-connect'
        ),
        'options'     => [
            'no'  => __('Não (recomendável)', 'pagbank-connect'),
            'yes' => __('Sim', 'pagbank-connect'),
        ],
    ],

    'cc_installments_options_max_installments' => [
        'title'             => __('Número Máximo de Parcelas', 'pagbank-connect'),
        'type'              => 'number',
        'default'           => 18,
        'custom_attributes' => [
            'min' => 1,
            'max' => 18,
        ],
    ],
    'cc_installment_product_page'              => [
        'title'       => __('Informações de Parcelamento', 'pagbank-connect'),
        'label'       => __('Exibir informações de parcelamento na tela do produto?', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => 'Veja <a href="https://pagsegurotransparente.zendesk.com/hc/pt-br'
            .'/articles/26223028355597-Exibir-informa%C3%A7%C3%B5es-de-parcelamento-na-p%C3%A1gina-de-produt'
            .'o" target="_blank">como funciona</a>.',
        'default'     => 'no',
    ],
    'cc_installment_shortcode_enabled'              => [
        'title'       => __('Shortcode de parcelamento', 'pagbank-connect'),
        'label'       => __('Habilitar', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => 'Veja <a href="https://pagsegurotransparente.zendesk.com/hc/pt-br'
            .'/articles/26223028355597-Exibir-informa%C3%A7%C3%B5es-de-parcelamento-na-p%C3%A1gina-de-produt'
            .'o#shortcode" target="_blank">como usar</a>.',
        'default'     => 'no',
    ],
    'cc_installment_product_page_type'         => [
        'title'       => __('Formato das informações de parcelamento', 'pagbank-connect'),
        'type'        => 'select',
        'description' => __(
            '<a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/26223028355597'
            .'-Exibir-informa%C3%A7%C3%B5es-de-parcelamento-na-p%C3%A1gina-de-produto" target="_blank"'
            .'>Saiba mais </a>',
            'pagbank-connect'
        ),
        'options'     => [
            'table'                 => __('Tabela com todas as parcelas', 'pagbank-connect'),
            'text-installment-free' => __('Texto com parcela máxima sem juros', 'pagbank-connect'),
            'text-installment-max'  => __('Texto com parcela máxima', 'pagbank-connect'),
        ],
    ],
    'cc_soft_descriptor'                       => [
        'title'             => __('Identificador na Fatura', 'pagbank-connect'),
        'type'              => 'text',
        'default'           => 'CompraViaPagBank',
        'description'       => __(
            'Nome que será exibido na fatura do Cliente. <a href="https://'
            .'pagsegurotransparente.zendesk.com/hc/pt-br/articles/19945038495629-Identificador-na-fatura" '
            .'target="_blank">Veja algumas dicas</a>.',
            'pagbank-connect'
        ),
        'desc_tip'          => false,
        'custom_attributes' => [
            'maxlength' => 17,
        ],
    ],
    'cc_3ds'                                   => [
        'title'       => __('Autenticação 3D', 'pagbank-connect'),
        'label'       => __('Habilitar', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => 'Habilita a autenticação <a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/a'
            .'rticles/22375922278157-Autentica%C3%A7%C3%A3o-3DS-Sua-prote%C3%A7%C3%A3o-contra-Chargeback" '
            .'target="_blank">3D Secure</a> para compras com cartão de crédito. <br/>'
            .'A autenticação 3D Secure é um protocolo de segurança que adiciona uma camada extra de proteção '
            .'para compras online, <br/> e evita que chargebacks de compras não reconhecidas sejam '
            .'cobrados do lojista.',
        'default'     => 'yes',
    ],
    'cc_3ds_allow_continue'                    => [
        'title'       => __('Quando 3D não for suportado', 'pagbank-connect'),
        'label'       => __('Permitir concluir', 'pagbank-connect'),
        'type'        => 'checkbox',
        'description' => 'Alguns cartões não possuem suporte a autenticação 3D. <br/>'
            .'Ao marcar esta opção, o cliente poderá concluir a compra mesmo que o cartão não suporte tal recurso <br/>'
            .'ou se a obtenção da sessão 3D Secure junto ao PagBank falhar.',
        'default'     => 'no',
    ],
);
