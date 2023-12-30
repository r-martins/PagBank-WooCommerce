<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/** @var Gateway $this */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Params;

$expiry = (int)$this->get_option('boleto_expiry_days');

$hasDiscount = $this->get_option('boleto_discount');
$discountText = Params::getDiscountText('boleto');
?>
<p class="instructions">
    <?php echo wp_kses($this->get_option('boleto_instructions'), 'strong'); ?>
    <br/>
    <?php echo sprintf( _n( 'Seu boleto vencerá amanhã.', 'Seu boleto vence em %d dias.', esc_attr($expiry), 'pagbank-connect' ), esc_attr($expiry) ); ?>
    <?php if ($hasDiscount): ?>
        <br/>
        <?php echo wp_kses($discountText, 'strong'); ?>
    <?php endif; ?>
</p>
