<?php

namespace RM_PagBank\Helpers;

use DateTime;
use DateTimeZone;
use Exception;
use RM_PagBank\Connect;
use WC_Admin_Settings;
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
}
