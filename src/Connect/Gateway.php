<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Admin_Settings;
use WC_Payment_Gateway_CC;

/**
 * Class Gateway
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Gateway extends WC_Payment_Gateway_CC
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use ThankyouInstructions;

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

        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);
    }

    public function init_form_fields()
    {
        $fields = [];
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/general-fields.php';
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
        //check if it looks like a token (contains lower case and dashes)
        if (preg_match('/[a-z-]/', $connect_key)) {
            WC_Admin_Settings::add_error(__('Parece que você informou o Token PagBank no lugar da Connect Key. Clique em Obter Connect Key para obter a sua gratuitamente e ainda economizar nas taxas oficiais.', 'pagbank-connect'));
            return '';
        }

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
}
