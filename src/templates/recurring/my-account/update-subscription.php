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

defined( 'ABSPATH' ) || exit;
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
<?php do_action( 'rm_pagbank_update_subscription_change_credit_card', $subscription ); ?>



