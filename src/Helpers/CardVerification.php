<?php
namespace RM_PagBank\Helpers;

use Exception;
use RM_PagBank\Connect\Exception as PagBankException;
use WC_Order;
use WP_Error;

/**
 * Vindi require_cvv: first charge must use a fresh gateway_token (with CVV).
 * For trial / change-card (no real sale), charge R$1.00 then refund.
 */
class CardVerification
{
    public const AMOUNT_REAIS = 1.0;
    public const AMOUNT_CENTS = 100;

    /**
     * Charge R$1 with gateway_token (store card), then refund.
     * Blocks on charge failure. Refund failure is logged but does not undo a successful verify.
     *
     * @return array{
     *   profile_id:int,
     *   customer_id:int,
     *   charge_id:string,
     *   order_id:string,
     *   response:array<string,mixed>,
     *   refund_ok:bool
     * }
     * @throws PagBankException|Exception
     */
    public static function chargeOneRealAndRefund(WC_Order $order, string $gatewayToken, array $opts = []): array
    {
        $gatewayToken = trim($gatewayToken);
        if ($gatewayToken === '') {
            throw new PagBankException([[
                'code' => 'TOKEN_MISSING',
                'description' => __('Token do cartão ausente para verificação.', 'pagbank-connect'),
            ]], 40000);
        }

        $api = new Api();
        if (!$api->partner()->isVindi()) {
            throw new PagBankException([[
                'code' => 'NOT_VINDI',
                'description' => __('Verificação de cartão R$1 só se aplica à Vindi.', 'pagbank-connect'),
            ]], 40000);
        }

        $holder = (string) ($opts['holder_name'] ?? OrderMeta::get($order, '_card_holder_name') ?: '');
        if ($holder === '') {
            $holder = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }
        if ($holder === '') {
            $holder = 'Cliente';
        }

        $taxId = TaxId::sanitizeForApi(
            (string) ($opts['registry_code'] ?? $order->get_meta('_rm_pagbank_customer_document') ?: '')
        );
        if ($taxId === '' && !empty($_POST['rm-pagbank-card-cpf-cnpj'])) {
            $taxId = TaxId::sanitizeForApi(wp_unslash((string) $_POST['rm-pagbank-card-cpf-cnpj']));
        }
        if ($taxId === '' && !empty($_POST['rm-pagbank-customer-document'])) {
            $taxId = TaxId::sanitizeForApi(wp_unslash((string) $_POST['rm-pagbank-customer-document']));
        }

        $common = new \RM_PagBank\Connect\Payments\Common($order);
        $customer = $common->getCustomerData();
        if ($taxId !== '' && method_exists($customer, 'setTaxId')) {
            $customer->setTaxId($taxId);
        }

        $suffix = strtolower(wp_generate_password(6, false, false));
        $referenceId = (string) $order->get_id() . '-verify-' . $suffix;

        $payload = [
            'reference_id' => $referenceId,
            'customer' => $customer,
            'items' => [[
                'reference_id' => 'card-verify',
                'name' => 'Verificacao de cartao',
                'quantity' => 1,
                'unit_amount' => self::AMOUNT_CENTS,
            ]],
            'enable_proxy' => $common->getEnableProxy(),
            // Avoid WC webhook treating this R$1 verify as the shop order payment.
            'notification_urls' => [get_site_url() . '/?wc-api=rm_ps_notif&hash=card-verify'],
            'charges' => [[
                'amount' => [
                    'value' => self::AMOUNT_CENTS,
                    'currency' => 'BRL',
                ],
                'payment_method' => [
                    'type' => 'CREDIT_CARD',
                    'installments' => 1,
                    'capture' => true,
                    'discard_credit_card' => false,
                    'card' => [
                        'gateway_token' => $gatewayToken,
                        'store' => true,
                        'holder' => [
                            'name' => $holder,
                        ],
                    ],
                ],
            ]],
        ];

        if (!empty($opts['company_code'])) {
            // Stored on order for later display; Vindi billing ignores company on /billing.
            OrderMeta::set($order, '_payment_company_code', (string) $opts['company_code'], true);
        }

        Functions::log(
            sprintf('Card verify: charging R$1 for order %d (ref %s)', $order->get_id(), $referenceId),
            'info'
        );

        $resp = $api->post('ws/orders', $payload);
        if (isset($resp['error_messages']) || !empty($resp['error'])) {
            throw new PagBankException(
                $resp['error_messages'] ?? [[
                    'code' => (string) ($resp['error'] ?? 'VERIFY_ERROR'),
                    'description' => (string) ($resp['message'] ?? __('Falha na verificação do cartão.', 'pagbank-connect')),
                ]],
                40000
            );
        }

        $charge = is_array($resp['charges'][0] ?? null) ? $resp['charges'][0] : [];
        $status = strtoupper((string) ($charge['status'] ?? $resp['status'] ?? ''));
        if (!in_array($status, ['PAID', 'AUTHORIZED'], true)) {
            $msg = (string) ($charge['payment_response']['message'] ?? '');
            $code = (string) ($charge['payment_response']['code'] ?? '');
            $detail = trim($msg . ($code !== '' ? ' (' . $code . ')' : ''));
            if ($detail === '' && $status !== '') {
                $detail = sprintf(
                    /* translators: %s: charge status */
                    __('Verificação do cartão não aprovada (status: %s).', 'pagbank-connect'),
                    $status
                );
            }
            throw new PagBankException([[
                'code' => 'CARD_VERIFY_DECLINED',
                'description' => $detail !== ''
                    ? $detail
                    : __('Cartão recusado na verificação de R$1,00.', 'pagbank-connect'),
            ]], 40000);
        }

        $profileId = (int) (
            $charge['payment_method']['payment_profile_id']
            ?? $charge['payment_method']['card']['id']
            ?? $charge['payment_method']['card']['payment_profile_id']
            ?? 0
        );
        $customerId = (int) (
            $charge['payment_method']['customer_id']
            ?? $resp['customer']['id']
            ?? OrderMeta::get($order, '_vindi_customer_id')
            ?? 0
        );

        if ($profileId <= 0) {
            throw new PagBankException([[
                'code' => 'PROFILE_ID_MISSING',
                'description' => __('Verificação ok, mas sem payment_profile_id na resposta.', 'pagbank-connect'),
            ]], 40000);
        }

        $pbOrderId = (string) ($resp['id'] ?? '');
        $chargeId = (string) ($charge['id'] ?? $pbOrderId);
        if ($chargeId === '') {
            throw new PagBankException([[
                'code' => 'CHARGE_ID_MISSING',
                'description' => __('Verificação sem identificador de cobrança para estorno.', 'pagbank-connect'),
            ]], 40000);
        }

        OrderMeta::set($order, '_payment_profile_id', $profileId, true);
        if ($customerId > 0) {
            OrderMeta::set($order, '_vindi_customer_id', $customerId, true);
        }
        OrderMeta::set($order, '_card_verify_charge_id', $chargeId, true);
        OrderMeta::set($order, '_card_verify_order_id', $pbOrderId, true);

        $card = is_array($charge['payment_method']['card'] ?? null) ? $charge['payment_method']['card'] : [];
        if (!empty($card['last_digits'])) {
            OrderMeta::set($order, 'card_last4', (string) $card['last_digits'], true);
        }
        if (!empty($card['first_digits'])) {
            OrderMeta::set($order, '_card_first_digits', (string) $card['first_digits'], true);
        }
        if (!empty($card['brand'])) {
            OrderMeta::set($order, '_payment_company_code', strtolower((string) $card['brand']), true);
        }
        if (!empty($card['holder']['name'])) {
            OrderMeta::set($order, '_card_holder_name', (string) $card['holder']['name'], true);
        }

        $order->add_order_note(
            sprintf(
                /* translators: 1: charge id */
                __('PB Integrações: cartão verificado com cobrança de R$1,00 (cobrança %s). Estornando…', 'pagbank-connect'),
                $chargeId
            )
        );

        $refundOk = self::refundVerificationCharge($order, $chargeId);
        if ($refundOk) {
            $order->add_order_note(
                __('PB Integrações: estorno de R$1,00 da verificação do cartão solicitado com sucesso.', 'pagbank-connect')
            );
        } else {
            $order->add_order_note(
                __(
                    'PB Integrações: cobrança de verificação aprovada, mas o estorno automático de R$1,00 falhou. Estorne manualmente no painel.',
                    'pagbank-connect'
                )
            );
            Functions::log(
                sprintf('Card verify: refund failed for charge %s (order %d)', $chargeId, $order->get_id()),
                'error'
            );
        }

        $order->save();

        return [
            'profile_id' => $profileId,
            'customer_id' => $customerId,
            'charge_id' => $chargeId,
            'order_id' => $pbOrderId,
            'response' => $resp,
            'refund_ok' => $refundOk,
        ];
    }

    /**
     * @param string $chargeId Partner charge id or PB order id accepted by charges/cancel.
     */
    private static function refundVerificationCharge(WC_Order $order, string $chargeId): bool
    {
        // Temporarily point Api::refund at the verification charge without clobbering a real charge_id.
        $prevCharge = OrderMeta::get($order, 'charge_id');
        $prevOrderId = OrderMeta::get($order, 'order_id');
        OrderMeta::set($order, 'charge_id', $chargeId, true);
        $order->save();

        $result = Api::refund($order->get_id(), self::AMOUNT_REAIS);

        if ($prevCharge) {
            OrderMeta::set($order, 'charge_id', $prevCharge, true);
        } else {
            OrderMeta::delete($order, 'charge_id');
        }
        if ($prevOrderId) {
            OrderMeta::set($order, 'order_id', $prevOrderId, true);
        }
        $order->save();

        return !($result instanceof WP_Error) && $result === true;
    }
}
