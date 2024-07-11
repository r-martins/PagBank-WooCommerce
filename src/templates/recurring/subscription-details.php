<?php
/**
 * Subscription Details table (customer view)
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

use RM_PagBank\Helpers\Recurring;

defined( 'ABSPATH' ) || exit;
$dashboard = new RM_PagBank\Connect\Recurring\RecurringDashboard();

if ( ! isset($subscription->id) || ! $subscription->id ) {
    return;
}

wc_print_notices();
?>
<section class="woocommerce-order-details">
    <?php do_action( 'rm_pagbank_recurring_details_before_subscription_table', $subscription ); ?>
   
    <h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Detalhes da Assinatura', 'pagbank-connect' ); ?></h2>

    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

        <thead>
        <tr>
            <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Informações de Pagamento', 'pagbank-connect' ); ?></th>
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
                <th scope="row"><?php _e('Valor da assinatura', 'pagbank-connect')?></th>
                <td><?php echo wc_price( $subscription->recurring_amount );?></td>
            </tr>
            <?php if ($subscription->recurring_trial_period): ?>
                <tr>
                    <th scope="row"><?php _e('Período de testes (dias)', 'pagbank-connect')?></th>
                    <td><?php echo $subscription->recurring_trial_period;?></td>
                </tr>
            <?php endif;?>
            <?php if ((int)$subscription->recurring_discount_cycles && (float)$subscription->recurring_discount_amount): ?>
                <tr>
                    <th scope="row"><?php _e('Desconto', 'pagbank-connect')?></th>
                    <td>
                        <?php
                        $msg = __('%s por %s ciclos de cobrança.', 'pagbank-connect');
                        $msg = sprintf($msg, wc_price($subscription->recurring_discount_amount), $subscription->recurring_discount_cycles);
                        ?>
                        <?php echo $msg;?>
                    </td>
                </tr>
            <?php endif;?>
            <tr>
                <th scope="row"><?php _e('Status', 'pagbank-connect')?></th>
                <td><?php echo Recurring::getFriendlyStatus($subscription->status);?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Cobrança', 'pagbank-connect')?></th>
                <td><?php echo Recurring::getFriendlyType($subscription->recurring_type);?></td>
            </tr>
            <?php if ( in_array($subscription->status, ['ACTIVE', 'PENDING', 'SUSPENDED']) ): ?>
                <tr>
                    <th scope="row"><?php _e('Próxima Cobrança', 'pagbank-connect')?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->next_bill_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( in_array($subscription->status, ['CANCELED']) ): ?>
                <tr>
                    <th scope="row"><?php _e('Cancelada em', 'pagbank-connect')?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->canceled_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( in_array($subscription->status, ['PAUSED']) ): ?>
                <tr>
                    <th scope="row"><?php _e('Pausada em', 'pagbank-connect')?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->paused_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( ! empty($subscription->canceled_reason) ): ?>
                <tr>
                    <th scope="row"><?php _e('Razão do Cancelamento', 'pagbank-connect')?></th>
                    <td><?php echo esc_html($subscription->canceled_reason);?></td>
                </tr>
            <?php endif;?>

            <?php if ( $subscription->status == 'PENDING_CANCEL' ): ?>
                <tr>
                    <th scope="row"><?php _e('Assinatura será cancelada em', 'pagbank-connect')?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->next_bill_at));?></td>
                </tr>
            <?php endif;?>

            <?php if ( ! empty($subscription->suspended_reason) ): ?>
                <tr>
                    <th scope="row"><?php _e('Razão da Suspensão', 'pagbank-connect')?></th>
                    <td><?php echo wc_format_datetime(wc_string_to_datetime($subscription->suspended_reason));?></td>
                </tr>
            <?php endif;?>
        
            
        </tfoot>
        
    </table>
</section>
