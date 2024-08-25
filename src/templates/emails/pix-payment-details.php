<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Email Template Block: PIX Payment Details
 *
 * @var string $pixQrCode
 * @var string $pixQrCodeExpiration
 * @var string $pixQrCodeText
 */
?>

<div class="order-payment-details method-pix">
    <h2><?php esc_html_e( 'Detalhes do pagamento com PIX', 'pagbank-connect' ); ?></h2>

    <p><?php esc_html_e( 'Para pagar com PIX, escaneie o QR Code abaixo ou copie o código e cole no aplicativo do seu banco.', 'pagbank-connect' ); ?></p>

    <div class="pix-qr-code">
        <img src="<?php echo esc_url( $pixQrCode ); ?>" alt="<?php esc_attr_e( 'PIX QR Code', 'pagbank-connect' ); ?>" width="200px">
    </div>
    <br>
    <p class="pix-qr-code-text"><?php echo wp_kses_post( $pixQrCodeText ); ?></p>

    <p class="pix-qr-code-expiration">
        <b>
            <?php
            printf(
            /* translators: %s: QR code expiration date */
                esc_html__( 'Este QR Code expira em: %s.', 'pagbank-connect' ),
                esc_html( $pixQrCodeExpiration )
            );
            ?>
        </b>
    </p>
</div>
