<?php
/**
 * Subscription Update
 *
 * Update subscription on the account page.
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/update-subscription.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */

/** @var stdClass $subscription */
/** @var RecurringDashboard $dashboard */
/** @var WC_Order $initialOrder */

use RM_PagBank\Connect\Recurring\RecurringDashboard;
use RM_PagBank\Helpers\Recurring as RecurringHelper;

defined( 'ABSPATH' ) || exit;

$paymentInfoUpdate = RecurringHelper::normalizePaymentInfo($subscription->payment_info ?? null);
if (!empty($paymentInfoUpdate['needs_retoken'])) {
    echo '<div class="woocommerce-info">';
    echo esc_html__(
        'Sua loja mudou o processador de cartões. Cadastre um novo cartão abaixo para reativar a assinatura. Não cobramos de novo até você concluir esta etapa.',
        'pagbank-connect'
    );
    echo '</div>';
}
?>

<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
    <thead>
    <tr>
        <th class="woocommerce-table__product-name product-name"><strong><?php esc_html_e( 'Informações de Pagamento', 'pagbank-connect' ); ?></strong></th>
        <th class="woocommerce-table__product-table product-total">&nbsp;</th>
    </tr>
    </thead>
    <tbody>
    <?php
    do_action('rm_pagbank_recurring_details_subscription_table_payment_info', $subscription );
    ?>
    </tbody>
</table>
<br>
<div id="payment">
    <?php do_action( 'pagbank_recurring_subscription_update_payment_method', $subscription ); ?>
</div>


