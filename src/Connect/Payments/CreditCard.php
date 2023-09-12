<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\Card;
use RM_PagBank\Object\Charge;
use RM_PagBank\Object\Holder;
use RM_PagBank\Object\PaymentMethod;
use WC_Order;

/**
 * Class CreditCard
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class CreditCard extends Common
{
    public string $code = 'credit_card';

	/**
	 * @param WC_Order $order
	 */
	public function __construct(WC_Order $order)
    {
        parent::__construct($order);
    }

	/**
	 * Create the array with the data to be sent to the API on CreditCard payments
	 * @return array
	 */
	public function prepare():array
    {
        $return = $this->getDefaultParameters();
        $charge = new Charge();
        $amount = new Amount();
        $amount->setValue(Params::convertToCents($this->order->get_total()));
        $charge->setAmount($amount);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setType('CREDIT_CARD');
        $paymentMethod->setCapture(true);
        $paymentMethod->setInstallments(intval($this->order->get_meta('pagbank_card_installments')));
        $paymentMethod->setSoftDescriptor(Params::getConfig('cc_soft_descriptor'));
        $card = new Card();
        $card->setEncrypted($this->order->get_meta('_pagbank_card_encrypted'));
        $holder = new Holder();
        $holder->setName($this->order->get_meta('_pagbank_card_holder_name'));
        $card->setHolder($holder);
        $paymentMethod->setCard($card);
        $charge->setPaymentMethod($paymentMethod);

        $return['charges'] = [$charge];
        return $return;
    }

	/**
	 * Outputs the installment options to populate the select field on checkout
	 * @return void
	 */
	public static function getAjaxInstallments(){
        global $woocommerce;

        $order_total = floatval($woocommerce->cart->get_total('edit'));
        $cc_bin = intval($_REQUEST['cc_bin']);

		if (!$order_total) return;

        $installments = Params::getInstallments($order_total, $cc_bin);
        if (!$installments){
			wp_send_json(
				['error' =>
					 __('Não foi possível obter as parcelas. Verifique o número do cartão digitado.', Connect::DOMAIN)],
				400);
        }
        wp_send_json($installments);
    }
}
