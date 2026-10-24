<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
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

/** Standalone Pix */
class Boleto extends WC_Payment_Gateway
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
        $this->code = 'boleto';
        $this->id = Connect::DOMAIN . '-' . $this->code;
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds'
        ];
        $this->icon = plugins_url('public/images/boleto.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE);
        $this->method_title = $this->get_option(
            'title',
            __('Boleto', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Boleto (por Ricardo Martins)',
            'pagbank-connect'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Boleto', 'pagbank-connect'));
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN . '-boleto', [$this, 'addThankyouInstructions']);
        // Fallback hook for checkout legado where payment_method might not match gateway ID
        add_action('woocommerce_thankyou', [$this, 'maybeAddThankyouInstructions'], 5);
        add_action('woocommerce_email_after_order_table', [$this, 'addPaymentDetailsToEmail'], 10, 4);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/boleto-fields.php';

        $detector = \RM_PagBank\Partner\PartnerDetector::fromSettings();
        if (!$detector->isVindi()) {
            return;
        }

        $vindiPortalUrl = 'https://portal.vindi.com.br/pt-br/settings/sales';
        $notice = sprintf(
            /* translators: %s = URL to Vindi Portal sales settings */
            __(
                'As linhas de instrução e logo podem ser configuradas '
                . '<a href="%s" target="_blank" rel="noopener noreferrer">aqui</a> '
                . '(Portal Vindi).',
                'pagbank-connect'
            ),
            esc_url($vindiPortalUrl)
        );

        if (isset($this->form_fields['boleto_line_1'])) {
            $this->form_fields['boleto_line_1'] = [
                'title' => __('Instruções do boleto', 'pagbank-connect'),
                'type' => 'title',
                'description' => $notice,
            ];
        }
        unset($this->form_fields['boleto_line_2']);

        if (isset($this->form_fields['boleto_expiry_days'])) {
            $this->form_fields['boleto_expiry_days']['description'] = sprintf(
                /* translators: %s = URL to Vindi Portal sales settings */
                __(
                    'dias<br/><em>Obs.: o valor acima é apenas visual. A real validade do boleto é configurada '
                    . '<a href="%s" target="_blank" rel="noopener noreferrer">aqui</a> (Portal Vindi).</em>',
                    'pagbank-connect'
                ),
                esc_url($vindiPortalUrl)
            );
        }
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
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
        $payment_method = 'boleto';
        if(isset($_POST['payment_method'])){
            $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');
        }

        // region Add note if customer changed payment method
        $this->handleCustomerChangeMethod($order, $payment_method);
        // endregion

        $order->add_meta_data(
            '_rm_pagbank_checkout_blocks',
            wc_bool_to_string(isset($_POST['wc-rm-pagbank-boleto-new-payment-method'])),
            true
        );

        if(isset($_POST['rm-pagbank-customer-document'])) {
            $order->add_meta_data(
                '_rm_pagbank_customer_document',
                TaxId::sanitizeForApi(wp_unslash($_POST['rm-pagbank-customer-document'])),
                true
            );
        }

        $method = new \RM_PagBank\Connect\Payments\Boleto($order);
        $params = $method->prepare();

        $resp = $this->makeRequest($order, $params, $method);

        $method->process_response($order, $resp);
        self::updateTransaction($order, $resp);

        $this->maybeSendNewOrderEmail($order, $resp);

        // some notes to customer (or keep them private if order is pending)
        $shouldNotify = $order->get_status('edit') !== 'pending';
        $order->add_order_note(\RM_PagBank\Partner\Branding::notePrefix($order) . ': Pedido criado com sucesso!', $shouldNotify);


        if (isset($woocommerce->cart) && $woocommerce->cart) {
            $woocommerce->cart->empty_cart();
        }
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Validates Boleto discount field
     *
     * @param $key
     * @param $value
     *
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @return float|int|string
     */
    public function validate_boleto_discount_field($key, $value){
        return Functions::validateDiscountValue($value);
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

        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/boleto.php';
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
     * Fallback method to display Boleto instructions when payment_method doesn't match gateway ID
     * This is needed for legacy checkout where the payment_method might not be set correctly
     * 
     * IMPORTANT: This hook only processes Boleto orders. It checks:
     * 1. If the order has pagbank_payment_method meta = 'boleto'
     * 2. If the payment_method doesn't match the gateway ID (meaning specific hook didn't fire)
     * 3. Only then it displays the instructions
     * 
     * This ensures it won't interfere with other payment methods or gateways.
     * 
     * @param int $order_id
     * @return void
     */
    public function maybeAddThankyouInstructions($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $payment_method_meta = OrderMeta::get($order, 'payment_method');
        if ($payment_method_meta !== 'boleto') {
            return; // Not a Boleto order, exit immediately
        }
        
        $payment_method = $order->get_payment_method();
        if ($payment_method === Connect::DOMAIN . '-boleto') {
            return; // Already processed by specific hook woocommerce_thankyou_rm-pagbank-boleto
        }
        
        $this->addThankyouInstructions($order_id);
    }

    public function addPaymentDetailsToEmail($order, $sent_to_admin, $plain_text, $email) {
        if ($order && $order->is_paid()) {
            return;
        }
        $emailIds = ['customer_invoice', 'new_order', 'customer_processing_order'];
        if (OrderMeta::get($order, 'payment_method') === 'boleto' && in_array($email->id, $emailIds)) {
            $boletoBarcode = OrderMeta::get($order, 'boleto_barcode_formatted');
            $boletoPdfLink = OrderMeta::get($order, 'boleto_pdf');
            $boletoDueDate = OrderMeta::get($order, 'boleto_due_date');
            $boletoDueDate = $boletoDueDate ? Functions::formatDate($boletoDueDate) : '';

            ob_start();
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/emails/boleto-payment-details.php';
            $output = ob_get_clean();
            echo $output;
        }
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return bool|WP_Error True or false based on success, or a WP_Error object.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return Api::refund($order_id, $amount);
    }

    /**
     * Send new order email with invoice and payment details
     *
     * @param $order
     * @param $resp
     * @return void
     */
    public function maybeSendNewOrderEmail($order, $resp) {
        $this->sendNewOrder($order);
        $shouldNotify = wc_string_to_bool(Params::getBoletoConfig('boleto_send_new_order_email', 'yes'));

        if (!$shouldNotify) {
            return;
        }

        $this->sendOrderInvoiceEmail($order);
    }
}