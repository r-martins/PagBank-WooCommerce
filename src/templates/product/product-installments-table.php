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
/** @var stdClass|array $args */

?>

<div class="woocommerce pagbank-connect-installments">
    <h2><?php echo esc_html(__('Parcelamento PagBank', 'pagbank-connect'));?></h2>
    <table class="shop_table shop_table_responsive">
        <tbody id="pagbank_load_installment">
            <?php if ($args[0] && $args[1]->get_type() !== 'variable') {
                echo wp_kses_post($args[0]);
            } ?>
        </tbody>
    </table>
</div>