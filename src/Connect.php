<?php

namespace RM_PagSeguro;

use RM_PagSeguro\Connect\Gateway;
use RM_PagSeguro\Connect\Gatewayd;

/**
 * Class Connect
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Connect 
{

    public const DOMAIN = 'rm_pagseguro_connect';

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
            add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin text domain
        load_plugin_textdomain(Connect::DOMAIN, false, dirname(plugin_basename( __FILE__ )) . '/languages/');

        // Load plugin files
        self::includes();

        // Add Gateway
        add_filter('woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ));

        // Add action links
        add_filter( 'plugin_action_links_' . plugin_basename( WC_PAGSEGURO_CONNECT_PLUGIN_FILE ), array( self::class, 'plugin_action_links' ) );
    }

    /**
     * WooCommerce missing notice.
     */
    public static function woocommerce_missing_notice() {
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/messages/html-notice-missing-woocommerce.php';
    }
    
    /**
     * Includes module files.
     *
     * @return void
     */
    public static function includes()
    {
        //@TODO Remover em prol de Helpers\Functions\genetic_message
        if ( is_admin() ) {
            include_once WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/messages/generic.php';
        }
    }

    /**
     * Add Gateway
     *
     * @param array $gateways
     * @return array
     */
    public static function add_gateway( $gateways )
    {
        $gateways[] = new Gateway();
        return $gateways;
    }

    public static function plugin_action_links( $links ) {
        $plugin_links   = array();
        $plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . self::DOMAIN ) ) . '">' . __( 'Settings', self::DOMAIN ) . '</a>';

        return array_merge( $plugin_links, $links );
    }
}