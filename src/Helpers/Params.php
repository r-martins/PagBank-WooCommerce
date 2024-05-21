<?php

namespace RM_PagBank\Helpers;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Object\Address;
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
     *
     * @see https://dev.pagseguro.uol.com.br/reference/phone-object
     *
     * @param $order WC_Order
     *
     * @return array
     */
    public static function extractPhone(WC_Order $order):array
    {
        $full_phone = $order->get_billing_phone();
        $clean_phone = preg_replace('/[^0-9]/', '', $full_phone);
        $ddd = substr($clean_phone, 0, 2);
        $number = substr($clean_phone, 2);

        return [
            'country' => '55',
            'area' => $ddd,
            'number' => $number,
            'type' => (strlen($number) == 9) ? 'MOBILE' : 'HOME'
		];
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
    public static function convertToCents($amount): int
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
     * @param string $default
     *
     * @return mixed|string
     */
    public static function getConfig($key, string $default = '')
    {
        $settings = get_option('woocommerce_rm-pagbank_settings');
        if (isset($settings[$key])){
            return $settings[$key];
        }
        return $default;
    }

    /**
     * Gets the max allowed installments or false if no limit
	 *
     * @return false|int
     */
    public static function getMaxInstallments(){
        $recurringHelper = new Recurring();
        $recurring = $recurringHelper->isCartRecurring();
        if ($recurring){
            return 1; //when recurring, only 1 installment is allowed
        }
        
        //returns false if cc_installments_options_limit_installments == no
        if (self::getConfig('cc_installments_options_limit_installments', 'no') == 'no'){
            return false;
        }
        return (int)self::getConfig('cc_installments_options_max_installments', 18);
    }

	/**
	 * Get the max installments without interest based on order total and config options
	 * Will return '' if the option is set to get from the PagBank Config, 0 if the option is set to buyer,
	 * a fixed number if the option is set to fixed or the calculated number based on the order total
	 * @param $orderTotal
	 *
	 * @return false|float|int|string
	 */
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
            $installments = $installments == 1 ? 0 : $installments; //1 is not acceptable as a value by the api
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
        
        if (Params::getConfig('is_sandbox') == 'yes') {
            $params['credit_card_bin'] = '555566'; //Because test credit card numbers are not accepted by the API
        }

        if ($mi = self::getMaxInstallments()) {
            $params['max_installments'] = $mi;
        }

        $params['max_installments_no_interest'] = self::getMaxInstallmentsNoInterest($orderTotal);

        try {
            $installments = $api->get($url, $params, 30);
        } catch (Exception $e) {
            return [];
        }

        if (isset($installments['error_messages'])){
			$return['error'] = $installments['error_messages'][0]['description'] ?? 'Erro ao calcular as parcelas';
            Functions::log('Erro ao calcular as parcelas' . print_r([$installments['error_messages'], $params], true), 'debug');
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
				if (isset($installment['amount']['fees']['buyer']['interest']['total'])) {
					$interest_amount = number_format(
						$installment['amount']['fees']['buyer']['interest']['total'] ?? 0 / 100,
						2,
						'.',
						''
					);
                }

                $return[] = [
					'installments' => $installment['installments'],
					'total_amount' => $total_amount,
					'total_amount_raw' => $installment['amount']['value'],
					'installment_amount' => $installment_value,
					'interest_free' => $installment['interest_free'],
					'interest_amount' => $interest_amount,
//					'interest_amount_raw' => $installment['amount']['fees']['buyer']['interest']['total'] ?? 0
					'fees' => $installment['amount']['fees'] ?? []
                ];
            }
        }
        return $return;
    }

	/**
	 * Extracts the installment information from the array returned by the API
	 * @param $installments
	 * @param $installmentNumber
	 *
	 * @return false|mixed
	 */
	public static function extractInstallment($installments, $installmentNumber)
	{
		foreach ($installments as $installment) {
			if ($installment['installments'] == $installmentNumber) {
				return $installment;
			}
		}
		return false;
	}

    /**
     * Return if discount config value is a PERCENT or FIXED discount, or false if no discount is to be applied
     * @param $configValue
     *
     * @return false|string FIXED or PERCENT
     */
    public static function getDiscountType($configValue)
    {
        if (empty($configValue)){
            return false;
        }

        if (is_numeric($configValue)){
            return 'FIXED';
        }

        if (strpos($configValue, '%') !== false){
            return 'PERCENT';
        }

        return false;
    }

    /**
     * Return the total discount amount value for the order based on the discount config value (% or fixed)
     *
     * @param string $configValue
     * @param WC_Order $order
     * @param bool $excludesShipping
     *
     * @return float
     */
    public static function getDiscountValue($configValue, $order, $excludesShipping): float
    {
        $orderTotal = $order->get_total();
        if ($excludesShipping) {
            $orderTotal -= $order->get_shipping_total();
        }
        
        $discountType = self::getDiscountType($configValue);
        if (!$discountType) {
            return 0;
        }

        if ('FIXED' == $discountType) {
            return floatval($configValue);
        }

        if ('PERCENT' == $discountType) {
            return floatval($orderTotal) * (floatval($configValue) / 100);
        }

        return 0;
    }

	/**
	 * Gets the message about the discount that will be displayed in the checkout page
	 * @param $method
	 *
	 * @return string
	 */
	public static function getDiscountText($method): string
	{
        $discountConfig = self::getConfig($method . '_discount', 0);
        $discountType = self::getDiscountType($discountConfig);
        if ( ! $discountType || is_wc_endpoint_url('order-pay')) {
            return '';
        }

        if ('FIXED' == $discountType){
			return sprintf(
				__('Um desconto de %s será aplicado.', 'pagbank-connect'),
				'<strong>'.wc_price($discountConfig).'</strong>'
			);
        }

        if ('PERCENT' == $discountType){
			return sprintf(
				__('Um desconto de %s será aplicado', 'pagbank-connect'),
				'<strong>'.$discountConfig.'</strong>'
			);
        }

        return '';
    }

    /**
     * Checks if all required address attributes are not empty
     * @param Address $address
     *
     * @return bool
     */
    public function isAddressValid(Address $address): bool
    {
        $required = [
            'street',
            'number',
            'locality',
            'city',
            'regionCode',
            'country',
            'postalCode',
        ];
        foreach ($required as $field){
            if (empty($address->{'get' . ucfirst($field)}())){
                return false;
            }
        }
        
        return true;
    }


    public static function isPaymentMethodEnabled(string $method): bool
    {
        $recurringHelper = new Recurring();
        $recurring = $recurringHelper->isCartRecurring();
        
        if ($recurring){
            return in_array($method, Params::getConfig('recurring_payments'));
        }

        return Params::getConfig($method . '_enabled') == 'yes';
    }
}
