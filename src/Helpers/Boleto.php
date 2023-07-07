<?php

namespace RM_PagSeguro\Helpers;

use WC_Order;

class Boleto
{
    /**
     * Extracts the Boleto request params from the order.
     * @param $order WC_Order
     *
     * @return bool|string
     */
    public function extractBoletoRequestParams($order)
    {
        $phone = Params::extract_phone($order);
        $return = array(
            'reference_id' => $order->get_id(),
            'customer' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'tax_id'=> Params::remove_non_numeric($order->get_meta('_billing_cpf')),
                'phones' => array(
                    array(
                    'country' => '55',
                    'area'=> $phone['area'],
                    'number' => $phone['number'],
                    'type'  => $phone['type']
                    )
                ),
            ),
            'shipping' => array(
                'address' => array(
                    'street' => $order->get_billing_address_1(),
                    'number' => $order->get_meta('_billing_number'),
                    'complement' => $order->get_billing_address_2(),
                    'locality' => $order->get_meta('_billing_neighborhood'),
                    'city' => $order->get_billing_city(),
                    'region_code' => $order->get_billing_state(),
                    'country' => 'BRA',
                    'postal_code' => Params::remove_non_numeric($order->get_billing_postcode()),
                ),
            ),
            'notification_urls' => array(
                'https://webhook.site/'
            )
        );
        return $return;
    }
}