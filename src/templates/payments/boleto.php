<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/** @var Gateway $this */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;

$expiry = (int)$this->get_option('boleto_expiry_days');

$hasDiscount = $this->get_option('boleto_discount');
$discountText = Params::getDiscountText('boleto');
$recHelper = new Recurring();
$isCartRecurring = $recHelper->isCartRecurring();
?>
<p class="instructions">
    <?php echo wp_kses($this->get_option('boleto_instructions'), 'strong'); ?>
    <br/>
    <?php echo esc_html(sprintf( _n( 'Seu boleto vencerá amanhã.', 'Seu boleto vence em %d dias.', esc_attr($expiry), 'pagbank-connect' ), esc_attr($expiry) )); ?>
    <?php if ($isCartRecurring) :?>
        <p class="form-row form-row-wide">
            <?php echo wp_kses($recHelper->getRecurringTermsFromCart('boleto'), 'strong');?>
        </p>
    <?php endif;?>
    <?php if ($hasDiscount): ?>
        <br/>
        <?php echo wp_kses($discountText, 'strong'); ?>
    <?php endif; ?>
</p>
<input type="hidden" name="ps_connect_method" value="boleto"/>
