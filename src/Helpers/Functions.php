<?php

namespace RM_PagBank\Helpers;

use DateTime;
use DateTimeZone;
use Exception;
use RM_PagBank\Connect;
use WC_Admin_Settings;
use WC_Blocks_Utils;
use WC_Order;

/**
 * Class Functions
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Helpers
 */
class Functions
{
    const NOTICE_ERROR = 'error'; //red
    const NOTICE_UPDATE = 'updated'; //green
    const NOTICE_NAG = 'updated-nag'; //gray

    /**
     * Format Date a date like 2023-07-05T15:12:56.000-03:00 to "15/07/2023 15:12:56 (Horário de Brasília)"
     * @param $date
     *
     * @return string
     */
    public static function formatDate($date): string
    {
        if (empty($date) || !is_string($date)) {
            return '';
        }

        try {
            $date = new DateTime($date);
            $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));

            return $date->format('d/m/Y à\s H:i:s').' (Horário de Brasília)';
        } catch (Exception $e) {
            return '';
        }
    }

	/**
	 * Prints(echo) a generic notice in the admin
	 *
	 * @param string $msg
	 * @param string $type
	 * @param bool   $isDismissible
	 *
	 * @return void
	 */
    public static function generic_notice_pagbank(string $msg, string $type = self::NOTICE_UPDATE, bool $isDismissible=true)
    {
        if( !is_admin() ) {
            return;
        }

        $class = 'notice';
        $class .= ' ' . $type;
        if ($isDismissible) {
            $class .= ' is-dismissible';
        }

        echo '<div class="' . $class . '"><p><strong>' . esc_html_e( 'PagBank Connect', 'pagbank-connect' ) . '</strong> ' . esc_html($msg) . '</p></div>';
    }

    /**
     * @param string $msg
     * @param string $level  One of the following:
     *                      'emergency': System is unusable.
     *                      'alert': Action must be taken immediately.
     *                      'critical': Critical conditions.
     *                      'error': Error conditions.
     *                      'warning': Warning conditions.
     *                      'notice': Normal but significant condition.
     *                      'info': Informational messages.
     *                      'debug': Debug-level messages.
     * @param array  $additional
     *
     * @return void
     */
    public static function log(string $msg, string $level = 'info', array $additional = []): void
    {
        $logger = wc_get_logger();
        $msg = $msg . PHP_EOL . var_export($additional, true);
        $logger->log($level, $msg, ['source' => 'pagbank-connect']);
    }

	public static function getCcFlagUrl(string $brand): string
	{
		if (file_exists(WC_PAGSEGURO_CONNECT_BASE_DIR . '/public/images/credit-cards/' . $brand . '.svg')) {
			return plugins_url('public/images/credit-cards/' . $brand . '.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE);
		}

		return '';
	}

    public static function getFriendlyPaymentMethodName(string $method): string
    {
        switch ($method) {
            case 'boleto':
                return __('Boleto', 'pagbank-connect');
            case 'pix':
                return __('Pix', 'pagbank-connect');
            case 'credit_card':
            case 'cc':
                return __('Cartão de Crédito', 'pagbank-connect');
            default:
                return __('Desconhecido', 'pagbank-connect');
        }
    }

    /**
     * Convert a given weight to kg, based on the current configured weight unit
     * @param float $weight
     *
     * @return float
     */
    public static function convertToKg(float $weight): float
    {
        $currentUnit = get_option('woocommerce_weight_unit');

        switch ($currentUnit) {
            case 'g':
                $weightInKg = $weight / 1000;
                break;
            case 'lbs':
                $weightInKg = $weight * 0.45359237;
                break;
            case 'oz':
                $weightInKg = $weight * 0.02834952;
                break;
            default:
                $weightInKg = $weight;
                break;
        }
        
        return $weightInKg;
    }

    /**
     * Checks if the inputed discount value is a valid fixed or % discount value (used both in boleto and pix)
     *
     * @param $value
     *
     * @return float|int|string
     */
    public static function validateDiscountValue($value, $allowNegative = false)
    {
        if (empty($value)) {
            return $value;
        }

        //remove spaces
        $value = str_replace(' ', '', $value);
        //replace comma with dot
        $value = str_replace(',', '.', $value);

        if (strpos($value, '%')) {
            $value = str_replace('%', '', $value);

            if (!is_numeric($value) || $value > 100 || (!$allowNegative && $value < 0)) {
                $positive = $allowNegative ? '' : __('positivo', 'pagbank-connect');
                WC_Admin_Settings::add_error(
                    __(sprintf('O desconto deve ser um número %s ou percentual de 0 a 100.', $positive), 'pagbank-connect')
                );

                return '';
            }

            return $value.'%';
        }

        if (!is_numeric($value) || (!$allowNegative && $value < 0)) {
            WC_Admin_Settings::add_error(
                __('O desconto deve ser um número positivo ou percentual de 0 a 100', 'pagbank-connect')
            );

            return '';
        }

        return $value;
    }

    public static function applyPriceAdjustment($price, $adjustment)
    {
        if (empty($adjustment) || $adjustment === 0) {
            return $price;
        }

        if (strpos($adjustment, '%')) {
            $adjustment = str_replace('%', '', $adjustment);
            if ($adjustment < 0) {
                $price = $price - ($price * (abs($adjustment) / 100));
            } else {
                $price = $price + ($price * ($adjustment / 100));
            }
        } else {
            $price = $price + $adjustment;
        }

        return round($price, 2);
    }

    /**
     * Get a parameter from the order meta or from the $_POST array if not present
     *
     * @param WC_Order $order
     * @param          $metaParam
     * @param          $postParam
     * @param          $default
     *
     * @return array|mixed|string|null
     */
    public static function getParamFromOrderMetaOrPost(WC_Order $order, $metaParam, $postParam, $default = '')
    {
        if ($order->get_meta($metaParam)) {
            return $order->get_meta($metaParam);
        }

        if (isset($_POST[$postParam])) {
            return sanitize_text_field(wp_unslash(($_POST[$postParam])));
        }

        return $default;
    }

    /**
     * Encrypts data using openssl and aes-256-cbc algorithm or base64 if openssl is not available
     * @param $data
     *
     * @return string
     */
    public static function encrypt($data): string
    {
        $key = Params::getConfig('connect_key');

        if (extension_loaded('openssl')) {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
            return base64_encode($encrypted . '::' . $iv);
        }

        return base64_encode($data);
    }

    /**
     * Decrypts data using openssl and aes-256-cbc algorithm or base64 if openssl is not available
     * @param $data
     *
     * @return false|string
     */
    public static function decrypt($data)
    {
        $key = Params::getConfig('connect_key');
        if (extension_loaded('openssl')) {
            if (!empty($data)) {
                list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
                return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
            }
        }
        
        // Fallback to base64 decoding if OpenSSL is not available
        return base64_decode($data);
    }

    /**
     * Check if the block checkout is in use
     * @return bool
     */
    public static function isBlockCheckoutInUse(): bool
    {
        // Get the ID of the checkout page.
        $checkout_page_id = wc_get_page_id('checkout');

        // Get the content of the checkout page.
        $checkout_page_content = get_post_field('post_content', $checkout_page_id);

        // Check if the content contains the `woocommerce_checkout` block.
        return strpos($checkout_page_content, '<!-- wp:woocommerce/checkout ') !== false;
    }

    /**
     * Check if the current call was made using by do_shortcode function
     * @return bool
     */
    public static function isCalledByDoShortcode(): bool
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $calledByDoShortcode = false;

        foreach ($backtrace as $trace) {
            if (isset($trace['function']) && $trace['function'] === 'do_shortcode_tag') {
                $calledByDoShortcode = true;
                break;
            }
        }
        
        return $calledByDoShortcode;
    }

    /**
     * Validates if the generated QrCode is valid (BETA)
     * @param $pixCode
     *
     * @return bool
     */
    public static function isValidPixCode($pixCode): bool
    {
        if (strpos($pixCode, 'br.gov.bcb.pix') !== false && strpos($pixCode, 'pagseguro.com') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Adds a meta query filter to the main query
     * @return void
     */
    public static function addMetaQueryFilter(): void
    {
        add_filter('woocommerce_get_wp_query_args', function ($wp_query_args, $query_vars) {
            if (isset($query_vars['meta_query'])) {
                $meta_query = $wp_query_args['meta_query'] ?? [];
                $wp_query_args['meta_query'] = array_merge($meta_query, $query_vars['meta_query']);
            }

            return $wp_query_args;
        }, 10, 2);
    }

    /**
     * Check if the current page is the checkout page and uses Woocommerce Blocks. Also returns false if the page is a CartFlows checkout.
     * @return bool
     */
    public static function isCheckoutBlocks(): bool
    {
        $page_id = get_the_ID();
        return is_checkout() && WC_Blocks_Utils::has_block_in_page( $page_id, 'woocommerce/checkout' ) && !Functions::isCartflowCheckout();
    }

    public static function isCartflowCheckout() {
        // Check if CartFlows plugin is active and the current page is a wcf checkout
        if ( ! class_exists( 'CartFlows_Checkout' ) || !function_exists('_is_wcf_checkout_type')) {
            return false;
        }
        return _is_wcf_checkout_type();
    }

    /**
     * @return array
     */
    public static function getExpiredPixOrders(): array
    {
        $expiryMinutes = Params::getPixConfig('pix_expiry_minutes');

        Functions::addMetaQueryFilter();

        // Check if HPOS is enabled
        if (wc_get_container()->get(
            \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class
        )->custom_orders_table_usage_is_enabled()) {
            $expiredDate = strtotime(gmdate('Y-m-d H:i:s')) - $expiryMinutes * 60;
            return wc_get_orders([
                'limit'        => -1,
                'status'       => 'pending',
                'date_created' => '<'.$expiredDate,
                'meta_query'   => [
                    [
                        'key'     => 'pagbank_payment_method',
                        'value'   => 'pix',
                        'compare' => '='
                    ]
                ]
            ]);
        }
        // else, HPOS is disabled
        $expiredDate = current_time('timestamp') - $expiryMinutes * 60;
        $args = array(
            'post_type'      => 'shop_order',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'post_status'    => 'wc-pending',
            'date_query'     => [
                'before' => date('Y-m-d H:i:s', $expiredDate),
            ],
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'pagbank_payment_method',
                    'value'   => 'pix',
                    'compare' => '='
                ]
            ],
        );

        $query = new \WP_Query($args);

        $expiringOrders = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $order_id = get_the_ID();
                $order = wc_get_order($order_id);
                $expiringOrders[] = $order;
            }
            wp_reset_postdata();
        }
        return $expiringOrders;
    }

    /**
     * Applies placeholders to a string based on the order data
     * @param $string
     * @param $order_id
     *
     * @return mixed|null
     */
    public static function applyOrderPlaceholders($string, $order_id)
    {
        $order = new WC_Order($order_id);
        $placeholders = [
            '{paymentMethod}' => $order->get_payment_method(),
            '{orderTotal}' => $order->get_total(),
            '{orderId}' => $order_id,
            '{customerName}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '{customerEmail}' => $order->get_billing_email(),
        ];
        return apply_filters('pagbank_connect_order_placeholders', strtr($string, $placeholders), $order_id);
    }

    /**
     * Get the pending orders that are using PagBank as payment method in the last 7 days
     * @return array
     * @throws \Automattic\WooCommerce\Internal\DependencyManagement\ContainerException
     */
    public static function getPagBankPendingOrders(): array
    {
        Functions::addMetaQueryFilter();

        // Check if HPOS is enabled
        if (wc_get_container()->get(
            \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class
        )->custom_orders_table_usage_is_enabled()) {
            $createdAtDate = strtotime(gmdate('Y-m-d H:i:s')) - 3600 * 24 * 7;
            return wc_get_orders([
                'limit'        => -1,
                'status'       => ['wc-pending', 'wc-on-hold'],
                'date_created' => '>'.$createdAtDate,
                'orderby'      => 'date',
                'order'        => 'ASC',
                'meta_query'   => [
                    [
                        'key'     => 'pagbank_payment_method',
                        'value'   => '',
                        'compare' => '!='
                    ]
                ]
            ]);
        }
        // else, HPOS is disabled
        $createdAtDate = current_time('timestamp') - 3600 * 24 * 7;
        $args = array(
            'post_type'      => 'shop_order',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'ASC',
            'post_status'    => ['wc-pending', 'wc-on-hold'],
            'date_query'     => [
                'after' => date('Y-m-d H:i:s', $createdAtDate),
            ],
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'pagbank_payment_method',
                    'value'   => '',
                    'compare' => '!='
                ]
            ],
        );

        $query = new \WP_Query($args);

        $expiringOrders = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $order_id = get_the_ID();
                $order = wc_get_order($order_id);
                $expiringOrders[] = $order;
            }
            wp_reset_postdata();
        }
        return $expiringOrders;
    }

    /**
     * Removes special characters from a string and convert accents to their base characters
     * @param $string
     *
     * @return array|string|string[]|null
     */
    public static function stringClear($string)
    {
        $table = [
            'Š' => 'S',
            'š' => 's',
            'Ð' => 'D',
            'd' => 'd',
            'Ž' => 'Z',
            'ž' => 'z',
            'C' => 'C',
            'c' => 'c',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'A',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'B',
            'ß' => 'Ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'o',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ý' => 'y',
            'þ' => 'b',
            'ÿ' => 'y',
        ];

        $result = strtr($string, $table);
        $result = preg_replace('/[^A-Za-z0-9\ ]/', '', $result);
        return $result;
    }

    /**
     * Get the template file path for a given template name
     *
     * @param string $template_name
     * @return string
     */
    public static function get_template($template_name) 
    {
        $default_template = plugin_dir_path(__FILE__) . '../templates/' . $template_name;
        $theme_template = locate_template('pagbank-connect/' . $template_name);

        $template_path = $theme_template ?: $default_template;

        // Verify version
        $default_version = self::get_template_version($default_template);
        $theme_version   = $theme_template ? self::get_template_version($theme_template) : null;

        if ($theme_version && version_compare($theme_version, $default_version, '<')) {
            // Log, warning in admin, version mismatch
            Functions::log("O template sobrescrito '$template_name' está desatualizado (versão $theme_version, esperado $default_version).", 'warning', [
                'context' => 'pagbank-connect',
                'type'    => 'template_version_mismatch',
                'template' => $template_name,
                'version'  => $theme_version,
                'expected' => $default_version,
            ]);
        }

        return $template_path;
    }

    /**
     * Get the version of a template file based on its header
     *
     * @param string $file_path
     * @return string|null
     */
    public static function get_template_version($file_path) {
        $default_headers = [
            'Template Version' => 'Template Version',
        ];
        $file_data = get_file_data($file_path, $default_headers);
        return $file_data['Template Version'] ?? null;
    }
}
