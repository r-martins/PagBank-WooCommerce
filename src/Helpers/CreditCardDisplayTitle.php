<?php

namespace RM_PagBank\Helpers;

use RM_PagBank\Connect;
use WC_Order;

/**
 * Customer vs API payment method titles with installment placeholders for PagBank credit card orders.
 */
class CreditCardDisplayTitle
{
    /**
     * @param string $title
     * @param WC_Order $order
     * @return string
     */
    public static function filterPaymentMethodTitle($title, $order)
    {
        if (!$order instanceof WC_Order || !self::isPagbankCreditCardOrder($order)) {
            return $title;
        }

        $template = self::isApiContext()
            ? Params::getCcConfig('title_api', '')
            : Params::getCcConfig('title_customer', '');

        $template = is_string($template) ? trim($template) : '';
        if ($template === '') {
            return $title;
        }

        return self::applyTemplate($template, $order);
    }

    public static function isPagbankCreditCardOrder(WC_Order $order): bool
    {
        if ($order->get_meta('pagbank_payment_method') !== 'credit_card') {
            return false;
        }

        $pm = $order->get_payment_method();

        return $pm === 'rm-pagbank-cc' || $pm === Connect::DOMAIN;
    }

    public static function isApiContext(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        return function_exists('WC') && WC()->is_rest_api_request();
    }

    /**
     * @return array<string, string>
     */
    public static function buildPlaceholderValues(WC_Order $order): array
    {
        $installments = (int) $order->get_meta('_pagbank_card_installments');
        if ($installments < 1) {
            $installments = (int) $order->get_meta('pagbank_card_installments');
        }
        if ($installments < 1) {
            $installments = 1;
        }

        $totalFloat = null;
        $metaTotal = $order->get_meta('pagbank_cc_total_charged');
        if ($metaTotal !== '' && $metaTotal !== null && is_numeric($metaTotal)) {
            $totalFloat = (float) $metaTotal;
        }
        if ($totalFloat === null || $totalFloat <= 0) {
            $totalFloat = (float) $order->get_total('');
        }

        $perFloat = null;
        $instMeta = $order->get_meta('pagbank_cc_installment_amount');
        if ($instMeta !== '' && $instMeta !== null && is_numeric($instMeta)) {
            $perFloat = (float) $instMeta;
        }
        if (($perFloat === null || $perFloat <= 0) && $installments > 0) {
            $perFloat = $totalFloat / $installments;
        }

        $perFloat = (float) ($perFloat ?? 0);

        $installmentValue = wc_format_decimal($perFloat, wc_get_price_decimals());
        $installmentValueFormatted = number_format(
            $perFloat,
            wc_get_price_decimals(),
            wc_get_price_decimal_separator(),
            wc_get_price_thousand_separator()
        );

        $brand = (string) $order->get_meta('_pagbank_card_brand');
        if ($brand !== '') {
            $brand = function_exists('wc_strtoupper') ? wc_strtoupper($brand) : strtoupper($brand);
        }

        $buyerInterestFloat = self::getBuyerInterestAmount($order);
        $buyerInterest = '';
        $buyerInterestFormatted = '';
        if ($buyerInterestFloat > 0) {
            $buyerInterest = wc_format_decimal($buyerInterestFloat, wc_get_price_decimals());
            $buyerInterestFormatted = number_format(
                $buyerInterestFloat,
                wc_get_price_decimals(),
                wc_get_price_decimal_separator(),
                wc_get_price_thousand_separator()
            );
        }

        $totalCharged = wc_format_decimal($totalFloat, wc_get_price_decimals());
        $totalChargedFormatted = number_format(
            $totalFloat,
            wc_get_price_decimals(),
            wc_get_price_decimal_separator(),
            wc_get_price_thousand_separator()
        );

        $interestFreeMeta = $order->get_meta('pagbank_cc_interest_free');
        if ($interestFreeMeta === 'yes') {
            $yesNo = __('sem juros', 'pagbank-connect');
        } elseif ($interestFreeMeta === 'no') {
            $yesNo = __('com juros', 'pagbank-connect');
        } else {
            $yesNo = $buyerInterestFloat > 0
                ? __('com juros', 'pagbank-connect')
                : __('sem juros', 'pagbank-connect');
        }

        return [
            '{installments}' => (string) $installments,
            '{installment}' => (string) $installments,
            '{installmentValue}' => $installmentValue,
            '{installmentValueFormatted}' => $installmentValueFormatted,
            '{brand}' => $brand,
            '{buyerInterest}' => $buyerInterest,
            '{buyerInterestFormatted}' => $buyerInterestFormatted,
            '{totalCharged}' => $totalCharged,
            '{totalChargedFormatted}' => $totalChargedFormatted,
            '{yesNoInterest}' => $yesNo,
        ];
    }

    public static function applyTemplate(string $template, WC_Order $order): string
    {
        $replacements = self::buildPlaceholderValues($order);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * WooCommerce REST builds order data via get_base_data(), which bypasses
     * woocommerce_order_get_payment_method_title. Patch payment_method_title here for API consumers.
     *
     * @param \WP_REST_Response $response
     * @param \WC_Order $order
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function filterRestPrepareShopOrderObject($response, $order, $request)
    {
        if (!$response instanceof \WP_REST_Response || !$order instanceof WC_Order) {
            return $response;
        }

        if (!self::isPagbankCreditCardOrder($order)) {
            return $response;
        }

        $template = Params::getCcConfig('title_api', '');
        $template = is_string($template) ? trim($template) : '';
        if ($template === '') {
            return $response;
        }

        $data = $response->get_data();
        $data['payment_method_title'] = self::applyTemplate($template, $order);
        $response->set_data($data);

        return $response;
    }

    public static function getBuyerInterestAmount(WC_Order $order): float
    {
        $stored = $order->get_meta('_pagbank_cc_buyer_interest_total');
        if ($stored !== '' && $stored !== null && is_numeric($stored)) {
            return max(0, (float) $stored);
        }

        $charges = $order->get_meta('pagbank_order_charges');
        if (!is_array($charges) || !isset($charges[0]) || !is_array($charges[0])) {
            return 0.0;
        }

        $raw = $charges[0]['amount']['fees']['buyer']['interest']['total'] ?? null;
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return 0.0;
        }

        $val = (float) $raw;
        // v4 amounts are typically in cents
        if ($val > 0) {
            return max(0, $val / 100);
        }

        return 0.0;
    }
}
