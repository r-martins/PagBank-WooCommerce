<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\Card;
use RM_PagBank\Object\Charge;
use RM_PagBank\Object\Holder;

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
    
    public function __construct($order)
    {
        parent::__construct($order);
    }

    public function prepare():array
    {
        $return = $this->getDefaultParameters();
        $charge = new Charge();
        $amount = new Amount();
        $amount->setValue(Params::convertToCents($this->order->get_total()));
        $charge->setAmount($amount);
        
        $paymentMethod = new \RM_PagBank\Object\PaymentMethod();
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
    
    public static function pagseguro_connect_inline_scripts()
    {
        wp_add_inline_script(
            'pagseguro-connect-public-key',
            'var pagseguro_connect_public_key = \'' . Params::getConfig('public_key') . '\';'
        );
    }
    public static function getAjaxInstallments(){
        global $woocommerce;

        $order_total = $woocommerce->cart->get_total('edit');
        $cc_bin = intval($_REQUEST['cc_bin']);

        $installments = Params::getInstallments($order_total, $cc_bin);
        if (!$installments){
            wp_send_json(array('error' => 'Não foi possível obter as parcelas. Verifique o número do cartão digitado.'), 400);
        }
        wp_send_json($installments);
    }
    
    
}