<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use RM_PagBank\Partner\Capabilities;
use WC_Order;

/**
 * When Checkout PB mode is "default", "Finalizar compra" / checkout URL
 * creates a Woo order from the cart and sends the buyer to the hosted checkout.
 */
class CheckoutPbDefaultCheckout
{
    public const API_ACTION = 'rm_pagbank_checkout_pb_default';
    public const SESSION_FALLBACK = 'rm_pagbank_checkout_pb_fallback';
    public const SESSION_FALLBACK_MSG = 'rm_pagbank_checkout_pb_fallback_msg';

    /** Soft-fail redirect must read the real /checkout/ permalink. */
    private static bool $bypassCheckoutPageLinkFilter = false;

    /** Prevent page_link ↔ permalink recursion. */
    private static bool $rewritingCheckoutLink = false;

    private static ?bool $defaultModeCache = null;

    private static ?int $checkoutPageIdCache = null;

    private static ?string $entrypointUrlCache = null;

    public static function init(): void
    {
        add_filter('woocommerce_get_checkout_url', [__CLASS__, 'filterCheckoutUrl'], 20);
        // WP menus / get_permalink(checkout) skip woocommerce_get_checkout_url.
        add_filter('page_link', [__CLASS__, 'filterCheckoutPageLink'], 20, 2);
        add_filter('wp_nav_menu_objects', [__CLASS__, 'filterCheckoutNavMenuObjects'], 20);
        // Thank-you / pay URLs must never inherit ?wc-api= from our entrypoint rewrite.
        add_filter('woocommerce_get_checkout_order_received_url', [__CLASS__, 'filterOrderReceivedUrl'], 5, 2);
        add_filter('woocommerce_get_checkout_payment_url', [__CLASS__, 'filterOrderPayUrl'], 5, 2);
        add_action('woocommerce_api_' . self::API_ACTION, [__CLASS__, 'handleApi']);
        add_action('template_redirect', [__CLASS__, 'maybeClearFallbackOnCart'], 4);
        add_action('template_redirect', [__CLASS__, 'maybeInterceptCheckoutPage'], 5);
        add_action('woocommerce_thankyou', [__CLASS__, 'clearWooCheckoutFallback'], 1);
        add_action('woocommerce_cart_emptied', [__CLASS__, 'clearWooCheckoutFallback']);
        // Cart changes (after fixing product dims / qty) should allow a fresh hosted attempt.
        add_action('woocommerce_add_to_cart', [__CLASS__, 'clearWooCheckoutFallback'], 5);
        add_action('woocommerce_cart_item_removed', [__CLASS__, 'clearWooCheckoutFallback'], 5);
        add_action('woocommerce_after_cart_item_quantity_update', [__CLASS__, 'clearWooCheckoutFallback'], 5);
        // Classic checkout / shortcode.
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'printFallbackNotice'], 5);
        // Blocks checkout does not reliably surface wc_add_notice — inject into the block markup.
        add_filter('render_block_woocommerce/checkout', [__CLASS__, 'prependFallbackNoticeToCheckoutBlock'], 10, 2);
    }

    public static function isDefaultMode(): bool
    {
        if (self::$defaultModeCache !== null) {
            return self::$defaultModeCache;
        }

        $caps = new Capabilities();
        self::$defaultModeCache = $caps->hasCheckoutPb() && Params::getCheckoutPbMode() === 'default';

        return self::$defaultModeCache;
    }

    private static function checkoutPageId(): int
    {
        if (self::$checkoutPageIdCache !== null) {
            return self::$checkoutPageIdCache;
        }
        self::$checkoutPageIdCache = function_exists('wc_get_page_id') ? (int) wc_get_page_id('checkout') : 0;

        return self::$checkoutPageIdCache;
    }

    /**
     * Official WC checkout page, or any page embedding the checkout shortcode/block
     * (stores often keep a leftover "Finalizar compra" page in the menu).
     *
     * @var array<int, bool>
     */
    private static array $checkoutLikePageIdCache = [];

    private static function isCheckoutLikePageId(int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }
        if ($pageId === self::checkoutPageId()) {
            return true;
        }
        if (array_key_exists($pageId, self::$checkoutLikePageIdCache)) {
            return self::$checkoutLikePageIdCache[$pageId];
        }

        $post = get_post($pageId);
        if (!$post || $post->post_type !== 'page') {
            return self::$checkoutLikePageIdCache[$pageId] = false;
        }

        $content = (string) $post->post_content;
        $like = has_shortcode($content, 'woocommerce_checkout')
            || (function_exists('has_block') && has_block('woocommerce/checkout', $post));

        return self::$checkoutLikePageIdCache[$pageId] = $like;
    }

    private static function isCurrentCheckoutLikeRequest(): bool
    {
        if (function_exists('is_wc_endpoint_url')
            && (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received'))) {
            return false;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        if (!function_exists('is_page') || !is_page()) {
            return false;
        }

        return self::isCheckoutLikePageId((int) get_queried_object_id());
    }

    private static function shouldRewriteCheckoutLinks(): bool
    {
        if (!self::isDefaultMode()) {
            return false;
        }
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }
        if (self::$bypassCheckoutPageLinkFilter) {
            return false;
        }
        // Soft-fail still rewrites "Finalizar compra" → entrypoint so a second click retries hosted.
        // Loop prevention is only in maybeInterceptCheckoutPage (landing on /checkout/ after soft-fail).
        if (function_exists('is_wc_endpoint_url')
            && (is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay'))) {
            return false;
        }

        return true;
    }

    /**
     * True when the URL is (or will be) order-received / order-pay — never rewrite those.
     */
    private static function linkLooksLikeCheckoutEndpoint(string $url): bool
    {
        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '');
        if (preg_match('#/(order-received|order-pay)(/|$)#', $path)) {
            return true;
        }
        $query = (string) (wp_parse_url($url, PHP_URL_QUERY) ?? '');
        if ($query !== ''
            && (str_contains($query, 'order-received') || str_contains($query, 'order-pay'))) {
            return true;
        }

        return false;
    }

    /**
     * Build a clean checkout-base URL without our ?wc-api= entrypoint pollution.
     */
    private static function realCheckoutBaseUrl(): string
    {
        self::$bypassCheckoutPageLinkFilter = true;
        remove_filter('woocommerce_get_checkout_url', [__CLASS__, 'filterCheckoutUrl'], 20);
        remove_filter('page_link', [__CLASS__, 'filterCheckoutPageLink'], 20);
        $base = wc_get_checkout_url();
        add_filter('woocommerce_get_checkout_url', [__CLASS__, 'filterCheckoutUrl'], 20);
        add_filter('page_link', [__CLASS__, 'filterCheckoutPageLink'], 20, 2);
        self::$bypassCheckoutPageLinkFilter = false;

        return $base ?: home_url('/');
    }

    /**
     * @param string        $url
     * @param WC_Order|null $order
     */
    public static function filterOrderReceivedUrl($url, $order): string
    {
        $url = (string) $url;
        if (!self::isDefaultMode() || !$order instanceof WC_Order) {
            return $url;
        }
        // Only rebuild when polluted by our hosted entrypoint (?wc-api=…).
        if (strpos($url, 'wc-api=' . self::API_ACTION) === false) {
            return $url;
        }

        $clean = wc_get_endpoint_url('order-received', (string) $order->get_id(), self::realCheckoutBaseUrl());

        return add_query_arg('key', $order->get_order_key(), $clean);
    }

    /**
     * @param string        $url
     * @param WC_Order|null $order
     */
    public static function filterOrderPayUrl($url, $order): string
    {
        $url = (string) $url;
        if (!self::isDefaultMode() || !$order instanceof WC_Order) {
            return $url;
        }
        if (strpos($url, 'wc-api=' . self::API_ACTION) === false) {
            return $url;
        }

        $clean = wc_get_endpoint_url('order-pay', (string) $order->get_id(), self::realCheckoutBaseUrl());
        return add_query_arg('key', $order->get_order_key(), $clean);
    }

    /**
     * Soft-fail banner/flag only applies while Checkout PB mode is still "default".
     * Stale session leftovers (e.g. after merchant disables Checkout PB) must not stick.
     */
    private static function softFailContextActive(): bool
    {
        return self::isDefaultMode();
    }

    /**
     * Drop soft-fail session when hosted default mode is no longer in effect.
     */
    private static function discardStaleWooCheckoutFallback(): void
    {
        if (self::softFailContextActive()) {
            return;
        }
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        if (WC()->session->get(self::SESSION_FALLBACK) !== 'yes'
            && !WC()->session->get(self::SESSION_FALLBACK_MSG)) {
            return;
        }
        self::clearWooCheckoutFallback();
        if (method_exists(WC()->session, 'save_data')) {
            WC()->session->save_data();
        }
    }

    /**
     * When hosted checkout cannot proceed, allow the real Woo /checkout/ once.
     */
    public static function hasWooCheckoutFallback(): bool
    {
        self::discardStaleWooCheckoutFallback();
        if (!self::softFailContextActive()) {
            return false;
        }

        return function_exists('WC')
            && WC()->session
            && WC()->session->get(self::SESSION_FALLBACK) === 'yes';
    }

    public static function clearWooCheckoutFallback(): void
    {
        if (function_exists('WC') && WC()->session) {
            WC()->session->set(self::SESSION_FALLBACK, null);
            WC()->session->set(self::SESSION_FALLBACK_MSG, null);
        }
    }

    /**
     * Visiting the cart releases soft-fail so "Finalizar compra" can retry hosted checkout
     * (e.g. after fixing Envio Fácil boxes or product dimensions in admin).
     */
    public static function maybeClearFallbackOnCart(): void
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        if (WC()->session->get(self::SESSION_FALLBACK) !== 'yes'
            && !WC()->session->get(self::SESSION_FALLBACK_MSG)) {
            return;
        }
        self::clearWooCheckoutFallback();
        if (method_exists(WC()->session, 'save_data')) {
            WC()->session->save_data();
        }
    }

    private static function enableWooCheckoutFallback(string $message): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        if (!self::softFailContextActive()) {
            return;
        }
        WC()->session->set(self::SESSION_FALLBACK, 'yes');
        WC()->session->set(self::SESSION_FALLBACK_MSG, $message);
        // Persist before exit/redirect — otherwise Blocks checkout can load with an empty session.
        if (method_exists(WC()->session, 'save_data')) {
            WC()->session->save_data();
        }
    }

    private static function getFallbackMessage(): string
    {
        self::discardStaleWooCheckoutFallback();
        if (!self::softFailContextActive()) {
            return '';
        }
        if (!function_exists('WC') || !WC()->session) {
            return '';
        }
        $message = WC()->session->get(self::SESSION_FALLBACK_MSG);
        return is_string($message) ? trim($message) : '';
    }

    /**
     * Classic checkout (shortcode / hook).
     */
    public static function printFallbackNotice(): void
    {
        $message = self::getFallbackMessage();
        if ($message === '') {
            return;
        }
        echo '<div class="woocommerce-info rm-pagbank-checkout-pb-fallback-notice" role="status">'
            . esc_html($message)
            . '</div>';
    }

    /**
     * Blocks checkout: prepend a visible info banner (wc_add_notice is often invisible here).
     *
     * @param string $content
     * @param array  $block
     */
    public static function prependFallbackNoticeToCheckoutBlock($content, $block): string
    {
        $message = self::getFallbackMessage();
        if ($message === '') {
            return (string) $content;
        }

        $banner = sprintf(
            '<div class="woocommerce-info wc-block-components-notice-banner is-info rm-pagbank-checkout-pb-fallback-notice" role="status" style="margin:0 0 1rem;">'
            . '<div class="wc-block-components-notice-banner__content">%s</div>'
            . '</div>',
            esc_html($message)
        );

        return $banner . (string) $content;
    }

    private static function checkoutPbTitle(): string
    {
        $title = (string) Params::getCheckoutPbConfig(
            'title',
            __('Checkout externo', 'pagbank-connect')
        );

        return $title !== '' ? $title : __('Checkout externo', 'pagbank-connect');
    }

    /**
     * Soft-fail: restore cart from the orphan order (if any), trash it, then Woo checkout.
     */
    private static function redirectToWooCheckoutWithWarning(string $message, ?WC_Order $orphanOrder = null): void
    {
        $restored = 0;
        $orphanOrderId = null;
        if ($orphanOrder instanceof WC_Order) {
            $orphanOrderId = $orphanOrder->get_id();
            // Cart may already be empty (session) or about to be — rebuild from the Woo order
            // before trashing so the shopper can finish on the store checkout.
            $restored = self::restoreCartFromOrder($orphanOrder);
            $orphanOrder->add_order_note(
                sprintf(
                    /* translators: %s = Checkout PB payment method title */
                    __('Pedido movido para a lixeira: falha ao iniciar %s (fallback para checkout da loja).', 'pagbank-connect'),
                    self::checkoutPbTitle()
                )
            );
            // false = trash (not force-delete). Avoids cancelled clutter on every soft-fail retry.
            $orphanOrder->delete(false);
        }

        Functions::log('Checkout PB default: soft-fail → Woo checkout: ' . $message, 'warning', [
            'orphan_order_id' => $orphanOrderId,
            'cart_items_restored' => $restored,
        ]);

        // Persist once in session; classic/blocks banners read SESSION_FALLBACK_MSG.
        // Do not also wc_add_notice() — that duplicates the message on first paint (refresh shows one).
        self::enableWooCheckoutFallback($message);

        // Must not go through page_link → API URL (soft-fail loop).
        self::$bypassCheckoutPageLinkFilter = true;
        $checkoutUrl = wc_get_page_permalink('checkout');
        self::$bypassCheckoutPageLinkFilter = false;
        if (!$checkoutUrl) {
            $checkoutUrl = wc_get_cart_url();
        }
        // If restore failed and cart is empty, cart URL is clearer than an empty checkout bounce.
        if ((!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) && function_exists('wc_get_cart_url')) {
            $checkoutUrl = wc_get_cart_url();
        }
        wp_safe_redirect($checkoutUrl);
        exit;
    }

    /**
     * Rebuild the session cart (+ customer addresses/coupons) from a Checkout PB orphan order.
     *
     * @return int Number of line items successfully added to the cart.
     */
    private static function restoreCartFromOrder(WC_Order $order): int
    {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }

        $cart = WC()->cart;
        $cart->empty_cart();

        $added = 0;
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $productId = (int) $item->get_product_id();
            $variationId = (int) $item->get_variation_id();
            $qty = max(1, (int) $item->get_quantity());
            if ($productId <= 0) {
                continue;
            }

            $variation = [];
            if ($variationId > 0) {
                $variationProduct = wc_get_product($variationId);
                if ($variationProduct && $variationProduct->is_type('variation')) {
                    $variation = $variationProduct->get_variation_attributes();
                }
            }

            $cartItemKey = $cart->add_to_cart(
                $productId,
                $qty,
                $variationId,
                $variation
            );
            if ($cartItemKey) {
                $added++;
            }
        }

        foreach ($order->get_coupon_codes() as $code) {
            $code = (string) $code;
            if ($code !== '' && !$cart->has_discount($code)) {
                $cart->apply_coupon($code);
            }
        }

        self::hydrateCustomerSessionFromOrder($order);

        if ($added > 0) {
            $cart->calculate_totals();
        }

        return $added;
    }

    /**
     * Copy billing/shipping from the orphan order into WC()->customer for checkout forms.
     */
    private static function hydrateCustomerSessionFromOrder(WC_Order $order): void
    {
        $customer = WC()->customer;
        if (!$customer) {
            return;
        }

        $billing = [
            'set_billing_first_name' => 'get_billing_first_name',
            'set_billing_last_name' => 'get_billing_last_name',
            'set_billing_email' => 'get_billing_email',
            'set_billing_phone' => 'get_billing_phone',
            'set_billing_company' => 'get_billing_company',
            'set_billing_address_1' => 'get_billing_address_1',
            'set_billing_address_2' => 'get_billing_address_2',
            'set_billing_city' => 'get_billing_city',
            'set_billing_state' => 'get_billing_state',
            'set_billing_postcode' => 'get_billing_postcode',
            'set_billing_country' => 'get_billing_country',
        ];
        $shipping = [
            'set_shipping_first_name' => 'get_shipping_first_name',
            'set_shipping_last_name' => 'get_shipping_last_name',
            'set_shipping_company' => 'get_shipping_company',
            'set_shipping_address_1' => 'get_shipping_address_1',
            'set_shipping_address_2' => 'get_shipping_address_2',
            'set_shipping_city' => 'get_shipping_city',
            'set_shipping_state' => 'get_shipping_state',
            'set_shipping_postcode' => 'get_shipping_postcode',
            'set_shipping_country' => 'get_shipping_country',
        ];

        foreach ($billing + $shipping as $setter => $getter) {
            if (!method_exists($order, $getter) || !method_exists($customer, $setter)) {
                continue;
            }
            $value = (string) $order->{$getter}();
            if ($value !== '') {
                $customer->{$setter}($value);
            }
        }

        if (method_exists($customer, 'save')) {
            $customer->save();
        }
    }

    /**
     * Cart / Blocks "Finalizar compra" → our entrypoint instead of /checkout/.
     */
    public static function filterCheckoutUrl(string $url): string
    {
        if (self::linkLooksLikeCheckoutEndpoint($url)) {
            return $url;
        }
        if (!self::shouldRewriteCheckoutLinks()) {
            return $url;
        }

        return self::hostedCheckoutEntrypointUrl();
    }

    /**
     * Appearance → Menus and get_permalink(checkout page) use page_link.
     *
     * @param string     $link
     * @param int|string $postId
     */
    public static function filterCheckoutPageLink($link, $postId): string
    {
        $link = (string) $link;
        if (self::$rewritingCheckoutLink || self::$bypassCheckoutPageLinkFilter) {
            return $link;
        }
        if (self::linkLooksLikeCheckoutEndpoint($link)) {
            return $link;
        }
        if (!self::isCheckoutLikePageId((int) $postId)) {
            return $link;
        }
        if (!self::shouldRewriteCheckoutLinks()) {
            return $link;
        }

        self::$rewritingCheckoutLink = true;
        try {
            return self::hostedCheckoutEntrypointUrl();
        } finally {
            self::$rewritingCheckoutLink = false;
        }
    }

    /**
     * Extra safety for nav menus that keep a stale absolute /checkout/ URL.
     *
     * @param array<int, \WP_Post> $items
     * @return array<int, \WP_Post>
     */
    public static function filterCheckoutNavMenuObjects($items): array
    {
        if (!is_array($items) || !self::shouldRewriteCheckoutLinks()) {
            return is_array($items) ? $items : [];
        }

        $entrypoint = self::hostedCheckoutEntrypointUrl();

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }
            $objectId = isset($item->object_id) ? (int) $item->object_id : 0;
            // Astra page-list fallback uses page_item-{ID} with object_id often unset — parse URL path later.
            if ($objectId > 0 && self::isCheckoutLikePageId($objectId)) {
                $item->url = $entrypoint;
                continue;
            }
            $url = isset($item->url) ? (string) $item->url : '';
            if ($url === '') {
                continue;
            }
            // Match leftover pages by path (e.g. /finalizar-compra/) when object_id is missing.
            $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '');
            $path = trim($path, '/');
            if ($path === '') {
                continue;
            }
            $page = get_page_by_path($path);
            if ($page && self::isCheckoutLikePageId((int) $page->ID)) {
                $item->url = $entrypoint;
            }
        }

        return $items;
    }

    private static function hostedCheckoutEntrypointUrl(): string
    {
        if (self::$entrypointUrlCache !== null) {
            return self::$entrypointUrlCache;
        }

        self::$entrypointUrlCache = add_query_arg(
            [
                'wc-api' => self::API_ACTION,
                '_wpnonce' => wp_create_nonce(self::API_ACTION),
            ],
            home_url('/')
        );

        return self::$entrypointUrlCache;
    }

    /**
     * Direct visits to /checkout/ (and leftover checkout-like pages) while mode is default.
     */
    public static function maybeInterceptCheckoutPage(): void
    {
        if (!self::isDefaultMode()) {
            return;
        }
        // Avoid loop when already on our API or pay endpoints.
        if (isset($_GET['wc-api']) && (string) $_GET['wc-api'] === self::API_ACTION) {
            return;
        }
        if (!self::isCurrentCheckoutLikeRequest()) {
            return;
        }

        // Soft-fail: shopper is on Woo checkout — never start another hosted session.
        if (self::hasWooCheckoutFallback()) {
            return;
        }

        self::startFromCartAndRedirect(false);
    }

    public static function handleApi(): void
    {
        if (!self::isDefaultMode()) {
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::API_ACTION)) {
            wc_add_notice(__('Link de checkout inválido ou expirado. Tente novamente.', 'pagbank-connect'), 'error');
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        // Intentional "Finalizar compra" (menu/cart) after soft-fail → clear flag and retry hosted.
        if (self::hasWooCheckoutFallback()) {
            self::clearWooCheckoutFallback();
            if (function_exists('WC') && WC()->session && method_exists(WC()->session, 'save_data')) {
                WC()->session->save_data();
            }
        }

        self::startFromCartAndRedirect(true);
    }

    /**
     * @param bool $fromApi True when coming from filtered checkout URL (nonce already checked).
     */
    private static function startFromCartAndRedirect(bool $fromApi): void
    {
        if (!function_exists('WC') || !WC()->cart) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        if (WC()->cart->is_empty()) {
            wc_add_notice(__('Seu carrinho está vazio.', 'pagbank-connect'), 'notice');
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        $recHelper = new RecurringHelper();
        if ($recHelper->cartHasUnsupportedCheckoutPbRecurrence()) {
            self::redirectToWooCheckoutWithWarning($recHelper->unsupportedCheckoutPbRecurrenceMessage());
        }

        Functions::log('Checkout PB default: creating hosted session from cart', 'info', [
            'from_api' => $fromApi,
            'cart_count' => (int) WC()->cart->get_cart_contents_count(),
            'cart_total' => (string) WC()->cart->get_total('edit'),
        ]);

        $order = null;
        try {
            $order = self::createOrderFromCart();
            $orderId = $order->get_id();

            Functions::log('Checkout PB default: Woo order created, calling process_payment', 'info', [
                'order_id' => $orderId,
            ]);

            $gateway = new Standalone\CheckoutPb();
            $result = $gateway->process_payment($orderId);

            if (($result['result'] ?? '') !== 'success') {
                throw new Exception(
                    sprintf(
                        /* translators: %s = Checkout PB payment method title */
                        __('Falha ao criar sessão do %s.', 'pagbank-connect'),
                        self::checkoutPbTitle()
                    )
                );
            }

            $order = wc_get_order($orderId);
            $payUrl = $order instanceof WC_Order ? (string) OrderMeta::get($order, 'redirect_url') : '';
            if ($payUrl === '' && !empty($result['redirect']) && is_string($result['redirect'])) {
                $candidate = $result['redirect'];
                if (strpos($candidate, '/c/') !== false || strpos($candidate, 'checkout.') !== false) {
                    $payUrl = $candidate;
                }
            }
            if ($payUrl === '' || !self::isAllowedHostedCheckoutUrl($payUrl)) {
                throw new Exception(
                    sprintf(
                        /* translators: %s = Checkout PB payment method title */
                        __('%s não retornou URL de pagamento.', 'pagbank-connect'),
                        self::checkoutPbTitle()
                    )
                );
            }

            self::clearWooCheckoutFallback();

            // process_payment already empties the cart on success.
            // wp_safe_redirect() blocks external hosts (checkout.pbintegracoes.com) and
            // falls back to wp-admin when the merchant is logged in — use wp_redirect.
            wp_redirect($payUrl);
            exit;
        } catch (Exception $e) {
            Functions::log('Checkout PB default checkout: ' . $e->getMessage(), 'error', [
                'from_api' => $fromApi,
            ]);
            $warning = sprintf(
                /* translators: 1: Checkout PB title, 2: error detail */
                __('Não foi possível abrir o %1$s (%2$s). Continue o pedido no checkout da loja.', 'pagbank-connect'),
                self::checkoutPbTitle(),
                $e->getMessage()
            );
            self::redirectToWooCheckoutWithWarning(
                $warning,
                $order instanceof WC_Order ? $order : null
            );
        }
    }

    /**
     * @throws Exception
     */
    private static function createOrderFromCart(): WC_Order
    {
        $cart = WC()->cart;
        $order = wc_create_order([
            'customer_id' => is_user_logged_in() ? get_current_user_id() : 0,
        ]);
        if (is_wp_error($order) || !$order instanceof WC_Order) {
            throw new Exception(__('Falha ao criar pedido.', 'pagbank-connect'));
        }

        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (!$product || !is_object($product)) {
                continue;
            }
            $qty = max(1, (int) ($cartItem['quantity'] ?? 1));
            $args = [];
            if (!empty($cartItem['variation']) && is_array($cartItem['variation'])) {
                $args['variation'] = $cartItem['variation'];
            }
            if (!empty($cartItem['variation_id'])) {
                $args['variation_id'] = (int) $cartItem['variation_id'];
            }
            $itemId = $order->add_product($product, $qty, $args);
            if (!$itemId) {
                throw new Exception(__('Não foi possível adicionar um produto do carrinho ao pedido.', 'pagbank-connect'));
            }
        }

        if ($order->get_item_count() < 1) {
            throw new Exception(__('Carrinho sem produtos válidos.', 'pagbank-connect'));
        }

        foreach ($cart->get_applied_coupons() as $code) {
            $order->apply_coupon($code);
        }

        self::hydrateCustomerFromSession($order);

        $order->set_payment_method('rm-pagbank-checkout-pb');
        $order->set_payment_method_title(
            (string) Params::getCheckoutPbConfig('title', __('Checkout externo', 'pagbank-connect'))
        );
        OrderMeta::set($order, 'payment_method', 'checkout_pb', true);
        OrderMeta::set($order, '_hosted_checkout_origin', 'checkout_pb', true);
        $order->calculate_totals();
        $order->save();

        return $order;
    }

    /**
     * Hosted Checkout PB pay URL (https only).
     */
    private static function isAllowedHostedCheckoutUrl(string $url): bool
    {
        if ($url === '' || !preg_match('#^https://#i', $url)) {
            return false;
        }
        $host = (string) (wp_parse_url($url, PHP_URL_HOST) ?? '');
        if ($host === '') {
            return false;
        }
        $allowed = apply_filters('rm_pagbank_checkout_pb_allowed_redirect_hosts', [
            'checkout.pbintegracoes.com',
            'checkout.pb.local',
            'localhost',
        ]);
        foreach ((array) $allowed as $allowedHost) {
            $allowedHost = strtolower((string) $allowedHost);
            $hostLower = strtolower($host);
            if ($hostLower === $allowedHost || str_ends_with($hostLower, '.' . $allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private static function hydrateCustomerFromSession(WC_Order $order): void
    {
        $customer = WC()->customer;
        if (!$customer) {
            return;
        }

        $map = [
            'set_billing_first_name' => 'get_billing_first_name',
            'set_billing_last_name' => 'get_billing_last_name',
            'set_billing_email' => 'get_billing_email',
            'set_billing_phone' => 'get_billing_phone',
            'set_billing_company' => 'get_billing_company',
            'set_billing_address_1' => 'get_billing_address_1',
            'set_billing_address_2' => 'get_billing_address_2',
            'set_billing_city' => 'get_billing_city',
            'set_billing_state' => 'get_billing_state',
            'set_billing_postcode' => 'get_billing_postcode',
            'set_billing_country' => 'get_billing_country',
            'set_shipping_first_name' => 'get_shipping_first_name',
            'set_shipping_last_name' => 'get_shipping_last_name',
            'set_shipping_company' => 'get_shipping_company',
            'set_shipping_address_1' => 'get_shipping_address_1',
            'set_shipping_address_2' => 'get_shipping_address_2',
            'set_shipping_city' => 'get_shipping_city',
            'set_shipping_state' => 'get_shipping_state',
            'set_shipping_postcode' => 'get_shipping_postcode',
            'set_shipping_country' => 'get_shipping_country',
        ];
        foreach ($map as $setter => $getter) {
            if (!method_exists($customer, $getter) || !method_exists($order, $setter)) {
                continue;
            }
            $value = (string) $customer->{$getter}();
            if ($value !== '') {
                $order->{$setter}($value);
            }
        }
        if ($order->get_billing_country() === '') {
            $order->set_billing_country('BR');
        }

        // CPF/CNPJ from customer meta when Brazilian fields plugin / account data exists.
        if (is_user_logged_in()) {
            $userId = get_current_user_id();
            $doc = (string) get_user_meta($userId, 'billing_cpf', true);
            if ($doc === '') {
                $doc = (string) get_user_meta($userId, 'billing_cnpj', true);
            }
            $doc = preg_replace('/\D+/', '', $doc) ?? '';
            if ($doc !== '') {
                $order->update_meta_data('_rm_pagbank_customer_document', $doc);
                if (strlen($doc) <= 11) {
                    $order->update_meta_data('_billing_cpf', $doc);
                } else {
                    $order->update_meta_data('_billing_cnpj', $doc);
                }
            }
        }
    }
}
