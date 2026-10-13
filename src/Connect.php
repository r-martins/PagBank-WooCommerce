<?php

namespace RM_PagBank;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\StoreApi\Utilities\PaymentUtils;
use Exception;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Connect\MenuPagBank;
use RM_PagBank\Connect\OrderMetaBoxes;
use RM_PagBank\Connect\OrderProcessor;
use RM_PagBank\Connect\Payments\Boleto;
use RM_PagBank\Connect\Payments\CreditCard;
use RM_PagBank\Connect\Payments\Pix;
use RM_PagBank\Connect\Standalone\Pix as StandalonePix;
use RM_PagBank\Connect\Standalone\CreditCard as StandaloneCc;
use RM_PagBank\Connect\Standalone\Boleto as StandaloneBoleto;
use RM_PagBank\Connect\Standalone\Redirect;
use RM_PagBank\Connect\Standalone\Redirect as StandaloneRedirect;
use RM_PagBank\Connect\Blocks\Boleto as BoletoBlock;
use RM_PagBank\Connect\Blocks\Redirect as RedirectBlock;
use RM_PagBank\Connect\Blocks\CreditCard as CreditCardBlock;
use RM_PagBank\Connect\Blocks\Pix as PixBlock;
use RM_PagBank\Cron\CancelExpiredPix;
use RM_PagBank\Cron\ForceOrderUpdate;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\CreditCardDisplayTitle;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;
use WC_Order;
use WP_Query;
use RM_PagBank\Helpers\OrderMeta;

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
        add_action('wp_ajax_ps_get_3d_session', [CreditCard::class, 'getAjax3DSession']);
        add_action('wp_ajax_nopriv_ps_get_3d_session', [CreditCard::class, 'getAjax3DSession']);
        \RM_PagBank\Connect\Payments\VindiThreeDs::register();
        \RM_PagBank\Helpers\PixQr::register();
        add_action('wp_ajax_ps_deactivate_feedback', [__CLASS__, 'deactivateFeedback']);
        add_action('woocommerce_api_pagbank_force_order_update', [__CLASS__, 'forceOrderUpdate']);
        add_action('woocommerce_before_template_part', [CreditCard::class, 'orderPayScript'], 10, 1);
        add_action('woocommerce_product_object_updated_props', [CreditCard::class, 'updateProductTransient'], 10, 2);
        add_action('woocommerce_update_product_variation', [CreditCard::class, 'updateProductVariationTransient'], 10, 2);
        add_action('woocommerce_after_add_to_cart_form', [CreditCard::class, 'getProductInstallments'], 25);
        add_shortcode('rm_pagbank_credit_card_installments', [CreditCard::class, 'getProductInstallments']);
        add_action('update_option', [CreditCard::class, 'deleteInstallmentsTransientIfConfigHasChanged'], 10, 3);
        add_action('wp_loaded', [__CLASS__, 'removeOtherPaymentMethodsWhenRecurring']);
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'recurringRestrictPaymentMethod']);
        add_action('admin_notices', [\RM_PagBank\Admin\MigrationNotice::class, 'render']);
        add_action('current_screen', [\RM_PagBank\Admin\MigrationNotice::class, 'suppressWooConnectNoticeOnOurScreens']);
        // Fallback: remove immediately before the notice would print (priority 10).
        add_action('admin_notices', [\RM_PagBank\Admin\MigrationNotice::class, 'suppressWooConnectNoticeOnOurScreens'], 1);
        add_action('admin_enqueue_scripts', [\RM_PagBank\Admin\MigrationNotice::class, 'suppressWooConnectNoticeOnOurScreens'], 1);
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'filterGatewaysByPartner'], 20);
        // Order-pay: keep only PIX/boleto when that method's discount fee is already on the order.
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'restrictGatewaysOnOrderPayWithMethodDiscount'], 25);
        add_action('woocommerce_checkout_create_order_fee_item', [__CLASS__, 'stampPaymentMethodDiscountFeeMeta'], 10, 4);
        add_filter('woocommerce_rest_prepare_shop_order_object', [__CLASS__, 'addOrderMetaToApiResponse'], 10, 3 );
        add_filter('woocommerce_rest_prepare_shop_order_object', [CreditCardDisplayTitle::class, 'filterRestPrepareShopOrderObject'], 20, 3);
        add_filter('woocommerce_order_get_payment_method_title', [CreditCardDisplayTitle::class, 'filterPaymentMethodTitle'], 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'addPaymentInfoAdmin'], 10, 1);
        add_action('woocommerce_api_wc_order_status', [__CLASS__, 'getOrderStatus']);
        add_filter('woocommerce_order_item_needs_processing', [__CLASS__, 'orderItemNeedsProcessing'], 10, 3);
        add_filter('woocommerce_get_checkout_order_received_url', [Redirect::class, 'getOrderReceivedURL'], 100, 2);
        add_filter('woocommerce_get_checkout_payment_url', [Redirect::class, 'changePaymentLink'], 10, 2);
        add_action('rest_api_init', [CreditCard::class,'restApiInstallments']);

        // Load plugin files
        self::includes();

        \RM_PagBank\Compat\BrazilianCheckoutFields::init();

        // Add action links
        add_filter( 'plugin_action_links_' . plugin_basename( WC_PAGSEGURO_CONNECT_PLUGIN_FILE ), array( self::class, 'addPluginActionLinks' ) );

        // Payment method title used
        add_filter('woocommerce_gateway_title', [__CLASS__, 'getMethodTitle'], 10, 2);

        self::addPagBankMenu();
        
        // Initialize order meta boxes
        OrderMetaBoxes::init();

        if (Params::getRecurringConfig('recurring_enabled')) {
            $recurring = new Connect\Recurring();
            $recurring->init();
        }

        //if pix enabled
        if (Params::getPixConfig('enabled') === 'yes') {
            add_filter('woocommerce_get_price_html', [Pix::class, 'showPriceDiscountPixProduct'], 10, 2);
            //region cron to cancel expired pix non-paid payments
            add_action('rm_pagbank_cron_cancel_expired_pix', [CancelExpiredPix::class, 'execute']);
            if (!wp_next_scheduled('rm_pagbank_cron_cancel_expired_pix')) {
                wp_schedule_event(
                    time(),
                    'hourly',
                    'rm_pagbank_cron_cancel_expired_pix'
                );
            }
            //endregion
        }
        if ((Params::getPixConfig('enabled') === 'yes' && Params::getPixConfig('pix_discount', 0))
            || (Params::getBoletoConfig('enabled') === 'yes' && Params::getBoletoConfig('boleto_discount', 0))) {
            add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'addPaymentMethodDiscountFees'], 20);
        }

        //if force order update enabled
        if (Params::getConfig('force_order_update', false)) {
            add_action('rm_pagbank_cron_force_order_update', [ForceOrderUpdate::class, 'execute']);
            if (!wp_next_scheduled('rm_pagbank_cron_force_order_update')) {
                wp_schedule_event(
                    time(),
                    'hourly',
                    'rm_pagbank_cron_force_order_update'
                );
            }
        }

        add_action('wp_ajax_pagbank_dismiss_pix_order_keys_notice', [StandalonePix::class, 'dismissPixOrderKeysNotice']);
        add_filter('woocommerce_admin_reports', [\RM_PagBank\Connect\Recurring\Admin\Reports\RecurringsReport::class, 'reportsFilter']);
    }

    public static function gatewayBlockSupport() {
        // Check if the required class exists
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new BoletoBlock() );
                $payment_method_registry->register( new PixBlock() );
                $payment_method_registry->register( new CreditCardBlock() );
                $payment_method_registry->register( new RedirectBlock() );
            }
        );
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
    public static function addGateway(array $gateways): array
    {
        $section = sanitize_text_field($_GET['section'] ?? '');
        $caps = new \RM_PagBank\Partner\Capabilities();

        if ($section !== self::DOMAIN) {// checkout / other settings sections → standalone gateways
            $pix = new StandalonePix();
            $gateways[] = $pix;

            $cc = new StandaloneCc();
            $gateways[] = $cc;

            $boleto = new StandaloneBoleto();
            $gateways[] = $boleto;

            if ($caps->hasHostedCheckout()) {
                $redirect = new StandaloneRedirect();
                $gateways[] = $redirect;
            }

            return $gateways;
        }

        $gateways[] = new Gateway();

        return $gateways;
    }

    /**
     * Hide PagBank-only methods when Connect Key is Vindi (checkout + leftovers).
     *
     * @param array $gateways
     * @return array
     */
    public static function filterGatewaysByPartner($gateways)
    {
        if (!is_array($gateways)) {
            return $gateways;
        }
        $caps = new \RM_PagBank\Partner\Capabilities();
        if (!$caps->hasHostedCheckout() && isset($gateways['rm-pagbank-redirect'])) {
            unset($gateways['rm-pagbank-redirect']);
        }
        return $gateways;
    }

    /**
     * Persist cart fee id on the order fee line so order-pay can detect PIX/boleto discounts reliably.
     *
     * @param \WC_Order_Item_Fee $item
     * @param string             $fee_key Cart fee id (e.g. pagbank_pix_discount).
     * @param object             $fee
     * @param WC_Order           $order
     */
    public static function stampPaymentMethodDiscountFeeMeta($item, $fee_key, $fee, $order): void
    {
        if (!in_array($fee_key, [Pix::DISCOUNT_FEE_ID, Boleto::DISCOUNT_FEE_ID], true)) {
            return;
        }
        if (!is_object($item) || !method_exists($item, 'add_meta_data')) {
            return;
        }
        $item->add_meta_data('_pb_discount_fee_id', $fee_key, true);
    }

    /**
     * On /order-pay, if the order already has our PIX or boleto discount fee, hide our other gateways
     * so the customer cannot switch to card (etc.) while keeping the method-specific discount.
     * Does not remove third-party gateways. Does not run outside order-pay (recurring checkout untouched).
     *
     * @param array<string, \WC_Payment_Gateway> $gateways
     * @return array<string, \WC_Payment_Gateway>
     */
    public static function restrictGatewaysOnOrderPayWithMethodDiscount($gateways)
    {
        if (!is_array($gateways) || !function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-pay')) {
            return $gateways;
        }

        $order = self::getOrderPayOrder();
        if (!$order instanceof WC_Order) {
            return $gateways;
        }

        $feeId = self::getPaymentMethodDiscountFeeIdOnOrder($order);
        if ($feeId === null) {
            return $gateways;
        }

        $keepGatewayId = $feeId === Pix::DISCOUNT_FEE_ID
            ? self::DOMAIN . '-pix'
            : self::DOMAIN . '-boleto';

        $ourGatewayIds = [
            self::DOMAIN,
            self::DOMAIN . '-pix',
            self::DOMAIN . '-cc',
            self::DOMAIN . '-boleto',
            self::DOMAIN . '-redirect',
        ];

        foreach (array_keys($gateways) as $id) {
            if (!in_array($id, $ourGatewayIds, true)) {
                continue;
            }
            if ($id !== $keepGatewayId) {
                unset($gateways[$id]);
            }
        }

        return $gateways;
    }

    /**
     * @return WC_Order|null
     */
    private static function getOrderPayOrder(): ?WC_Order
    {
        global $wp;
        $orderId = 0;
        if (isset($wp->query_vars['order-pay'])) {
            $orderId = absint($wp->query_vars['order-pay']);
        }
        if (!$orderId && function_exists('get_query_var')) {
            $orderId = absint(get_query_var('order-pay'));
        }
        if (!$orderId) {
            return null;
        }
        $order = wc_get_order($orderId);
        return $order instanceof WC_Order ? $order : null;
    }

    /**
     * Detect which method-specific discount fee (if any) is on the order.
     *
     * @return string|null Pix::DISCOUNT_FEE_ID, Boleto::DISCOUNT_FEE_ID, or null
     */
    public static function getPaymentMethodDiscountFeeIdOnOrder(WC_Order $order): ?string
    {
        $known = [Pix::DISCOUNT_FEE_ID, Boleto::DISCOUNT_FEE_ID];

        foreach ($order->get_items('fee') as $item) {
            $meta = method_exists($item, 'get_meta') ? $item->get_meta('_pb_discount_fee_id', true) : '';
            if (is_string($meta) && in_array($meta, $known, true)) {
                return $meta;
            }
            if (!empty($item->legacy_fee_key) && in_array($item->legacy_fee_key, $known, true)) {
                return (string) $item->legacy_fee_key;
            }
        }

        // Orders placed before meta stamp: match the fee name we generate at checkout.
        $pixLabel = __('Desconto', 'pagbank-connect') . ' ' . Params::getPixConfig('title', __('PIX', 'pagbank-connect'));
        $boletoLabel = __('Desconto', 'pagbank-connect') . ' ' . Params::getBoletoConfig('title', __('Boleto', 'pagbank-connect'));

        foreach ($order->get_items('fee') as $item) {
            if ((float) $item->get_total() >= 0) {
                continue;
            }
            $name = $item->get_name();
            if ($name === $pixLabel) {
                return Pix::DISCOUNT_FEE_ID;
            }
            if ($name === $boletoLabel) {
                return Boleto::DISCOUNT_FEE_ID;
            }
        }

        return null;
    }

    public static function addPluginActionLinks( $links ): array
    {
        $plugin_links   = array();
        $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . self::DOMAIN ) ) . '">' . __( 'Configurações', 'pagbank-connect' ) . '</a>';
        $plugin_links[] = '<a href="' . esc_url( 'https://ajuda.pbintegracoes.com/hc/pt-br' ) . '" target="_blank">' . __( 'Documentação', 'pagbank-connect' ) . '</a>';
        $plugin_links[] = '<a href="' . esc_url( 'https://ajuda.pbintegracoes.com/hc/pt-br/requests/new' ) . '" target="_blank">' . __( 'Suporte', 'pagbank-connect' ) . '</a>';

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
            'block_checkout' => Functions::isBlockCheckoutInUse(),
            'connect_key' => (static function () {
                $key = (string) Params::getConfig('connect_key');
                if ($key === '' || stripos($key, 'CON') !== 0) {
                    return 'Missing';
                }
                // PagBank + Vindi (new) = 40 chars; legacy shorter CONVD… still OK
                return strlen($key) >= 20 ? 'Good' : 'Wrong size';
            })(),
            'enable_proxy' => Params::getConfig('enable_proxy', "no"),
            // Use same defaults as admin form_fields when option not saved yet
            'settings' => [
                'enabled' => Params::getConfig('enabled', ''),
                'cc_enabled' => Params::getCcConfig('enabled', 'yes'),
                'pix_enabled' => Params::getPixConfig('enabled', 'yes'),
                'boleto_enabled' => Params::getBoletoConfig('enabled', 'no'),
                'public_key' => substr(Params::getConfig('public_key', 'null'), 0, 50) . '...',
                'sandbox' => $api->getIsSandbox(),
                'boleto' => [
                    'enabled' => Params::getBoletoConfig('enabled', 'no'),
                    'expiry_days' => Params::getBoletoConfig('boleto_expiry_days', '7'),
                    'discount' => Params::getBoletoConfig('boleto_discount', '0'),
                ],
                'pix' => [
                    'enabled' => Params::getPixConfig('enabled', 'yes'),
                    'expiry_minutes' => Params::getPixConfig('pix_expiry_minutes', '1440'),
                    'discount' => Params::getPixConfig('pix_discount', '0'),
                ],
                'cc' => [
                    'enabled' => Params::getCcConfig('enabled', 'yes'),
                    'enabled_installment' => Params::getCcConfig('cc_installment_product_page', 'no'),
                    'installment_options' => Params::getCcConfig('cc_installment_options', ''),
                    'installment_options_fixed' => Params::getCcConfig('cc_installment_options_fixed', '3'),
                    'installments_options_min_total' => Params::getCcConfig('cc_installments_options_min_total', '50'),
                    'installments_options_limit_installments' => Params::getCcConfig('cc_installments_options_limit_installments', ''),
                    'installments_options_max_installments' => Params::getCcConfig('cc_installments_options_max_installments', '18'),
                    '3d_secure' => Params::getCcConfig('cc_3ds', 'yes'),
                    '3d_secure_allow_continue' => Params::getCcConfig('cc_3ds_allow_continue', 'no'),
                    '3d_secure_retry' => Params::getCcConfig('cc_3ds_retry', 'yes'),
                    '3d_retry_failed' => Params::getCcConfig('cc_3ds_retry', 'yes'),
                ],
                'recurring' => [
                    'enabled' => Params::getRecurringConfig('recurring_enabled', 'no'),
                    'recurring_process_frequency' => Params::getRecurringConfig('recurring_process_frequency', 'hourly'),
                    'customer_can_cancel' => Params::getRecurringConfig('recurring_customer_can_cancel', 'yes'),
                    'customer_can_pause' => Params::getRecurringConfig('recurring_customer_can_pause', 'yes'),
                    'clear_cart' => Params::getRecurringConfig('recurring_clear_cart', 'no'),
                    'recurring_retry_charge' => Params::getRecurringConfig('recurring_retry_charge', 'yes'),
                    'recurring_retry_attempts' => Params::getRecurringConfig('recurring_retry_attempts', '3'),
                ],
                'redirect' => [
                    'enabled' => Params::getRedirectConfig('enabled', 'yes'),
                    'redirect_expiry_minutes' => Params::getRedirectConfig('redirect_expiry_minutes', '120'),
                    'redirect_discount' => Params::getRedirectConfig('redirect_discount', '0'),
                    'redirect_discount_excludes_shipping' => Params::getRedirectConfig('redirect_discount_excludes_shipping', 'no'),
                    'redirect_payment_methods' => Params::getRedirectConfig('redirect_payment_methods', ''),
                ],
            ],
            'extra' => [
                'hpos_enabled' => Functions::isHposEnabled() ? 'yes' : 'no',
                'litespeed_cache' => is_plugin_active('litespeed-cache/litespeed-cache.php') ? 'yes' : 'no',
                'wordfence_active' => is_plugin_active('wordfence/wordfence.php') ? 'yes' : 'no',
                'cron_ok' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'yes' : 'no',
            ],
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
        if ($id == 'rm-pagbank' && wp_doing_ajax() && isset($_POST['ps_connect_method'])) //phpcs:ignore WordPress.Security.NonceVerification
        {
            $method = htmlspecialchars($_POST['ps_connect_method'], ENT_QUOTES, 'UTF-8');
            $method = Functions::getFriendlyPaymentMethodName($method);
            $title = Params::getConfig('title', __('PB Integrações', 'pagbank-connect')) . ' - ' . $method;
        }

        return $title;
    }

    public static function activate()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';

        $sql = "CREATE TABLE IF NOT EXISTS $recurringTable
                (
                    id               int auto_increment,
                    initial_order_id int           not null UNIQUE comment 'Order that generated the subscription profiler',
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
                    PRIMARY KEY  (id)
                )
                    comment 'Recurring profiles information for PagBank Subscribers';";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        add_option('pagbank_db_version', '4.0');
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table_name The table name to check
     * @return bool True if table exists, false otherwise
     */
    private static function tableExists($table_name)
    {
        global $wpdb;
        $table_name = esc_sql($table_name);
        // Suppress errors to avoid warnings if table doesn't exist
        $wpdb->suppress_errors();
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $wpdb->suppress_errors(false);
        return $result === $table_name;
    }

    public static function upgrade()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';
        $stored_version = get_option('pagbank_db_version');

        if (version_compare($stored_version, '4.12', '<')) {
            // Check if table exists before trying to modify it
            if (self::tableExists($recurringTable)) {
                if ($wpdb->get_var("SHOW COLUMNS FROM $recurringTable LIKE 'recurring_initial_fee'") !== 'recurring_initial_fee') { //if column recurring_initial_fee does not exist
                    $sql = "ALTER TABLE $recurringTable
                            ADD COLUMN recurring_initial_fee float(8, 2) null comment 'Initial fee to be charged on the first payment' AFTER recurring_amount,
                            ADD COLUMN recurring_trial_period int null comment 'Number of days to wait before charging the first fee' AFTER recurring_initial_fee,
                            ADD COLUMN recurring_discount_amount float(8, 2) null comment 'Discount amount to be applied to the recurring amount' AFTER recurring_trial_period,
                            ADD COLUMN recurring_discount_cycles int null comment 'Number of cycles to apply the discount' AFTER recurring_discount_amount;
                            ";

                    $wpdb->query($sql);
                }
            }
            update_option('pagbank_db_version', '4.12');
        }

        if (version_compare($stored_version, '4.13', '<')) {
            $settingsTable = $wpdb->prefix . 'options';
            $settings = get_option('woocommerce_rm-pagbank_settings');

            if (!$settings) {
                update_option('pagbank_db_version', '4.13');
                return;
            }

            $generalSettings = array();
            $recurringSettings = array();
            $ccSettings = array();
            $pixSettings = array();
            $boletoSettings = array();
            foreach ($settings as $key => $setting) {
                if (strpos($key,'cc_') !== false) {
                    $ccSettings[$key] = $setting;
                    continue;
                }
                if (strpos($key,'pix_') !== false) {
                    $pixSettings[$key] = $setting;
                    continue;
                }
                if (strpos($key,'boleto_') !== false) {
                    $boletoSettings[$key] = $setting;
                    continue;
                }
                if (strpos($key,'recurring_') !== false) {
                    $recurringSettings[$key] = $setting;
                    continue;
                }

                $generalSettings[$key] = $setting;
            }

            if (isset($ccSettings['cc_enabled'])) {
                $ccSettings['enabled'] = $ccSettings['cc_enabled'];
                unset($ccSettings['cc_enabled']);
            }
            if (isset($pixSettings['pix_enabled'])) {
                $pixSettings['enabled'] = $pixSettings['pix_enabled'];
                unset($pixSettings['pix_enabled']);
            }
            if (isset($boletoSettings['boleto_enabled'])) {
                $boletoSettings['enabled'] = $boletoSettings['boleto_enabled'];
                unset($boletoSettings['boleto_enabled']);
            }

            if (isset($ccSettings['cc_title'])) {
                $ccSettings['title'] = $ccSettings['cc_title'];
                unset($ccSettings['cc_title']);
            }
            if (isset($pixSettings['pix_title'])) {
                $pixSettings['title'] = $pixSettings['pix_title'];
                unset($pixSettings['pix_title']);
            }
            if (isset($boletoSettings['boleto_title'])) {
                $boletoSettings['title'] = $boletoSettings['boleto_title'];
                unset($boletoSettings['boleto_title']);
            }

            if (isset($generalSettings['hide_id_unavailable'])) {
                $generalSettings['hide_if_unavailable'] = $generalSettings['hide_id_unavailable'];
            }

            $generalSettings = serialize($generalSettings);
            $ccSettings = $ccSettings;
            $pixSettings = $pixSettings;
            $boletoSettings = $boletoSettings;

            // Idempotency: if main option was already migrated (no cc_*/pix_*/boleto_* keys), the split
            // arrays are empty. Do NOT overwrite existing gateway options with empty arrays — that would
            // wipe 'enabled' and cause payment methods to disappear from checkout. Only set DB version.
            $alreadyMigrated = (count($ccSettings) === 0 && count($pixSettings) === 0 && count($boletoSettings) === 0);
            if ($alreadyMigrated) {
                update_option('pagbank_db_version', '4.13');
                return;
            }

            $wpdb->update(
                $settingsTable,
                ['option_value' => $generalSettings],
                ['option_name' => 'woocommerce_rm-pagbank_settings']
            );

            // Use update_option which automatically handles INSERT or UPDATE
            // This prevents duplicate key errors
            update_option('woocommerce_rm-pagbank-cc_settings', $ccSettings);
            update_option('woocommerce_rm-pagbank-pix_settings', $pixSettings);
            update_option('woocommerce_rm-pagbank-boleto_settings', $boletoSettings);

            foreach ($recurringSettings as $key => $setting) {
                $key = 'woocommerce_rm-pagbank-' . $key;
                update_option($key, $setting);
            }

            update_option('pagbank_db_version', '4.13');
        }
        
        if (version_compare($stored_version, '4.25', '<')) {
            global $wpdb;
            $contentRestrictionTable = $wpdb->prefix . 'pagbank_content_restriction';
            $sql = "CREATE TABLE IF NOT EXISTS $contentRestrictionTable
                    (
                        user_id    int  not null,
                        categories text null,
                        pages      text null,
                        CONSTRAINT pagbank_content_restriction_pk
                            UNIQUE (user_id)
                    )
                        comment 'User permissions based on subscription status';";
            $wpdb->query($sql);
            update_option('pagbank_db_version', '4.25');
        }

        if (version_compare($stored_version, '4.27', '<')) {
            // Check if table exists before trying to modify it
            if (self::tableExists($recurringTable)) {
                if ($wpdb->get_var("SHOW COLUMNS FROM $recurringTable LIKE 'recurring_max_cycles'") !== 'recurring_max_cycles') {
                    $sql = "ALTER TABLE $recurringTable
                        ADD COLUMN recurring_max_cycles int null comment 'Maximum number of billing cycles' AFTER recurring_discount_cycles;";
                    $wpdb->query($sql);
                }
            }
            update_option('pagbank_db_version', '4.27');
        }

        if (version_compare($stored_version, '4.28', '<')) {
            // Check if table exists before trying to modify it
            if (self::tableExists($recurringTable)) {
                // Check if column already exists to avoid errors
                if ($wpdb->get_var("SHOW COLUMNS FROM $recurringTable LIKE 'retry_attempts_remaining'") !== 'retry_attempts_remaining') {
                    $sql = "ALTER TABLE $recurringTable
                            ADD COLUMN retry_attempts_remaining int null comment 'Number of billing attempts remaining on suspended subscriptions' AFTER suspended_at;
                            ";

                    $wpdb->query($sql);
                }
            }
            update_option('pagbank_db_version', '4.28');
        }

        if (version_compare($stored_version, '4.29', '<')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            
            $boxesTable = $wpdb->prefix . 'pagbank_ef_boxes';
            $sql = "CREATE TABLE IF NOT EXISTS $boxesTable
                    (
                        box_id int NOT NULL AUTO_INCREMENT,
                        reference varchar(30) NOT NULL,
                        is_available tinyint NOT NULL DEFAULT '1',
                        outer_width varchar(30) NOT NULL,
                        outer_depth varchar(30) NOT NULL,
                        outer_length varchar(30) NOT NULL,
                        thickness varchar(30) NOT NULL DEFAULT 0.20,
                        inner_length varchar(30) NOT NULL,
                        inner_width varchar(30) NOT NULL,
                        inner_depth decimal(30) NOT NULL,
                        max_weight int NOT NULL,
                        empty_weight int NOT NULL,
                        cost float(4,2) DEFAULT '0.00',
                        created_at datetime DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (box_id),
                        UNIQUE KEY reference (reference)
                    )
                    ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Boxes related to Envio Fácil Shipping';";
            
            dbDelta($sql);
            update_option('pagbank_db_version', '4.29');
        }

        // Remove legacy installment option "external" (PagBank account settings) → buyer.
        if (version_compare((string) $stored_version, '5.0.0', '<')) {
            foreach (['woocommerce_rm-pagbank-cc_settings', 'woocommerce_rm-pagbank_settings'] as $optionName) {
                $settings = get_option($optionName);
                if (!is_array($settings)) {
                    continue;
                }
                if (($settings['cc_installment_options'] ?? '') === 'external') {
                    $settings['cc_installment_options'] = 'buyer';
                    update_option($optionName, $settings);
                }
            }
            update_option('pagbank_db_version', '5.0.0');
        }
    }

    public static function uninstall()
    {
        global $wpdb;
        $recurringTable = $wpdb->prefix . 'pagbank_recurring';
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS $recurringTable"));
    }

    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('rm_pagbank_cron_process_recurring_payments');
        wp_unschedule_event($timestamp, 'rm_pagbank_cron_process_recurring_payments');
    }

    /**
     * Process the feedback response from user when deactivating the plugin
     * @return void
     */
    public static function deactivateFeedback()
    {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'pagbank_connect_send_feedback')) {
            wp_send_json_error(
                [
                    'error' => __(
                        'Chave de formulário inválida. '.'Recarregue a página e tente novamente.',
                        'pagbank-connect'
                    ),
                ],
                400
            );
        }
        parse_str($_REQUEST['feedback'], $formData);

        if (isset($formData['selected-reason']) || isset($formData['comment'])) {
            $reason = $formData['selected-reason'];
            $commment = $formData['comment'] ?? '';
            $openTicket = $formData['autorizaContato'] ?? false;
            $siteUrl = get_site_url();

            /** @var WP_User $currentUser */
            $currentUser = wp_get_current_user();
            $email = $currentUser->user_email;

            $url = 'https://docs.google.com/forms/d/e/1FAIpQLSd4cTW1fWcFZwhJmoICTVc9--rEggj-aJMAqpxv6KFf9dIOjw/'
                .'formResponse?&submit=Submit?usp=pp_url';

            $params = http_build_query([
                'entry.160403419' => $reason,
                'entry.581422256' => $email,
                'entry.1295704444' => $siteUrl,
                'entry.715814172' => $openTicket ? 'Sim' : 'Não',
                'entry.1095777573' => Params::getConfig('connect_key'),
                'entry.16669314' => 'WooCommerce',
                'entry.760515818' => WC()->version,
                'entry.764056986' => WC_PAGSEGURO_CONNECT_VERSION,
                'entry.817525399' => $commment

            ]);
            $url .= '&' . $params;
            wp_remote_get($url, [
                'user-agent' => 'WooCommerce / PagBank Integracoes',
            ]);
        }
    }

    public static function removeOtherPaymentMethodsWhenRecurring()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // if cart is recurring only show PagBank as payment method
        $recHelper = new Recurring();
        $isCartRecurring = $recHelper->isCartRecurring();
        if ($isCartRecurring) {
            add_filter('woocommerce_available_payment_gateways', function ($gateways) {
                    $cc = new StandaloneCc();
                    $cc->id = Connect::DOMAIN . '-cc';
                    return [$cc->id => $cc];
            });
        }
    }

    public static function recurringRestrictPaymentMethod($gateways)
    {
        $recHelper = new Recurring();
        $isCartRecurring = $recHelper->isCartRecurring();
        if ($isCartRecurring) {
            $cc = new StandaloneCc();
            $cc->id = Connect::DOMAIN . '-cc';
            return [$cc->id => $cc];
        }
        return $gateways;
    }

    /**
     * Add order metadata to the API response with PagBank related information
     * @param $response
     * @param $order
     * @param $request
     *
     * @return mixed
     */
    public static function addOrderMetaToApiResponse( $response, $order, $request ) {
        // Check if there's a request for the meta
        if ( empty( $request['include_meta'] ) || 'true' !== $request['include_meta'] ) {
            return $response;
        }

        // Get all metadata for the order
        $meta_data = $order->get_meta_data();
        $meta_array = array();

        foreach ( $meta_data as $meta ) {
            if (strpos($meta->key, 'pagbank') === false) {
                continue;
            }
            // Each item in meta_data is an instance of the WC_Meta_Data class
            $meta_array[ $meta->key ] = $meta->value;
        }

        // Add the meta data array to the response
        $response->data['meta_data'] = array_merge_recursive($response->data['meta_data'], $meta_array);

        return $response;
    }

    /**
     * Adds order info to the admin order page by including the order info template
     *
     * @param $order
     *
     * @return void
     * @noinspection PhpUnusedParameterInspection*/
    public static function addPaymentInfoAdmin($order)
    {
        include_once WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/order-info.php';
    }

    /**
     * Whether a given payment method is currently selected (blocks: rm-pagbank-pix / rm-pagbank-boleto;
     * legacy: rm-pagbank + ps_connect_method=pix|boleto). Checks POST and post_data first so update_order_review
     * AJAX applies the fee before session is updated.
     *
     * @param string      $gatewayId Gateway ID (e.g. 'rm-pagbank-pix', 'rm-pagbank-boleto').
     * @param string|null $subMethod  Legacy sub-method when gateway is rm-pagbank (e.g. 'pix', 'boleto').
     * @return bool
     */
    public static function isPaymentMethodSelected(string $gatewayId, ?string $subMethod = null): bool
    {
        $post_method = null;
        $post_data_parsed = null;

        if (!empty($_POST['payment_method'])) {
            $post_method = sanitize_text_field(wp_unslash($_POST['payment_method']));
        }
        if ($post_method === null && !empty($_POST['post_data']) && is_string($_POST['post_data'])) {
            parse_str(wp_unslash($_POST['post_data']), $post_data_parsed);
            $post_method = isset($post_data_parsed['payment_method']) ? sanitize_text_field($post_data_parsed['payment_method']) : null;
        }
        if ($post_data_parsed === null && !empty($_POST['post_data']) && is_string($_POST['post_data'])) {
            parse_str(wp_unslash($_POST['post_data']), $post_data_parsed);
        }

        if ($post_method !== null) {
            if ($post_method === $gatewayId) {
                return true;
            }
            if ($post_method === self::DOMAIN && $subMethod !== null) {
                $sub = false;
                if (!empty($_POST['ps_connect_method']) && sanitize_text_field(wp_unslash($_POST['ps_connect_method'])) === $subMethod) {
                    $sub = true;
                }
                if (!$sub && is_array($post_data_parsed) && isset($post_data_parsed['ps_connect_method']) && $post_data_parsed['ps_connect_method'] === $subMethod) {
                    $sub = true;
                }
                if ($sub) {
                    return true;
                }
            }
            if ($post_method !== $gatewayId && $post_method !== self::DOMAIN) {
                return false;
            }
        }

        $chosen = WC()->session ? WC()->session->get('chosen_payment_method', '') : '';
        if ($chosen === $gatewayId) {
            return true;
        }
        // Blocks Store API: session may not have chosen_payment_method yet while the UI
        // already shows the first gateway as selected — align fees with that default.
        if ($chosen === '' && class_exists(PaymentUtils::class)) {
            $cart = WC()->cart;
            if ($cart && isset($cart->cart_context) && 'store-api' === $cart->cart_context) {
                $defaultMethod = PaymentUtils::get_default_payment_method();
                if ($defaultMethod === $gatewayId) {
                    return true;
                }
            }
        }
        if ($chosen !== self::DOMAIN) {
            return false;
        }
        if ($subMethod !== null && !empty($_POST['ps_connect_method']) && sanitize_text_field(wp_unslash($_POST['ps_connect_method'])) === $subMethod) {
            return true;
        }
        if ($subMethod !== null && is_array($post_data_parsed) && isset($post_data_parsed['ps_connect_method']) && $post_data_parsed['ps_connect_method'] === $subMethod) {
            return true;
        }
        if ($subMethod !== null && $post_data_parsed === null && !empty($_POST['post_data']) && is_string($_POST['post_data'])) {
            parse_str(wp_unslash($_POST['post_data']), $post_data_parsed);
            return is_array($post_data_parsed) && isset($post_data_parsed['ps_connect_method']) && $post_data_parsed['ps_connect_method'] === $subMethod;
        }
        return false;
    }

    /**
     * Adds PIX/Boleto discount fees when the respective method is selected at checkout.
     * Called from woocommerce_cart_calculate_fees; tries each method that has discount configured.
     *
     * @param \WC_Cart $cart Cart instance.
     * @return void
     */
    public static function addPaymentMethodDiscountFees($cart): void
    {
        self::addDiscountAsFeeForMethod($cart, 'pix');
        self::addDiscountAsFeeForMethod($cart, 'boleto');
    }

    /**
     * Adds discount as a cart fee (negative) for one payment method when it is selected.
     *
     * @param \WC_Cart $cart  Cart instance.
     * @param string   $method 'pix' or 'boleto'.
     * @return void
     */
    protected static function addDiscountAsFeeForMethod($cart, string $method): void
    {
        if (function_exists('is_cart') && is_cart()) {
            return;
        }
        $config = [
            'pix'    => [
                'enabled'    => Params::getPixConfig('enabled') === 'yes',
                'discount'   => Params::getPixConfig('pix_discount', 0),
                'excludes'   => Params::getPixConfig('pix_discount_excludes_shipping', 'no') === 'yes',
                'title'      => Params::getPixConfig('title', __('PIX', 'pagbank-connect')),
                'gateway_id' => 'rm-pagbank-pix',
                'sub_method' => 'pix',
                'fee_id'     => Pix::DISCOUNT_FEE_ID,
            ],
            'boleto' => [
                'enabled'    => Params::getBoletoConfig('enabled') === 'yes',
                'discount'   => Params::getBoletoConfig('boleto_discount', 0),
                'excludes'   => Params::getBoletoConfig('boleto_discount_excludes_shipping', 'no') === 'yes',
                'title'      => Params::getBoletoConfig('title', __('Boleto', 'pagbank-connect')),
                'gateway_id' => 'rm-pagbank-boleto',
                'sub_method' => 'boleto',
                'fee_id'     => Boleto::DISCOUNT_FEE_ID,
            ],
        ];
        if (!isset($config[$method])) {
            return;
        }
        $c = $config[$method];
        if (!$c['enabled'] || !self::isPaymentMethodSelected($c['gateway_id'], $c['sub_method'])) {
            return;
        }
        if (!Params::getDiscountType($c['discount'])) {
            return;
        }
        $cartTotal = (float) $cart->get_cart_contents_total() + (float) $cart->get_shipping_total();
        $shippingTotal = (float) $cart->get_shipping_total();
        $discountContext = [
            'method'            => $method,
            'gateway_id'        => $c['gateway_id'],
            'sub_method'        => $c['sub_method'],
            'fee_id'            => $c['fee_id'],
            'title'             => $c['title'],
            'config_discount'   => $c['discount'],
            'excludes_shipping' => $c['excludes'],
            'cart_total'        => $cartTotal,
            'shipping_total'    => $shippingTotal,
        ];

        /** 
         * @since 4.55.0
         * @param bool                         $should_apply Default true when gateway discount applies.
         * @param \WC_Cart                     $cart
         * @param array<string, mixed>         $discountContext Keys: method (pix|boleto), gateway_id, sub_method,
         */
        if (!apply_filters('pagbank_connect_should_apply_payment_method_discount', true, $cart, $discountContext)) {
            return;
        }

        $discount = Params::getDiscountValueForTotal($c['discount'], $cartTotal, $c['excludes'], $shippingTotal);

        /** @since 4.55.0
         *
         * Override the PIX/Boleto discount amount (store currency). Context keys match those passed to
         * pagbank_connect_should_apply_payment_method_discount.
         *
         * @param float                              $discount        Amount before cap (from gateway settings).
         * @param \WC_Cart                           $cart
         * @param array<string, mixed>               $discount_context
         */
        $discount = (float) apply_filters('pagbank_connect_payment_method_discount_amount', $discount, $cart, $discountContext);

        $discount = max(0.0, $discount);
        if ($discount <= 0) {
            return;
        }

        /** Do not exceed the base used for percentage discounts (shipping excluded when configured). */
        $discountBaseCap = $c['excludes']
            ? max(0.0, $cartTotal - $shippingTotal)
            : max(0.0, $cartTotal);
        if ($discountBaseCap > 0 && $discount > $discountBaseCap) {
            $discount = $discountBaseCap;
        }

        $discountLabel = __('Desconto', 'pagbank-connect') . ' ' . $c['title'];
        $cart->fees_api()->add_fee([
            'id'        => $c['fee_id'],
            'name'      => $discountLabel,
            'amount'    => -$discount,
            'taxable'   => false,
            'tax_class' => '',
        ]);
    }

    /**
     * Get order status (used for automatically update pix payment on the success page)
     * @return void
     */
    public static function getOrderStatus()
    {
        $orderId = $_GET['order_id'] ?? null;
        if (!$orderId) {
            wp_send_json_error(__('Pedido não encontrado', 'pagbank-connect'));
        }

        $order = wc_get_order((int)$orderId);
        if (!$order) {
            wp_send_json_error(__('Pedido não encontrado', 'pagbank-connect'));
        }

        $status = $order->get_status();
        wp_send_json_success($status);    
    }

    /**
     * Will check if skip_processing_virtual is enabled and skip processing for virtual products
     *
     * @param $needsProcessing
     * @param $product
     * @param $orderId
     *
     * @return void
     */
    public static function orderItemNeedsProcessing($needsProcessing, $product, $orderId)
    {
        $order = wc_get_order($orderId);
        $paymentMethod = $order->get_payment_method();
        if (strpos($paymentMethod, 'rm-pagbank') === false) {
            return $needsProcessing;
        }
        
        $isVirtual = $product->is_virtual();
        $skipProcessing = Params::getConfig('skip_processing_virtual') == 'yes';
        if ($isVirtual && $skipProcessing) {
            return false;
        }
        
        return $needsProcessing;
    }
    
    private static function addPagBankMenu()
    {
        add_action('admin_menu', [MenuPagBank::class, 'addPagBankMenu']);
        add_action('admin_menu', [MenuPagBank::class, 'addPagBankSubmenuItems']);
        add_action('admin_enqueue_scripts', [MenuPagBank::class, 'adminPagesStyle']);
    }

    public static function forceOrderUpdate()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Você não tem permissão para acessar esta página.', 'pagbank-connect'));
        }

        $order_id = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT);
        $pagbank_order_id = isset($_GET['pagbank_order_id']) ? sanitize_text_field($_GET['pagbank_order_id']) : '';

        if (empty($pagbank_order_id) || empty($order_id)) {
            wp_send_json_error(__('Faltando order_id ou pagbank_order_id', 'pagbank-connect'));
        }

        // Obter o pedido com base no pagbank_order_id e id
        $order = wc_get_order($order_id);

        if (!$order || OrderMeta::get($order, 'order_id') !== $pagbank_order_id) {
            wp_send_json_error(__('Pedido não encontrado', 'pagbank-connect'));
        }


        $edit_order_url = admin_url('post.php?post=' . $order_id . '&action=edit');

        $orderData = Api::getOrderData($pagbank_order_id);
        $md5 = md5(serialize($orderData));
        if (OrderMeta::get($order, '_last_update_md5') == $md5) {
            $order->add_order_note(
                __('Pedido atualizado manualmente mas nada mudou desde o último update.', 'pagbank-connect'),
                false,
                true
            );
            return wp_redirect($edit_order_url);
        }

        $order->add_order_note(
            __('Pedido atualizado manualmente.', 'pagbank-connect'),
            false,
            true
        );
        $orderProcessor = new OrderProcessor();
        try {
            $orderProcessor->updateTransaction($order, $orderData);
        } catch (\Exception $e) {
            $order->add_order_note(
                __('Erro ao atualizar o pedido: ', 'pagbank-connect') . $e->getMessage(),
                false,
                true
            );
        }

        wp_redirect($edit_order_url);
    }

    public static function gatewayOrderFilter($order)
    {
        if (!is_array($order)) {
            return $order;
        }
        $pagbank_ids = array('rm-pagbank-pix', 'rm-pagbank-cc', 'rm-pagbank-boleto', 'rm-pagbank-redirect');
        // First pass: mark positions taken by non-PagBank gateways (e.g. OpenPix forces 0 and 1).
        $used = array();
        foreach ($order as $id => $pos) {
            if (!is_numeric($pos)) {
                continue;
            }
            $p = (int)$pos;
            if (in_array($id, $pagbank_ids, true)) {
                continue;
            }
            $used[$p] = $id;
        }
        $next = 0;
        foreach ($pagbank_ids as $id) {
            if (!isset($order[$id]) || !is_numeric($order[$id])) {
                continue;
            }
            $pos = (int)$order[$id];
            if (isset($used[$pos])) {
                while (isset($used[$next])) {
                    $next++;
                }
                $order[$id] = $next;
                $used[$next] = $id;
                $next++;
            } else {
                $used[$pos] = $id;
                if ($pos >= $next) {
                    $next = $pos + 1;
                }
            }
        }

        return $order;
    }

}
