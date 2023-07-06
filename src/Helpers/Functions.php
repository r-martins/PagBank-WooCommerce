<?php

namespace RM_PagSeguro\Helpers;

use DateTime;
use DateTimeZone;
use Exception;
use RM_PagSeguro\Connect;

/**
 * Class Functions
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagSeguro\Helpers
 */
class Functions
{
    const NOTICE_ERROR = 'error'; //red
    const NOTICE_UPDATE = 'updated'; //green
    const NOTICE_NAG = 'updated-nag'; //gray
    
    /**
     * formatDate from 2023-07-05T15:12:56.000-03:00 to "15/07/2023 15:12:56 (Horário de Brasília)"
     * @param $date
     *
     * @return string
     */
    static function format_date($date): string
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
     * @param $msg
     * @param string $type
     * @param bool $isDismissible
     *
     * @return void
     */
    static function generic_notice(string $msg, string $type = self::NOTICE_UPDATE, bool $isDismissible=true)
    {
        if( !is_admin() ) {
            return;
        }
        
        $class = 'notice';
        $class .= ' ' . $type;
        if ($isDismissible) {
            $class .= ' is-dismissible';
        }
        
        echo '<div class="' . $class . '"><p><strong>' . esc_html_e( 'PagSeguro Connect', Connect::DOMAIN ) . '</strong> ' . $msg . '</p></div>';
    }
}