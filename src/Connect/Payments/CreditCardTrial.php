<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
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
 * @copyright 2024 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class CreditCardTrial extends Common
{
    public string $code = 'credit_card_trial';

    /**
	 * @param WC_Order $order
	 */
    public function __construct(WC_Order $order)
    {
        parent::__construct($order);
    }

    /**
     * Create the array with the data to be sent to the API
     *
     * @return array
     */
    public function prepare():array
    {
        return [
            'encrypted' => $this->order->get_meta('_pagbank_card_encrypted')
        ];
    }

    /**
     * Process response from the API and add the metadata to the order
     * @param WC_Order $order
     * @param array    $response
     *
     * @return void
     */
    public function process_response(WC_Order $order, array $response)
    {
        $order->add_meta_data('pagbank_order_recurring_card', $response ?? null, true);
        $order->add_meta_data('pagbank_is_sandbox', Params::getConfig('is_sandbox', false) ? 1 : 0);
        $order->update_status('processing', 'PagBank: Pagamento Pendente');
        do_action('pagbank_connect_after_proccess_response', $order, $response);
    }
}
