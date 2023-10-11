<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Payments\Boleto;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use WC_Admin_Settings;
use WC_Data_Exception;
use WC_Order;
use WC_Payment_Gateway_CC;
use WP_Error;

/**
 * Class Gateway
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Gateway extends WC_Payment_Gateway_CC
{

    public function __construct()
    {
        $this->id = Connect::DOMAIN;
		$this->icon = apply_filters(
			'wc_pagseguro_connect_icon',
			plugins_url('public/images/pagbank.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
		);
        $this->has_fields = true;
        $this->method_title = __('PagBank Connect por Ricardo Martins', Connect::DOMAIN);
		$this->method_description = __(
			'Aceite PIX, Cartão e Boleto de forma transparente com PagBank (PagSeguro).',
			Connect::DOMAIN
		);
        $this->supports = [
            'products',
            'refunds',
            'default_credit_card_form',
//            'tokenization' //TODO: implement tokenization
		];


		$this->title = $this->get_option('title', __('PagBank (PagSeguro UOL)', Connect::DOMAIN));
		$this->description = $this->get_option('description');
		$this->init_settings();

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'addThankyouInstructions']);
        add_action('wp_enqueue_scripts', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'addPaymentInfoAdmin'], 10, 1);
    }

    public function init_settings(){
        $fields = [];
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/general-fields.php';
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/boleto-fields.php';
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/pix-fields.php';
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/cc-fields.php';
        $this->form_fields = array_merge(...$fields);

		parent::init_settings();

		switch ($this->get_option('title_display')) {
			case 'text_only':
				$this->icon = '';
				break;
			case 'logo_only':
				$this->title = '';
				break;
		}
	}

    /**
     * Updates a transaction from the order's json information
     *
     * @param $order WC_Order
     * @param $order_data array
     *
     * @return void
     */
    protected static function updateTransaction(WC_Order $order, array $order_data): void
    {
        $charge = $order_data['charges'][0] ?? [];
        $status = $charge['status'] ?? '';
        $payment_response = $charge['payment_response'] ?? null;
        $charge_id = $charge['id'] ?? null;

        $order->add_meta_data('pagbank_charge_id', $charge_id, true);
        $order->add_meta_data('pagbank_payment_response', $payment_response, true);
        $order->add_meta_data('pagbank_status', $status, true);

        do_action('pagbank_status_changed_to_' . strtolower($status), $order, $order_data);

		// Add some additional information about the payment
		if ($charge['payment_response']) {
			$order->add_order_note(
				'PagBank: Payment Response: '.sprintf(
					'%d: %s %s %s',
					$charge['payment_response']['code'] ?? 'N/A',
					$charge['payment_response']['message'] ?? 'N/A',
					isset($charge['payment_response']['reference']) 
                        ? ' - REF/NSU: '.$charge['payment_response']['reference'] 
						: '',
					($status) ? "(Status: $status)" : ''
				)
			);
		}

        switch ($status) {
            case 'AUTHORIZED': // Pre-Authorized but not captured yet
                $order->add_order_note(
                    'PagBank: Pagamento pré-autorizado (não capturado). Charge ID: '.$charge_id,
                );
                $order->update_status(
                    'on-hold',
                    'PagBank: Pagamento pré-autorizado (não capturado). Charge ID: '.$charge_id
                );
                break;
            case 'PAID': // Paid and captured
                //stocks are reduced at this point
                $order->payment_complete($charge_id);
				$order->add_order_note('PagBank: Pagamento aprovado e capturado. Charge ID: ' . $charge_id);
                break;
            case 'IN_ANALYSIS': // Paid with Credit Card, and PagBank is analyzing the risk of the transaction
                $order->update_status('on-hold', 'PagBank: Pagamento em análise.');
                break;
            case 'DECLINED': // Declined by PagBank or by the card issuer
                $order->update_status('failed', 'PagBank: Pagamento recusado.');
                $order->add_order_note(
                    'PagBank: Pagamento recusado. <br/>Charge ID: '.$charge_id,
                );
                break;
            case 'CANCELED':
                $order->update_status('cancelled', 'PagBank: Pagamento cancelado.');
                $order->add_order_note(
                    'PagBank: Pagamento cancelado. <br/>Charge ID: '.$charge_id,
                );
                break;
            default:
                $order->delete_meta_data('pagbank_status');
        }
    }


    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

//        wp_enqueue_script( 'pagseguro-admin', plugins_url( 'public/js/admin/admin' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), WC_PAGSEGURO_VERSION, true );

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Returns a table with the fields for the admin settings page in the specified $section
     * @param $section (general, pix, cc, or boleto)
     *
     * @return string|void
     */
    public function get_admin_fields($section){
        $available_sections = array('general', 'pix', 'cc', 'boleto');
        if (!in_array($section, $available_sections)) {
            return;
        }

        $fields = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/' . $section . '-fields.php';
        $form_fields = apply_filters(
            'woocommerce_settings_api_form_fields_'.$this->id,
            array_map(array($this, 'set_defaults'), $fields)
        );
        return '<table class="form-table">'.$this->generate_settings_html($form_fields, false)
            .'</table>'; // WPCS: XSS ok.
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
	 * Checks if the inputed discount value is a valid fixed or % discount value (used both in boleto and pix)
	 * @param $value
	 *
	 * @return float|int|string
	 */
	public function validateDiscountValue($value)
    {
        if (empty($value)){
            return $value;
        }

        //remove spaces
        $value = str_replace(' ', '', $value);
        //replace comma with dot
        $value = str_replace(',', '.', $value);

        if (strpos($value, '%')){
            $value = str_replace('%', '', $value);

            if (!is_numeric($value) || $value < 0 || $value > 100) {
                WC_Admin_Settings::add_error(__('O desconto deve ser um número positivo ou percentual de 0 a 100.', Connect::DOMAIN));
                return '';
            }
            return $value . '%';
        }

        if (!is_numeric($value) || $value < 0 ) {
            WC_Admin_Settings::add_error(__('O desconto deve ser um número positivo ou percentual de 0 a 100', Connect::DOMAIN));
            return '';
        }
        return $value;
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
        return $this->validateDiscountValue($value);
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
        return $this->validateDiscountValue($value);
    }

    /**
     * @inheritDoc
     */
    public function form() {
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payment-form.php';
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
	 * Add css files for checkout and success page
	 * @return void
	 */
	public function addStyles(){
        //thank you page
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received'))) {
            wp_enqueue_style(
                'pagseguro-connect-pix',
                plugins_url('public/css/success.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        if ( is_checkout() ) {
            wp_enqueue_style(
                'pagseguro-connect-checkout',
                plugins_url('public/css/checkout.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }
    }

	/**
	 * Add js files for checkout and success page
	 * @return void
	 */
    public function addScripts(){
		//thank you page
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received'))) {
            wp_enqueue_script(
                'pagseguro-connect',
                plugins_url('public/js/success.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        if ( is_checkout() ) {
            wp_enqueue_script(
                'pagseguro-connect-checkout',
                plugins_url('public/js/checkout.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                ['jquery'],
                true,
                true
            );
			wp_add_inline_script(
				'pagseguro-connect-checkout',
				'const rm_pagbank_nonce = "' . wp_create_nonce('rm_pagbank_nonce') . '";',
				'before'
			);

            if ( $this->get_option('cc_enabled')) {
                wp_enqueue_script(
                    'pagseguro-connect-creditcard',
                    plugins_url('public/js/creditcard.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                    ['jquery', 'jquery-payment'],
                    true,
                    true
                );
                wp_localize_script(
                    'pagseguro-connect-creditcard',
                    'ajax_object',
                    ['ajax_url' => admin_url('admin-ajax.php')]
                );
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    'var pagseguro_connect_public_key = \''.$this->get_option('public_key').'\';',
                    'before'
                );
                wp_enqueue_script('pagseguro-checkout-sdk',
                    'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
                    [],
                    false,
                    true
                );
            }

        }
    }

	/**
	 * Add css file to admin
	 * @return void
	 */
	public function addAdminStyles(){
        //admin pages
        if (!is_admin())
            return;

        wp_enqueue_style(
            'pagseguro-connect-admin-css',
            plugins_url('public/css/ps-connect-admin.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );

    }

	/**
	 * Add js file to admin, only in the plugin settings page
	 * @return void
	 */
	public function addAdminScripts(){
        if(!is_admin())
            return;

        global $current_section; //only when ?section=rm-pagbank (plugin config page)
        if ($current_section == Connect::DOMAIN)
            wp_enqueue_script(
                'pagseguro-connect-admin',
                plugins_url('public/js/admin/ps-connect-admin.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
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
        $payment_method = filter_input(INPUT_POST, 'ps_connect_method', FILTER_SANITIZE_STRING);

        // region Add note if customer changed payment method
        if ($order->get_meta('pagbank_payment_method')) {
            $current_method = $payment_method == 'cc' ? 'credit_card' : $payment_method;
            $old_method = $order->get_meta('pagbank_payment_method');
            if (strcasecmp($current_method, $old_method) !== 0) {
                $order->add_order_note(
                    'PagBank: Cliente alterou o método de pagamento de ' . $old_method . ' para ' . $current_method
                );
            }
        }
        // endregion

        switch ($payment_method) {
            case 'boleto':
                $method = new Boleto($order);
                $params = $method->prepare();
                break;
            case 'pix':
                $method = new Payments\Pix($order);
                $params = $method->prepare();
                break;
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
                    filter_input(INPUT_POST, 'rm-pagbank-card-encrypted', FILTER_SANITIZE_STRING),
                    true
                );
                $order->add_meta_data(
                    '_pagbank_card_holder_name',
                    filter_input(INPUT_POST, 'rm-pagbank-card-holder-name', FILTER_SANITIZE_STRING),
                    true
                );
                $method = new CreditCard($order);
                $params = $method->prepare();
                break;
            default:
                wc_add_wp_error_notices(new WP_Error('invalid_payment_method', __('Método de pagamento inválido')));
                return array(
                    'result' => 'fail',
                    'redirect' => ''
                );
        }

        $order->add_meta_data('pagbank_payment_method', $method->code, true);


        try {
            $api = new Api();
            $resp = $api->post('ws/orders', $params);

            if (isset($resp['error_messages'])) {
                throw new \RM_PagBank\Connect\Exception($resp['error_messages'], 40000);
            }

        } catch (Exception $e) {
            wc_add_wp_error_notices(new WP_Error('api_error', $e->getMessage()));
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
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
                    'redirect' => ''
                ];
            }
        }
        // endregion

        // some notes to customer (replace true with false to make it private)
        $order->add_order_note( 'Pedido criado com sucesso!', true );

        $woocommerce->cart->empty_cart();
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

	/**
	 * Add the instructions to the thankyou page for boleto and pix
	 * @param $order_id
	 *
	 * @return void
	 */
	public function addThankyouInstructions($order_id)
    {
        $order = wc_get_order($order_id);
        switch ($order->get_meta('pagbank_payment_method')) {
            case 'boleto':
                    $method = new Boleto($order);
                    break;
                case 'pix':
                    $method = new Payments\Pix($order);
                    break;
        }
        if (!empty($method)) {
            $method->getThankyouInstructions($order_id);
        }
    }

	/**
	 * Get the default installments for the credit card payment method using VISA as the default BIN
	 * @return array
	 */
	public function getDefaultInstallments(): array
	{
        return Params::getInstallments(WC()->cart->get_total('edit'), '411111');
    }

    public static function notification()
    {
        $body = file_get_contents('php://input');
        $hash = filter_input(INPUT_GET, 'hash', FILTER_SANITIZE_STRING);

        Functions::log('Notification received: ' . $body, 'debug', ['hash' => $hash]);

        // Decode body
        $order_data = json_decode($body, true);
        if ($order_data === null)
            wp_die('Falha ao decodificar o Json', 400);

        // Check presence of id and reference
        $id = $order_data['id'] ?? null;
        $reference = $order_data['reference_id'] ?? null;
        if (!$id || !$reference)
            wp_die('ID ou Reference não informados', 400);

        // Sanitize $reference and $id
        $reference = filter_var($reference, FILTER_SANITIZE_STRING);

        // Validate hash
        $order = wc_get_order($reference);
        if (!$order)
            wp_die('Pedido não encontrado', 404);

        if ($hash != Api::getOrderHash($order))
            wp_die('Hash inválido', 403);

        if (!isset($order_data['charges']))
            wp_die('Charges não informado. Notificação ignorada.', 200);

        try{
            self::updateTransaction($order, $order_data);
        }catch (Exception $e){
            Functions::log('Error updating transaction: ' . $e->getMessage(), 'error', ['order_id' => $order->get_id()]);
            wp_die('Erro ao atualizar transação', 500);
        }

        wp_die('OK', 200);
    }

	/**
	 * Adds order info to the admin order page by including the order info template
	 *
	 * @param $order
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection*/
	public function addPaymentInfoAdmin($order)
    {
        include_once WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/order-info.php';
    }

}
