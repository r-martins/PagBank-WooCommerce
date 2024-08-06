<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Connect\Payments\CreditCardTrial;
use RM_PagBank\Connect\Recurring;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use WC_Order;
use WC_Payment_Gateway;
use Exception;
use WC_Admin_Settings;
use WC_Data_Exception;
use WP_Error;

/** Standalone Pix */
class Pix extends WC_Payment_Gateway
{
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
            'pix_title',
            __('Pix via PagBank', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Pix via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('boleto_title', __('Pix via PagBank', 'pagbank-connect'));
        $this->description = $this->get_option('description');
//        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/pix-fields.php';
    }
    public function init_settings(){
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

    public function admin_options() {
//        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
//
//        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
        parent::admin_options();
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
     * Validates Pix discount field
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
     * @inheritDoc
     */
    public function form() {
        if ($this->paymentUnavailable()) {
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/unavailable.php';
            return;
        }

        if (Params::getConfig('standalone', 'yes') == 'no') {
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payment-form.php';
            return;
        }

        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/pix.php';
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
     * Add the instructions to the thankyou page for pix and pix
     * @param $order_id
     *
     * @return void
     */
    public function addThankyouInstructions($order_id)
    {
        $order = wc_get_order($order_id);
        $method = new \RM_PagBank\Connect\Payments\Pix($order);
        if (!empty($method)) {
            $method->getThankyouInstructions($order_id);
        }
    }

    /**
     * Payment is unavailable if the total is less than R$1.00
     * @return bool
     */
    public function paymentUnavailable(): bool
    {
        $total = Api::getOrderTotal();
        $total = Params::convertToCents($total);
        $isTotalLessThanOneReal = $total < 100;
        if (!$isTotalLessThanOneReal) {
            return false;
        }

        $recHelper = new RecurringHelper();
        if ($recHelper->isCartRecurring()) {
            return false;
        }

        return true;
    }
}