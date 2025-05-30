<?php
namespace RM_PagBank\Connect\Recurring;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Connect\Payments\Boleto;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Connect\Payments\Pix;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;
use WC_Data_Exception;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;
use WP_Error;

/**
 * Related to recurring orders and its creations
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Recurring
 */
class RecurringOrder
{
    private $subscription;

    /**
     * @param $subscription
     */
    public function __construct($subscription)
    {
        $this->subscription = $subscription;
    }

    public function createRecurringOrderFromSub()
    {
        $subscription = $this->subscription;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        
        if (!$initialOrder) {
            Functions::log('Pedido inicial não encontrado pra assinatura ' . esc_attr($subscription->id) . '. Recorrência não foi processada.', 'error', [
                'subscription' => $subscription->id,
            ]);
            return;
        }

        $recHelper = new Recurring();

        // calculate total before new order creation
        $total = $subscription->recurring_amount;
        $hasDiscount = $recHelper->hasSubscriptionDiscountRemaining($subscription);
        if ($hasDiscount) {
            $total = $total - $subscription->recurring_discount_amount;
        }

        $order = wc_create_order([
            'customer_id' => $initialOrder->get_customer_id('edit'),
            'parent'    => $initialOrder->get_id(),
            'total' => $initialOrder->get_total('edit'),
        ]);
        
        /** @var WC_Order_Item_Product $item */
        foreach ($initialOrder->get_items() as $item){
            /** @var WC_Product|null|false $itemObj */
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $itemObj = wc_get_product($product_id);
            $itemObj->update_meta_data('_frequency', $initialOrder->get_meta('_recurring_frequency'));
            $itemObj->update_meta_data('_cycle', $initialOrder->get_meta('_recurring_cycle'));

            $order->add_product($itemObj, $item->get_quantity('edit'));
        }

        $order->set_address($initialOrder->get_address('billing'), 'billing');
        $order->set_address($initialOrder->get_address('shipping'), 'shipping');
        $order->set_payment_method_title($initialOrder->get_payment_method_title('edit'));
        $order->set_payment_method($initialOrder->get_payment_method('edit'));

        $order->set_total($total);

        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title($initialOrder->get_shipping_method());
        
        $order->add_item($shipping);
        $order->set_shipping_total($initialOrder->get_shipping_total('edit'));

        $order->add_order_note(
            sprintf(
                __('Este é um pedido recorrente. Perfil recorrente #%s. Pedido inicial: #%s. Frequência: %s. '
                    .'Ciclo de cobrança: %s'),
                $subscription->id,
                $subscription->initial_order_id,
                $recHelper->translateFrequency($initialOrder->get_meta('_recurring_frequency')),
                $initialOrder->get_meta('_recurring_cycle'),
            )
        );
        $order->add_meta_data('_pagbank_is_recurring', true);
        $this->addMetaFromOriginalOrder($order, $initialOrder);

        $order->add_meta_data(
            '_rm_pagbank_checkout_blocks',
            $initialOrder->get_meta('_rm_pagbank_checkout_blocks'),
            true
        );

        if($initialOrder->get_meta('_rm_pagbank_customer_document')) {
            $order->add_meta_data(
                '_rm_pagbank_customer_document',
                $initialOrder->get_meta('_rm_pagbank_customer_document'),
                true
            );
        }

        $order->save();

        try {
            $this->processSubscriptionPayment($order, $subscription);
        } catch (\Exception $e) {
            do_action('pagbank_recurring_failed_process_subscription_payment', $subscription, $order, $e);
        }

        $canContinue = $recHelper->hasSubscriptionChargeRemaining($subscription);
        if (!$canContinue) {
            $recurring = new \RM_PagBank\Connect\Recurring();
            $recurring->completeSubscription($subscription);
        }
    }
    

    /**
     * @throws WC_Data_Exception|Connect\Exception
     */
    public function processSubscriptionPayment(WC_Order $order, $subscription)
    {
        $paymentInfo = json_decode($subscription->payment_info);
        $recurring = new \RM_PagBank\Connect\Recurring();
        if (json_last_error() !== JSON_ERROR_NONE) {
            $recurring->cancelSubscription(
                $subscription->id,
                __('Erro ao decodificar informações de pagamento para processar assinatura.', 'pagbank-connect'),
                'FAILURE'
            );
            throw new Exception('Erro ao decodificar informações de pagamento para subscription ' . esc_attr($subscription->id));
        }
        
        if(!isset($paymentInfo->method)){
            $recurring->cancelSubscription(
                $subscription->id,
                __('Método de pagamento não encontrado.', 'pagbank-connect'),
                'FAILURE'
            );
            throw new Exception('Método de pagamento não encontrado para subscription ' . esc_attr($subscription->id));
        }
        
        $payment_method = $paymentInfo->method;

        switch ($payment_method) {
            case 'boleto':
                $method = new Boleto($order);
                $params = $method->prepare();
                break;
            case 'pix':
                $method = new Pix($order);
                $params = $method->prepare();
                break;
            case 'credit_card':
                $order->add_meta_data(
                    'pagbank_card_installments',
                    1,
                    true
                );
                $order->add_meta_data(
                    'pagbank_card_last4',
                    substr($paymentInfo->card->number, -4),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_first_digits',
                    substr($paymentInfo->card->number, 0, 6),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_holder_name',
                    $paymentInfo->card->holder_name,
                    true
                );
                $method = new CreditCard($order);
                $params = $method->prepare();
                break;
            default:
                Functions::log('Invalid payment method: ' . $payment_method, 'error',[
                    'subscription' => $subscription->id,
                    'paymentInfo' => $paymentInfo,
                ]);
                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
        }

        $order->add_meta_data('pagbank_payment_method', $method->code, true);
        
        try {
            $api = new Api();
            $resp = $api->post('ws/orders', $params);

            if (isset($resp['error_messages'])) {
                throw new \RM_PagBank\Connect\Exception($resp['error_messages'], 40000);
            }

        } catch (Exception $e) {
            $recurring->cancelSubscription($subscription, $e->getMessage(), 'FAILURE');
            throw $e;
        }
        $method->process_response($order, $resp);
        Gateway::updateTransaction($order, $resp);

        $charge = $resp['charges'][0] ?? false;

        // region Immediately decline if payment method is credit card and charge was declined
        if ($payment_method == 'credit_card' && $charge !== false) {
            if ($charge['status'] == 'DECLINED' && $subscription->status != 'SUSPENDED'){
                $canRetry = wc_string_to_bool(Params::getRecurringConfig('recurring_retry_charge', 'yes'));
                if (!$canRetry) {
                    $recurring->cancelSubscription(
                        $subscription->id,
                        __('Pagamento recusado durante a renovação da assinatura.', 'pagbank-connect'),
                        'FAILURE'
                    );
                }

                $retryAttemptsRemaining = (int) Params::getRecurringConfig('recurring_retry_attempts', '3') - 1;
                $recurring->suspendSubscription(
                    $subscription,
                    __('Pagamento recusado durante a renovação da assinatura.', 'pagbank-connect'),
                    $retryAttemptsRemaining
                );
            }

            if ($charge['status'] == 'DECLINED' && $subscription->status == 'SUSPENDED'){
                $retryAttemptsRemaining = $subscription->retry_attempts_remaining;
                if (!$retryAttemptsRemaining) {
                    $recurring->cancelSubscription(
                        $subscription,
                        __('Pagamento recusado durante a renovação da assinatura. Número de tentativas de cobrança esgotado.', 'pagbank-connect'),
                        'FAILURE'
                    );
                    return 0;
                }

                $recurring->updateSuspendedSubscription($subscription);
            }
        }
        // endregion
        return 0;
    }
    
//    public function addMetaFromOriginalOrder(&$order, $initialOrder){
//        $prefixes = ['_billing', '_shipping', 'is_vat_exempt'];
//        /** @var WC_Meta_Data $meta */
//        foreach ($prefixes as $prefix) {
//            foreach ($initialOrder->get_meta_data() as $meta) { 
//                /** @var array $data */
//                $data = $meta->get_data();
//                if ( isset($data['key']) && strpos($data['key'], $prefix) === 0 ) {
//                    $order->add_meta_data($data['key'], $data['value'], true);
//                }
//            }
//        }
//    }

    public function addMetaFromOriginalOrder(&$order, $initialOrder)
    {
        $prefixes = ['_billing', '_shipping', 'is_vat_exempt'];
        foreach ($prefixes as $prefix) {
            // Filter meta data by prefix
            $filtered_meta = array_filter($initialOrder->get_meta_data(), function ($meta) use ($prefix) {
                /* @var WC_Meta_Data $meta */
                $data = $meta->get_data();

                return isset($data['key']) && strpos($data['key'], $prefix) === 0;
            });
            // Add meta data to order
            array_walk($filtered_meta, function ($meta) use (&$order) {
                /* @var WC_Meta_Data $meta */
                $data = $meta->get_data();
                $order->add_meta_data($data['key'], $data['value'], true);
            });
        }
    }
}