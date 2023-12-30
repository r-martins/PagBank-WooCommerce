<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\AuthenticationMethod;
use RM_PagBank\Object\Buyer;
use RM_PagBank\Object\Card;
use RM_PagBank\Object\Charge;
use RM_PagBank\Object\Fees;
use RM_PagBank\Object\Holder;
use RM_PagBank\Object\Interest;
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

        //3ds
        if ($this->order->get_meta('_pagbank_card_3ds_id') && Params::getConfig('cc_3ds') === 'yes'){
            $authMethod = new AuthenticationMethod();
            $authMethod->setType('THREEDS');
            $authMethod->setId($this->order->get_meta('_pagbank_card_3ds_id'));
            $paymentMethod->setAuthenticationMethod($authMethod);
        }
        
        $charge->setPaymentMethod($paymentMethod);

		if ($paymentMethod->getInstallments() > 1)
		{
			$selectedInstallments = $paymentMethod->getInstallments();
			$installments = Params::getInstallments($this->order->get_total(), $this->order->get_meta('_pagbank_card_first_digits'));
			$installment = Params::extractInstallment($installments, $selectedInstallments);
			if ($installment['fees']){
				$interest = new Interest();
				$interest->setInstallments($installment['fees']['buyer']['interest']['installments']);
				$interest->setTotal($installment['fees']['buyer']['interest']['total']);
				$buyer = new Buyer();
				$buyer->setInterest($interest);
				$fees = new Fees();
				$fees->setBuyer($buyer);
				$amount->setFees($fees);
				$amount->setValue($installment['total_amount_raw']);
			}
		}
        
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
		if (!wp_verify_nonce($_REQUEST['nonce'], 'rm_pagbank_nonce')) {
			wp_send_json_error([
				'error' => __(
					'Não foi possível obter as parcelas. Chave de formulário inválida. '
					.'Recarregue a página e tente novamente.',
					'pagbank-connect'
				),
			],
				400);
		}

        $cc_bin = intval($_REQUEST['cc_bin']);

		if (!$order_total) return;
        $installments = Params::getInstallments($order_total, $cc_bin);
        if (isset($installments['error'])){
			$error = $installments['error'] ?? '';
			wp_send_json(
                ['error' => sprintf(__('Não foi possível obter as parcelas. %s', 'pagbank-connect'), $error)],
				400);
        }
        wp_send_json($installments);
    }

    /**
     * Outputs the cart total (used via ajax with nonce validation)
     * @return void
     */
    public static function getCartTotal()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'rm_pagbank_nonce')) {
            wp_send_json_error([
                'error' => __(
                    'Não foi possível obter o total. Chave de formulário inválida. '
                    .'Recarregue a página e tente novamente.',
                    'pagbank-connect'
                ),
            ],
                400);
        }
        global $woocommerce;
        echo $woocommerce->cart->get_total('edit');
        wp_die();
    }
}
