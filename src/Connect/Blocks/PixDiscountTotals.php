<?php

declare(strict_types=1);

namespace RM_PagBank\Connect\Blocks;

use RM_PagBank\Helpers\Params;
use WC_Cart;

/**
 * Exposes Pix discount and "Total no Pix" in the Store API cart response
 * so the Checkout Block order summary can display them.
 */
class PixDiscountTotals
{
    public const CART_EXTENSION_NAMESPACE = 'pagbank_connect_pix';

    /**
     * Register the rest_post_dispatch filter. Called on rest_api_init so it runs
     * for every REST request (Store API cart/checkout are served via REST and
     * do not trigger woocommerce_blocks_loaded).
     */
    public static function registerFilter(): void
    {
        if (!self::shouldShowInTotals()) {
            return;
        }
        add_filter('rest_post_dispatch', [__CLASS__, 'injectPixFeesIntoCartResponse'], 10, 3);
    }

    /**
     * Register the hydration filter early (on wp) so it runs when the Checkout Block
     * hydrates cart/checkout data during page render, even if woocommerce_blocks_loaded
     * fires after our hook.
     */
    public static function registerHydrationFilter(): void
    {
        if (!self::shouldShowInTotals()) {
            return;
        }
        add_filter('woocommerce_hydration_request_after_callbacks', [__CLASS__, 'injectPixFeesIntoHydrationResponse'], 10, 3);
    }

    public static function init(): void
    {
        $show = self::shouldShowInTotals();
        if (!$show) {
            return;
        }

        self::registerCartExtension();
        add_filter('rest_post_dispatch', [__CLASS__, 'injectPixFeesIntoCartResponse'], 10, 3);
    }

    /**
     * Injects Pix fees into cart/checkout response when the Checkout Block loads data via hydration
     * (server-side preload). Hydration does not go through rest_post_dispatch.
     *
     * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response
     * @param array                                             $handler
     * @param \WP_REST_Request                                  $request
     * @return \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed
     */
    public static function injectPixFeesIntoHydrationResponse($response, $handler, $request)
    {
        if (!self::shouldShowInTotals() || !$response instanceof \WP_REST_Response) {
            return $response;
        }
        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }
        $modified = self::injectPixFeesIntoResponseData($data);
        if ($modified !== null) {
            $response->set_data($modified);
        }
        return $response;
    }

    private static function shouldShowInTotals(): bool
    {
        if (Params::getPixConfig('enabled') !== 'yes') {
            return false;
        }
        if (Params::getPixConfig('pix_show_discount_in_totals', 'no') !== 'yes') {
            return false;
        }
        $discountConfig = Params::getPixConfig('pix_discount', '0');
        if (!Params::getDiscountType($discountConfig)) {
            return false;
        }
        return true;
    }

    private static function registerCartExtension(): void
    {
        if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => 'cart',
            'namespace'       => self::CART_EXTENSION_NAMESPACE,
            'schema_callback' => [__CLASS__, 'getExtensionSchema'],
            'data_callback'   => [__CLASS__, 'getExtensionData'],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /**
     * Schema for cart extension (for documentation / validation).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getExtensionSchema(): array
    {
        return [
            'discount_label' => [
                'description' => __('Pix discount line label', 'pagbank-connect'),
                'type'        => 'string',
                'readonly'    => true,
            ],
            'discount_value' => [
                'description' => __('Pix discount amount (negative)', 'pagbank-connect'),
                'type'        => 'number',
                'readonly'    => true,
            ],
            'total_no_pix' => [
                'description' => __('Total when paying with Pix', 'pagbank-connect'),
                'type'        => 'number',
                'readonly'    => true,
            ],
        ];
    }

    /**
     * Cart extension data (used by slot/fill if needed).
     *
     * @return array<string, mixed>
     */
    public static function getExtensionData(): array
    {
        $cart = WC()->cart;
        if (!$cart) {
            return [];
        }

        $discountConfig = Params::getPixConfig('pix_discount', '0');
        if (!Params::getDiscountType($discountConfig)) {
            return [];
        }

        $excludesShipping = Params::getPixConfig('pix_discount_excludes_shipping', 'no') === 'yes';
        $cartTotal        = (float) $cart->get_total('edit');
        $shippingTotal    = (float) $cart->get_shipping_total();
        $discount         = Params::getDiscountValueForTotal($discountConfig, $cartTotal, $excludesShipping, $shippingTotal);
        if ($discount <= 0) {
            return [];
        }

        $pixTitle      = Params::getPixConfig('title', __('PIX via PagBank', 'pagbank-connect'));
        $discountLabel = __('Desconto', 'pagbank-connect') . ' ' . $pixTitle;

        return [
            'discount_label' => $discountLabel,
            'discount_value' => -$discount,
            'total_no_pix'   => $cartTotal - $discount,
        ];
    }

    /**
     * Injects two "virtual" fees into the Store API cart response so the Checkout Block
     * order summary displays "Desconto ..." and "Total no Pix" without modifying the real cart.
     *
     * @param \WP_HTTP_Response $response
     * @param \WP_REST_Server   $server
     * @param \WP_REST_Request  $request
     * @return \WP_HTTP_Response
     */
    public static function injectPixFeesIntoCartResponse($response, $server, $request)
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST || !$response instanceof \WP_REST_Response) {
            return $response;
        }
        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        // Detect Store API cart data: direct cart (fees), checkout (__experimentalCart as object or array, or cart), or batch (responses[].body)
        $hasExpCart = isset($data['__experimentalCart']) && (
            (is_array($data['__experimentalCart']) && isset($data['__experimentalCart']['fees']))
            || (is_object($data['__experimentalCart']) && isset($data['__experimentalCart']->fees))
        );
        $hasCartStructure = (isset($data['fees']) && is_array($data['fees']))
            || $hasExpCart
            || (isset($data['cart']) && is_array($data['cart']) && isset($data['cart']['fees']))
            || (isset($data['responses']) && is_array($data['responses']));
        if (!$hasCartStructure) {
            return $response;
        }

        // Batch response: cart is inside each response body (data.responses[].body or body.cart)
        if (isset($data['responses']) && is_array($data['responses'])) {
            foreach ($data['responses'] as $idx => $sub) {
                if (!is_array($sub) || !isset($sub['body'])) {
                    continue;
                }
                $body = $sub['body'];
                if (is_object($body)) {
                    $body = json_decode(json_encode($body), true);
                }
                if (!is_array($body)) {
                    continue;
                }
                $modified = self::injectPixFeesIntoResponseData($body);
                if ($modified !== null) {
                    $data['responses'][$idx]['body'] = $modified;
                }
            }
            $response->set_data($data);
            return $response;
        }

        // Direct response: cart at top level or inside data.cart (checkout endpoint)
        $modified = self::injectPixFeesIntoResponseData($data);
        if ($modified !== null) {
            $response->set_data($modified);
        }
        return $response;
    }

    /**
     * Injects Pix fees into a response that may be cart-only or contain a nested cart (e.g. checkout response).
     * Returns modified array or null if nothing injected.
     *
     * @param array<string, mixed> $data Response data (cart at top level or under 'cart' key).
     * @return array<string, mixed>|null
     */
    private static function injectPixFeesIntoResponseData(array $data): ?array
    {
        // Checkout response embeds cart in __experimentalCart (object or array) or cart
        $cartKey = null;
        if (isset($data['__experimentalCart']) && (is_array($data['__experimentalCart']) || is_object($data['__experimentalCart']))) {
            $cartKey = '__experimentalCart';
        } elseif (isset($data['cart']) && is_array($data['cart'])) {
            $cartKey = 'cart';
        }
        if ($cartKey !== null) {
            $cart = $data[$cartKey];
            $cartArray = is_object($cart) ? json_decode(json_encode($cart), true) : $cart;
            if (!is_array($cartArray)) {
                return null;
            }
            $cartModified = self::injectPixFeesIntoCartData($cartArray);
            if ($cartModified !== null) {
                $data[$cartKey] = $cartModified;
                return $data;
            }
            return null;
        }
        // Direct cart response
        return self::injectPixFeesIntoCartData($data);
    }

    /**
     * Injects Pix discount and Total no Pix into a cart data array (top-level or from batch body).
     * Returns modified array or null if nothing injected.
     *
     * @param array<string, mixed> $data Cart data (must have 'fees' key).
     * @return array<string, mixed>|null
     */
    private static function injectPixFeesIntoCartData(array $data): ?array
    {
        if (!isset($data['fees']) || !is_array($data['fees'])) {
            $data['fees'] = [];
        }

        $cart = WC()->cart;
        if (!$cart) {
            return null;
        }

        $discountConfig = Params::getPixConfig('pix_discount', '0');
        if (!Params::getDiscountType($discountConfig)) {
            return null;
        }

        $excludesShipping = Params::getPixConfig('pix_discount_excludes_shipping', 'no') === 'yes';
        $cartTotal        = (float) $cart->get_total('edit');
        $shippingTotal    = (float) $cart->get_shipping_total();
        $discount         = Params::getDiscountValueForTotal($discountConfig, $cartTotal, $excludesShipping, $shippingTotal);
        if ($discount <= 0) {
            return null;
        }

        $pixTitle      = Params::getPixConfig('title', __('PIX via PagBank', 'pagbank-connect'));
        $discountLabel  = __('Desconto', 'pagbank-connect') . ' ' . $pixTitle;
        $totalNoPix     = $cartTotal - $discount;
        $decimals       = wc_get_price_decimals();
        $minor_unit     = (int) pow(10, $decimals);
        $discountCents  = (int) round(-$discount * $minor_unit);
        $totalNoPixCents = (int) round($totalNoPix * $minor_unit);
        $totals         = isset($data['totals']) && is_array($data['totals']) ? $data['totals'] : [];
        $currencyProps  = self::getTotalsCurrencyProps($totals);

        $ourKeys = ['pagbank-pix-discount', 'pagbank-pix-total'];
        $data['fees'] = array_values(array_filter($data['fees'], function ($fee) use ($ourKeys) {
            $key = is_array($fee) ? ($fee['key'] ?? null) : (is_object($fee) ? ($fee->key ?? null) : null);
            return !in_array($key, $ourKeys, true);
        }));

        $data['fees'][] = [
            'key'    => 'pagbank-pix-discount',
            'name'   => $discountLabel,
            'totals' => (object) array_merge($currencyProps, [
                'total'     => (string) $discountCents,
                'total_tax' => '0',
            ]),
        ];
        $data['fees'][] = [
            'key'    => 'pagbank-pix-total',
            'name'   => __('Total no Pix', 'pagbank-connect'),
            'totals' => (object) array_merge($currencyProps, [
                'total'     => (string) $totalNoPixCents,
                'total_tax' => '0',
            ]),
        ];

        return $data;
    }

    /**
     * Extract currency-related keys from cart totals so fee totals match the same shape.
     *
     * @param array<string, mixed> $totals
     * @return array<string, string>
     */
    private static function getTotalsCurrencyProps(array $totals): array
    {
        $keys = [
            'currency_code',
            'currency_decimal_separator',
            'currency_minor_unit',
            'currency_prefix',
            'currency_suffix',
            'currency_symbol',
            'currency_thousand_separator',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (isset($totals[$key])) {
                $out[$key] = $totals[$key];
            }
        }
        if (empty($out) && function_exists('wc_get_woocommerce_currency')) {
            $out['currency_code']        = \wc_get_woocommerce_currency();
            $out['currency_symbol']      = \get_woocommerce_currency_symbol();
            $out['currency_minor_unit']  = (string) (2 === wc_get_price_decimals() ? 100 : (int) pow(10, wc_get_price_decimals()));
        }
        return $out;
    }
}
