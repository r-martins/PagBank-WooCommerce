<?php

namespace RM_PagBank;

use Exception;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Connect\MenuPagBank;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
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
        add_action('wp_ajax_get_cart_total', [CreditCard::class, 'getCartTotal']);
        add_action('wp_ajax_nopriv_get_cart_total', [CreditCard::class, 'getCartTotal']);

        // Load plugin files
        self::includes();

        // Add Gateway
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'addGateway'));

        // Add action links
        add_filter( 'plugin_action_links_' . plugin_basename( WC_PAGSEGURO_CONNECT_PLUGIN_FILE ), array( self::class, 'addPluginActionLinks' ) );

        // Payment method title used
        add_filter('woocommerce_gateway_title', [__CLASS__, 'getMethodTitle'], 10, 2);
        
        self::addPagBankMenu();
        
        if (Params::getConfig('recurring_enabled')){
            $recurring = new Connect\Recurring();
            $recurring->init();
        }
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
        $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . self::DOMAIN ) ) . '">' . __( 'Configurações', 'pagbank-connect' ) . '</a>';

        return array_merge( $plugin_links, $links );
    }

    /**
     * This will help our support team to detect problems in your configuration
     * @return void
     */
    public static function configInfo(): void
    {
		$api = new Api();
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
				'public_key' => substr(Params::getConfig('public_key', 'null'), 0, 50) . '...',
				'sandbox' => $api->getIsSandbox(),
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
                    'installment_options' => Params::getConfig('cc_installment_options'),
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
			if (isset($resp['public_key'])){
				$resp['public_key'] = substr($resp['public_key'], 0, 50) . '...';
			}
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
        $dir = __DIR__ . '/../languages/';
        load_plugin_textdomain('pagbank-connect', false, $dir);
    }

    public static function getMethodTitle($title, $id){
        //get order
        if ($id == 'rm-pagbank' && wp_doing_ajax() && isset($_POST['ps_connect_method']))
        {
            $method = filter_input(INPUT_POST, 'ps_connect_method', FILTER_SANITIZE_STRING);
            $method = Functions::getFriendlyPaymentMethodName($method);
            $title = Params::getConfig('title') . ' - ' . $method;
        }

        return $title;
    }
    
    public static function activate()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';
        
        $sql = "CREATE TABLE $recurringTable 
                (
                    id               int auto_increment primary key,
                    initial_order_id int           not null comment 'Order that generated the subscription profiler',
                    recurring_amount float(8, 2)   not null comment 'Amount to be charged regularly',
                    status           varchar(100)  not null comment 'Current subscription status (ACTIVE, PAUSED, SUSPENDED, CANCELED)',
                    recurring_type   varchar(15)   not null comment 'Daily, Weekly, Monthly, Yearly ',
                    recurring_cycle  int default 1 not null comment 'Type multiplier',
                    created_at       datetime      null,
                    updated_at       datetime      null,
                    paused_at        datetime      null,
                    canceled_at      datetime      null,
                    suspended_at     datetime      null,
                    canceled_reason  text          null,
                    suspended_reason text          null,
                    next_bill_at     datetime      not null,
                    payment_info     text          null comment 'Payment details for the subscription',
                    CONSTRAINT wp_pagbank_recurring_unique_order_id
                        unique (initial_order_id)
                )
                    comment 'Recurring profiles information for PagBank Subscribers';";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        add_option('pagbank_db_version', '4.0');
    }
    
    public static function uninstall()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';
        $wpdb->query("DROP TABLE IF EXISTS $recurringTable");
    }
    
    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('rm_pagbank_cron_process_recurring_payments');
        wp_unschedule_event($timestamp, 'rm_pagbank_cron_process_recurring_payments');
    }

    private static function addPagBankMenu()
    {
        add_action('admin_menu', [MenuPagBank::class, 'addPagBankMenu']);
        add_action('admin_menu', [MenuPagBank::class, 'addPagBankSubmenuItems']);
        add_action('admin_enqueue_scripts', [MenuPagBank::class, 'adminPagesStyle']);
    }
}
