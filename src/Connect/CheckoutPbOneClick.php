<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use RM_PagBank\Partner\Capabilities;
use WC_Order;

/**
 * "Compra com um clique" on the product page → Checkout PB with CALCULATE shipping.
 */
class CheckoutPbOneClick
{
    public static function init(): void
    {
        add_action('woocommerce_after_add_to_cart_button', [__CLASS__, 'renderButton'], 20);
        add_action('wp_ajax_rm_pagbank_checkout_pb_one_click', [__CLASS__, 'handleAjax']);
        add_action('wp_ajax_nopriv_rm_pagbank_checkout_pb_one_click', [__CLASS__, 'handleAjax']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function isEnabled(): bool
    {
        $caps = new Capabilities();
        if (!$caps->hasCheckoutPb()) {
            return false;
        }
        if (!Params::isCheckoutPbEnabled()) {
            return false;
        }
        return Params::getCheckoutPbConfig('checkout_pb_one_click_enabled', 'no') === 'yes';
    }

    public static function enqueue(): void
    {
        if (!is_product() || !self::isEnabled()) {
            return;
        }
        wp_enqueue_script(
            'rm-pagbank-checkout-pb-one-click',
            plugins_url('public/js/checkout-pb-one-click.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            ['jquery'],
            WC_PAGSEGURO_CONNECT_VERSION,
            true
        );
        wp_localize_script('rm-pagbank-checkout-pb-one-click', 'rmPagBankCheckoutPbOneClick', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rm_pagbank_checkout_pb_one_click'),
            'action' => 'rm_pagbank_checkout_pb_one_click',
            'errorGeneric' => __('Não foi possível iniciar o Checkout PB. Tente novamente.', 'pagbank-connect'),
        ]);
    }

    public static function renderButton(): void
    {
        if (!self::isEnabled()) {
            return;
        }
        global $product;
        if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
            return;
        }
        $recHelper = new RecurringHelper();
        if (!$recHelper->productSupportsCheckoutPbRecurrence($product)) {
            return;
        }
        $label = Params::getCheckoutPbConfig(
            'checkout_pb_one_click_label',
            __('Compra com um clique', 'pagbank-connect')
        );
        echo '<p class="rm-pagbank-checkout-pb-one-click-wrap" style="margin-top:0.75em;">';
        echo '<button type="button" class="button alt rm-pagbank-checkout-pb-one-click" data-product-id="'
            . esc_attr((string) $product->get_id()) . '">';
        echo esc_html($label);
        echo '</button></p>';
    }

    public static function handleAjax(): void
    {
        check_ajax_referer('rm_pagbank_checkout_pb_one_click', 'nonce');

        if (!self::isEnabled()) {
            wp_send_json_error(['message' => __('Compra com um clique não está habilitada.', 'pagbank-connect')], 403);
        }

        $productId = absint($_POST['product_id'] ?? 0);
        $variationId = absint($_POST['variation_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $variation = [];
        if (!empty($_POST['variation']) && is_array($_POST['variation'])) {
            foreach ($_POST['variation'] as $k => $v) {
                $variation[sanitize_text_field((string) $k)] = sanitize_text_field((string) $v);
            }
        }

        $product = wc_get_product($variationId ?: $productId);
        if (!$product || !$product->is_purchasable()) {
            wp_send_json_error(['message' => __('Produto inválido.', 'pagbank-connect')], 400);
        }

        $recHelper = new RecurringHelper();
        if (!$recHelper->productSupportsCheckoutPbRecurrence($product)) {
            wp_send_json_error([
                'message' => $recHelper->unsupportedCheckoutPbRecurrenceMessage(),
            ], 400);
        }

        try {
            $order = wc_create_order();
            if (is_wp_error($order) || !$order instanceof WC_Order) {
                throw new Exception(__('Falha ao criar pedido.', 'pagbank-connect'));
            }

            $itemId = $order->add_product($product, $qty, [
                'variation' => $variation,
            ]);
            if (!$itemId) {
                throw new Exception(__('Não foi possível adicionar o produto ao pedido.', 'pagbank-connect'));
            }

            if (is_user_logged_in()) {
                $order->set_customer_id(get_current_user_id());
                $customer = WC()->customer;
                if ($customer) {
                    $order->set_billing_first_name($customer->get_billing_first_name());
                    $order->set_billing_last_name($customer->get_billing_last_name());
                    $order->set_billing_email($customer->get_billing_email());
                    $order->set_billing_phone($customer->get_billing_phone());
                    $order->set_billing_address_1($customer->get_billing_address_1());
                    $order->set_billing_address_2($customer->get_billing_address_2());
                    $order->set_billing_city($customer->get_billing_city());
                    $order->set_billing_state($customer->get_billing_state());
                    $order->set_billing_postcode($customer->get_billing_postcode());
                    $order->set_billing_country($customer->get_billing_country() ?: 'BR');
                }
            }

            $order->set_payment_method('rm-pagbank-checkout-pb');
            $order->set_payment_method_title(
                Params::getCheckoutPbConfig('title', __('Checkout externo', 'pagbank-connect'))
            );
            OrderMeta::set($order, '_checkout_pb_one_click', 'yes', true);
            $order->calculate_totals();
            $order->save();

            $orderId = $order->get_id();
            $gateway = new Standalone\CheckoutPb();
            $result = $gateway->process_payment($orderId);

            if (($result['result'] ?? '') !== 'success') {
                throw new Exception(__('Falha ao criar sessão do Checkout PB.', 'pagbank-connect'));
            }

            // process_payment() uses a fresh WC_Order; reload so redirect_url from process_response is visible.
            $order = wc_get_order($orderId);
            $payUrl = $order instanceof WC_Order ? (string) OrderMeta::get($order, 'redirect_url') : '';
            if ($payUrl === '' && !empty($result['redirect']) && is_string($result['redirect'])) {
                $candidate = $result['redirect'];
                // One-click gateway returns the hosted pay URL; ignore thank-you URLs.
                if (strpos($candidate, '/c/') !== false || strpos($candidate, 'checkout.') !== false) {
                    $payUrl = $candidate;
                }
            }
            if ($payUrl === '') {
                throw new Exception(__('Checkout PB não retornou URL de pagamento.', 'pagbank-connect'));
            }

            wp_send_json_success([
                'redirect' => $payUrl,
                'order_id' => $orderId,
            ]);
        } catch (Exception $e) {
            Functions::log('Checkout PB one-click: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }
}
