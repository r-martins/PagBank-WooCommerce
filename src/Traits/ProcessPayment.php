<?php

namespace RM_PagBank\Traits;

use Automattic\WooCommerce\Enums\OrderStatus;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Exception;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Connect\Recurring;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Partner\Branding;
use WC_Data_Exception;
use WC_Order;
use RM_PagBank\Helpers\OrderMeta;

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
        $notePrefix = Branding::notePrefix($order);
        $md5 = md5(serialize($order_data));
        if (OrderMeta::get($order, '_last_update_md5') == $md5) {
            Functions::log(sprintf(__('Notificação de atualização ignorada para o pedido %s pois o conteúdo é o mesmo da última atualização.' . $cronMsg, 'pagbank-connect'), $order->get_id()), 'debug');
            return; // Do not update if the data is the same
        }
        OrderMeta::set($order, '_last_update_md5', $md5);
        
        $charge = $order_data['charges'][0] ?? [];
        $status = $charge['status'] ?? '';
        $payment_response = $charge['payment_response'] ?? null;
        $charge_id = $charge['id']
            ?? ($order_data['partner_charge_id'] ?? null)
            ?? OrderMeta::get($order, 'charge_id')
            ?: null;
        if ($charge_id !== null && $charge_id !== '') {
            $charge_id = (string) $charge_id;
        } else {
            $charge_id = null;
        }
        $bill_id = $order_data['partner_bill_id']
            ?? OrderMeta::get($order, 'partner_bill_id')
            ?: null;
        if ($bill_id !== null && $bill_id !== '') {
            $bill_id = (string) $bill_id;
        } else {
            $bill_id = null;
        }
        $redirectStatus = $order_data['status'] ?? null; //redirect checkout status (if expirable)

        // Checkout expired: never cancel (or overwrite) an order that was already paid
        if (!$status && $redirectStatus === 'EXPIRED' && $order->is_paid()) {
            Functions::log(
                sprintf(
                    __('Notificação de checkout expirado ignorada para o pedido %s pois o pedido já está pago.' . $cronMsg, 'pagbank-connect'),
                    $order->get_id()
                ),
                'debug'
            );
            $order->add_order_note(
                $notePrefix . ': Checkout expirado, mas o pedido já estava pago. Cancelamento ignorado.' . $cronMsg
            );
            $order->save_meta_data();
            return;
        }

        if ($charge_id) {
            OrderMeta::set($order, 'charge_id', $charge_id, true);
        }
        if ($bill_id) {
            OrderMeta::set($order, 'partner_bill_id', $bill_id, true);
        }
        OrderMeta::set($order, 'payment_response', $payment_response, true);
        OrderMeta::set($order, 'order_id', $order_data['id'] ?? null, true);
        $chargePaymentType = (string) ($charge['payment_method']['type'] ?? '');
        if (in_array($chargePaymentType, ['CREDIT_CARD', 'CREDIT_CARD_3DS'], true)) {
            CreditCard::applyChargeInstallmentMeta($order, $charge);
            OrderMeta::set($order, 'tid', $charge['payment_response']['tid'] ?? null);
            OrderMeta::set($order, '_card_brand', $charge['payment_method']['card']['brand'] ?? null);
            OrderMeta::set($order, '_card_first_digits', $charge['payment_method']['card']['first_digits'] ?? null);
            OrderMeta::set($order, '_card_last_digits', $charge['payment_method']['card']['last_digits'] ?? null);
            OrderMeta::set($order, '_card_holder', $charge['payment_method']['card']['holder']['name'] ?? null);
            OrderMeta::set($order, '_card_exp_month', $charge['payment_method']['card']['exp_month'] ?? null);
            OrderMeta::set($order, '_card_exp_year', $charge['payment_method']['card']['exp_year'] ?? null);
            OrderMeta::set($order, '_card_response_reference', $charge['payment_response']['reference'] ?? null);
            $auth = is_array($charge['payment_method']['authentication_method'] ?? null)
                ? $charge['payment_method']['authentication_method']
                : [];
            $threeDs = is_array($charge['payment_method']['three_ds'] ?? null)
                ? $charge['payment_method']['three_ds']
                : [];
            $threeDsStatus = (string) ($auth['status'] ?? ($threeDs['status'] ?? ''));
            $threeDsId = (string) ($auth['id'] ?? ($threeDs['authentication_transaction_id'] ?? ''));
            if ($threeDsStatus === '' && $chargePaymentType === 'CREDIT_CARD_3DS') {
                $payloadRaw = OrderMeta::get($order, '_card_3ds_payload');
                $payload = is_string($payloadRaw) ? json_decode($payloadRaw, true) : null;
                if (is_array($payload) && !empty($payload['status'])) {
                    $threeDsStatus = (string) $payload['status'];
                }
                if ($threeDsId === '' && is_array($payload) && !empty($payload['authentication_transaction_id'])) {
                    $threeDsId = (string) $payload['authentication_transaction_id'];
                }
                if ($threeDsStatus === '') {
                    $threeDsStatus = 'AUTHENTICATED';
                }
            }
            if ($threeDsStatus !== '') {
                OrderMeta::set($order, '_card_3ds_status', $threeDsStatus);
            }
            if ($threeDsId !== '') {
                OrderMeta::set($order, '_card_3ds_id', $threeDsId);
            }
            $profileId = (int) (
                ($charge['payment_method']['payment_profile_id'] ?? null)
                ?: ($charge['payment_method']['card']['id'] ?? null)
                ?: ($charge['payment_method']['card']['payment_profile_id'] ?? null)
            );
            if ($profileId > 0) {
                OrderMeta::set($order, '_payment_profile_id', $profileId);
            }
        }

        //redirect payments will change payment from 'redirect' to the payment actually used
        if (isset($charge[0]['payment_method']['type'])) {
            OrderMeta::set($order, 'payment_method', $charge[0]['payment_method']['type']);
        }

        if (isset($charge['payment_response']['reference'])) {
            OrderMeta::set($order, 'nsu', $charge['payment_response']['reference']);
        }
        
        if (!$status && $redirectStatus === 'EXPIRED') {
            $status = 'EXPIRED';
            $order->update_status(
                OrderStatus::CANCELLED,
                $notePrefix . ': Pagamento não realizado durante o período aceito.' . $cronMsg
            );
        }

        $rawData = $charge['payment_response']['raw_data'] ?? null;
        if (is_array($rawData)) {
            if (isset($rawData['authorization_code']) && $rawData['authorization_code'] !== '') {
                OrderMeta::set($order, 'authorization_code', $rawData['authorization_code'], false);
            }
            if (isset($rawData['transaction_id']) && $rawData['transaction_id'] !== '') {
                OrderMeta::set($order, 'yapay_transaction_id', (string) $rawData['transaction_id'], false);
            }
        }

        OrderMeta::set($order, 'status', $status, true);
        $order->save_meta_data();

        do_action('pagbank_status_changed_to_' . strtolower($status), $order, $order_data);

        // Add some additional information about the payment
        if (isset($charge['payment_response'])) {
            $order->add_order_note(
                $notePrefix . ': Payment Response: '.sprintf(
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
        $idsNote = self::formatPaymentIdsNote($charge_id, $bill_id);
        switch ($status) {
            case 'AUTHORIZED': // Pre-Authorized but not captured yet
                $order->add_order_note(
                    $notePrefix . ': Pagamento pré-autorizado (não capturado).' . $idsNote . $cronMsg,
                );
                $order->update_status(
                    'on-hold',
                    $notePrefix . ': Pagamento pré-autorizado (não capturado).' . $idsNote . $cronMsg
                );
                break;
            case 'PAID': // Paid and captured
                //stocks are reduced at this point
                $order->payment_complete($charge_id);
                $order->add_order_note($notePrefix . ': Pagamento aprovado e capturado.' . $idsNote . $cronMsg);
                break;
            case 'IN_ANALYSIS': // Paid with Credit Card, and PagBank is analyzing the risk of the transaction
                $order->update_status('on-hold', $notePrefix . ': Pagamento em análise.' . $cronMsg);
                break;
            case 'DECLINED': // Declined by PagBank or by the card issuer
                $order->update_status('failed', $notePrefix . ': Pagamento recusado.' . $cronMsg);
                $order->add_order_note(
                    $notePrefix . ': Pagamento recusado.' . ($idsNote ? '<br/>' . ltrim($idsNote) : '') . $cronMsg,
                );
                break;
            case 'CANCELED': //this will not be hit if payment method is checkout pagbank (no $charge) and name is CANCELLED (double L)
                $order->update_status('cancelled', $notePrefix . ': Pagamento cancelado.');
                $order->add_order_note(
                    $notePrefix . ': Pagamento cancelado.' . ($idsNote ? '<br/>' . ltrim($idsNote) : '') . $cronMsg,
                );
                break;
            case 'WAITING': // PIX / boleto aguardando pagamento
                if (!$order->has_status(['pending', 'on-hold'])) {
                    $order->update_status('pending', $notePrefix . ': Aguardando pagamento.' . $cronMsg);
                } else {
                    $order->add_order_note($notePrefix . ': Aguardando pagamento.' . $cronMsg);
                }
                break;
            default:
                OrderMeta::delete($order, 'status');
        }

        if (OrderMeta::get($order, '_recurring_initial')) {
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
        if (OrderMeta::get($order, '_is_recurring')) {
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
     * Builds a short suffix with Charge / Bill IDs for order notes.
     */
    private static function formatPaymentIdsNote(?string $chargeId, ?string $billId): string
    {
        $parts = [];
        if ($chargeId !== null && $chargeId !== '') {
            $parts[] = 'Charge ID: ' . $chargeId;
        }
        if ($billId !== null && $billId !== '') {
            $parts[] = 'Bill ID: ' . $billId;
        }
        return $parts === [] ? '' : ' ' . implode('. ', $parts);
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

        $order_pagbank_id = OrderMeta::get($order, 'order_id');
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
        OrderMeta::set($order, 'payment_method', $method->code, true);

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
        if (!empty($resp['error']) && is_string($resp['error'])) {
            throw new Exception([[
                'code' => (string) $resp['error'],
                'description' => (string) ($resp['message'] ?? $resp['error']),
            ]], 40000);
        }
        // Successful order create must return an id (PagBank ORDEM_* or PB pb_ord_*)
        if ($endpoint === 'ws/orders' && empty($resp['id'])) {
            throw new Exception([[
                'code' => 'INVALID_RESPONSE',
                'description' => __('Resposta inválida da API: pedido sem identificador.', 'pagbank-connect'),
            ]], 40000);
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
        if (OrderMeta::get($order, 'payment_method')) {
            $current_method = $payment_method == 'cc' ? 'credit_card' : $payment_method;
            $old_method = OrderMeta::get($order, 'payment_method');
            if (strcasecmp($current_method, $old_method) !== 0) {
                $order->add_order_note(
                    Branding::notePrefix($order) . ': Cliente alterou o método de pagamento de ' . $old_method . ' para ' . $current_method
                );
            }
        }
    }
}