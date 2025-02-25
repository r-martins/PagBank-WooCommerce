<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/** @var Gateway $this */

use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Params;

$expiry = (int)$this->get_option('redirect_expiry_minutes');
$text = sprintf(
    esc_html__('Você terá %s para finalizar sua compra no checkout PagBank.', 'pagbank-connect'),
    esc_html(Params::convertMinutesToHumanTime($expiry))
);

$hasDiscount = $this->get_option('redirect_discount');
$discountText = Params::getDiscountText('redirect');
?>
<p class="instructions">
    <?php echo wp_kses($text, 'strong'); ?>
    
    <?php if ($hasDiscount): ?>
        <br/>
        <?php echo wp_kses($discountText, 'strong'); ?>
    <?php endif; ?>
</p>
<input type="hidden" name="ps_connect_method" value="redirect"/>
