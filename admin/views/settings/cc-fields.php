<?php
return array(
    'cc_enabled'            => array(
        'title'       => __( 'Habilitar', \RM_PagBank\Connect::DOMAIN),
        'label'       => __( 'Habilitar', \RM_PagBank\Connect::DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
    ),
    'cc_installment_options' => array(
        'title' => __( 'Opções de Parcelamento', \RM_PagBank\Connect::DOMAIN ),
        'type'  => 'select',
        'desc'  => '',
        'options' => array(
            'external' => __( 'Obedecer configurações da conta PagBank (padrão)', \RM_PagBank\Connect::DOMAIN ),
            'buyer'  => __( 'Juros por conta do comprador', \RM_PagBank\Connect::DOMAIN ),
            'fixed'  => __( 'Até X parcelas sem juros', \RM_PagBank\Connect::DOMAIN ),
            'min_total'  => __( 'Até X parcelas sem juros dependendo do valor da parcela', \RM_PagBank\Connect::DOMAIN ),
        ),
    ),
    'cc_installment_options_fixed' => array(
        'title' => __( 'Número de Parcelas sem Juros', \RM_PagBank\Connect::DOMAIN ),
        'type'  => 'number',
        'desc'  => '',
        'default' => 3,
        'custom_attributes' => array(
            'min' => 1,
            'max' => 18,
        )
    ),
    'cc_installments_options_min_total' => array(
        'title' => __( 'Valor Mínimo da Parcela sem Juros', \RM_PagBank\Connect::DOMAIN ),
        'type'  => 'number',
        'description'  => __('Valor inteiro sem decimais. Exemplo: 10 para R$ 10,00 <br/><small>Neste exemplo, um pedido de R$100 poderá ser parcelado em 10x sem juros.<br/>Taxa padrão de juros: 2,99% a.m (consulte valor atualizado).</small>', \RM_PagBank\Connect::DOMAIN ),
        //        'desc_tip' => true,
        'default' => 50,
        'custom_attributes' => array(
            'min' => 5,
            'max' => 99999,
        )
    ),
    'cc_installments_options_limit_installments' => array(
        'title' => __( 'Limitar parcelas?', \RM_PagBank\Connect::DOMAIN ),
        'type'  => 'select',
        'description' => __('Recomendação: Não impeça seu cliente de comprar com um parcelamento elevado mesmo que ele queira assumir os juros.<br/>Não há um custo maior pra você.', \RM_PagBank\Connect::DOMAIN),
        'options' => array(
            'no' => __( 'Não (recomendável)', \RM_PagBank\Connect::DOMAIN ),
            'yes'  => __( 'Sim', \RM_PagBank\Connect::DOMAIN ),
        )
    ),
    
    'cc_installments_options_max_installments' => array(
        'title' => __( 'Número Máximo de Parcelas', \RM_PagBank\Connect::DOMAIN ),
        'type'  => 'number',
        'default' => 18,
        'custom_attributes' => array(
            'min' => 1,
            'max' => 18,
        ),
    )
);