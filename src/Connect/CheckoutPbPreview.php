<?php

namespace RM_PagBank\Connect;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Partner\Capabilities;

/**
 * Builds checkout.pbintegracoes.com/preview URL from Checkout PB admin settings.
 */
class CheckoutPbPreview
{
    public const AJAX_ACTION = 'rm_pagbank_checkout_pb_preview';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'handleAjax']);
    }

    public static function handleAjax(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'pagbank-connect')], 403);
        }

        check_ajax_referer(self::AJAX_ACTION, 'nonce');

        $caps = new Capabilities();
        if (!$caps->hasCheckoutPb()) {
            wp_send_json_error([
                'message' => __('Checkout PB disponível apenas com Connect Key Vindi (CONVD…).', 'pagbank-connect'),
            ], 400);
        }

        $snapshot = [];
        if (!empty($_POST['settings']) && is_array($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                $snapshot[sanitize_key((string) $key)] = wp_unslash($value);
            }
        }

        try {
            $url = self::buildPreviewUrl($snapshot);
            wp_send_json_success(['url' => $url]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @param array<string, mixed> $snapshot Form values (unsaved) or empty to use saved options.
     */
    public static function buildPreviewUrl(array $snapshot = []): string
    {
        $base = apply_filters(
            'rm_pagbank_checkout_pb_preview_base',
            'https://checkout.pbintegracoes.com'
        );
        $base = rtrim((string) $base, '/');

        $item = self::resolvePreviewItem();
        $itemCents = (int) $item['unit_amount'];

        $theme = (string) self::cfg($snapshot, 'checkout_pb_theme', 'painel');
        if (!in_array($theme, ['painel', 'coluna', 'central'], true)) {
            $theme = 'painel';
        }

        $colors = array_values(array_filter([
            self::normalizeHex((string) self::cfg($snapshot, 'checkout_pb_color_button', '#171717')),
            self::normalizeHex((string) self::cfg($snapshot, 'checkout_pb_color_summary', '#171717')),
            self::normalizeHex((string) self::cfg($snapshot, 'checkout_pb_color_background', '#f5f5f4')),
            self::normalizeHex((string) self::cfg($snapshot, 'checkout_pb_color_text', '#171717')),
        ]));

        $name = trim((string) self::cfg($snapshot, 'checkout_pb_brand_name', ''));
        if ($name === '') {
            $name = get_bloginfo('name') ?: 'Loja';
        }

        $methods = self::cfg($snapshot, 'checkout_pb_payment_methods', ['CREDIT_CARD', 'PIX']);
        if (!is_array($methods)) {
            $methods = array_filter(array_map('trim', explode(',', (string) $methods)));
        }
        $methods = array_values(array_filter(array_map(static function ($m) {
            $m = strtoupper((string) $m);
            return in_array($m, ['CREDIT_CARD', 'PIX', 'BOLETO'], true) ? $m : '';
        }, $methods)));
        if ($methods === []) {
            $methods = ['CREDIT_CARD', 'PIX'];
        }

        $q = [
            'theme' => $theme,
            'name' => mb_substr($name, 0, 80),
            'methods' => implode(',', $methods),
            'items' => self::encodeJsonParam([$item]),
            'cm' => '1',
            'ship_addr' => '1',
        ];

        if ($colors !== []) {
            $q['colors'] = implode(',', array_map(static function ($c) {
                return ltrim($c, '#');
            }, $colors));
        }

        $logo = trim((string) self::cfg($snapshot, 'checkout_pb_logo_url', ''));
        if ($logo !== '' && preg_match('#^https://#i', $logo)) {
            $q['logo'] = $logo;
        }
        $bg = trim((string) self::cfg($snapshot, 'checkout_pb_summary_bg_image', ''));
        if ($bg !== '' && preg_match('#^https://#i', $bg)) {
            $q['bg'] = $bg;
        }

        $ship = self::mapShipping($snapshot);
        $q['ship'] = $ship['mode'];
        if ($ship['mode'] === 'FIXED' && $ship['amount'] > 0) {
            $q['ship_amount'] = (string) $ship['amount'];
        }

        $discountCfg = (string) self::cfg($snapshot, 'checkout_pb_discount', '');
        $discountCents = self::discountToCents($discountCfg, $itemCents);
        if ($discountCents > 0) {
            $q['discount'] = (string) $discountCents;
        }

        $imax = self::previewMaxInstallments($snapshot);
        $q['imax'] = (string) $imax;
        $ifree = self::previewMaxNoInterest($snapshot, $itemCents / 100);
        if ($ifree > 0) {
            $q['ifree'] = (string) $ifree;
        }

        return $base . '/preview?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{name: string, quantity: int, unit_amount: int, image_url?: string}
     */
    private static function resolvePreviewItem(): array
    {
        if (!function_exists('wc_get_products')) {
            return self::fakeItem();
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        if (!is_array($products) || $products === []) {
            return self::fakeItem();
        }

        $product = $products[0];
        $price = (float) $product->get_price();
        if ($price <= 0) {
            $price = (float) $product->get_regular_price();
        }
        $cents = max(100, (int) round($price * 100));
        $row = [
            'name' => mb_substr(wp_strip_all_tags((string) $product->get_name()), 0, 80) ?: __('Produto', 'pagbank-connect'),
            'quantity' => 1,
            'unit_amount' => $cents,
        ];
        $imageId = (int) $product->get_image_id();
        if ($imageId > 0) {
            $url = wp_get_attachment_image_url($imageId, 'medium');
            if (is_string($url) && preg_match('#^https://#i', $url)) {
                $row['image_url'] = $url;
            }
        }

        return $row;
    }

    /**
     * @return array{name: string, quantity: int, unit_amount: int}
     */
    private static function fakeItem(): array
    {
        return [
            'name' => __('Produto de exemplo', 'pagbank-connect'),
            'quantity' => 1,
            'unit_amount' => 7900,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array{mode: string, amount: int}
     */
    private static function mapShipping(array $snapshot): array
    {
        $mode = (string) self::cfg($snapshot, 'checkout_pb_shipping_mode', 'free');
        if ($mode === 'fixed') {
            $raw = str_replace(',', '.', trim((string) self::cfg($snapshot, 'checkout_pb_shipping_fixed_amount', '0')));
            $reais = is_numeric($raw) ? (float) $raw : 0.0;
            $cents = max(0, (int) round($reais * 100));

            return ['mode' => 'FIXED', 'amount' => $cents];
        }
        if ($mode === 'calculate') {
            return ['mode' => 'CALCULATE', 'amount' => 0];
        }
        if ($mode === 'free') {
            return ['mode' => 'FREE', 'amount' => 0];
        }

        return ['mode' => 'none', 'amount' => 0];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private static function previewMaxInstallments(array $snapshot): int
    {
        if (self::cfg($snapshot, 'checkout_pb_installments_options_limit_installments', 'no') === 'no') {
            return 12;
        }
        $max = (int) self::cfg($snapshot, 'checkout_pb_installments_options_max_installments', 12);

        return max(1, min(12, $max));
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private static function previewMaxNoInterest(array $snapshot, float $orderTotalReais): int
    {
        $option = (string) self::cfg($snapshot, 'checkout_pb_installment_options', 'buyer');
        if ($option === '' || $option === 'buyer') {
            return 0;
        }
        if ($option === 'fixed') {
            return max(0, min(12, (int) self::cfg($snapshot, 'checkout_pb_installment_options_fixed', 3)));
        }
        $minTotal = max(5, (int) self::cfg($snapshot, 'checkout_pb_installments_options_min_total', 50));
        $installments = (int) floor($orderTotalReais / $minTotal);
        if ($installments === 1) {
            return 0;
        }

        return $installments > 12 ? 12 : $installments;
    }

    private static function discountToCents(string $configValue, int $itemCents): int
    {
        $configValue = trim($configValue);
        if ($configValue === '' || $configValue === '0') {
            return 0;
        }
        if (strpos($configValue, '%') !== false) {
            $pct = (float) str_replace(['%', ','], ['', '.'], $configValue);
            if ($pct <= 0) {
                return 0;
            }

            return max(0, (int) round($itemCents * ($pct / 100)));
        }
        $reais = (float) str_replace(',', '.', $configValue);
        if ($reais <= 0) {
            return 0;
        }

        return min($itemCents, (int) round($reais * 100));
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param mixed                $default
     * @return mixed
     */
    private static function cfg(array $snapshot, string $key, $default = '')
    {
        if (array_key_exists($key, $snapshot)) {
            return $snapshot[$key];
        }

        return Params::getCheckoutPbConfig($key, $default);
    }

    private static function normalizeHex(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if ($value[0] !== '#') {
            $value = '#' . $value;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return '';
        }

        return strtolower($value);
    }

    /**
     * Same encoding as pb-checkout encodeJsonParam (UTF-8 → base64).
     *
     * @param mixed $value
     */
    public static function encodeJsonParam($value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return '';
        }

        return base64_encode($json);
    }
}
