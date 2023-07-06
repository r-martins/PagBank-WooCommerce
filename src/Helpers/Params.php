<?php


namespace RM_PagSeguro\Helpers;

use WC_Order;

/**
 * Helper Params - used to extract information from order to build api requests
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagSeguro\Helpers
 */
class Params
{
    /**
     * Extract phone number and return an array with the phone object to be used in the request
     * @see https://dev.pagseguro.uol.com.br/reference/phone-object
     * @param $order WC_Order
     *
     * @return array
     */
    public static function extract_phone($order):array
    {
        $full_phone = $order->get_billing_phone();
        $clean_phone = preg_replace('/[^0-9]/', '', $full_phone);
        $ddd = substr($clean_phone, 0, 2);
        $number = substr($clean_phone, 2);
        
        return array(
            'country' => '55',
            'area' => $ddd,
            'number' => $number,
            'type' => (strlen($number) == 9) ? 'MOBILE' : 'HOME'
        );
    }

    /**
     * @param $string
     *
     * @return array|string|string[]|null
     */
    public static function remove_non_numeric($string)
    {
        return preg_replace('/[^0-9]/', '', $string);
    }

    /**
     * Converts the amount to cents
     * @param $amount
     *
     * @return int
     */
    public static function convert_to_cents($amount)
    {
        if ( ! is_numeric($amount) ) 
            return 0;
        
        $return = number_format($amount, 2, '', '');
        
        //remove leading 0
        $return = ltrim($return, '0');
        return (int)$return;
    }
}