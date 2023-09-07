<?php

namespace RM_PagBank;

use Exception;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;

/**
 * Class Connect
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Connect
{

    public const DOMAIN = 'rm-pagbank';

    /**
     * @return void
     */
    public function __construct()
    {

    }

    public static function init()
    {
        // Checks if WooCommerce is installed or return
        if ( !class_exists('WooCommerce')) {
            add_action('admin_notices', [__CLASS__, 'wooMissingNotice']);
            return;
        }
        add_action('wp_ajax_nopriv_ps_get_installments', [CreditCard::class, 'getAjaxInstallments']);
        add_action('wp_ajax_ps_get_installments', [CreditCard::class, 'getAjaxInstallments']);
        add_action('woocommerce_api_wc_pagseguro_info', [__CLASS__, 'configInfo']);
        add_action('woocommerce_api_rm_ps_notif', [__CLASS__, 'notification']);

        // Load plugin files
        self::includes();

        // Add Gateway
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'addGateway'));

        // Add action links
        add_filter( 'plugin_action_links_' . plugin_basename( WC_PAGSEGURO_CONNECT_PLUGIN_FILE ), array( self::class, 'addPluginActionLinks' ) );
    }

    /**
     * WooCommerce missing notice.
     */
    public static function wooMissingNotice() {
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/messages/html-notice-missing-woocommerce.php';
    }

    /**
     * Includes module files.
     *
     * @return void
     */
    public static function includes()
    {
        //@TODO Remover em prol de Helpers\Functions\generic_message
        if ( is_admin() ) {
            include_once WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/messages/generic.php';
        }
    }

    /**
     * Add Gateway
     *
     * @param array $gateways
     *
     * @return array
     */
    public static function addGateway(array $gateways ): array
    {
        $gateways[] = new Gateway();
        return $gateways;
    }

    public static function addPluginActionLinks( $links ): array
    {
        $plugin_links   = array();
        $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . self::DOMAIN ) ) . '">' . __( 'Configurações', self::DOMAIN ) . '</a>';

        return array_merge( $plugin_links, $links );
    }

    /**
     * This will help our support team to detect problems in your configuration
     * @return void
     */
    public static function configInfo(): void
    {
        $settings = [
            'platform' => 'Wordpress',
            'module_version' =>[
                'Version' => WC_PAGSEGURO_CONNECT_VERSION,
            ],
            'extra_fields' => class_exists('Extra_Checkout_Fields_For_Brazil'),
            'connect_key' => strlen(Params::getConfig('connect_key')) == 40 ? 'Good' : 'Wrong size',
            'settings' => [
                'enabled' => Params::getConfig('enabled'),
                'cc_enabled' => Params::getConfig('cc_enabled'),
                'pix_enabled' => Params::getConfig('pix_enabled'),
                'boleto_enabled' => Params::getConfig('boleto_enabled'),
                'public_key' => Params::getConfig('public_key'),
                'sandbox' => Params::getConfig('sandbox'),
                'boleto' => [
                    'enabled' => Params::getConfig('boleto_enabled'),
                    'expiry_days' => Params::getConfig('boleto_expiry_days'),
                ],
                'pix' => [
                    'enabled' => Params::getConfig('pix_enabled'),
                    'expiry_minutes' => Params::getConfig('pix_expiry_minutes'),
                ],
                'cc' => [
                    'enabled' => Params::getConfig('cc_enabled'),
                    'installment_options' => Params::getConfig('cc_installments_options'),
                    'installment_options_fixed' => Params::getConfig('cc_installment_options_fixed'),
                    'installments_options_min_total' => Params::getConfig('cc_installments_options_min_total'),
                    'installments_options_limit_installments' => Params::getConfig('cc_installments_options_limit_installments'),
                    'installments_options_max_installments' => Params::getConfig('cc_installments_options_max_installments'),
                ]
            ]
        ];

        try{
            $api = new Api();
            $resp = $api->get('ws/public-keys/card');
            $settings['live_auth'] = $resp;
        }catch (Exception $e){
            $settings['live_auth'] = $e->getMessage();
        }
        wp_send_json($settings);

    }

    /**
     * Register the notification route to deal with PagBank notifications about order updates
     * @return void
     */
    public static function notification(): void
    {
        $gateway = new Gateway();
        $gateway->notification();
    }

    public static function loadTextDomain(): void
    {
        $dir = self::DOMAIN . '/languages/';
        load_plugin_textdomain(Connect::DOMAIN, false, $dir);
    }
}
