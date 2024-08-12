<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\QrCode;
use WC_Data_Exception;
use WC_Order;

/**
 * Class Pix
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class Pix extends Common
{
    public string $code = 'pix';

	/**
	 * Prepares PIX params to be sent to PagSeguro
	 *
	 * @return array
	 * @throws WC_Data_Exception
	 */
    public function prepare() :array
    {
        $return = $this->getDefaultParameters();
        $qr_code = new QrCode();

        $amount = new Amount();
        $orderTotal = $this->order->get_total();
        $discountExcludesShipping = Params::getPixConfig('pix_discount_excludes_shipping', false) == 'yes';

        if (($discountConfig = Params::getPixConfig('pix_discount', 0)) && ! is_wc_endpoint_url('order-pay')) {
            $discount = Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping);
            $this->order->set_discount_total(floatval($this->order->get_discount_total()) + $discount);
            $this->order->set_total($this->order->get_total() - $discount);
            $orderTotal = $orderTotal - $discount;
        }

        $amount->setValue(Params::convertToCents($orderTotal));
        $qr_code->setAmount($amount);
        //calculate expiry date based on current time + expiry days using ISO 8601 format
        $qr_code->setExpirationDate(gmdate('c', strtotime('+' . Params::getPixConfig('pix_expiry_minutes', 1440) . 'minute')));

        $return['qr_codes'] = [$qr_code];
        return $return;
    }

	/**
	 * Set some variables and requires the template with pix instructions for the success page
	 *
	 * @param $order_id
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 **/
	public function getThankyouInstructions($order_id){
        $order = new WC_Order($order_id);
        $qr_code = $order->get_meta('pagbank_pix_qrcode');
        $qr_code_text = $order->get_meta('pagbank_pix_qrcode_text');
        $qr_code_exp = $order->get_meta('pagbank_pix_qrcode_expiration');
        require_once dirname(__FILE__) . '/../../templates/pix-instructions.php';
    }
}
