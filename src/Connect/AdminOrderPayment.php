<?php
namespace RM_PagBank\Connect;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Partner\Branding;
use WC_Admin_Meta_Boxes;
use WC_Order;

/**
 * Creates PIX/boleto charges when an unpaid order is saved in wp-admin
 * with those payment methods selected (WooCommerce never calls process_payment there).
 */
class AdminOrderPayment
{
    /** @var array<int, true> */
    private static array $processing = [];

    public static function init(): void
    {
        // After WC_Meta_Box_Order_Data::save (priority 40).
        add_action('woocommerce_process_shop_order_meta', [__CLASS__, 'maybeCreatePayment'], 50, 2);
    }

    /**
     * @param int            $order_id
     * @param WC_Order|mixed $order
     */
    public static function maybeCreatePayment($order_id, $order = null): void
    {
        $order_id = absint($order_id);
        if ($order_id <= 0 || isset(self::$processing[$order_id])) {
            return;
        }

        if (!is_admin()) {
            return;
        }

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order instanceof WC_Order) {
            return;
        }

        $gatewayId = (string) $order->get_payment_method();
        $code = self::gatewayCode($gatewayId);
        if ($code === null) {
            return;
        }

        // Already charged via API (checkout, previous admin save, etc.).
        if (OrderMeta::get($order, 'order_id')) {
            return;
        }

        if ((float) $order->get_total() <= 0) {
            return;
        }

        if (!$order->get_billing_email()) {
            self::fail(
                $order,
                __('Não foi possível gerar o pagamento: informe o e-mail de cobrança.', 'pagbank-connect')
            );
            return;
        }

        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        if (empty($gateways[$gatewayId]) || !is_object($gateways[$gatewayId]) || !method_exists($gateways[$gatewayId], 'process_payment')) {
            self::fail(
                $order,
                sprintf(
                    /* translators: %s = gateway id */
                    __('Gateway de pagamento %s indisponível para criar a cobrança.', 'pagbank-connect'),
                    $gatewayId
                )
            );
            return;
        }

        self::$processing[$order_id] = true;

        try {
            // Align POST field used by standalone process_payment() notes.
            if (empty($_POST['payment_method'])) {
                $_POST['payment_method'] = $code;
            }

            $gateways[$gatewayId]->process_payment($order_id);

            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order) {
                $order->add_order_note(
                    Branding::notePrefix($order) . ': '
                    . __('Cobrança gerada a partir do pedido manual no admin.', 'pagbank-connect')
                );
            }
        } catch (\Throwable $e) {
            $message = wp_strip_all_tags($e->getMessage());
            Functions::log(
                sprintf(
                    'Admin order %d payment create failed (%s): %s',
                    $order_id,
                    $code,
                    $message
                ),
                'error'
            );
            self::fail(
                $order,
                sprintf(
                    /* translators: %s = error message */
                    __('Falha ao gerar cobrança PIX/boleto: %s', 'pagbank-connect'),
                    $message
                )
            );
        } finally {
            unset(self::$processing[$order_id]);
        }
    }

    private static function gatewayCode(string $gatewayId): ?string
    {
        $map = [
            Connect::DOMAIN . '-pix' => 'pix',
            Connect::DOMAIN . '-boleto' => 'boleto',
        ];

        return $map[$gatewayId] ?? null;
    }

    private static function fail(WC_Order $order, string $message): void
    {
        $order->add_order_note(Branding::notePrefix($order) . ': ' . $message);
        if (class_exists(WC_Admin_Meta_Boxes::class)) {
            WC_Admin_Meta_Boxes::add_error($message);
        }
    }
}
