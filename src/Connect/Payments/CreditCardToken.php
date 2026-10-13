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
use RM_PagBank\Helpers\OrderMeta;

/**
 * Class CreditCard
 *
 * @author    Ricardo Martins
 * @copyright 2024 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class CreditCardToken extends Common
{
    public string $code = 'credit_card_token';

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
            'encrypted' => OrderMeta::get($this->order, '_card_encrypted')
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
        $api = new \RM_PagBank\Helpers\Api();
        OrderMeta::set($order, 'partner', $api->partner()->getPartner(), true);
        OrderMeta::set($order, 'connect_key_fp', $api->partner()->fingerprint(), true);
        OrderMeta::set($order, 'order_recurring_card', $response ?? null, true);
        OrderMeta::set($order, 'is_sandbox', Params::getConfig('is_sandbox', false) ? 1 : 0);

        $brand = (string) ($response['brand'] ?? OrderMeta::get($order, '_payment_company_code') ?: '');
        $first = (string) ($response['first_digits'] ?? OrderMeta::get($order, '_card_first_digits') ?: '');
        $last = (string) ($response['last_digits'] ?? OrderMeta::get($order, 'card_last4') ?: '');
        $holder = (string) ($response['holder']['name'] ?? OrderMeta::get($order, '_card_holder_name') ?: '');
        if ($brand !== '') {
            OrderMeta::set($order, '_card_brand', $brand, true);
        }
        if ($first !== '') {
            OrderMeta::set($order, '_card_first_digits', $first, true);
        }
        if ($last !== '') {
            OrderMeta::set($order, '_card_last_digits', $last, true);
            OrderMeta::set($order, 'card_last4', substr($last, -4), true);
        }
        if ($holder !== '') {
            OrderMeta::set($order, '_card_holder', $holder, true);
            OrderMeta::set($order, '_card_holder_name', $holder, true);
        }
        if (!OrderMeta::get($order, 'card_installments')) {
            OrderMeta::set($order, 'card_installments', 1, true);
        }
        // Keep admin order-info on the same path as charged cards.
        OrderMeta::set($order, 'payment_method', 'credit_card', true);

        $partnerLabel = \RM_PagBank\Partner\Branding::notePrefix($order);
        $order->update_status('processing', $partnerLabel . ': Pagamento Pendente');
        do_action('pagbank_connect_after_proccess_response', $order, $response);
    }
}
