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
        $currentPm = (string) OrderMeta::get($order, 'payment_method');
        $chargeTypePreview = (string) ($order_data['charges'][0]['payment_method']['type'] ?? '');
        // Same payload: still refresh method meta when hosted checkout never remapped to PIX/CC/boleto.
        $needsHostedMethodRefresh = $chargeTypePreview !== ''
            && in_array($currentPm, ['checkout_pb', 'redirect'], true);
        if (OrderMeta::get($order, '_last_update_md5') == $md5 && !$needsHostedMethodRefresh) {
            Functions::log(sprintf(__('Notificação de atualização ignorada para o pedido %s pois o conteúdo é o mesmo da última atualização.' . $cronMsg, 'pagbank-connect'), $order->get_id()), 'debug');
            return; // Do not update if the data is the same
        }
        OrderMeta::set($order, '_last_update_md5', $md5);

        // Checkout PB: sync canonical shipping/customer from notification/order payload.
        self::syncCheckoutPbOrderFromPayload($order, $order_data);
        
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
            $installments = max(1, (int) ($charge['payment_method']['installments'] ?? 1));
            OrderMeta::set($order, 'card_installments', $installments, true);
            OrderMeta::set($order, '_card_installments', $installments, true);
            OrderMeta::set($order, 'tid', $charge['payment_response']['tid'] ?? null);
            $cardBrand = $charge['payment_method']['card']['brand']
                ?? ($charge['payment_method']['card']['payment_company']['code'] ?? null);
            OrderMeta::set($order, '_card_brand', $cardBrand);
            if ($cardBrand) {
                OrderMeta::set($order, '_payment_company_code', (string) $cardBrand, false);
            }
            OrderMeta::set($order, '_card_first_digits', $charge['payment_method']['card']['first_digits'] ?? null);
            OrderMeta::set($order, '_card_last_digits', $charge['payment_method']['card']['last_digits'] ?? null);
            if (!empty($charge['payment_method']['card']['last_digits'])) {
                OrderMeta::set(
                    $order,
                    'card_last4',
                    substr((string) $charge['payment_method']['card']['last_digits'], -4),
                    true
                );
            }
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

        // Persist charges so admin order-info / titles can read brand, installments, etc.
        if (!empty($order_data['charges']) && is_array($order_data['charges'])) {
            OrderMeta::set($order, 'order_charges', $order_data['charges'], true);
        }

        // Hosted checkout (PagBank redirect / Checkout PB): record the method actually used.
        $previousMethod = (string) OrderMeta::get($order, 'payment_method');
        $hostedOrigin = (string) OrderMeta::get($order, '_hosted_checkout_origin');
        $isHostedPending = in_array($previousMethod, ['redirect', 'checkout_pb'], true)
            || in_array($hostedOrigin, ['redirect', 'checkout_pb'], true);
        if ($chargePaymentType !== '' && ($isHostedPending || $previousMethod === '')) {
            $mapped = self::mapChargeTypeToPaymentMethodMeta($chargePaymentType);
            if ($mapped !== '') {
                $originForTitle = in_array($previousMethod, ['redirect', 'checkout_pb'], true)
                    ? $previousMethod
                    : ($hostedOrigin !== '' ? $hostedOrigin : $previousMethod);
                if (in_array($previousMethod, ['redirect', 'checkout_pb'], true)) {
                    OrderMeta::set($order, '_hosted_checkout_origin', $previousMethod, true);
                    $originForTitle = $previousMethod;
                }
                OrderMeta::set($order, 'payment_method', $mapped, true);
                if (in_array($originForTitle, ['redirect', 'checkout_pb'], true)) {
                    self::maybeUpdateHostedCheckoutPaymentTitle($order, $charge, $originForTitle, $mapped);
                }
                self::persistHostedCheckoutMethodExtras($order, $order_data, $charge, $mapped);
            }
        } elseif ($chargePaymentType !== '' && isset($charge['payment_method']['type'])) {
            // Legacy buggy path used $charge[0]; keep non-hosted updates when type present.
            $mapped = self::mapChargeTypeToPaymentMethodMeta($chargePaymentType);
            if ($mapped !== '' && $previousMethod === '') {
                OrderMeta::set($order, 'payment_method', $mapped, true);
            }
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
            $pm = (string) $order->get_payment_method();
            $hostedOrigin = (string) OrderMeta::get($order, '_hosted_checkout_origin');
            $paymentMethodMeta = (string) OrderMeta::get($order, 'payment_method');
            $isHostedCheckout = in_array($pm, ['rm-pagbank-redirect', 'rm-pagbank-checkout-pb'], true)
                || in_array($hostedOrigin, ['redirect', 'checkout_pb'], true)
                || in_array($paymentMethodMeta, ['redirect', 'checkout_pb'], true);
            if ($isHostedCheckout) {
                // Before first paid order is linked, order_id is empty or still the checkout session (chk_/CHEC_).
                $checkoutId = (string) (
                    OrderMeta::get($order, 'checkout_id')
                    ?: ($_SERVER['HTTP_X_PRODUCT_ID'] ?? '')
                );
                $awaitingOrderLink = $order_pagbank_id === null
                    || $order_pagbank_id === ''
                    || Api::isCheckoutSessionId((string) $order_pagbank_id)
                    || ($checkoutId !== '' && (string) $order_pagbank_id === $checkoutId);
                if (!$awaitingOrderLink) {
                    wp_die('ID do pedido não corresponde ao código de checkout.', 400);
                }
            } else {
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
            case 'checkout_pb':
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
     * Map API charge type to OrderMeta payment_method (plugin convention).
     */
    public static function mapChargeTypeToPaymentMethodMeta(string $chargeType): string
    {
        $type = strtoupper($chargeType);
        if ($type === 'CREDIT_CARD' || $type === 'CREDIT_CARD_3DS') {
            return 'credit_card';
        }
        if ($type === 'PIX' || $type === 'BOLEPIX') {
            return 'pix';
        }
        if ($type === 'BOLETO') {
            return 'boleto';
        }
        return '';
    }

    /**
     * Human-readable payment method title for hosted checkout orders in admin/emails.
     *
     * @param array<string, mixed> $charge
     */
    public static function maybeUpdateHostedCheckoutPaymentTitle(
        WC_Order $order,
        array $charge,
        string $origin,
        string $mappedMethod
    ): void {
        $prefix = $origin === 'checkout_pb'
            ? (string) Params::getCheckoutPbConfig('title', __('Checkout externo', 'pagbank-connect'))
            : __('Checkout PagBank', 'pagbank-connect');

        if ($mappedMethod === 'pix') {
            $order->set_payment_method_title($prefix . ' — PIX');
            return;
        }
        if ($mappedMethod === 'boleto') {
            $order->set_payment_method_title($prefix . ' — ' . __('Boleto', 'pagbank-connect'));
            return;
        }
        if ($mappedMethod === 'credit_card') {
            $brand = (string) (
                $charge['payment_method']['card']['brand']
                ?? ($charge['payment_method']['card']['payment_company']['code'] ?? null)
                ?? OrderMeta::get($order, '_card_brand')
                ?? ''
            );
            $brand = $brand !== '' ? wc_strtoupper(str_replace('_', ' ', $brand)) : '';
            $inst = max(1, (int) (
                $charge['payment_method']['installments']
                ?? OrderMeta::get($order, 'card_installments')
                ?? 1
            ));
            $parts = array_filter([
                $prefix,
                __('Cartão', 'pagbank-connect') . ($brand !== '' ? ' ' . $brand : ''),
                $inst > 1 ? ($inst . 'x') : null,
            ]);
            $order->set_payment_method_title(implode(' — ', $parts));
        }
    }

    /**
     * PIX / boleto links from hosted-checkout charge payloads (not set at session create).
     *
     * @param array<string, mixed> $order_data
     * @param array<string, mixed> $charge
     */
    public static function persistHostedCheckoutMethodExtras(
        WC_Order $order,
        array $order_data,
        array $charge,
        string $mappedMethod
    ): void {
        if ($mappedMethod === 'pix') {
            $qr = $order_data['qr_codes'][0] ?? null;
            if (is_array($qr)) {
                if (!empty($qr['links'][0]['href'])) {
                    OrderMeta::set($order, 'pix_qrcode', $qr['links'][0]['href'], true);
                }
                if (!empty($qr['text'])) {
                    OrderMeta::set($order, 'pix_qrcode_text', $qr['text'], true);
                }
                if (!empty($qr['expiration_date'])) {
                    OrderMeta::set($order, 'pix_qrcode_expiration', $qr['expiration_date'], true);
                }
            }
            return;
        }

        if ($mappedMethod !== 'boleto') {
            return;
        }

        $boletoPdf = $charge['links'][0]['href']
            ?? ($order_data['links'][0]['href'] ?? null)
            ?? ($charge['payment_method']['boleto']['print_url'] ?? null);
        $boletoPng = $charge['links'][1]['href'] ?? null;
        $partner = (string) OrderMeta::get($order, 'partner');
        if ($partner === 'vindi') {
            $boletoPng = null;
        }
        if ($boletoPdf) {
            OrderMeta::set($order, 'boleto_pdf', $boletoPdf, true);
        }
        if ($boletoPng) {
            OrderMeta::set($order, 'boleto_png', $boletoPng, true);
        }
        $boleto = is_array($charge['payment_method']['boleto'] ?? null)
            ? $charge['payment_method']['boleto']
            : [];
        if (!empty($boleto['due_date'])) {
            OrderMeta::set($order, 'boleto_due_date', $boleto['due_date'], true);
        }
        if (!empty($boleto['formatted_barcode']) || !empty($order_data['boleto']['formatted_barcode'])) {
            OrderMeta::set(
                $order,
                'boleto_barcode_formatted',
                $boleto['formatted_barcode'] ?? $order_data['boleto']['formatted_barcode'],
                true
            );
        }
        if (!empty($boleto['barcode']) || !empty($order_data['boleto']['barcode'])) {
            OrderMeta::set(
                $order,
                'boleto_barcode',
                $boleto['barcode'] ?? $order_data['boleto']['barcode'],
                true
            );
        }
    }

    /**
     * Apply Checkout PB canonical shipping/customer onto the Woo order.
     *
     * @param WC_Order $order
     * @param array    $order_data Canonical PB order (or nested under 'order')
     */
    public static function syncCheckoutPbOrderFromPayload(WC_Order $order, array $order_data): void
    {
        $pm = (string) OrderMeta::get($order, 'payment_method');
        $checkoutId = (string) OrderMeta::get($order, 'checkout_id');
        $isCheckoutPb = $pm === 'checkout_pb'
            || $order->get_payment_method() === 'rm-pagbank-checkout-pb'
            || strpos($checkoutId, 'chk_') === 0;
        if (!$isCheckoutPb) {
            return;
        }

        // Nested complete result: { order: {...} }
        if (isset($order_data['order']) && is_array($order_data['order']) && empty($order_data['id'])) {
            $order_data = $order_data['order'];
        }

        $notePrefix = Branding::notePrefix($order);
        $changed = false;

        if (!empty($order_data['customer']) && is_array($order_data['customer'])) {
            $c = $order_data['customer'];
            if (!empty($c['name'])) {
                $parts = preg_split('/\s+/', trim((string) $c['name']), 2);
                $order->set_billing_first_name($parts[0] ?? '');
                $order->set_billing_last_name($parts[1] ?? '');
                $changed = true;
            }
            if (!empty($c['email'])) {
                $order->set_billing_email((string) $c['email']);
                $changed = true;
            }
            if (!empty($c['tax_id'])) {
                OrderMeta::set($order, '_rm_pagbank_customer_document', preg_replace('/\D+/', '', (string) $c['tax_id']), true);
            }
            if (!empty($c['address']) && is_array($c['address'])) {
                self::applyPbAddressToOrder($order, $c['address'], 'billing');
                $changed = true;
            }
            if (!empty($c['phone'])) {
                $phone = $c['phone'];
                if (is_array($phone)) {
                    $first = $phone[0] ?? $phone;
                    if (is_array($first)) {
                        $digits = ($first['area'] ?? '') . ($first['number'] ?? '');
                        if ($digits !== '') {
                            $order->set_billing_phone((string) $digits);
                            $changed = true;
                        }
                    }
                } elseif (is_string($phone) && $phone !== '') {
                    $order->set_billing_phone($phone);
                    $changed = true;
                }
            }
        }

        $shipping = is_array($order_data['shipping'] ?? null) ? $order_data['shipping'] : null;
        if ($shipping) {
            if (!empty($shipping['address']) && is_array($shipping['address'])) {
                self::applyPbAddressToOrder($order, $shipping['address'], 'shipping');
                $changed = true;
            }

            $amountCents = null;
            if (isset($shipping['amount'])) {
                $amountCents = (int) $shipping['amount'];
            } elseif (isset($order_data['amounts']['shipping'])) {
                $amountCents = (int) $order_data['amounts']['shipping'];
            } elseif (is_array($shipping['selected'] ?? null) && isset($shipping['selected']['amount'])) {
                $amountCents = (int) $shipping['selected']['amount'];
            }

            if ($amountCents !== null) {
                $amount = $amountCents / 100;
                $label = (string) (
                    $shipping['service_type']
                    ?? ($shipping['selected']['method'] ?? null)
                    ?? ($shipping['method'] ?? null)
                    ?? __('Frete', 'pagbank-connect')
                );
                if (is_array($shipping['selected'] ?? null)) {
                    $sel = $shipping['selected'];
                    $parts = array_filter([
                        $sel['provider'] ?? null,
                        $sel['method'] ?? null,
                        $shipping['service_type'] ?? null,
                    ]);
                    if ($parts) {
                        $label = implode(' / ', $parts);
                    }
                }

                // Replace existing shipping lines
                foreach ($order->get_items('shipping') as $item_id => $item) {
                    $order->remove_item($item_id);
                }
                if ($amount > 0 || strtoupper((string) ($shipping['type'] ?? '')) === 'FREE') {
                    $rate = new \WC_Order_Item_Shipping();
                    $rate->set_method_title($label);
                    $rate->set_method_id('rm_pagbank_checkout_pb');
                    $rate->set_total(max(0, $amount));
                    $order->add_item($rate);
                }
                $order->set_shipping_total(max(0, $amount));
                $changed = true;
            }
        }

        if ($changed) {
            $order->calculate_totals(false);
            $order->add_order_note(
                $notePrefix . ': Endereço/frete sincronizados a partir do Checkout PB.'
            );
            $order->save();
        }
    }

    /**
     * @param WC_Order             $order
     * @param array<string, mixed> $addr
     * @param string               $type billing|shipping
     */
    protected static function applyPbAddressToOrder(WC_Order $order, array $addr, string $type): void
    {
        $street = (string) ($addr['street'] ?? '');
        $number = (string) ($addr['number'] ?? '');
        $complement = (string) ($addr['complement'] ?? '');
        $locality = (string) ($addr['locality'] ?? '');
        $city = (string) ($addr['city'] ?? '');
        $region = (string) ($addr['region_code'] ?? ($addr['region'] ?? ''));
        $postal = preg_replace('/\D+/', '', (string) ($addr['postal_code'] ?? '')) ?? '';
        $country = (string) ($addr['country'] ?? 'BR');
        if (strtoupper($country) === 'BRA') {
            $country = 'BR';
        }

        if ($type === 'shipping') {
            $order->set_shipping_address_1($street);
            $order->set_shipping_address_2($complement);
            $order->set_shipping_city($city);
            $order->set_shipping_state($region);
            $order->set_shipping_postcode($postal);
            $order->set_shipping_country($country);
            $order->update_meta_data('_shipping_number', $number);
            $order->update_meta_data('_shipping_neighborhood', $locality);
            if ($complement !== '') {
                $order->update_meta_data('_shipping_complement', $complement);
            }
        } else {
            $order->set_billing_address_1($street);
            $order->set_billing_address_2($complement);
            $order->set_billing_city($city);
            $order->set_billing_state($region);
            $order->set_billing_postcode($postal);
            $order->set_billing_country($country);
            $order->update_meta_data('_billing_number', $number);
            $order->update_meta_data('_billing_neighborhood', $locality);
            if ($complement !== '') {
                $order->update_meta_data('_billing_complement', $complement);
            }
        }
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