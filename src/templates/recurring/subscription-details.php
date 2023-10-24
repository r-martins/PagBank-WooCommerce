<?php
/**
 * Subscription Details table
 *
 * Shows subscription details
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/subscription-details.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */

/** @var stdClass $subscription */
defined( 'ABSPATH' ) || exit;
$dashboard = new RM_PagBank\Connect\Recurring\RecurringDashboard();

if ( ! isset($subscription->id) || ! $subscription->id ) {
    return;
}

wc_print_notices();
?>
<section class="woocommerce-order-details">
    <?php do_action( 'rm_pagbank_recurring_details_before_subscription_table', $subscription ); ?>
   
    <h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Detalhes da Assinatura', RM_PagBank\Connect::DOMAIN ); ?></h2>

    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

        <thead>
        <tr>
            <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Informações de Pagamento', RM_PagBank\Connect::DOMAIN ); ?></th>
            <th class="woocommerce-table__product-table product-total">&nbsp;</th>
        </tr>
        </thead>
        
        <tbody>
            <?php
            do_action( 'rm_pagbank_recurring_details_before_subscription_table_items', $subscription );
            do_action('rm_pagbank_recurring_details_subscription_table_payment_info', $subscription ); 
            ?>
        </tbody>
        
        <tfoot>
            <tr>
                <th scope="row"><?php _e('Valor da assinatura', RM_PagBank\Connect::DOMAIN)?></th>
                <td><?php echo wc_price( $subscription->recurring_amount );?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Status', RM_PagBank\Connect::DOMAIN)?></th>
                <td><?php echo $dashboard->getFriendlyStatus($subscription->status);?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Cobrança', RM_PagBank\Connect::DOMAIN)?></th>
                <td><?php echo $dashboard->getFriendlyType($subscription->recurring_type);?></td>
            </tr>
            <?php if ( in_array($subscription->status, ['ACTIVE', 'PENDING', 'SUSPENDED']) ): ?>
                <tr>
                    <th scope="row"><?php _e('Próxima Cobrança', RM_PagBank\Connect::DOMAIN)?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->next_bill_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( in_array($subscription->status, ['CANCELED']) ): ?>
                <tr>
                    <th scope="row"><?php _e('Cancelada em', RM_PagBank\Connect::DOMAIN)?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->canceled_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( in_array($subscription->status, ['PAUSED']) ): ?>
                <tr>
                    <th scope="row"><?php _e('Pausada em', RM_PagBank\Connect::DOMAIN)?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->paused_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( ! empty($subscription->cancelation_reason) ): ?>
                <tr>
                    <th scope="row"><?php _e('Razão do Cancelamento', RM_PagBank\Connect::DOMAIN)?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->cancelation_reason));?></td>
                </tr>
            <?php endif;?>

            <?php if ( ! empty($subscription->suspended_reason) ): ?>
                <tr>
                    <th scope="row"><?php _e('Razão da Suspensão', RM_PagBank\Connect::DOMAIN)?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->suspended_reason));?></td>
                </tr>
            <?php endif;?>
        
            
        </tfoot>
        
    </table>
</section>
