<?php
/**
 * PagSeguro Connect - Ricardo Martins (com descontos)
 *
 * @package           PagSeguroConnect
 * @author            Ricardo Martins
 * @copyright         2023 Ricardo Martins
 * @license           GPL-3.0
 *
 * @wordpress-plugin
 * Plugin Name:       PagSeguro Connect - Ricardo Martins (com descontos)
 * Plugin URI:        https://pagseguro.ricardomartins.net.br/
 * Description:       Integra seu WooCommerce com as APIs PagSeguro v4 através da aplicação de Ricardo Martins (com descontos nas taxas oficiais) 
 * Version:           4.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Ricardo Martins
 * Author URI:        https://magenteiro.com
 * License:           GPL-3.0
 * License URI:       https://opensource.org/license/gpl-3/
 * Update URI:        https://pagseguro.ricardomartins.net.br/
 * Text Domain:       rm-pagseguro-connect
 */

use RM_PagSeguro\Connect;

// Prevent direct file access.
defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

// Plugin constants.
define( 'WC_PAGSEGURO_CONNECT_VERSION', '4.0.0' );
define( 'WC_PAGSEGURO_CONNECT_PLUGIN_FILE', __FILE__ );
define( 'WC_PAGSEGURO_CONNECT_BASE_DIR', __DIR__ );
define( 'WC_PAGSEGURO_CONNECT_URL', plugins_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

add_action('init', [Connect::class, 'init']);


//region old spl
//register spl autoload function to look into ./src folder with PSR-4 standard
/*spl_autoload_register(function ($class) {
    // project-specific namespace prefix
    $prefix = 'RM_PagSeguro\\';

    // base directory for the namespace prefix
    $base_dir = WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader (if any)
        return;
    }
    
    // get the relative class name
    $relative_class = substr($class, $len);
    
    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});*/
//endregion

//region old init
//add_action('plugins_loaded', 'rm_pagseguro_connect_init');
//function rm_pagseguro_connect_init() {
//    /** @var  \RM_PagSeguro\Connect $connect */
//    static $connect;
//
//    if ( ! isset( $connect ) ) {
//        $connect = new Connect();
//    }
//
//    return $connect;
//}
//endregion

//region old RMPagseguroConnect
//class RMPagseguroConnect
//{
//    public function __construct()
//    {
////        add_action('init', [$this, 'init']);
//    }
//
//    public function init() {
//        /** @var Connect $connect */
//        static $connect;
//
//        if ( ! isset( $connect ) ) {
//            $connect = new Connect();
//        }
//
//        return $connect;
//    }
//    public function activate()
//    {
//    }
//    public function deactivate()
//    {
//    }
//    public static function uninstall()
//    {
//    }
//}
//endregion

//$psConnect = new RMPagseguroConnect();
//register_activation_hook(__FILE__, [$psConnect, 'activate']);
//register_deactivation_hook(__FILE__, [$psConnect, 'deactivate']);
//register_uninstall_hook(__FILE__, 'RMPagseguroConnect::uninstall');