<?php
namespace RM_PagBank\Cron;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;

/**
 * Class responsible to cancel order with expired PIX payment
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2025 Magenteiro
 * @package   RM_PagBank\Cron
 */
class CancelExpiredPix
{
    /**
     * Execute the cron job rm_pagbank_cron_cancel_expired_pix.
     * @link https://ajuda.pbintegracoes.com/hc/pt-br/articles/24770387325837-Cancelamento-autom%C3%A1tico-de-pedidos-PIX-expirados
     * @return void
     */
    public static function execute()
    {
        //list all orders with pix payment method and status pending created longer than configured expiry time
        $expiredOrders = Functions::getExpiredPixOrders();
        foreach ($expiredOrders as $order) {
            //let's double-check if the order was paid, just in case
            if (self::wasPixOrderPaid($order)) {
                continue;
            }
            
            //cancel order
            $order->update_status(
                'cancelled'
            );

            //send cancelled order email to customer
            $order->add_order_note(
                __('PagBank: O código PIX expirou e o pagamento não foi identificado. O pedido foi cancelado.', 'pagbank-connect'),
                true
            );
        }
    }
    
    /**
     * Checks if the order was paid (live check on PagBank)
     * @param $order
     *
     * @return bool
     */
    private static function wasPixOrderPaid($order):bool
    {
        $pagBankOrderId = $order->get_meta('pagbank_order_id');
        if (!$pagBankOrderId) {
            return false;
        }

        try {
            $orderData = Api::getOrderData($pagBankOrderId);
        } catch (Exception $e) {
            return false;
        }
        if ($orderData) {
            $status = '';
            if (isset($orderData['charges'][0]['status'])) {
                $status = $orderData['charges'][0]['status'];
            }
            if ($status == 'PAID') {
                return true;
            }
        }
        
        return false;
    }
}