<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Connect;

trait StaticResources
{
    /**
     * @var true
     */
    private static $addedScripts = false;

    /**
     * Add css files for checkout and success page
     * @return void
     */
    public static function addStyles($styles){
        //thank you page
        $alreadyEnqueued = wp_style_is('pagseguro-connect-pix');
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received')) && !$alreadyEnqueued) {
            $styles['pagseguro-connect-pix'] = [
                'src'     => plugins_url('public/css/success.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }

        $alreadyEnqueued = wp_style_is('pagseguro-connect-checkout');
        if (is_checkout() && !$alreadyEnqueued) {
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

    /**
     * Add js files for checkout and success page
     * @return void
     */
    public function addScripts() {
        // If the method has already been called, return early
        if (self::$addedScripts) {
            return;
        }

        //thank you page
        $alreadyEnqueued = wp_script_is('pagseguro-connect');
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received')) && !$alreadyEnqueued) {
            wp_enqueue_script(
                'pagseguro-connect',
                plugins_url('public/js/success.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        $alreadyEnqueued = wp_script_is('pagseguro-connect-checkout');
        if ( is_checkout() && !is_order_received_page() && !$alreadyEnqueued) {
            wp_enqueue_script(
                'pagseguro-connect-checkout',
                plugins_url('public/js/checkout.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                ['jquery'],
                WC_PAGSEGURO_CONNECT_VERSION,
                true
            );
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

        $alreadyEnqueued = wp_style_is('pagseguro-connect-admin-css');
        if (!$alreadyEnqueued) {
            wp_enqueue_style(
                'pagseguro-connect-admin-css',
                plugins_url('public/css/ps-connect-admin.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        $alreadyEnqueued = wp_style_is('pagseguro-connect-deactivate');
        if ($hook == 'plugins.php' && !$alreadyEnqueued) {
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