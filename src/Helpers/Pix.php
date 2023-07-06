<?php

namespace RM_PagSeguro\Helpers;

use WC_Order;

class Pix
{
    /**
     * Extracts the Pix request params from the order.
     * @param $order WC_Order
     *
     * @return bool|string
     */
    public function extractPixRequestParams($order)
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
            'qr_codes' => array(
                array(
                'amount' => array(
                    'value' => Params::convert_to_cents($order->get_total()),
                ),
                'expiration_date' => date('Y-m-d\TH:i:s-03:00', strtotime('+1 day')),
                )
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