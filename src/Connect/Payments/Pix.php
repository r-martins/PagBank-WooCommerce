<?php

namespace RM_PagSeguro\Connect\Payments;

use RM_PagSeguro\Helpers\Params;
use RM_PagSeguro\Object\Amount;
use RM_PagSeguro\Object\QrCode;
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
        $amount->setValue(Params::convert_to_cents($this->order->get_total()));
        $qr_code->setAmount($amount);
        //calculate expiry date based on current time + expiry days using ISO 8601 format
        $qr_code->setExpirationDate(date('c', strtotime('+' . Params::getConfig('pix_expiry_days', 1440) . 'minute')));
        
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