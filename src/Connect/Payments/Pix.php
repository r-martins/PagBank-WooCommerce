<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\QrCode;
use WC_Order;

class Pix extends Common
{
    public string $code = 'pix';
    
    

    /**
     * Prepares PIX params to be sent to PagSeguro
     * @return array
     */
    public function prepare() :array
    {
        $return = $this->getDefaultParameters();
        $qr_code = new QrCode();
        
        $amount = new Amount();
        $orderTotal = $this->order->get_total();
        
        if ($discountConfig = Params::getConfig('pix_discount', 0)){
            $discount = Params::getDiscountValue($discountConfig, $orderTotal);
            $this->order->set_discount_total($this->order->get_discount_total() + $discount);
            $this->order->set_total($this->order->get_total() - $discount);
            $orderTotal = $orderTotal - $discount;
        }
        
        $amount->setValue(Params::convertToCents($orderTotal));
        $qr_code->setAmount($amount);
        //calculate expiry date based on current time + expiry days using ISO 8601 format
        $qr_code->setExpirationDate(date('c', strtotime('+' . Params::getConfig('pix_expiry_minutes', 1440) . 'minute')));
        
        $return['qr_codes'] = [$qr_code];
        return $return;
    }
    
    public function getThankyouInstructions($order_id){
        $qr_code = get_post_meta($order_id, 'pagbank_pix_qrcode', true);
        $qr_code_text = get_post_meta($order_id, 'pagbank_pix_qrcode_text', true);
        $qr_code_exp = get_post_meta($order_id, 'pagbank_pix_qrcode_expiration', true);
        require_once dirname(__FILE__) . '/../../templates/pix-instructions.php';
    }
}