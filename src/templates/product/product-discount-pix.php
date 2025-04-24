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

if (!$product || !$discount_config) {
    return;
}

// Prepara os dados
$original_price = (float) $product->get_price();
$discount = (float) $discount_config / 100.0;  
$price_with_discount = $original_price - $discount * $original_price;
$price_with_discount_formatted = '<b>' . wc_price($price_with_discount) . '</b>';
$html_discount = sprintf(__('À vista no Pix: %s', 'pagbank-connect'), $price_with_discount_formatted);
?>
<span class="rm-pagbank-price">
    <?php echo $html_discount?>
</span> 
<br />