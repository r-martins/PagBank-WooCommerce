<?php


namespace RM_PagBank\Helpers;

use Exception;
use WC_Order;

/**
 * Helper Params - used to extract information from order to build api requests
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Helpers
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
    public static function extractPhone($order):array
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
    public static function removeNonNumeric($string)
    {
        return preg_replace('/[^0-9]/', '', $string);
    }

    /**
     * Converts the amount to cents
     * @param $amount
     *
     * @return int
     */
    public static function convertToCents($amount)
    {
        if ( ! is_numeric($amount) ) 
            return 0;
        
        $return = number_format($amount, 2, '', '');
        
        //remove leading 0
        $return = ltrim($return, '0');
        return (int)$return;
    }

    /**
     * @param $key
     * @param $default
     *
     * @return mixed|string
     */
    public static function getConfig($key, $default = '')
    {
        $settings = get_option('woocommerce_rm_pagseguro_connect_settings');
        if (isset($settings[$key])){
            return $settings[$key];
        }
        return $default;
    }

    /**
     * Gets the max allowed installments or false if no limit
     * @param $orderTotal
     *
     * @return false|int
     */
    public static function getMaxInstallments($orderTotal){
        //returns false if cc_installments_options_limit_installments == no
        if (self::getConfig('cc_installments_options_limit_installments', 'no') == 'no'){
            return false;
        }
        return (int)self::getConfig('cc_installments_options_max_installments', 18);
    }

    public static function getMaxInstallmentsNoInterest($orderTotal)
    {
        $installment_option = self::getConfig('cc_installment_options', 'external');
        if ('external' == $installment_option){
            return '';
        }
        
        if ('buyer' == $installment_option) {
            return 0;
        }
        
        if ('fixed' == $installment_option) {
            return (int)self::getConfig('cc_installment_options_fixed', 3);
        }
        
//        if ('min_total' == $installment_option) {
            $min_total = (int)self::getConfig('cc_installments_options_min_total', 50);
            $min_total = max(5, $min_total); //avoiding blanks
            $installments = floor($orderTotal / $min_total);
            return $installments > 18 ? 18 : $installments;
//        }
    }
    
    /**
     * Gets the credit card amount with interest information based on order total and cc used
     * @param $orderTotal
     * @param $bin
     *
     * @return array
     */
    public static function getInstallments($orderTotal, $bin): array
    {
        $return = [];
        $api = new Api();
        $url = 'ws/charges/fees/calculate';
        $params['payment_methods'] = 'CREDIT_CARD';
        $params['value']  = self::convertToCents($orderTotal);
        $params['credit_card_bin'] = $bin;

        if ($mi = self::getMaxInstallments($orderTotal))
            $params['max_installments'] = $mi;
        
        $params['max_installments_no_interest'] = self::getMaxInstallmentsNoInterest($orderTotal);

        try {
            $installments = $api->get($url, $params);
        } catch (Exception $e) {
            return [];
        }
        
        if (isset($installments['error_messages'])){
            Functions::log('Erro ao calcular as parcelas' . print_r([$installments['error_messages'], $params], true));
        }
        
        if (isset($installments['payment_methods']['credit_card'])){
            $installments = reset($installments['payment_methods']['credit_card']);
            if ( ! isset($installments['installment_plans'])) {
                return [];
            }
            
            
            foreach ($installments['installment_plans'] as $installment){
                //convert values from cents to float with 2 decimals
                $total_amount = number_format($installment['amount']['value'] / 100, 2, '.', '');
                $installment_value = number_format($installment['installment_value'] / 100, 2, '.', '');
                $interest_amount = 0;
                if (isset($installment['amount']['fees']['buyer']['interest']['total'])){
                    $interest_amount = number_format($installment['amount']['fees']['buyer']['interest']['total'] / 100, 2, '.', '');
                }
                
                $return[] = [
                    'installments' => $installment['installments'],
                    'total_amount' => $total_amount,
                    'installment_amount' => $installment_value,
                    'interest_free' => $installment['interest_free'],
                    'interest_amount' => $interest_amount
                ];
            }
        }
        return $return;
    }
}