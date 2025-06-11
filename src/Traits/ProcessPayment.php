<?php

namespace RM_PagBank\Traits;

use Automattic\WooCommerce\Enums\OrderStatus;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Exception;
use RM_PagBank\Connect\Recurring;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use WC_Data_Exception;
use WC_Order;

trait ProcessPayment
{
    /**
     * Updates a transaction from the order's json information
     *
     * @param $order      WC_Order
     * @param $order_data array
     *
     * @return void
     * @throws Exception|\Exception
     */
    public static function updateTransaction(WC_Order $order, array $order_data): void
    {
        $cronMsg = wp_doing_cron() ? __(' (Atualizado via Cron)', 'pagbank-connect') : '';
        $md5 = md5(serialize($order_data));
        if ($order->get_meta('_pagbank_last_update_md5') == $md5) {
            Functions::log(sprintf(__('Notificação de atualização ignorada para o pedido %s pois o conteúdo é o mesmo da última atualização.' . $cronMsg, 'pagbank-connect'), $order->get_id()), 'debug');
            return; // Do not update if the data is the same
        }
        $order->update_meta_data('_pagbank_last_update_md5', $md5);
        
        $charge = $order_data['charges'][0] ?? [];
        $status = $charge['status'] ?? '';
        $payment_response = $charge['payment_response'] ?? null;
        $charge_id = $charge['id'] ?? null;

        $order->add_meta_data('pagbank_charge_id', $charge_id, true);
        $order->add_meta_data('pagbank_payment_response', $payment_response, true);
        $order->add_meta_data('pagbank_order_id', $order_data['id'] ?? null, true);
        if (isset($charge['payment_method']['type']) && $charge['payment_method']['type'] == 'CREDIT_CARD') {
            $order->update_meta_data('pagbank_tid', $charge['payment_response']['tid'] ?? null);
            $order->update_meta_data('_pagbank_card_brand', $charge['payment_method']['card']['brand'] ?? null);
            $order->update_meta_data('_pagbank_card_first_digits', $charge['payment_method']['card']['first_digits'] ?? null);
            $order->update_meta_data('_pagbank_card_last_digits', $charge['payment_method']['card']['last_digits'] ?? null);
            $order->update_meta_data('_pagbank_card_holder', $charge['payment_method']['card']['holder']['name'] ?? null);
            $order->update_meta_data('_pagbank_card_exp_month', $charge['payment_method']['card']['exp_month'] ?? null);
            $order->update_meta_data('_pagbank_card_exp_year', $charge['payment_method']['card']['exp_year'] ?? null);
            $order->update_meta_data('_pagbank_card_response_reference', $charge['payment_response']['reference'] ?? null);
            $order->update_meta_data('_pagbank_card_3ds_status', $charge['payment_method']['authentication_method']['status'] ?? null);
        }

        //redirect payments will change payment from 'redirect' to the payment actually used
        if (isset($charge[0]['payment_method']['type'])) {
            $order->update_meta_data('pagbank_payment_method', $charge[0]['payment_method']['type']);
        }

        if (isset($charge['payment_response']['reference'])) {
            $order->add_meta_data('pagbank_nsu', $charge['payment_response']['reference']);
        }
        
        $redirectStatus = $order_data['status'] ?? null; //redirect checkout status (if expirable)
        if (!$status && $redirectStatus == 'EXPIRED') {
            $status = 'EXPIRED';
            $order->update_status(
                OrderStatus::CANCELLED,
                'PagBank: Pagamento não realizado durante o período aceito.' . $cronMsg
            );
        }

        if (isset($charge['payment_response']['raw_data']['authorization_code'])) {
            $order->add_meta_data('pagbank_authorization_code', $charge['payment_response']['raw_data']['authorization_code']);
        }

        $order->add_meta_data('pagbank_status', $status, true);
        $order->save_meta_data();

        do_action('pagbank_status_changed_to_' . strtolower($status), $order, $order_data);

        // Add some additional information about the payment
        if (isset($charge['payment_response'])) {
            $order->add_order_note(
                'PagBank: Payment Response: '.sprintf(
                    '%d: %s %s %s',
                    $charge['payment_response']['code'] ?? 'N/A',
                    $charge['payment_response']['message'] ?? 'N/A',
                    isset($charge['payment_response']['reference'])
                        ? ' - REF/NSU: '.$charge['payment_response']['reference']
                        : '',
                    ($status) ? "(Status: $status)" : ''
                )
            );
        }
        
        // @link https://developer.pagbank.com.br/reference/objeto-charge
        switch ($status) {
            case 'AUTHORIZED': // Pre-Authorized but not captured yet
                $order->add_order_note(
                    'PagBank: Pagamento pré-autorizado (não capturado). Charge ID: '.$charge_id . $cronMsg,
                );
                $order->update_status(
                    'on-hold',
                    'PagBank: Pagamento pré-autorizado (não capturado). Charge ID: '.$charge_id . $cronMsg
                );
                break;
            case 'PAID': // Paid and captured
                //stocks are reduced at this point
                $order->payment_complete($charge_id);
                $order->add_order_note('PagBank: Pagamento aprovado e capturado. Charge ID: ' . $charge_id . $cronMsg);
                break;
            case 'IN_ANALYSIS': // Paid with Credit Card, and PagBank is analyzing the risk of the transaction
                $order->update_status('on-hold', 'PagBank: Pagamento em análise.' . $cronMsg);
                break;
            case 'DECLINED': // Declined by PagBank or by the card issuer
                $order->update_status('failed', 'PagBank: Pagamento recusado.' . $cronMsg);
                $order->add_order_note(
                    'PagBank: Pagamento recusado. <br/>Charge ID: '.$charge_id . $cronMsg,
                );
                break;
            case 'CANCELED': //this will not be hit if payment method is checkout pagbank (no $charge) and name is CANCELLED (double L)
                $order->update_status('cancelled', 'PagBank: Pagamento cancelado.');
                $order->add_order_note(
                    'PagBank: Pagamento cancelado. <br/>Charge ID: '.$charge_id . $cronMsg,
                );
                break;
            default:
                $order->delete_meta_data('pagbank_status');
        }

        if ($order->get_meta('_pagbank_recurring_initial')) {
            $recurring = new Recurring();
            try {
                $recurring->processInitialResponse($order);
            } catch (Exception $e) {
                Functions::log(
                    'Erro ao processar resposta inicial da assinatura: '.$e->getMessage() . $cronMsg,
                    'error',
                    $e->getTrace()
                );
            }
        }

        //region Update subscription status accordingly
        if ($order->get_meta('_pagbank_is_recurring')) {
            $recurring = new Recurring();
            $recurringHelper = new \RM_PagBank\Helpers\Recurring();
            $shouldBeStatus = $recurringHelper->getStatusFromOrder($order);
            $subscription = $recurring->getSubscriptionFromOrder($order->get_parent_id('edit'));
            $parentOrder = wc_get_order($order->get_parent_id('edit'));
            $frequency = $parentOrder->get_meta('_recurring_frequency');
            $cycle = (int)$parentOrder->get_meta('_recurring_cycle');
            if ( ! $subscription instanceof \stdClass) {
                return;
            }

            if ($status == 'DECLINED' && $charge["recurring"]["type"] == 'SUBSEQUENT') {
                $canRetry = wc_string_to_bool(Params::getRecurringConfig('recurring_retry_charge', 'yes'));
                $shouldBeStatus = $canRetry ? 'SUSPENDED' : $shouldBeStatus;
            }

            if ($subscription->status != $shouldBeStatus) {
                $recurring->updateSubscription($subscription, [
                    'status' => $shouldBeStatus,
                ]);
            }

            if ($shouldBeStatus == 'ACTIVE') {
                $recurring->updateSubscription($subscription, [
                    'next_bill_at' => $recurringHelper->calculateNextBillingDate(
                        $frequency,
                        $cycle
                    )->format('Y-m-d H:i:s'),
                    'retry_attempts_remaining' => null
                ]);
            }
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    public static function notification()
    {
        $body = file_get_contents('php://input');
        $hash = htmlspecialchars($_GET['hash'], ENT_QUOTES, 'UTF-8');

        Functions::log('Notification received: ' . $body, 'debug', ['hash' => $hash]);

        // Decode body
        $order_data = json_decode($body, true);
        if ($order_data === null)
            wp_die('Falha ao decodificar o Json', 400);

        // Check presence of id and reference
        $id = $order_data['id'] ?? null;
        $reference = $order_data['reference_id'] ?? null;
        if (!$id || !$reference)
            wp_die('ID ou Reference não informados', 400);

        // Sanitize $reference and $id
        $reference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');

        // Validate hash
        $order = wc_get_order($reference);
        if (!$order)
            wp_die('Pedido não encontrado', 404);

        $order_pagbank_id = $order->get_meta('pagbank_order_id');
        if ($order_pagbank_id != $id) {
            if ($order->get_payment_method() == 'rm-pagbank-redirect') {
                // get x-product-id header
                $checkoutId = $_SERVER['HTTP_X_PRODUCT_ID'] ?? null;
                if ($order_pagbank_id != $checkoutId)
                    wp_die('ID do pedido não corresponde ao código de checkout. Verifique header x-product-id.', 400);
            }else{
                wp_die('ID do pedido não corresponde', 400);
            }
        }

        if ($hash != Api::getOrderHash($order))
            wp_die('Hash inválido', 403);

//        if (!isset($order_data['charges']))
//            wp_die('Charges não informado. Notificação ignorada.', 200);

        try{
            self::updateTransaction($order, $order_data);
        }catch (Exception $e){
            Functions::log('Error updating transaction: ' . $e->getMessage(), 'error', ['order_id' => $order->get_id()]);
            wp_die('Erro ao atualizar transação', 500);
        }

        wp_die('OK', 200);
    }

    /**
     * @throws WC_Data_Exception
     * @throws Exception
     */
    public function makeRequest(WC_Order $order, $params, $method)
    {
        $order->add_meta_data('pagbank_payment_method', $method->code, true);

        //force payment method, to avoid problems with standalone methods
        $order->set_payment_method(Connect::DOMAIN);

        switch ($method->code) {
            case 'credit_card_token':
                $endpoint = 'ws/tokens/cards';
                break;
            case 'redirect':
                $endpoint = 'ws/checkouts';
                break;
            default:
                $endpoint = 'ws/orders';
                break;
        }
        $api = new Api();
        $resp = $api->post($endpoint, $params);
        if (isset($resp['error_messages'])) {
            throw new Exception($resp['error_messages'], 40000);
        }

        return $resp;
    }

    /**
     * Add note if customer changed payment method
     *
     * @param WC_Order $order
     * @param string $payment_method
     * @return void
     */
    public function handleCustomerChangeMethod(WC_Order $order, string $payment_method): void
    {
        if ($order->get_meta('pagbank_payment_method')) {
            $current_method = $payment_method == 'cc' ? 'credit_card' : $payment_method;
            $old_method = $order->get_meta('pagbank_payment_method');
            if (strcasecmp($current_method, $old_method) !== 0) {
                $order->add_order_note(
                    'PagBank: Cliente alterou o método de pagamento de ' . $old_method . ' para ' . $current_method
                );
            }
        }
    }
}