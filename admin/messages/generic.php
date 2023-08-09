<?php

use RM_PagBank\Connect;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @param $msg
 * @param $type one of 'updated', 'error', 'update-nag' (green, red, yellow)
 * @param $isDismissable
 *
 * @return void
 */
function generic_notice($msg, $type="updated", $isDismissable=true) {
    $class = 'notice';
    $class .= ' ' . $type;
    if ($isDismissable) {
        $class .= ' is-dismissible';
    }
    echo '<div class="' . $class . '"><p><strong>' . esc_html_e( 'PagSeguro Connect', Connect::DOMAIN ) . '</strong> ' . $msg . '</p></div>';
}

