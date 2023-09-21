<?php

use RM_PagBank\Connect;

return array(
	'cc_enabled'                                 => [
		'title'       => __('Habilitar', Connect::DOMAIN),
		'label'       => __('Habilitar', Connect::DOMAIN),
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'yes',
	],
	'cc_installment_options'                     => [
		'title'   => __('Opções de Parcelamento', Connect::DOMAIN),
		'type'    => 'select',
		'desc'    => '',
		'options' => [
			'external'  => __('Obedecer configurações da conta PagBank (padrão)', Connect::DOMAIN),
			'buyer'     => __('Juros por conta do comprador', Connect::DOMAIN),
			'fixed'     => __('Até X parcelas sem juros', Connect::DOMAIN),
			'min_total' => __('Até X parcelas sem juros dependendo do valor da parcela', Connect::DOMAIN),
		],
	],
	'cc_installment_options_fixed'               => [
		'title'             => __('Número de Parcelas sem Juros', Connect::DOMAIN),
		'type'              => 'number',
		'desc'              => '',
		'default'           => 3,
		'custom_attributes' => [
			'min' => 1,
			'max' => 18,
		],
	],
	'cc_installments_options_min_total'          => [
		'title'             => __('Valor Mínimo da Parcela sem Juros', Connect::DOMAIN),
		'type'              => 'number',
		'description'       => __(
			'Valor inteiro sem decimais. Exemplo: 10 para R$ 10,00 <br/><small>Neste exemplo, um pedido '
			.'de R$100 poderá ser parcelado em 10x sem juros.<br/>Taxa padrão de juros: '
			.'2,99% a.m (consulte valor atualizado).</small>',
			Connect::DOMAIN
		),
		//        'desc_tip' => true,
		'default'           => 50,
		'custom_attributes' => [
			'min' => 5,
			'max' => 99999,
		],
	],
	'cc_installments_options_limit_installments' => [
		'title'       => __('Limitar parcelas?', Connect::DOMAIN),
		'type'        => 'select',
		'description' => __(
			'Recomendação: Não impeça seu cliente de comprar com um parcelamento elevado mesmo que ele '
			.'queira assumir os juros.<br/>Não há um custo maior pra você.',
			Connect::DOMAIN
		),
		'options'     => [
			'no'  => __('Não (recomendável)', Connect::DOMAIN),
			'yes' => __('Sim', Connect::DOMAIN),
		],
	],

	'cc_installments_options_max_installments' => [
		'title'             => __('Número Máximo de Parcelas', Connect::DOMAIN),
		'type'              => 'number',
		'default'           => 18,
		'custom_attributes' => [
			'min' => 1,
			'max' => 18,
		],
	],

	'cc_soft_descriptor' => [
		'title'             => __('Identificador na Fatura', Connect::DOMAIN),
		'type'              => 'text',
		'default'           => 'CompraViaPagBank',
		'description'       => __(
			'Nome que será exibido na fatura do Cliente. '
			.'Escolha um nome que faça o cliente lembrar que comprou na sua loja e evite chargebacks. '
			.'Algumas empresas de cartão podem exibir somente os 13 primeiros caracteres.',
			Connect::DOMAIN
		),
		'desc_tip'          => true,
		'custom_attributes' => [
			'maxlength' => 17,
		],
	],
);
