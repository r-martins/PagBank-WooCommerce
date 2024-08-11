<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Payments\Boleto;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Connect\Payments\CreditCardTrial;
use RM_PagBank\Connect\Standalone\Pix as StandalonePix;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\ThankyouInstructions;
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
    use ProcessPayment;
    use ThankyouInstructions;

    /**
     * @var true
     */
    private static $addedScripts = false;

    public function __construct()
    {
        $this->id = Connect::DOMAIN;
		$this->icon = apply_filters(
			'wc_pagseguro_connect_icon',
			plugins_url('public/images/pagbank.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
		);
        $this->method_title = __('PagBank Connect por Ricardo Martins', 'pagbank-connect');
		$this->method_description = __(
			'Aceite PIX, Cartão e Boleto de forma transparente com PagBank (PagSeguro).',
			'pagbank-connect'
		);
		$this->title = $this->get_option('title', __('PagBank (PagSeguro UOL)', 'pagbank-connect'));
		$this->description = $this->get_option('description');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);

        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'addPaymentInfoAdmin'], 10, 1);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);
    }

    public function init_form_fields()
    {
        $fields = [];
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/general-fields.php';
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/recurring-fields.php';
        $this->form_fields = array_merge(...$fields);
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Validates the eligibility of the key used in the recurring feature
     * Note: attempting to modify this behavior will not make the plugin work in your favor
     *
     * @param $key
     * @param $recurring_enabled
     *
     * @return string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate_recurring_enabled_field($key, $recurring_enabled): string
    {
        $connect_key = $this->get_option('connect_key');
        if (substr($connect_key, 0, 9) == 'CONPSFLEX' && $recurring_enabled) {
            WC_Admin_Settings::add_message(__('A recorrência foi desativada pois'
                .' a Connect Key informada usa taxas personalizadas.', 'pagbank-connect'));
            return 'no';
        }
        
        return $recurring_enabled ? 'yes' : 'no';
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

    /**
     * Disables PagBank if order < R$1.00
     * @param $gateways
     *
     * @return mixed
     */
    public function disableIfOrderLessThanOneReal($gateways)
    {
        $hideIfUnavailable = $this->get_option('hide_id_unavailable');
        if (!wc_string_to_bool($hideIfUnavailable) || is_admin()) {
            return $gateways;
        }

        if ($this->paymentUnavailable()) {
            foreach ($gateways as $key => $gateway) {
                if (strpos($key, Connect::DOMAIN) !== false) {
                    unset($gateways[$key]);
                }
            }
        }

        return $gateways;
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
        if ( is_checkout() && Params::getConfig('enabled') == 'yes' ) {
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

    public static function addStylesWoo($styles)
    {
        if ( Recurring::isRecurringEndpoint() )
        {
            $styles['rm-pagbank-recurring'] = [
                'src'     => plugins_url('public/css/recurring.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
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
        $api = new Api();
        //thank you page
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received'))) {
            wp_enqueue_script(
                'pagseguro-connect',
                plugins_url('public/js/success.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        if ( is_checkout() && !is_order_received_page() ) {
            wp_enqueue_script(
                'pagseguro-connect-checkout',
                plugins_url('public/js/checkout.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                ['jquery'],
                WC_PAGSEGURO_CONNECT_VERSION,
                true
            );
            wp_add_inline_script(
                'pagseguro-connect-checkout',
                'const rm_pagbank_nonce = "' . wp_create_nonce('rm_pagbank_nonce') . '";',
                'before'
            );

            if ( $this->get_option('cc_enabled') == 'yes') {
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
                    'var pagseguro_connect_public_key = \''.$this->get_option('public_key').'\';',
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
                        'var pagseguro_connect_cc_3ds_allow_continue = \''.Params::getConfig('cc_3ds_allow_continue', 'no').'\';',
                        'before'
                    );
                    // add user notice
                    if ($threeDSession === '' && Params::getConfig('cc_3ds_allow_continue', 'no') === 'no') {
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

    /**
     * Add css file to admin
     * @return void
     */
    public function addAdminStyles($hook){
        //admin pages
        if (!is_admin()) {
            return;
        }

        wp_enqueue_style(
            'pagseguro-connect-admin-css',
            plugins_url('public/css/ps-connect-admin.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );

        if ($hook == 'plugins.php') {
            wp_enqueue_style(
                'pagseguro-connect-deactivate',
                plugins_url('public/css/admin/deactivate.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                [],
                WC_PAGSEGURO_CONNECT_VERSION
            );
        }
    }

    /**
     * Add js file to admin, only in the plugin settings page
     * @return void
     */
    public function addAdminScripts($hook){

        if (!is_admin()) {
            return;
        }

        # region Add general script to handle the pix notice dismissal (and maybe other features in the future)
        wp_register_script(
            'pagseguro-connect-admin-pix-notice',
            plugins_url('public/js/admin/ps-connect-admin-general.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            ['jquery']
        );
        $scriptData = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => 'pagbank_dismiss_pix_order_keys_notice'
        );
        wp_localize_script('pagseguro-connect-admin-pix-notice', 'script_data', $scriptData);
        wp_enqueue_script('pagseguro-connect-admin-pix-notice');
        # endregion

        global $current_section; //only when ?section=rm-pagbank (plugin config page)

        if ($current_section && strpos($current_section, Connect::DOMAIN) !== false) {
            wp_enqueue_script(
                'pagseguro-connect-admin',
                plugins_url('public/js/admin/ps-connect-admin.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        if ($hook == 'plugins.php') {
            $feedbackModal = file_get_contents(WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/feedback-modal.php');
            wp_enqueue_script(
                'pagbank-connect-deactivate',
                plugins_url('public/js/admin/deactivate.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                ['jquery', 'jquery-ui-dialog'],
                WC_PAGSEGURO_CONNECT_VERSION,
            );
            wp_add_inline_script(
                'pagbank-connect-deactivate',
                'var pagbankFeedbackFormNonce = "' . wp_create_nonce('pagbank_connect_send_feedback') . '";'
            );
            wp_localize_script(
                'pagbank-connect-deactivate',
                'pagbankConnect',
                ['feedbackModalHtml' => $feedbackModal]
            );
        }

    }
}
