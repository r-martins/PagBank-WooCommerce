<?php

namespace RM_PagSeguro\Connect\Payments;

use RM_PagSeguro\Helpers\Params;
use RM_PagSeguro\Object\Amount;
use RM_PagSeguro\Object\Card;
use RM_PagSeguro\Object\Charge;
use RM_PagSeguro\Object\Holder;

/**
 * Class CreditCard
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagSeguro\Connect\Payments
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
        $amount->setValue(Params::convert_to_cents($this->order->get_total()));
        $charge->setAmount($amount);
        
        $paymentMethod = new \RM_PagSeguro\Object\PaymentMethod();
        $paymentMethod->setType('CREDIT_CARD');
        $paymentMethod->setCapture(true);
        $paymentMethod->setInstallments(intval($this->order->get_meta('pagseguro_card_installments')));
        $card = new Card();
        $card->setEncrypted($this->order->get_meta('_pagseguro_card_encrypted'));
        $holder = new Holder();
        $holder->setName($this->order->get_meta('_pagseguro_card_holder_name'));
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
    public static function get_ajax_installments(){
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