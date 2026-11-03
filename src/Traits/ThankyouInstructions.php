<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Connect\Payments\Boleto;
use RM_PagBank\Connect\Payments\Pix;
use RM_PagBank\Connect\Payments\Redirect;
use RM_PagBank\Connect\Payments\CheckoutPb;
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
        if (!$order) {
            return;
        }

        $paymentMethod = (string) OrderMeta::get($order, 'payment_method');
        $hostedOrigin = (string) OrderMeta::get($order, '_hosted_checkout_origin');
        // Hosted checkout already paid: never show pay CTA / PIX-boleto pending UI.
        $hostedPaid = in_array($hostedOrigin, ['checkout_pb', 'redirect'], true)
            || in_array($paymentMethod, ['checkout_pb', 'redirect'], true);
        if ($hostedPaid && ($order->is_paid() || !in_array($order->get_status(), ['pending', 'on-hold'], true))) {
            if (OrderMeta::get($order, '_recurring_initial')) {
                $recurring = new Recurring();
                $recurring->getThankyouInstructions($order);
            }
            return;
        }

        // Remapped after charge, but still awaiting payment on hosted flow — keep Checkout PB CTA.
        if (in_array($hostedOrigin, ['checkout_pb', 'redirect'], true)
            && in_array($paymentMethod, ['pix', 'boleto', 'credit_card'], true)
            && in_array($order->get_status(), ['pending', 'on-hold'], true)
            && !empty(OrderMeta::get($order, 'redirect_url'))
        ) {
            $paymentMethod = $hostedOrigin;
        }

        switch ($paymentMethod) {
            case 'boleto':
                $method = new Boleto($order);
                break;
            case 'pix':
                $method = new Pix($order);
                break;
            case 'credit_card':
                $method = new CreditCard($order);
                break;
            case 'redirect':
                $method = new Redirect($order);
                break;
            case 'checkout_pb':
                $method = new CheckoutPb($order);
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
