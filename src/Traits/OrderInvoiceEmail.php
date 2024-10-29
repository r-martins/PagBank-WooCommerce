<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Helpers\Params;

trait OrderInvoiceEmail
{
    public function sendOrderInvoiceEmail($order) {
        try {
            $emailHasBeenSent = wc_string_to_bool($order->get_meta('pagbank_email_sent'));

            if ($emailHasBeenSent) {
                return;
            }

            $customerInvoiceEmail = WC()->mailer()->emails['WC_Email_Customer_Invoice'];
            $customerInvoiceEmail->trigger($order->get_id());
            $order->add_meta_data('pagbank_email_sent', 'yes', true);
            $order->add_order_note('PagBank: Email do pedido enviado com sucesso!');
        } catch (\Exception $e) {
            $order->add_order_note('PagBank: Erro ao enviar email do pedido: ' . $e->getMessage());
        }
    }
}
