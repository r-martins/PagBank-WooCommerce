<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Connect\Payments\CreditCardTrial;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Payment_Gateway_CC;
use Exception;
use WC_Admin_Settings;
use WC_Data_Exception;
use WC_Order;
use WP_Error;

/** Standalone Credit Card */
class CreditCard extends WC_Payment_Gateway_CC
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use ThankyouInstructions;

    public function __construct()
    {
        $this->id = Connect::DOMAIN . '-cc';
        $this->icon = apply_filters(
            'wc_pagseguro_connect_icon',
            plugins_url('public/images/payment-icon.php?method=cc', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );
        $this->method_title = $this->get_option(
            'title',
            __('Cartão de Crédito via PagBank', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Cartão de Crédito via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );
        $this->title = $this->get_option('title', __('Cartão de Crédito via PagBank', 'pagbank-connect'));
        $this->description = $this->get_option('description');


        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
            'default_credit_card_form',
//            'tokenization' //TODO: implement tokenization
        ];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/cc-fields.php';
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Validates the inputed connect key and save additional information like public key and sandbox mode
     *
     * @param $key
     * @param $connect_key
     *
     * @return mixed|string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate_connect_key_field($key, $connect_key)
    {
        $api = new Api();
        $api->setConnectKey($connect_key);

        try {
            $ret = $api->post('ws/public-keys', ['type' => 'card']);
            if (isset($ret['public_key'])) {
                $this->update_option('public_key', $ret['public_key']);
                $this->update_option('public_key_created_at', $ret['created_at']);
                $isSandbox = strpos($connect_key, 'CONSANDBOX') !== false;
                $this->update_option('is_sandbox', $isSandbox);
            }

            if (isset($ret['error_messages'])){
                //implode error_messages showing code and description
                $error_messages = array_map(function($error){
                    return $error['code'] . ' - ' . $error['description'];
                }, $ret['error_messages']);
                WC_Admin_Settings::add_error(implode('<br/>', $error_messages));
                $connect_key = '';
            }
        } catch (Exception $e) {
            WC_Admin_Settings::add_error($e->getMessage());
            $connect_key = '';
        }

        return $connect_key;

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
     * @inheritDoc
     */
    public function form() {
        if ($this->paymentUnavailable()) {
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/unavailable.php';
            return;
        }

        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/creditcard.php';
    }

    /**
     * Process Payment.
     *
     * @param int $order_id Order ID.
     *
     * @return array
     * @throws WC_Data_Exception
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;
        $order = wc_get_order( $order_id );

        //sanitize $_POST['ps_connect_method']
        $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');

        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $isCartRecurring = $recurringHelper->isCartRecurring();

        $payment_method = str_replace('rm-pagbank-', '', $payment_method);
        if ($isCartRecurring) {
            $payment_method = 'cc'; //@TODO change when supporting other methods for recurring orders
        }

        if ($isCartRecurring) {
            $order->add_meta_data('_pagbank_recurring_initial', true);
        }

        // region Add note if customer changed payment method
        $this->handleCustomerChangeMethod($order, $payment_method);
        // endregion

        $recurringTrialPeriod = $recurringHelper->getCartRecurringTrial();
        if ($recurringTrialPeriod) {
            $order->add_meta_data('_pagbank_recurring_trial_length', $recurringTrialPeriod);
        }

        if ($recurringTrialPeriod && $order->get_total() == 0) {
            $payment_method = $payment_method . '_trial';
        }

        switch ($payment_method) {
            case 'cc':
                $order->add_meta_data(
                    'pagbank_card_installments',
                    filter_input(INPUT_POST, 'rm-pagbank-card-installments', FILTER_SANITIZE_NUMBER_INT),
                    true
                );
                $order->add_meta_data(
                    'pagbank_card_last4',
                    substr(filter_input(INPUT_POST, 'rm-pagbank-card-number', FILTER_SANITIZE_NUMBER_INT), -4),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_first_digits',
                    substr(filter_input(INPUT_POST, 'rm-pagbank-card-number', FILTER_SANITIZE_NUMBER_INT), 0, 6),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_encrypted',
                    htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_holder_name',
                    htmlspecialchars($_POST['rm-pagbank-card-holder-name'], ENT_QUOTES, 'UTF-8'),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_3ds_id',
                    isset($_POST['rm-pagbank-card-3d'])
                        ? htmlspecialchars($_POST['rm-pagbank-card-3d'], ENT_QUOTES, 'UTF-8')
                        : false,
                );
                $method = new \RM_PagBank\Connect\Payments\CreditCard($order);
                $params = $method->prepare();
                break;
            case 'cc_trial':
                $order->add_meta_data(
                    '_pagbank_card_encrypted',
                    htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'),
                    true
                );
                $method = new \RM_PagBank\Connect\Payments\CreditCardTrial($order);
                $params = $method->prepare();
                break;
            default:
                wc_add_wp_error_notices(
                    new WP_Error('invalid_payment_method', __('Método de pagamento inválido', 'pagbank-connect'))
                );
                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
        }

        $resp = $this->makeRequest($order, $params, $method);

        $method->process_response($order, $resp);
        self::updateTransaction($order, $resp);

        $charge = $resp['charges'][0] ?? false;

        // region Immediately decline if payment method is credit card and charge was declined
        if ($payment_method == 'cc' && $charge !== false) {
            if ($charge['status'] == 'DECLINED'){
                $additional_error = '';
                if(isset($charge['payment_response']))
                    $additional_error .= $charge['payment_response']['message'] . ' ('
                        . $charge['payment_response']['code'] . ')';

                wc_add_wp_error_notices(new WP_Error('api_error', 'Pagamento Recusado. ' . $additional_error));
                return [
                    'result' => 'fail',
                    'redirect' => '',
                ];
            }
        }
        // endregion

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
     * Get the default installments for the credit card payment method using VISA as the default BIN
     * @return array
     */
    public function getDefaultInstallments(): array
    {
        $total = Api::getOrderTotal();

        return Params::getInstallments($total, '555566');
    }

    public function field_name( $name ) {
        return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( Connect::DOMAIN . '-' . $name ) . '" ';
    }

    /**
     * Add css files for checkout and success page
     * @return void
     */
    public static function addStyles($styles){
        //thank you page
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received'))) {
            $styles['pagseguro-connect-pix'] = [
                'src'     => plugins_url('public/css/success.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }
        if ( is_checkout() ) {
            $styles['pagseguro-connect-checkout'] = [
                'src'     => plugins_url('public/css/checkout.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }

        return $styles;
    }

    /**
     * Add js files for checkout and success page
     * @return void
     */
    public function addScripts() {

        // If the method has already been called, return early
        if (self::$addedScripts) {
            return;
        }

        //thank you page
        $alreadyEnqueued = wp_script_is('pagseguro-connect');
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received')) && !$alreadyEnqueued) {
            wp_enqueue_script(
                'pagseguro-connect',
                plugins_url('public/js/success.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        if ( is_checkout() && !is_order_received_page() ) {
            $alreadyEnqueued = wp_script_is('pagseguro-connect-checkout');
            if (!$alreadyEnqueued) {
                wp_enqueue_script(
                    'pagseguro-connect-checkout',
                    plugins_url('public/js/checkout.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                    ['jquery'],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    true
                );
            }

            wp_add_inline_script(
                'pagseguro-connect-checkout',
                'const rm_pagbank_nonce = "' . wp_create_nonce('rm_pagbank_nonce') . '";',
                'before'
            );

            $api = new Api();
            if ( $this->get_option('enabled') == 'yes') {
                wp_enqueue_script(
                    'pagseguro-connect-creditcard',
                    plugins_url('public/js/creditcard.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                    ['jquery', 'jquery-payment'],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    true
                );
                wp_localize_script(
                    'pagseguro-connect-creditcard',
                    'ajax_object',
                    ['ajax_url' => admin_url('admin-ajax.php')]
                );
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    'var pagseguro_connect_public_key = \''.Params::getConfig('public_key').'\';',
                    'before'
                );
                if ( $this->get_option('cc_3ds') === 'yes') {
                    $threeDSession = $api->get3DSession();
                    wp_add_inline_script(
                        'pagseguro-connect-creditcard',
                        'var pagseguro_connect_3d_session = \''.$threeDSession.'\';',
                        'before'
                    );
                    wp_add_inline_script(
                        'pagseguro-connect-creditcard',
                        'var pagseguro_connect_cc_3ds_allow_continue = \''.Params::getCcConfig('cc_3ds_allow_continue', 'no').'\';',
                        'before'
                    );
                    // add user notice
                    if ($threeDSession === '' && Params::getCcConfig('cc_3ds_allow_continue', 'no') === 'no') {
                        wc_add_notice(__('Erro ao obter a sessão 3D Secure PagBank. Pagamento com cartão de crédito foi '
                            .'desativado. Por favor recarregue a página.', 'pagbank-connect'), 'error');
                    }
                }
                $environment = $api->getIsSandbox() ? 'SANDBOX' : 'PROD';
                wp_add_inline_script(
                    'pagseguro-connect-checkout',
                    "const pagseguro_connect_environment = '$environment';",
                    'before'
                );
                wp_enqueue_script('pagseguro-checkout-sdk',
                    'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
                    [],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    true
                );
            }
            self::$addedScripts = true;
        }
    }
}