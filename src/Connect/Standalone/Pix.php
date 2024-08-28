<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Payment_Gateway;
use WC_Data_Exception;

/** Standalone Pix */
class Pix extends WC_Payment_Gateway
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use ThankyouInstructions;

    public function __construct()
    {
        $this->id = Connect::DOMAIN . '-pix';
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds'
        ];
        $this->icon = apply_filters(
            'wc_pagseguro_connect_icon',
            plugins_url('public/images/payment-icon.php?method=pix', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );
        $this->method_title = $this->get_option(
            'title',
            __('Pix via PagBank', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Pix via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Pix via PagBank', 'pagbank-connect'));
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);
        add_action('wp_ajax_pagbank_dismiss_pix_order_keys_notice', [$this, 'dismissPixOrderKeysNotice']);
        add_action('woocommerce_email_after_order_table', [$this, 'addPaymentDetailsToEmail'], 10, 4);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/pix-fields.php';
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Validates PIX discount field
     *
     * @param $key
     * @param $value
     *
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @return float|int|string
     */
    public function validate_pix_discount_field($key, $value){
        return Functions::validateDiscountValue($value);
    }

    /**
     * Validate frontend fields
     *
     * @return bool
     */
    public function validate_fields():bool
    {
        return true; //@TODO validate_fields
    }

    /**
     * Process Payment.
     *
     * @param int $order_id Order ID.
     *
     * @return array
     * @throws WC_Data_Exception|\RM_PagBank\Connect\Exception
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;
        $order = wc_get_order( $order_id );

        //sanitize $_POST['ps_connect_method']
        $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');

        // region Add note if customer changed payment method
        $this->handleCustomerChangeMethod($order, $payment_method);
        // endregion

        $method = new \RM_PagBank\Connect\Payments\Pix($order);
        $params = $method->prepare();

        $resp = $this->makeRequest($order, $params, $method);

        $method->process_response($order, $resp);
        self::updateTransaction($order, $resp);

        // some notes to customer (or keep them private if order is pending)
        $shouldNotify = $order->get_status('edit') !== 'pending';
        $order->add_order_note('PagBank: Pedido criado com sucesso!', $shouldNotify);

        // sends the new order email
        if ($shouldNotify) {
            $newOrderEmail = WC()->mailer()->emails['WC_Email_New_Order'];
            $newOrderEmail->trigger($order->get_id());
        }

        $woocommerce->cart->empty_cart();
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Builds our payment fields area
     */
    public function payment_fields() {
        $this->form();
    }

    /**
     * @inheritDoc
     */
    public function form() {
        if ($this->paymentUnavailable()) {
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/unavailable.php';
            return;
        }

        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/pix.php';
    }

    public static function dismissPixOrderKeysNotice() {
        // Get the current user ID
        $userId = get_current_user_id();

        // Set the user meta value
        update_user_meta($userId, 'pagbank_dismiss_pix_order_keys_notice', true);
    }

    public function addPaymentDetailsToEmail($order, $sent_to_admin, $plain_text, $email) {
        $emailIds = ['customer_invoice', 'new_order', 'customer_processing_order'];
        if ($order->get_meta('pagbank_payment_method') === 'pix' && in_array($email->id, $emailIds)) {
            $pixQrCode = $order->get_meta('pagbank_pix_qrcode');
            $pixQrCodeExpiration = $order->get_meta('pagbank_pix_qrcode_expiration');
            $pixQrCodeExpiration = $pixQrCodeExpiration ? Functions::formatDate($pixQrCodeExpiration) : '';
            $pixQrCodeText = $order->get_meta('pagbank_pix_qrcode_text');

            ob_start();
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/emails/pix-payment-details.php';
            $output = ob_get_clean();
            echo $output;
        }
    }
}