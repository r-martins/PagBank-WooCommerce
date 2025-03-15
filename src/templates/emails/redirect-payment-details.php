<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Email Template Block: Redirect Payment Details
 *
 * @var string $redirectLink
 * @var string $checkoutExpires
 */
?>

<div class="order-payment-details method-redirect">
    <h2><?php esc_html_e( 'Detalhes do pagamento com Checkout PagBank', 'pagbank-connect' ); ?></h2>

    <p><?php esc_html_e( 'Para pagar com PagBank, clique no link abaixo. Se já realizou o pagamento, ignore este e-mail.', 'pagbank-connect' ); ?></p>

    <a href="<?php echo esc_url( $redirectLink ); ?>" class="button button-primary" target="_blank">
        <?php esc_html_e( 'Pagar com PagBank', 'pagbank-connect' ); ?>
    </a>
    <br>

    <p class="redirect-expiration">
        <b>
            <?php
            if ($checkoutExpires) {
                printf(
                /* translators: %s: QR code expiration date */
                    esc_html__('Este Link de Pagamento expira em: %s.', 'pagbank-connect'),
                    esc_html($checkoutExpires)
                );
            }
            ?>
        </b>
    </p>
</div>
