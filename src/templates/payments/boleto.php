<?php
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
    <?php echo $this->get_option('boleto_instructions'); ?>
    <br/>
    <?php echo sprintf( _n( 'Seu boleto vencerá amanhã.', 'Seu boleto vence em %d dias.', $expiry, Connect::DOMAIN ), $expiry ); ?>
    <?php if ($isCartRecurring) :?>
        <p class="form-row form-row-wide">
            <?php echo $recHelper->getRecurringTermsFromCart('boleto');?>
        </p>
    <?php endif;?>
    <?php if ($hasDiscount): ?>
        <br/>
        <?php echo $discountText; ?>
    <?php endif; ?>
</p>
