<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use RM_PagBank\Helpers\TaxId;
use RM_PagBank\Traits\OrderInvoiceEmail;
use RM_PagBank\Traits\PaymentMethodIcon;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Payment_Gateway;
use WC_Data_Exception;
use WP_Error;
use RM_PagBank\Helpers\OrderMeta;

/** Standalone Checkout PB (Vindi hosted checkout) */
class CheckoutPb extends WC_Payment_Gateway
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use PaymentMethodIcon;
    use ThankyouInstructions;
    use OrderInvoiceEmail;

    public string $code = '';

    public function __construct()
    {
        $this->code = 'checkout_pb';
        // WooCommerce gateway ids use hyphens; payment meta keeps checkout_pb.
        $this->id = Connect::DOMAIN . '-checkout-pb';
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
        ];
        // Sem ícone PagBank — Checkout PB é marca PB Integrações.
        $this->icon = '';
        $this->method_title = $this->get_option(
            'title',
            __('Checkout externo', 'pagbank-connect')
        );
        $this->method_description = __(
            'Checkout hospedado da PB Integrações. O comprador conclui o pagamento em checkout.pbintegracoes.com.',
            'pagbank-connect'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Checkout externo', 'pagbank-connect'));
        $this->description = $this->get_option('description');
        $this->syncEnabledFromMode();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfUnsupportedRecurrence'], 15, 1);
        add_action('woocommerce_before_checkout_form', [$this, 'maybeNoticeUnsupportedRecurrence'], 9);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'addThankyouInstructions']);
        add_action('woocommerce_email_after_order_table', [$this, 'addPaymentDetailsToEmail'], 10, 4);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);
    }

    /**
     * Keep Woo `enabled` in sync with checkout_pb_mode (yes/no only).
     */
    public function syncEnabledFromMode(): void
    {
        $mode = Params::getCheckoutPbMode();
        $this->enabled = $mode === 'disabled' ? 'no' : 'yes';
    }

    public function process_admin_options()
    {
        $result = parent::process_admin_options();
        $mode = $this->get_option('checkout_pb_mode', 'disabled');
        if (!in_array($mode, ['disabled', 'default', 'payment_method'], true)) {
            $mode = 'disabled';
            $this->update_option('checkout_pb_mode', $mode);
        }
        $this->update_option('enabled', $mode === 'disabled' ? 'no' : 'yes');
        $this->enabled = $mode === 'disabled' ? 'no' : 'yes';

        return $result;
    }

    /**
     * Only offer as a Woo payment method when mode is payment_method.
     */
    public function is_available()
    {
        if (Params::getCheckoutPbMode() !== 'payment_method') {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Hide Checkout PB when cart/order has PagBank recurring that cannot map to API recurrence.
     *
     * @param array<string, \WC_Payment_Gateway> $gateways
     * @return array<string, \WC_Payment_Gateway>
     */
    public function disableIfUnsupportedRecurrence($gateways)
    {
        if (!is_array($gateways) || !isset($gateways[$this->id])) {
            return $gateways;
        }
        $recHelper = new RecurringHelper();
        if ($recHelper->cartHasUnsupportedCheckoutPbRecurrence()) {
            unset($gateways[$this->id]);
            return $gateways;
        }
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
            global $wp;
            $orderId = absint($wp->query_vars['order-pay'] ?? 0);
            $order = $orderId ? wc_get_order($orderId) : false;
            if ($order && $recHelper->orderHasUnsupportedCheckoutPbRecurrence($order)) {
                unset($gateways[$this->id]);
            }
        }
        return $gateways;
    }

    public function maybeNoticeUnsupportedRecurrence(): void
    {
        if ($this->enabled !== 'yes') {
            return;
        }
        $recHelper = new RecurringHelper();
        if (!$recHelper->cartHasUnsupportedCheckoutPbRecurrence()) {
            return;
        }
        wc_print_notice($recHelper->unsupportedCheckoutPbRecurrenceMessage(), 'notice');
    }

    /**
     * Sem ícone no checkout (não usar logo PagBank).
     */
    public function get_icon()
    {
        return '';
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/checkout-pb-fields.php';

        $caps = new \RM_PagBank\Partner\Capabilities();
        if ($caps->hasCheckoutPb()) {
            // Prefer migrated mode (legacy enabled=yes → payment_method) as select default.
            $this->form_fields['checkout_pb_mode']['default'] = Params::getCheckoutPbMode();
            return;
        }

        $this->form_fields = [
            'pagbank_unavailable' => [
                'title' => __('Checkout PB', 'pagbank-connect'),
                'type' => 'title',
                'description' => __(
                    'Disponível apenas com Connect Key Vindi (<code>CONVD…</code>). Com chave PagBank, use o meio “Pagar no PagBank”.',
                    'pagbank-connect'
                ),
            ],
        ];
        if (Params::isCheckoutPbEnabled()) {
            $this->update_option('checkout_pb_mode', 'disabled');
            $this->update_option('enabled', 'no');
            $this->enabled = 'no';
        }
    }

    public function admin_options()
    {
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/html-settings-page.php';
    }

    /**
     * Server-rendered preview button (does not depend on JS injecting into the save bar).
     *
     * @param string $key
     * @param array  $data
     */
    public function generate_checkout_pb_preview_html($key, $data): string
    {
        $fieldKey = $this->get_field_key($key);
        $ajaxAction = \RM_PagBank\Connect\CheckoutPbPreview::AJAX_ACTION;
        $label = __('Pré-visualizar', 'pagbank-connect');
        $error = __('Não foi possível gerar a pré-visualização.', 'pagbank-connect');

        ob_start();
        ?>
        <tr valign="top" class="rm-pagbank-checkout-pb-preview-row">
            <td colspan="2" class="forminp rm-pagbank-checkout-pb-preview-wrap">
                <button
                    type="button"
                    class="button button-secondary rm-pagbank-checkout-pb-preview"
                    id="rm-pagbank-checkout-pb-preview-section"
                    data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                    data-action="<?php echo esc_attr($ajaxAction); ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce($ajaxAction)); ?>"
                    data-label="<?php echo esc_attr($label); ?>"
                    data-error="<?php echo esc_attr($error); ?>"
                >
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <span class="rm-pagbank-checkout-pb-preview__label"><?php echo esc_html($label); ?></span>
                </button>
                <input type="hidden" id="<?php echo esc_attr($fieldKey); ?>" value="" />
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @param int $order_id
     * @return array
     * @throws WC_Data_Exception|\RM_PagBank\Connect\Exception|\Exception
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;
        $order = wc_get_order($order_id);

        $payment_method = 'checkout_pb';
        if (isset($_POST['payment_method'])) {
            $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');
        }

        $this->handleCustomerChangeMethod($order, $payment_method);

        $order->add_meta_data(
            '_rm_pagbank_checkout_blocks',
            wc_bool_to_string(isset($_POST['wc-rm-pagbank-checkout-pb-new-payment-method'])),
            true
        );

        if (isset($_POST['rm-pagbank-customer-document'])) {
            $order->add_meta_data(
                '_rm_pagbank_customer_document',
                TaxId::sanitizeForApi(wp_unslash($_POST['rm-pagbank-customer-document'])),
                true
            );
        }

        $method = new \RM_PagBank\Connect\Payments\CheckoutPb($order);
        $oneClick = OrderMeta::get($order, '_checkout_pb_one_click') === 'yes';
        $method->setOneClick($oneClick);
        $params = $method->prepare();

        $resp = $this->makeRequest($order, $params, $method);

        $method->process_response($order, $resp);
        self::updateTransaction($order, $resp);

        $this->maybeSendNewOrderEmail($order, $resp);

        $shouldNotify = $order->get_status('edit') !== 'pending';
        $order->add_order_note(
            \RM_PagBank\Partner\Branding::notePrefix($order) . ': Pedido criado com sucesso (Checkout PB)!',
            $shouldNotify
        );

        $woocommerce->cart->empty_cart();
        $payUrl = (string) OrderMeta::get($order, 'redirect_url');
        return [
            'result' => 'success',
            // One-click AJAX redirects straight to hosted checkout; store checkout keeps thank-you URL
            // (filter getOrderReceivedURL swaps it to redirect_url).
            'redirect' => ($oneClick && $payUrl !== '') ? $payUrl : $this->get_return_url($order),
        ];
    }

    public function payment_fields()
    {
        $this->form();
    }

    public function form()
    {
        if ($this->paymentUnavailable()) {
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/unavailable.php';
            return;
        }

        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/checkout-pb.php';
    }

    public function validate_fields(): bool
    {
        return true;
    }

    public function addPaymentDetailsToEmail($order, $sent_to_admin, $plain_text, $email)
    {
        if ($order && $order->is_paid()) {
            return;
        }
        $emailIds = ['customer_invoice', 'new_order', 'customer_processing_order'];
        $isCheckoutPbPending = OrderMeta::get($order, 'payment_method') === 'checkout_pb'
            || (string) OrderMeta::get($order, '_hosted_checkout_origin') === 'checkout_pb';
        if ($isCheckoutPbPending && in_array($email->id, $emailIds, true)) {
            $redirectLink = OrderMeta::get($order, 'redirect_url');
            if (empty($redirectLink)) {
                return;
            }
            $checkoutExpires = OrderMeta::get($order, 'redirect_expiration');
            $checkoutExpires = $checkoutExpires ? Functions::formatDate($checkoutExpires) : '';
            $checkoutTitle = (string) Params::getCheckoutPbConfig(
                'title',
                __('Checkout externo', 'pagbank-connect')
            );
            if ($checkoutTitle === '') {
                $checkoutTitle = __('Checkout externo', 'pagbank-connect');
            }
            ob_start();
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/emails/checkout-pb-payment-details.php';
            echo ob_get_clean();
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return Api::refund($order_id, $amount);
    }

    public function maybeSendNewOrderEmail($order, $resp)
    {
        $this->sendNewOrder($order);
        $shouldNotify = wc_string_to_bool(Params::getCheckoutPbConfig('checkout_pb_send_new_order_email', 'yes'));
        if (!$shouldNotify) {
            return;
        }
        $this->sendOrderInvoiceEmail($order);
    }

    public static function getOrderReceivedURL($order_received_url, $order)
    {
        if ($order->get_payment_method() === Connect::DOMAIN . '-checkout-pb'
            && $order->get_status() == 'pending'
            && !empty(OrderMeta::get($order, 'redirect_url'))) {
            $order_received_url = OrderMeta::get($order, 'redirect_url');
        }
        return $order_received_url;
    }

    public static function changePaymentLink($pay_url, $order)
    {
        if ($order->get_payment_method() === Connect::DOMAIN . '-checkout-pb') {
            $pay_url = OrderMeta::get($order, 'redirect_url') ?: $pay_url;
        }
        return $pay_url;
    }
}
