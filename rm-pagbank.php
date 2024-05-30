<?php
/**
 * PagSeguro Connect - Ricardo Martins (com descontos)
 *
 * @package           PagSeguroConnect
 * @author            Ricardo Martins
 * @copyright         2024 Ricardo Martins
 * @license           GPL-3.0
 *
 * @wordpress-plugin
 * Plugin Name:       PagBank Connect
 * Description:       Integra seu WooCommerce com as APIs PagSeguro v4 através da aplicação de Ricardo Martins (com descontos nas taxas oficiais), com suporte a PIX transparente e muito mais.
 * Version:           4.11.2
 * Requires at least: 5.2
 * Tested up to:      6.5
 * Requires PHP:      7.4
 * Author:            Ricardo Martins (PagSeguro/PagBank Integrações)
 * Author URI:        https://magenteiro.com
 * License:           GPL-3.0
 * License URI:       https://opensource.org/license/gpl-3/
 * Text Domain:       pagbank-connect
 * Domain Path:       /languages
 */

/** @noinspection PhpDefineCanBeReplacedWithConstInspection */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\EnvioFacil;

// Prevent direct file access.
defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

// Plugin constants.
define( 'WC_PAGSEGURO_CONNECT_VERSION', '4.11.2' );
define( 'WC_PAGSEGURO_CONNECT_PLUGIN_FILE', __FILE__ );
define( 'WC_PAGSEGURO_CONNECT_BASE_DIR', __DIR__ );
define( 'WC_PAGSEGURO_CONNECT_TEMPLATES_DIR', WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/' );
define( 'WC_PAGSEGURO_CONNECT_URL', plugins_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}
add_action('init', [Connect::class, 'init']);
add_action('init', [Connect\Recurring::class, 'addManageSubscriptionEndpoints']);
add_action('plugins_loaded', [Connect::class, 'loadTextDomain']);

// Add Gateway
add_filter('woocommerce_payment_gateways', array(Connect::class, 'addGateway'));

//envio facil
add_filter('woocommerce_shipping_methods', [EnvioFacil::class, 'addMethod']);

//recurring and styles
add_filter('woocommerce_enqueue_styles', [Gateway::class, 'addStyles'], 99, 1);
add_filter('woocommerce_enqueue_styles', [Gateway::class, 'addStylesWoo'], 99, 1);

//not needed so far...
register_activation_hook(__FILE__, [Connect::class, 'activate']);
register_deactivation_hook(__FILE__, [Connect::class, 'deactivate']);
register_uninstall_hook(__FILE__, [Connect::class, 'uninstall']);
