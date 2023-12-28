<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var Gateway $this */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;
use RM_PagBank\Helpers\Params;

$expiry = (int)$this->get_option('pix_expiry_minutes');
switch ($expiry){
    case $expiry <= 60:
        $text = sprintf(__('Você terá %d minutos para pagar com seu código PIX.', 'pagbank-connect'), $expiry);
        break;
    case 1440:
        $text = __('Você terá 24 horas para pagar com seu código PIX.', 'pagbank-connect');
        break;
    case $expiry % 1440 === 0:
        $expiry = $expiry / 1440;
        $text = sprintf(__('Você terá %d dias para usar seu código PIX.', 'pagbank-connect'), $expiry);
        break;
    default:
        $text = '';
        break;
}

$hasDiscount = $this->get_option('pix_discount');
$discountText = Params::getDiscountText('pix');
?>
<p class="instructions">
    <?php esc_html_e($this->get_option('pix_instructions'), 'pagbank-connect'); ?>
    <br/>
    <?php esc_html_e($text, 'pagbank-connect'); ?>
    <?php if ($hasDiscount): ?>
        <br/>
        <?php echo wp_kses($discountText, 'strong'); ?>
    <?php endif; ?>
</p>
