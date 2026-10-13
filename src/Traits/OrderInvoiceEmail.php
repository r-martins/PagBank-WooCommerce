<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Partner\Branding;

trait OrderInvoiceEmail
{
    public function sendOrderInvoiceEmail($order) {
        try {
            $emailHasBeenSent = wc_string_to_bool(OrderMeta::get($order, 'email_sent'));

            if ($emailHasBeenSent) {
                return;
            }

            $customerInvoiceEmail = WC()->mailer()->emails['WC_Email_Customer_Invoice'];
            $customerInvoiceEmail->trigger($order->get_id());
            OrderMeta::set($order, 'email_sent', 'yes', true);
            $order->add_order_note(Branding::notePrefix($order) . ': Email do pedido enviado com sucesso!');
        } catch (\Exception $e) {
            $order->add_order_note(Branding::notePrefix($order) . ': Erro ao enviar email do pedido: ' . $e->getMessage());
        }
    }

    public function sendNewOrder($order)
    {
        try {
            $emailHasBeenSent = wc_string_to_bool(OrderMeta::get($order, 'email_new_order_sent'));

            if ($emailHasBeenSent) {
                return;
            }

            $customerInvoiceEmail = WC()->mailer()->emails['WC_Email_New_Order'];
            $customerInvoiceEmail->trigger($order->get_id());
            OrderMeta::set($order, 'email_new_order_sent', 'yes', true);
        } catch (\Exception $e) {
            $order->add_order_note(Branding::notePrefix($order) . ': Erro ao enviar email de novo pedido: ' . $e->getMessage());
        }
    }
}
