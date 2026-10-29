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

    public static function init(): void
    {
        add_filter('woocommerce_get_checkout_url', [__CLASS__, 'filterCheckoutUrl'], 20);
        add_action('woocommerce_api_' . self::API_ACTION, [__CLASS__, 'handleApi']);
        add_action('template_redirect', [__CLASS__, 'maybeInterceptCheckoutPage'], 5);
        add_action('woocommerce_thankyou', [__CLASS__, 'clearWooCheckoutFallback'], 1);
        add_action('woocommerce_cart_emptied', [__CLASS__, 'clearWooCheckoutFallback']);
        // Classic checkout / shortcode.
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'printFallbackNotice'], 5);
        // Blocks checkout does not reliably surface wc_add_notice — inject into the block markup.
        add_filter('render_block_woocommerce/checkout', [__CLASS__, 'prependFallbackNoticeToCheckoutBlock'], 10, 2);
    }

    public static function isDefaultMode(): bool
    {
        $caps = new Capabilities();
        if (!$caps->hasCheckoutPb()) {
            return false;
        }

        return Params::getCheckoutPbMode() === 'default';
    }

    /**
     * When hosted checkout cannot proceed, allow the real Woo /checkout/ once.
     */
    public static function hasWooCheckoutFallback(): bool
    {
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

    private static function enableWooCheckoutFallback(string $message): void
    {
        if (!function_exists('WC') || !WC()->session) {
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
     * Soft-fail: keep the cart and send the buyer to the store checkout with a warning.
     */
    private static function redirectToWooCheckoutWithWarning(string $message, ?WC_Order $orphanOrder = null): void
    {
        if ($orphanOrder instanceof WC_Order) {
            $orphanOrder->update_status(
                'cancelled',
                sprintf(
                    /* translators: %s = Checkout PB payment method title */
                    __('Pedido cancelado: falha ao iniciar %s (fallback para checkout da loja).', 'pagbank-connect'),
                    self::checkoutPbTitle()
                )
            );
        }

        self::enableWooCheckoutFallback($message);
        // Keep classic notices for themes that still render them.
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, 'notice');
        }

        $checkoutUrl = wc_get_page_permalink('checkout');
        if (!$checkoutUrl) {
            $checkoutUrl = wc_get_cart_url();
        }
        wp_safe_redirect($checkoutUrl);
        exit;
    }

    /**
     * Cart / Blocks "Finalizar compra" → our entrypoint instead of /checkout/.
     */
    public static function filterCheckoutUrl(string $url): string
    {
        if (!self::isDefaultMode()) {
            return $url;
        }
        if (is_admin() && !wp_doing_ajax()) {
            return $url;
        }

        return add_query_arg(
            [
                'wc-api' => self::API_ACTION,
                '_wpnonce' => wp_create_nonce(self::API_ACTION),
            ],
            home_url('/')
        );
    }

    /**
     * Direct visits to /checkout/ while mode is default.
     */
    public static function maybeInterceptCheckoutPage(): void
    {
        if (!self::isDefaultMode()) {
            return;
        }
        // After a soft-fail, let the buyer use the real Woo checkout.
        if (self::hasWooCheckoutFallback()) {
            return;
        }
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        if (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received')) {
            return;
        }
        // Avoid loop when already on our API or pay endpoints.
        if (isset($_GET['wc-api']) && (string) $_GET['wc-api'] === self::API_ACTION) {
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

        $order = null;
        try {
            $order = self::createOrderFromCart();
            $orderId = $order->get_id();

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
