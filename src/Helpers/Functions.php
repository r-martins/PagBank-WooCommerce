<?php

namespace RM_PagBank\Helpers;

use DateTime;
use DateTimeZone;
use Exception;
use RM_PagBank\Connect;

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
    public static function generic_notice(string $msg, string $type = self::NOTICE_UPDATE, bool $isDismissible=true)
    {
        if( !is_admin() ) {
            return;
        }

        $class = 'notice';
        $class .= ' ' . $type;
        if ($isDismissible) {
            $class .= ' is-dismissible';
        }

        echo '<div class="' . $class . '"><p><strong>' . esc_html_e( 'PagBank Connect', Connect::DOMAIN ) . '</strong> ' . $msg . '</p></div>';
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
                return __('Boleto', Connect::DOMAIN);
            case 'pix':
                return __('Pix', Connect::DOMAIN);
            case 'credit_card':
            case 'cc':
                return __('Cartão de Crédito', Connect::DOMAIN);
            default:
                return __('Desconhecido', Connect::DOMAIN);
        }
    }
}
