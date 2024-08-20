<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var Gateway $this */

use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring;

$expiry = (int)$this->get_option('pix_expiry_minutes');
$text = sprintf(
    esc_html__('Você terá %s para pagar com seu código PIX.', 'pagbank-connect'),
    esc_html(Params::convertMinutesToHumanTime($expiry))
);
$recHelper = new Recurring();
$isCartRecurring = $recHelper->isCartRecurring();


$hasDiscount = $this->get_option('pix_discount');
$discountText = Params::getDiscountText('pix');
?>
<p class="instructions">
    <?php echo wp_kses($this->get_option('pix_instructions'), 'strong'); ?>
    <br/>
    <?php echo wp_kses($text, 'strong'); ?>
    <?php if ($isCartRecurring) :?>
        <p class="form-row form-row-wide">
            <?php echo wp_kses($recHelper->getRecurringTermsFromCart('pix'), 'strong');?>
        </p>
    <?php endif;?>
    <?php if ($hasDiscount) : ?>
        <br/>
        <?php echo wp_kses($discountText, 'strong'); ?>
    <?php endif; ?>
</p>
<input type="hidden" name="ps_connect_method" value="pix"/>