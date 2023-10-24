<?php
/**
 * Subscription View
 *
 * Shows subscriptions on the account page.
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/view-subscription.php.
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
do_action('rm_pagbank_before_account_recurring_view_subscription', $subscription);
?>
<p><?php echo sprintf(
        __(
            'A assinatura #%s foi criada em %s. O pedido original é o #%s, e o status atual desta assinatura é %s.',
            RM_PagBank\Connect::DOMAIN
        ),
        '<mark class="order-number">' . esc_html($subscription->id) . '</mark>',
        '<mark class="date">' . wc_format_datetime(wc_string_to_datetime($subscription->created_at)) . '</mark>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        '<mark class="order-number"><a href="' . $initialOrder->get_view_order_url() . '">' . $initialOrder->get_id() . '</a></mark>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        '<mark class="order-status">' . $dashboard->getFriendlyStatus($subscription->status) . '</mark>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    )?></p>

<?php do_action( 'rm_pagbank_view_subscription', $subscription ); ?>

<?php do_action( 'rm_pagbank_view_subscription_actions', $subscription ); ?>
    <hr class="rm-pagbank-separator"/>
<h2 class="woocommerce-order-details__title orders-title"><?php _e('Pedidos gerados a partir desta assinatura', RM_PagBank\Connect::DOMAIN);?></h2>
<p><?php _e('Após o pedido inicial, toda vez que uma cobrança é feita, um novo pedido é gerado.', RM_PagBank\Connect::DOMAIN)?></p>
<?php do_action( 'rm_pagbank_view_subscription_order_list', $subscription );