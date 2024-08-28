<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Email Template Block: Boleto Payment Details
 *
 * @var string $boletoBarcode
 * @var string $boletoPdfLink
 * @var string $boletoDueDate
 */
?>

<div class="order-payment-details method-boleto">
    <h2><?php esc_html_e( 'Detalhes do pagamento com boleto', 'pagbank-connect' ); ?></h2>

    <p class="boleto-instructions">
        <?php echo esc_html__( 'Para pagar o seu pedido, imprima o boleto ou copie o código de barras abaixo e pague em qualquer agência bancária ou lotérica até a data de vencimento. Após o pagamento, o pedido será processado e enviado.', 'pagbank-connect' ); ?>
    </p>
    <p class="boleto-due-date">
        <b>
            <?php
            printf(
            /* translators: %s: Boleto due date */
                esc_html__( 'O seu boleto vence em: %s.', 'pagbank-connect' ),
                esc_html( $boletoDueDate )
            );
            ?>
        </b>
    </p>
    <p class="boleto-pdf">
        <a href="<?php echo esc_url( $boletoPdfLink ); ?>" target="_blank">
            <?php echo esc_html__( 'Clique aqui para imprimir o boleto', 'pagbank-connect' ); ?>
        </a>
    </p>
    <p class="boleto-barcode">
        <?php echo esc_html__( 'Código de barras:', 'pagbank-connect' ); ?><br>
        <?php echo wp_kses_post( $boletoBarcode ); ?>
    </p>
</div>
