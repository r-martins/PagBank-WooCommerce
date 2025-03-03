<?php
namespace RM_PagBank\Cron;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RM_PagBank\Connect\Exception;
use RM_PagBank\Connect\OrderProcessor;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;

/**
 * Class ForceOrderUpdate
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2025 Magenteiro
 * @package   RM_PagBank\Cron
 */
class ForceOrderUpdate
{
    /**
     * Execute the cron job rm_pagbank_cron_force_order_update.
     * @link  https://ajuda.pbintegracoes.com/hc/pt-br/articles/34589281628813
     * @return void
     * @throws \Automattic\WooCommerce\Internal\DependencyManagement\ContainerException
     */
    public static function execute(): void
    {
        $orders = Functions::getPagBankPendingOrders();

        foreach ($orders as $order) {
            $pagbankOrderId = $order->get_meta('pagbank_order_id');
            if (!$pagbankOrderId) {
                continue;
            }

            $now = strtotime(gmdate('Y-m-d H:i:s'));
            $lastCheck = (int)$order->get_meta('_pagbank_last_check');

            /** @var \WC_DateTime $createdAt */
            $createdAt = $order->get_date_created()->getTimestamp();

            // Check if it's time to update based on payment method
            if (!self::shouldUpdateOrder($order, $lastCheck, $createdAt, $now)) {
                continue;
            }

            $order->update_meta_data('_pagbank_last_check', $now);
            $order->save();

            try {
                $orderData = Api::getOrderData($pagbankOrderId);
                if ($orderData) {
                    $orderProcessor = new OrderProcessor();
                    $orderProcessor->updateTransaction($order, $orderData);
                }
            } catch (Exception $e) {
                Functions::log(
                    'Cron: ' . __('Erro ao atualizar pedido', 'pagbank-connect') . ' ' . $order->get_id() . ' ' . __('no PagBank:', 'pagbank-connect') . ' ' . $e->getMessage(),
                    'error',
                    $e->getTrace()
                );
            }
        }
    }
    
    private static function shouldUpdateOrder($order, int $lastCheck, int $createdAt, int $now): bool
    {
        $paymentMethod = $order->get_meta('pagbank_payment_method');
        $hoursFromCreation = ($now - $createdAt) / 3600;
    
        if ($lastCheck === 0) {
            return true;
        }
        
        switch ($paymentMethod) {
            case 'boleto':
                // Every 6 hours until 3 days after due date
                return ($now - $lastCheck) >= 6 * 3600;
    
            case 'pix':
                // Every hour for first 3 hours, then every 6 hours for a week
                if ($hoursFromCreation <= 3) {
                    return ($now - $lastCheck) >= 3600;
                }
                if ($hoursFromCreation <= 168) { // 7 days in hours
                    return ($now - $lastCheck) >= 6 * 3600;
                }
                return false;
    
            case 'credit_card':
                // Every 6 hours for 3 days
                if ($hoursFromCreation <= 72) { // 3 days in hours
                    return ($now - $lastCheck) >= 6 * 3600;
                }
                return false;
    
            default:
                return false;
        }
    }
    
}