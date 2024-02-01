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
use RM_PagBank\Object\Recurring;
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
        $card = $this->getCardDetails();
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

        //region Recurring initial or subsequent order
        $recurring = new Recurring();
        if ($this->order->get_meta('_pagbank_recurring_initial')) {
            $recurring->setType('INITIAL');
            $charge->setRecurring($recurring);
            $card->setStore(true);
            $paymentMethod->setCard($card);
//            if (floatval($this->order->get_meta('_recurring_initial_fee')) > 0) {
//                $currentAmount = $charge->getAmount()->getValue();
//                $initialFee = $this->order->get_meta('_recurring_initial_fee');
//                $newAmount = new Amount();
//                $newAmount->setValue($currentAmount + Params::convertToCents($initialFee));
//                $charge->setAmount($newAmount);
//            }
        }
        
        if ($this->order->get_meta('_pagbank_is_recurring') === true) {
            $recurring->setType('SUBSEQUENT');
            $charge->setRecurring($recurring);
        }
        //endregion

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
		if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'rm_pagbank_nonce')) {
			wp_send_json_error([
				'error' => __(
					'Não foi possível obter as parcelas. Chave de formulário inválida. '
					.'Recarregue a página e tente novamente.',
					'pagbank-connect'
				),
			],
				400);
		}

        $cc_bin = isset( $_REQUEST['cc_bin'] ) ? intval($_REQUEST['cc_bin']) : 0;

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
     * Populates the Card object considering with data from order or subscription
     * @return Card
     */
    protected function getCardDetails(): Card
    {
        $card = new Card();
        //if subsequent recurring order...
        if ($this->order->get_meta('_pagbank_is_recurring') === true)
        {
            //get card data from subscription
            global $wpdb;
            $initialSubOrderId = $this->order->get_parent_id('edit');
            $sql = "SELECT * from {$wpdb->prefix}pagbank_recurring WHERE initial_order_id = 0{$initialSubOrderId}";
            $recurring = $wpdb->get_row( $wpdb->prepare( $sql ) );
            $paymentInfo = json_decode($recurring->payment_info);
            $card->setId($paymentInfo->card->id);
            $holder = new Holder();
            $holder->setName($paymentInfo->card->holder_name);
            $card->setHolder($holder);
            $card->setStore(true);
            return $card;
        }
        
        //non recurring...
        $card->setEncrypted($this->order->get_meta('_pagbank_card_encrypted'));
        $holder = new Holder();
        $holder->setName($this->order->get_meta('_pagbank_card_holder_name'));
        $card->setHolder($holder);

        return $card;
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
        echo esc_html( $woocommerce->cart->get_total('edit') );
        wp_die();
    }
}
