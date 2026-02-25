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
 * Plugin URI:        https://pbintegracoes.com
 * Description:       Integra seu WooCommerce com as APIs PagSeguro v4 através da aplicação de Ricardo Martins (com descontos nas taxas oficiais), com suporte a PIX transparente e muito mais.
 * Version:           4.53.0
 * Requires at least: 5.2
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Author:            PagBank Integrações (Ricardo Martins)
 * Author URI:        https://pbintegracoes.com
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
define( 'WC_PAGSEGURO_CONNECT_VERSION', '4.53.0' );
define( 'WC_PAGSEGURO_CONNECT_PLUGIN_FILE', __FILE__ );
define( 'WC_PAGSEGURO_CONNECT_BASE_DIR', __DIR__ );
define( 'WC_PAGSEGURO_CONNECT_TEMPLATES_DIR', WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/' );
define( 'WC_PAGSEGURO_CONNECT_URL', plugins_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}
add_action('init', [Connect::class, 'init']);
add_action('init', [Connect\Recurring::class, 'addManageSubscriptionEndpoints']);
add_action('after_setup_theme', [Connect::class, 'loadTextDomain']);

// Initialize Order Meta Boxes
add_action('init', [Connect\OrderMetaBoxes::class, 'init']);

// Initialize Dokan integration hooks
add_action('init', function() {
    if (class_exists('RM_PagBank\Integrations\Dokan\DokanHooks')) {
        \RM_PagBank\Integrations\Dokan\DokanHooks::init();
    }
});

// Add Gateway
add_filter('woocommerce_payment_gateways', array(Connect::class, 'addGateway'));
add_filter('option_woocommerce_gateway_order', array(Connect::class, 'gatewayOrderFilter'), 2);

// Redirect integrations section to main gateway with a flag
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'wc-settings' 
        && isset($_GET['tab']) && $_GET['tab'] === 'checkout'
        && isset($_GET['section']) && $_GET['section'] === 'rm-pagbank-integrations'
        && !isset($_GET['show_integrations'])) {
        
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=rm-pagbank&show_integrations=1'));
        exit;
    }
}, 1);

// Save integrations settings when form is submitted
add_action('admin_init', function() {
    // Check if we're on the integrations page (either direct or via redirect)
    $is_integrations_page = (
        isset($_GET['page']) && $_GET['page'] === 'wc-settings' 
        && isset($_GET['tab']) && $_GET['tab'] === 'checkout'
        && (
            (isset($_GET['section']) && $_GET['section'] === 'rm-pagbank-integrations')
            || (isset($_GET['section']) && $_GET['section'] === 'rm-pagbank' && isset($_GET['show_integrations']) && $_GET['show_integrations'] === '1')
            || (isset($_POST['section']) && $_POST['section'] === 'rm-pagbank-integrations')
        )
    );
    
    if ($is_integrations_page && isset($_POST['save']) && check_admin_referer('woocommerce-settings')) {
        
        // Get all posted data first
        $integrations_settings = [];
        $fields = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/dokan-split-fields.php';
        
        foreach ($fields as $key => $field) {
            // HTML form uses $key as name attribute, so we need to check $_POST[$key]
            // Skip title fields as they don't have values
            if ($field['type'] === 'title') {
                continue;
            }
            
            if ($field['type'] === 'checkbox') {
                $integrations_settings[$key] = isset($_POST[$key]) ? 'yes' : 'no';
            } else {
                $integrations_settings[$key] = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
            }
        }
        
        // Check mutual exclusivity with Split Payments
        $dokan_split_enabled = $integrations_settings['dokan_split_enabled'] ?? 'no';
        // Read directly from database to ensure we get the most recent value
        // This is important because the Gateway instance might have cached/old values
        $gateway_settings = get_option('woocommerce_rm-pagbank_settings', []);
        $split_payments_enabled = $gateway_settings['split_payments_enabled'] ?? 'no';
        
        if ($dokan_split_enabled === 'yes' && $split_payments_enabled === 'yes') {
            add_settings_error(
                'woocommerce_rm-pagbank-integrations',
                'mutual_exclusivity_error',
                __('Não é possível ativar Split Dokan enquanto a Divisão de Pagamentos estiver ativa. Desative a Divisão de Pagamentos primeiro.', 'pagbank-connect'),
                'error'
            );
            // Don't save and redirect back
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=rm-pagbank&show_integrations=1'));
            exit;
        }
        
        // Save to database
        update_option('woocommerce_rm-pagbank-integrations_settings', $integrations_settings);
        
        // Add success message
        add_settings_error('woocommerce_rm-pagbank-integrations', 'settings_updated', __('Configurações salvas com sucesso.', 'pagbank-connect'), 'updated');
        
        // Redirect to avoid form resubmission (maintain show_integrations flag)
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=rm-pagbank&show_integrations=1'));
        exit;
    }
});

// Add custom validation for PagBank Account ID
add_filter('woocommerce_admin_settings_sanitize_option', function($value, $option, $raw_value) {
    if (isset($option['validate']) && $option['validate'] === 'validate_pagbank_account_id') {
        if (!empty($value) && !preg_match('/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/', $value)) {
            WC_Admin_Settings::add_error(__('Formato de Account ID PagBank inválido. Use o formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'pagbank-connect'));
            return $raw_value; // Return original value to show error
        }
    }
    return $value;
}, 10, 3);
//add_action('woocommerce_blocks_payment_method_type_registration', array(Connect::class, 'registerPaymentMethodOnCheckoutBlocks'));
add_action('woocommerce_blocks_loaded', array(Connect::class, 'gatewayBlockSupport'));

//Add Recurring Config
add_filter('woocommerce_get_settings_checkout' , [Connect\Recurring::class, 'recurringSettingsFields'] , 10, 2 );
add_filter('woocommerce_settings_checkout' , [Connect\Recurring::class, 'recurringHeaderSettingsSection'] , 10, 2 );

//envio facil
add_filter('woocommerce_shipping_methods', [EnvioFacil::class, 'addMethod']);

//recurring and styles
add_filter('woocommerce_enqueue_styles', [Gateway::class, 'addStyles'], 99999, 1);
add_filter('woocommerce_enqueue_styles', [Gateway::class, 'addStylesWoo'], 99999, 1);

//not needed so far...
register_activation_hook(__FILE__, [Connect::class, 'activate']);
register_deactivation_hook(__FILE__, [Connect::class, 'deactivate']);
register_uninstall_hook(__FILE__, [Connect::class, 'uninstall']);

// Upgrading scripts
add_action('plugins_loaded', [Connect::class, 'upgrade']);