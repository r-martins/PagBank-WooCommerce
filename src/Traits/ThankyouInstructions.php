<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Connect\Payments\Boleto;
use RM_PagBank\Connect\Payments\Pix;
use RM_PagBank\Connect\Recurring;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Helpers\OrderMeta;

trait ThankyouInstructions
{
    /**
     * Add the instructions to the thankyou page for boleto and pix
     * @param $order_id
     *
     * @return void
     */
    public function addThankyouInstructions($order_id)
    {
        $order = wc_get_order($order_id);
        switch (OrderMeta::get($order, 'payment_method')) {
            case 'boleto':
                $method = new Boleto($order);
                break;
            case 'pix':
                $method = new Pix($order);
                break;
            case 'credit_card':
                $method = new CreditCard($order);
                break;
        }
        if (!empty($method)) {
            $method->getThankyouInstructions($order_id);
        }
        if (OrderMeta::get($order, '_recurring_initial')) {
            $recurring = new Recurring();
            $recurring->getThankyouInstructions($order);
        }
    }
}
