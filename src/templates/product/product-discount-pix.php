<?php

/**
 * DO NOT modify this file. If you want to make changes, copy it to wp-content/YOUR_THEME/pagbank-connect/
 * and edit it there.
 * NÃO MODIFIQUE este arquivo. Se você deseja fazer alterações, copie-o para wp-content/SEU_TEMA/pagbank-connect/
 * e edite-o lá.
 */

if (!defined('ABSPATH')) {
    exit;
}
/** @var stdClass $args */

$data = $args;

$product = $data['product'] ?? null;
$discount_config = $data['discount'] ?? 0;
$discount_type = $data['discount_type'] ?? null;

if (!$product || !$discount_config || !$discount_type) {
    return;
}

// Prepare discount data
$original_price = (float) $product->get_price();
$discountTotal = $discount_type == 'PERCENT' ? $original_price * (floatval($discount_config) / 100) : floatval($discount_config);
$price_with_discount = $original_price - $discountTotal;
$price_with_discount_formatted = '<b>' . wc_price($price_with_discount) . '</b>';
$html_discount = sprintf(__('À vista no Pix: %s', 'pagbank-connect'), $price_with_discount_formatted);
?>
<span class="rm-pagbank-price">
   <div class="icon-pix"></div> <?php echo $html_discount ?>
</span>
<br />