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
class Boleto extends WC_Payment_Gateway
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use ThankyouInstructions;

    public function __construct()
    {
        $this->id = Connect::DOMAIN . '-boleto';
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds'
        ];
        $this->icon = apply_filters(
            'wc_pagseguro_connect_icon',
            plugins_url('public/images/payment-icon.php?method=boleto', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );
        $this->method_title = $this->get_option(
            'title',
            __('Boleto via PagBank', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Boleto via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Boleto via PagBank', 'pagbank-connect'));
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);
        add_action('woocommerce_email_after_order_table', [$this, 'addPaymentDetailsToEmail'], 10, 4);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/boleto-fields.php';
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
        $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');

        // region Add note if customer changed payment method
        $this->handleCustomerChangeMethod($order, $payment_method);
        // endregion

        $method = new \RM_PagBank\Connect\Payments\Boleto($order);
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

    public function addPaymentDetailsToEmail($order, $sent_to_admin, $plain_text, $email) {
        $emailIds = ['customer_invoice', 'new_order', 'customer_processing_order'];
        if ($order->get_meta('pagbank_payment_method') === 'boleto' && in_array($email->id, $emailIds)) {
            $boletoBarcode = $order->get_meta('pagbank_boleto_barcode_formatted');
            $boletoPdfLink = $order->get_meta('pagbank_boleto_pdf');
            $boletoDueDate = $order->get_meta('pagbank_boleto_due_date');
            $boletoDueDate = $boletoDueDate ? Functions::formatDate($boletoDueDate) : '';

            ob_start();
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/emails/boleto-payment-details.php';
            $output = ob_get_clean();
            echo $output;
        }
    }
}