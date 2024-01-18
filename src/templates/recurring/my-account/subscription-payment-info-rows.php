<?php
/**
 * Subscription Payment Details Rows
 *
 * Shows the details of payment for specific subscription
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/subscription-paument-info-rows.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */

/** @var stdClass $subscription */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Recurring\RecurringDashboard;
use RM_PagBank\Helpers\Functions;

defined( 'ABSPATH' ) || exit;
do_action('rm_pagbank_before_account_recurring_view_subscription_payment_rows', $subscription);

if ( ! isset($subscription->id) || ! $subscription->id ) {
    return;
}
$payment = json_decode($subscription->payment_info);
?>
<tr class="woocommerce-table__line-item order_item">
    <td class="woocommerce-table__product-name product-name">
        <strong><?php _e('Forma de Pagamento', 'pagbank-connect')?></strong>
    </td>
    <td class="woocommerce-table__product-total product-total">
        <?php echo Functions::getFriendlyPaymentMethodName($payment->method);?>
    </td>
</tr>

<?php if ( $payment->method == 'credit_card' ) :?>
<tr class="woocommerce-table__line-item order_item">
    <td class="woocommerce-table__product-name product-name">
        <strong><?php _e('Cartão de Crédito', 'pagbank-connect')?></strong>
    </td>
    <td class="woocommerce-table__product-total product-total">
        <img src="<?php echo esc_url(plugins_url('public/images/credit-cards/' . $payment->card->brand . '.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)) ?>" class="cc-brand payment_methods cc-<?php echo $payment->card->brand?>" title="<?php echo esc_attr($payment->card->brand);?>" style="height: 20px;" alt="<?php echo esc_attr($payment->card->brand);?>"/><br/>
        <?php echo $payment->card->number?>
        <br/>
        <?php echo esc_html(strtoupper($payment->card->holder_name)); ?> - <?php echo esc_attr($payment->card->expiration_date)?>
    </td>
</tr>
<?php endif; 
