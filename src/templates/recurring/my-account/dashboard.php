<?php
/**
 * Subscriptions
 * 
 * Shows all subscriptions on the account page.
 * 
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/dashboard.php.
 * 
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */

/** @var array $subscriptions */
/** @var RecurringDashboard $dashboard */

use RM_PagBank\Connect\Recurring\RecurringDashboard;
use RM_PagBank\Helpers\Recurring;

defined( 'ABSPATH' ) || exit;

do_action('rm_pagbank_before_account_recurring_dashboard', $subscriptions);

$wp_button_class = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';

?>
<?php if ( ! empty ( $subscriptions ) ): ?>
<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
    <thead>
    <tr>
        <?php foreach ( $dashboard->getColumns() as $column_id => $column_name ) : ?>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
        <?php endforeach; ?>
    </tr>
    </thead>
    
    <tbody>
    <?php
    foreach ( $subscriptions as $subscription ) {
        ?>
    <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo strtolower( esc_attr( $subscription->status ) ); ?> order">
        <?php foreach ( $dashboard->getColumns() as $column_id => $column_name ) : ?>
            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
                <?php if ( has_action( 'rm_pagbank_recurring_dashboard_column_' . $column_id ) ) : ?>
                    <?php do_action( 'rm_pagbank_recurring_dashboard_column_' . $column_id, $subscription ); ?>
                <?php elseif ( 'recurring-id' === $column_id ) : ?>
                    <a href="<?php echo esc_url( $dashboard->getViewSubscriptionUrl($subscription) ); ?>">
                        #<?php echo esc_html( $subscription->id ); ?>
                    </a>
                <?php elseif ( 'status' === $column_id ) : ?>
                    <?php echo esc_html(Recurring::getFriendlyStatus($subscription->status)); ?>
                <?php elseif ( 'created_at' === $column_id ) : ?>
                    <?php echo esc_html( wc_format_datetime( wc_string_to_datetime( $subscription->created_at) ) ); ?>
                <?php elseif ( 'recurring_type' === $column_id ) : ?>
                    <?php echo esc_html(Recurring::getFriendlyType($subscription->recurring_type)); ?>
                <?php elseif ( 'recurring_amount' === $column_id ) : ?>
                    <?php echo esc_html( $subscription->recurring_amount ); ?>
                <?php elseif ( 'subscription-actions' === $column_id ) : ?>
                    <?php
                    $actions = $dashboard->getSubscriptionInRowActions( $subscription );
                    if ( ! empty( $actions ) ) {
                        foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button' . esc_attr( $wp_button_class ) . ' button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
                        }
                    }
                    ?>
            <?php endif;?>
            </td>
        <?php endforeach; ?>
    </tr>
        <?php
    }
    ?>
    </tbody>

</table>
    <?php else:?>

    <?php
    wc_print_notice( esc_html__( 'Você ainda não tem nenhuma assinatura.', 'pagbank-connect' ) . ' <a class="woocommerce-Button button' . esc_attr( $wp_button_class ) . '" href="' . esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ) . '">' . esc_html__( 'Browse products', 'woocommerce' ) . '</a>', 'notice' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment 
    ?>

<?php endif;