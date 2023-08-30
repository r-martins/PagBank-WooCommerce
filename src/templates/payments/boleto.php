<?php
/** @var \RM_PagBank\Connect\Gateway $this */

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Params;

$expiry = (int)$this->get_option('boleto_expiry_days');

$hasDiscount = $this->get_option('boleto_discount');
$discountText = Params::getDiscountText('boleto');
?>
<p class="instructions">
    <?php echo $this->get_option('boleto_instructions'); ?>
    <br/>
    <?php echo sprintf( _n( 'Seu boleto vencerá amanhã.', 'Seu boleto vence em %d dias.', $expiry, Connect::DOMAIN ), $expiry ); ?>
    <?php if ($hasDiscount): ?>
        <br/>
        <?php echo $discountText; ?>
    <?php endif; ?>
</p>